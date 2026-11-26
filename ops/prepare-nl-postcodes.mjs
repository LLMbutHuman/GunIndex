import { createHash } from 'node:crypto';
import { createReadStream, lstatSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { pathToFileURL } from 'node:url';

const BAG_REFERENCE_SYSTEM = 28992;
const sources = {
  verblijfsobject: ['Verblijfsobject in gebruik', 'Verblijfsobject in gebruik (niet ingemeten)'],
  standplaats: ['Plaats aangewezen'],
  ligplaats: ['Plaats aangewezen'],
};

// Read the horizontal coordinates from a GeoPackage Binary point.
function bagCoordinate(value, axis) {
  if (value === null) return null;
  if (!(value instanceof Uint8Array) || value.length < 8 || value[0] !== 71 || value[1] !== 80 || value[2] !== 0) {
    throw new Error('Invalid GeoPackage geometry header.');
  }
  const flags = value[3];
  const envelope = [0,32,48,48,64][(flags >> 1) & 7];
  const view = new DataView(value.buffer, value.byteOffset, value.byteLength);
  if ((flags & 0xe0) !== 0 || envelope === undefined || view.getInt32(4, Boolean(flags & 1)) !== BAG_REFERENCE_SYSTEM) {
    throw new Error('Unexpected geometry flags or reference system.');
  }
  if ((flags & 0x10) !== 0) return null;
  const start = 8 + envelope;
  if (value.length < start + 5 || value[start] > 1) throw new Error('Invalid WKB point header.');
  const littleEndian = value[start] === 1;
  const type = view.getUint32(start + 1, littleEndian);
  const dimensions = {1: 2, 1001: 3, 2001: 3, 3001: 4}[type];
  if (!dimensions || value.length !== start + 5 + dimensions * 8) throw new Error('Expected a WKB point.');
  const coordinate = view.getFloat64(start + 5 + axis * 8, littleEndian);
  if (!Number.isFinite(coordinate)) throw new Error('BAG point is non-finite.');
  return Math.round(coordinate * 100);
}

function verifiedBagLabels(db) {
  if (db.prepare('PRAGMA application_id').get().application_id !== 0x47504b47) throw new Error('Expected a GeoPackage.');
  const geometry = db.prepare('SELECT column_name, geometry_type_name, srs_id FROM gpkg_geometry_columns WHERE table_name=?');
  const selects = [];
  const statuses = [];
  for (const [table, allowedStatus] of Object.entries(sources)) {
    const shape = geometry.get(table);
    if (shape?.column_name !== 'geom' || !['POINT','GEOMETRY'].includes(shape.geometry_type_name) || shape.srs_id !== BAG_REFERENCE_SYSTEM) {
      throw new Error('Unexpected BAG GeoPackage metadata.');
    }
    const placeholders = allowedStatus.map(() => '?').join(',');
    selects.push(`SELECT upper(replace(trim(postcode),' ','')) AS postal, trim(woonplaats_naam) AS town, geom
      FROM ${table} WHERE status IN (${placeholders}) AND geom IS NOT NULL AND postcode IS NOT NULL AND woonplaats_naam IS NOT NULL`);
    statuses.push(...allowedStatus);
  }
  return {
    sql: `WITH addresses AS (${selects.join(' UNION ALL ')}),
      points AS MATERIALIZED (
        SELECT postal,town,bag_east(geom) AS east,bag_north(geom) AS north
        FROM addresses WHERE postal GLOB '[1-9][0-9][0-9][0-9][A-Z][A-Z]' AND bag_town_valid(town)
      ),
      distinct_points AS MATERIALIZED (
        SELECT postal,east,north,min(town) AS town FROM points
        WHERE east BETWEEN 1000000 AND 28500000 AND north BETWEEN 30000000 AND 63000000
        GROUP BY postal,east,north
      ),
      centres AS (
        SELECT postal,avg(east)/100 AS east,avg(north)/100 AS north FROM distinct_points
        GROUP BY postal HAVING count(*)>=3 AND max(east)-min(east)<=500000 AND max(north)-min(north)<=500000
      ),
      towns AS (
        SELECT postal,town,row_number() OVER (PARTITION BY postal ORDER BY count(*) DESC,town) AS priority
        FROM distinct_points GROUP BY postal,town
      )
      SELECT c.postal,t.town,c.east,c.north FROM centres c
      JOIN towns t ON t.postal=c.postal AND t.priority=1 ORDER BY c.postal`,
    statuses,
  };
}

export async function prepareNlPostcodes(input, output, expected) {
  if (!/^[a-f0-9]{64}$/.test(expected)) throw new Error('A reviewed source SHA-256 digest is required.');
  const before = lstatSync(input);
  if (!before.isFile() || before.size > 12 * 1024 ** 3) throw new Error('Expected a regular BAG GeoPackage up to 12 GiB.');
  const hash = createHash('sha256');
  for await (const chunk of createReadStream(input, { highWaterMark: 2 * 1024 * 1024 })) hash.update(chunk);
  if (hash.digest('hex') !== expected) throw new Error('Source SHA-256 mismatch.');
  const db = new DatabaseSync(input, { readOnly: true });
  const points = [];
  try {
    db.exec('PRAGMA query_only=ON; PRAGMA trusted_schema=OFF; PRAGMA temp_store=FILE');
    db.function('bag_east', { deterministic:true }, value => bagCoordinate(value, 0));
    db.function('bag_north', { deterministic:true }, value => bagCoordinate(value, 1));
    db.function('bag_town_valid', { deterministic:true }, value => /^[^\u0000-\u001f\u007f;"\ufffd]{1,150}$/u.test(value) ? 1 : 0);
    const {sql, statuses} = verifiedBagLabels(db);
    for (const {postal, town, east, north} of db.prepare(sql).iterate(...statuses)) {
      points.push([postal,town,east,north]);
      if (points.length > 650000) throw new Error('Unexpected number of postcode aggregates.');
    }
  } finally { db.close(); }
  const after = lstatSync(input);
  if (before.dev !== after.dev || before.ino !== after.ino || before.size !== after.size || before.mtimeMs !== after.mtimeMs) {
    throw new Error('BAG GeoPackage was changed during preparation.');
  }
  if (!points.length) throw new Error('No postcode aggregates passed validation.');
  writeFileSync(output, JSON.stringify(points) + '\n', {flag:'wx'});
  return points.length;
}

if(process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  const [input, output, expected] = process.argv.slice(2);
  try {
    if (!input || !output || !expected || process.argv.length !== 5) {
      throw new Error('Usage: node ops/prepare-nl-postcodes.mjs BAG.gpkg NEW.json EXPECTED_SHA256');
    }
    console.log(`Prepared ${await prepareNlPostcodes(input, output, expected)} NL postcode aggregates in EPSG:28992.`);
  } catch (error) {
    console.error(error.code ? 'Cannot read the BAG GeoPackage or create the aggregate file.' : error.message);
    process.exitCode = 1;
  }
}

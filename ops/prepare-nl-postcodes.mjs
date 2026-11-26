import { createHash } from 'node:crypto';
import { createReadStream, lstatSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { pathToFileURL } from 'node:url';

const BAG_REFERENCE_SYSTEM = 28992;
const sources = {
  verblijfsobject: {type:'POINT', statuses:['Verblijfsobject in gebruik', 'Verblijfsobject in gebruik (niet ingemeten)']},
  standplaats: {type:'POLYGON', statuses:['Plaats aangewezen']},
  ligplaats: {type:'POLYGON', statuses:['Plaats aangewezen']},
};

function polygonAxis(view,start,littleEndian,axis) {
  if (start+4>view.byteLength) throw new Error('Incomplete WKB polygon.');
  const rings=view.getUint32(start,littleEndian);
  let offset=start+4, totalArea=0, weighted=0;
  if (!rings || rings>(view.byteLength-offset)/68) throw new Error('Invalid WKB polygon ring count.');
  for (let ring=0;ring<rings;ring++) {
    if (offset+4>view.byteLength) throw new Error('Incomplete WKB polygon ring.');
    const size=view.getUint32(offset,littleEndian);
    offset+=4;
    if (size<4 || offset+size*16>view.byteLength) throw new Error('Invalid WKB polygon ring length.');
    const east=view.getFloat64(offset,littleEndian), north=view.getFloat64(offset+8,littleEndian);
    let twiceArea=0, moment=0, previousEast=0, previousNorth=0;
    for (let i=1;i<size;i++) {
      const point=offset+i*16;
      const x=view.getFloat64(point,littleEndian)-east, y=view.getFloat64(point+8,littleEndian)-north;
      const cross=previousEast*y-x*previousNorth;
      if (!Number.isFinite(cross)) throw new Error('BAG polygon is non-finite.');
      twiceArea+=cross;
      moment+=(axis===0?previousEast+x:previousNorth+y)*cross;
      previousEast=x; previousNorth=y;
    }
    if (previousEast!==0 || previousNorth!==0) throw new Error('BAG polygon ring is not closed.');
    offset+=size*16;
    if (twiceArea===0) continue;
    const area=(ring===0?1:-1)*Math.abs(twiceArea);
    totalArea+=area;
    weighted+=area*((axis===0?east:north)+moment/(3*twiceArea));
  }
  if (offset!==view.byteLength) throw new Error('Unexpected trailing WKB polygon bytes.');
  return totalArea>0?weighted/totalArea:null;
}

// Read an address point or derive the centre of its designated parcel geometry.
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
  if(type===3){
    const coordinate=polygonAxis(view,start+5,littleEndian,axis);
    return coordinate===null?null:Math.round(coordinate*100);
  }
  const dimensions = {1: 2, 1001: 3, 2001: 3, 3001: 4}[type];
  if (!dimensions || value.length !== start + 5 + dimensions * 8) throw new Error('Expected a WKB point.');
  const coordinate = view.getFloat64(start + 5 + axis * 8, littleEndian);
  if (!Number.isFinite(coordinate)) throw new Error('BAG point is non-finite.');
  return Math.round(coordinate * 100);
}

function bagAddressQuery(db) {
  if (db.prepare('PRAGMA application_id').get().application_id !== 0x47504b47) throw new Error('Expected a GeoPackage.');
  const geometry = db.prepare('SELECT column_name, geometry_type_name, srs_id FROM gpkg_geometry_columns WHERE table_name=?');
  const selects = [];
  const statuses = [];
  for (const [table, specification] of Object.entries(sources)) {
    const shape = geometry.get(table);
    if (shape?.column_name !== 'geom' || shape.geometry_type_name!==specification.type || shape.srs_id !== BAG_REFERENCE_SYSTEM) {
      throw new Error('Unexpected BAG GeoPackage metadata.');
    }
    const placeholders = specification.statuses.map(() => '?').join(',');
    selects.push(`SELECT upper(replace(trim(postcode),' ','')) AS postal, trim(woonplaats_naam) AS town, geom
      FROM ${table} WHERE status IN (${placeholders}) AND geom IS NOT NULL AND postcode IS NOT NULL AND woonplaats_naam IS NOT NULL`);
    statuses.push(...specification.statuses);
  }
  return {
    sql: `WITH addresses AS (${selects.join(' UNION ALL ')})
      SELECT postal,town,geom FROM addresses
      WHERE postal GLOB '[1-9][0-9][0-9][0-9][A-Z][A-Z]' ORDER BY postal,town COLLATE BINARY`,
    statuses,
  };
}

function bagAggregate(postal, locations) {
  if (locations.size<3) return null;
  const towns=new Map();
  let sumEast=0,sumNorth=0,minEast=Infinity,maxEast=-Infinity,minNorth=Infinity,maxNorth=-Infinity;
  for (const [town,east,north] of locations.values()) {
    towns.set(town,(towns.get(town)??0)+1);
    sumEast+=east;sumNorth+=north;
    minEast=Math.min(minEast,east);maxEast=Math.max(maxEast,east);
    minNorth=Math.min(minNorth,north);maxNorth=Math.max(maxNorth,north);
  }
  if (maxEast-minEast>500000 || maxNorth-minNorth>500000) return null;
  const [[town]]=Array.from(towns).sort(([a,aCount],[b,bCount])=>bCount-aCount||Buffer.compare(Buffer.from(a),Buffer.from(b)));
  return [postal,town,(sumEast/locations.size)/100,(sumNorth/locations.size)/100];
}

function* bagCentres(rows) {
  const locations=new Map();
  let previous=null;
  for (const {postal,town,geom} of rows) {
    if (postal!==previous) {
      const centre=bagAggregate(previous,locations);
      if (centre) yield centre;
      locations.clear();
      previous=postal;
    }
    if (!/^[^\u0000-\u001f\u007f;"\ufffd]{1,150}$/u.test(town)) continue;
    const east=bagCoordinate(geom,0), north=bagCoordinate(geom,1);
    if (!Number.isFinite(east) || !Number.isFinite(north) || east<1000000 || east>28500000 || north<30000000 || north>63000000) continue;
    const key=east+','+north;
    // The cursor orders towns within each postcode, so the first label wins for repeated points.
    if (!locations.has(key)) locations.set(key,[town,east,north]);
  }
  const last=bagAggregate(previous,locations);
  if (last) yield last;
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
    const {sql, statuses} = bagAddressQuery(db);
    for (const centre of bagCentres(db.prepare(sql).iterate(...statuses))) {
      points.push(centre);
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

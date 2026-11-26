import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { prepareNlPostcodes } from '../ops/prepare-nl-postcodes.mjs';

function point(east, north, {littleEndian = true, envelope = false, srid = 28992} = {}) {
  const start = envelope ? 40 : 8;
  const data = Buffer.alloc(start + 21);
  data.write('GP');
  data[3] = (littleEndian ? 1 : 0) | (envelope ? 2 : 0);
  const view = new DataView(data.buffer, data.byteOffset, data.byteLength);
  view.setInt32(4, srid, littleEndian);
  if (envelope) [east,east,north,north].forEach((coord,index) => view.setFloat64(8 + index * 8,coord,littleEndian));
  data[start] = littleEndian ? 1 : 0;
  view.setInt32(start + 1, 1, littleEndian);
  view.setFloat64(start + 5, east, littleEndian);
  view.setFloat64(start + 13, north, littleEndian);
  return data;
}

const directory = mkdtempSync(join(tmpdir(), 'gunindex-bag-test-'));
const input = join(directory,'fixture.gpkg');
const target = join(directory,'aggregates.json');
const digest = () => createHash('sha256').update(readFileSync(input)).digest('hex');
try {
  const fixture = new DatabaseSync(input);
  fixture.exec('PRAGMA application_id=1196444487; CREATE TABLE gpkg_geometry_columns(table_name TEXT, column_name TEXT, geometry_type_name TEXT, srs_id INTEGER)');
  for (const table of ['verblijfsobject','standplaats','ligplaats']) {
    fixture.exec(`CREATE TABLE ${table}(postcode TEXT, woonplaats_naam TEXT, status TEXT, geom BLOB)`);
    fixture.prepare("INSERT INTO gpkg_geometry_columns VALUES (?, 'geom', 'POINT', 28992)").run(table);
  }
  const add = fixture.prepare('INSERT INTO verblijfsobject VALUES (?,?,?,?)');
  const used = 'Verblijfsobject in gebruik';
  add.run('1234 ab','Synthetic locality',used,point(155000,463000));
  add.run('1234AB','Synthetic locality',used,point(155000,463000));
  add.run('1234AB','Synthetic locality',used,point(155030,463060,{littleEndian:false,envelope:true}));
  add.run('1234AB','Synthetic locality','Verblijfsobject ingetrokken',point(160001,463060));
  fixture.prepare('INSERT INTO ligplaats VALUES (?,?,?,?)').run('1234AB','Other synthetic locality','Plaats aangewezen',point(155060,463120));
  for (const [postal,town,easts] of [
    ['1234AA','Synthetic short group',[155001,155002]],
    ['1235AA','Synthetic wide group',[155001,155002,160010]],
    ['1235AB','Synthetic; invalid city',[156001,156002,156003]],
    ['1236 AA','Synthetic second group',[156001,156002,156003]],
  ]) for (const east of easts) add.run(postal,town,used,point(east,463100));
  fixture.close();

  await assert.rejects(prepareNlPostcodes(input,target,'0'.repeat(64)), /SHA-256 mismatch/);
  const sourceHash = digest();
  assert.equal(await prepareNlPostcodes(input,target,sourceHash),2);
  assert.deepEqual(JSON.parse(readFileSync(target,'utf8')),[
    ['1234AB','Synthetic locality',155030,463060],
    ['1236AA','Synthetic second group',156002,463100],
  ]);
  assert.equal(digest(),sourceHash);
  const second = join(directory,'second.json');
  await prepareNlPostcodes(input,second,sourceHash);
  assert.deepEqual(readFileSync(target),readFileSync(second));
  await assert.rejects(prepareNlPostcodes(input,target,sourceHash), {code:'EEXIST'});

  const invalid = new DatabaseSync(input);
  invalid.prepare('INSERT INTO standplaats VALUES (?,?,?,?)').run('1234AC','Synthetic invalid CRS','Plaats aangewezen',point(155000,463000,{srid:4326}));
  invalid.close();
  await assert.rejects(prepareNlPostcodes(input,join(directory,'invalid.json'),digest()), /reference system/);
  console.log('BAG fixture passed: binary geometry, source hash, coverage rules and reproducible aggregates.');
} finally { rmSync(directory,{recursive:true}); }

import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { validateBytes, validateFile } from '../ops/validate-postcodes.mjs';

const header = 'postal_code;city;latitude;longitude\n';
const csv = (body, options = {}) => validateBytes(Buffer.from(header + body), { country: 'DE', ...options });
let checks = 0;
function test(name, run) { run(); checks++; console.log(`OK ${name}`); }
test('UTF-8, BOM, CRLF, quoted fields and exact SHA', () => {
  const bytes = Buffer.from('\ufeff' + header.replace('\n', '\r\n') + '10115;"Test; \"\"City\"\"";52.5;13.4\r\n');
  const result = validateBytes(bytes, { country: 'DE' });
  assert.equal(result.unique_postcodes, 1);
  assert.equal(result.sha256, createHash('sha256').update(bytes).digest('hex'));
  assert.equal(result.database_changed, false);
  assert.equal(result.license_verified, false);
});
test('legacy pads zeros and reverses longitude/latitude', () => {
  const result = validateBytes(Buffer.from('"1067";"Synthetic";"13.7";"51.0"'), { country: 'DE', format: 'legacy-de' });
  assert.equal(result.padded_postcodes, 1);
  assert.equal(result.coordinate_bounds.min_latitude, 51);
  assert.equal(result.coordinate_bounds.min_longitude, 13.7);
  assert.throws(() => validateBytes(Buffer.from('1067;Synthetic;13;51'), { country: 'AT', format: 'legacy-de' }));
});
test('all supported postal formats and canonical duplicate keys', () => {
  for (const [country, postal] of Object.entries({ DE:'10115', FR:'75001', AT:'1010', CH:'8001', BE:'1000', LU:'1234', DK:'1000', LI:'9490', NL:'1234 AB', PL:'00-001', CZ:'100 00' })) {
    assert.equal(csv(`${postal};Synthetic;50;10`, { country }).unique_postcodes, 1);
  }
  assert.equal(csv('00-001;Synthetic;50;10\n00001;Synthetic;50;10', { country:'PL' }).identical_duplicates, 1);
  assert.throws(() => csv('1234;Synthetic;50;10', { country:'NL' }));
});
test('duplicates retain exact source counts but never average conflicts', () => {
  assert.equal(csv('10115;Synthetic;52;13\n10115;Synthetic;52.0;13.0').identical_duplicates, 1);
  assert.throws(() => csv('10115;Synthetic;52;13\n10115;Other;52;13'), /Conflicting/);
  assert.throws(() => csv('10115;Synthetic;52;13\n10115;Synthetic;52.1;13'), /Conflicting/);
});
test('rejects nonfinite, out-of-range and nondecimal coordinates', () => {
  for (const latitude of ['NaN', 'Infinity', '91', '-91', '', '0x20', '1e999', '52,1']) assert.throws(() => csv(`10115;Synthetic;${latitude};13`));
  assert.throws(() => csv('10115;Synthetic;52;181'));
});
test('rejects controls and oversized city fields', () => {
  for (const city of ['A\u0000B', 'A\tB', '"A\nB"', 'X'.repeat(151)]) assert.throws(() => csv(`10115;${city};52;13`));
});
test('rejects malformed CSV and extra fields', () => {
  for (const body of ['10115;"broken;52;13', '10115;"City"extra;52;13', '10115;Ci"ty;52;13', '10115;City;52;13;extra', '10115;City;52']) assert.throws(() => csv(body));
});
test('rejects invalid UTF-8, absent header, empty data and unsupported country', () => {
  assert.throws(() => validateBytes(Buffer.from([0xff]), { country:'DE' }), /UTF-8/);
  assert.throws(() => validateBytes(Buffer.from('10115;City;52;13'), { country:'DE' }), /header/);
  assert.throws(() => csv(''), /No valid/);
  assert.throws(() => csv('10115;City;52;13', { country:'XX' }));
});
test('rejects oversized sources before parsing', () => {
  assert.throws(() => validateBytes(Buffer.alloc(32 * 1024 * 1024 + 1), { country:'DE' }), /32 MiB/);
});
test('read-only file and CLI path with spaces', () => {
  const directory = mkdtempSync(join(tmpdir(), 'gunindex-postcode-test-'));
  try {
    const file = join(directory, 'synthetic data.csv');
    const bytes = Buffer.from(header + '10115;Synthetic;52;13');
    writeFileSync(file, bytes);
    assert.equal(validateFile(file, { country:'DE' }).unique_postcodes, 1);
    assert.deepEqual(readFileSync(file), bytes);
    const script = fileURLToPath(new URL('../ops/validate-postcodes.mjs', import.meta.url));
    const run = spawnSync(process.execPath, [script, '--country=DE', `--file=${file}`], { encoding:'utf8' });
    assert.equal(run.status, 0, run.stderr);
    assert.equal(JSON.parse(run.stdout).database_changed, false);
    assert.equal(spawnSync(process.execPath, [script, '--unknown=value']).status, 1);
    assert.throws(() => validateFile(directory, { country:'DE' }));
  } finally { rmSync(directory, { recursive:true }); }
});
console.log(`${checks} postcode validator test groups passed; synthetic data only.`);

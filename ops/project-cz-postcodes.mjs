import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';

const [input, projection, output] = process.argv.slice(2);
if (!input || !projection || !output || process.argv.length !== 5) {
  throw new Error('Usage: node ops/project-cz-postcodes.mjs AGGREGATES.json PROJ4.js NEW.csv');
}
const library = resolve(projection);
const digest = createHash('sha256').update(readFileSync(library)).digest('hex');
if (digest !== '3c800c9082229fe58fef68ed458b720ec81d9e9a398e71908275aeaecd76b437') {
  throw new Error('Expected the reviewed proj4 2.23.0 bundle.');
}
const proj4 = createRequire(import.meta.url)(library);
// EPSG:5514 with EPSG:1623, the Czech approximation for non-surveying use.
const conversion = proj4('+proj=krovak +lat_0=49.5 +lon_0=24.8333333333333 +alpha=30.2881397527778 +k=0.9999 +ellps=bessel +towgs84=570.8,85.7,462.8,4.998,1.587,5.261,3.56 +units=m', 'EPSG:4326');
const points = JSON.parse(readFileSync(input, 'utf8'));
if (!Array.isArray(points) || points.length < 2 || points.length > 10000) throw new Error('Unexpected postcode count.');
const rows = ['postal_code;city;latitude;longitude'];
let previous = '';
for (const [code, town, east, north] of points) {
  if (typeof code !== 'string' || !/^[1-7][0-9]{4}$/.test(code) || code <= previous || typeof town !== 'string'
    || !/^[^\u0000-\u001f\u007f;"\ufffd]{1,150}$/u.test(town) || !Number.isFinite(east) || !Number.isFinite(north)) {
    throw new Error('Unexpected aggregate row.');
  }
  const [longitude, latitude] = conversion.forward([east, north]);
  if (!(longitude >= 12 && longitude <= 19 && latitude >= 48.5 && latitude <= 51.1)) {
    throw new Error('Projected postcode lies outside the expected Czech bounds.');
  }
  rows.push(`${code};${town};${latitude.toFixed(5)};${longitude.toFixed(5)}`);
  previous = code;
}
writeFileSync(output, rows.join('\n') + '\n', {flag:'wx'});
console.log(`Projected ${points.length} aggregated postcodes to WGS 84.`);

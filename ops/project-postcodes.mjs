import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const systems = {
  // EPSG:5514 with EPSG:1623 for Czechia.
  CZ: {code:/^[1-7][0-9]{4}$/, lon:[12,19], lat:[48.5,51.1],
    definition:'+proj=krovak +lat_0=49.5 +lon_0=24.8333333333333 +alpha=30.2881397527778 +k=0.9999 +ellps=bessel +towgs84=570.8,85.7,462.8,4.998,1.587,5.261,3.56 +units=m'},
  // EPSG:2180 with the ETRS89 approximation for postcode distances.
  PL: {code:/^[0-9]{5}$/, lon:[14,24.3], lat:[48.9,55.2],
    definition:'+proj=tmerc +lat_0=0 +lon_0=19 +k=0.9993 +x_0=500000 +y_0=-5300000 +ellps=GRS80 +towgs84=0,0,0 +units=m'},
};

export function projectPostcodes(country, args) {
  const system = Object.hasOwn(systems,country) ? systems[country] : null;
  const [input, projection, output] = args;
  if (!system || !input || !projection || !output || args.length !== 3) {
    throw new Error('Usage: node ops/project-postcodes.mjs CZ|PL AGGREGATES.json PROJ4.js NEW.csv');
  }
  const library = resolve(projection);
  const digest = createHash('sha256').update(readFileSync(library)).digest('hex');
  if (digest !== '3c800c9082229fe58fef68ed458b720ec81d9e9a398e71908275aeaecd76b437') {
    throw new Error('Expected the reviewed proj4 2.23.0 bundle.');
  }
  const proj4 = createRequire(import.meta.url)(library);
  const conversion = proj4(system.definition,'EPSG:4326');
  const points = JSON.parse(readFileSync(input,'utf8'));
  if (!Array.isArray(points) || points.length < 2 || points.length > 100000) throw new Error('Unexpected postcode count.');
  const rows = ['postal_code;city;latitude;longitude'];
  let previous = '';
  for (const [code,town,east,north] of points) {
    if (typeof code !== 'string' || !system.code.test(code) || code <= previous || typeof town !== 'string'
      || !/^[^\u0000-\u001f\u007f;"\ufffd]{1,150}$/u.test(town) || !Number.isFinite(east) || !Number.isFinite(north)) {
      throw new Error('Unexpected aggregate row.');
    }
    const [longitude,latitude] = conversion.forward([east,north]);
    if (!(longitude >= system.lon[0] && longitude <= system.lon[1] && latitude >= system.lat[0] && latitude <= system.lat[1])) {
      throw new Error('Projected postcode lies outside the expected bounds.');
    }
    rows.push([code,town,latitude.toFixed(5),longitude.toFixed(5)].join(';'));
    previous = code;
  }
  writeFileSync(output,rows.join('\n') + '\n',{flag:'wx'});
  console.log('Projected ' + points.length + ' ' + country + ' postcode aggregates to WGS 84.');
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
  projectPostcodes(process.argv[2],process.argv.slice(3));
}

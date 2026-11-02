#!/usr/bin/env node
/** Read-only postcode preflight. The PHP importer remains authoritative for DB writes. */
import { createHash } from 'node:crypto';
import { closeSync, constants, fstatSync, lstatSync, openSync, readSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const MAX_BYTES = 32 * 1024 * 1024;
const MAX_RECORDS = 100000;
const patterns = {
  DE: /^[0-9]{5}$/, FR: /^[0-9]{5}$/,
  AT: /^[0-9]{4}$/, CH: /^[0-9]{4}$/, BE: /^[0-9]{4}$/, LU: /^[0-9]{4}$/,
  DK: /^[0-9]{4}$/, LI: /^[0-9]{4}$/,
  NL: /^[1-9][0-9]{3}[A-Z]{2}$/, PL: /^[0-9]{2}-?[0-9]{3}$/, CZ: /^[0-9]{3} ?[0-9]{2}$/,
};

function* rows(text) {
  let row = [], field = '', quoted = false, closed = false, started = false, line = 1;
  for (let i = 0; i < text.length; i++) {
    const char = text[i];
    if (quoted) {
      if (char === '"') {
        if (text[i + 1] === '"') { field += '"'; i++; }
        else { quoted = false; closed = true; }
      } else { field += char; if (char === '\n') line++; }
      continue;
    }
    if (char === '"') {
      if (started || closed) throw new Error(`Malformed CSV quote near line ${line}.`);
      quoted = true; started = true; continue;
    }
    if (char === ';') {
      row.push(field);
      if (row.length > 3) throw new Error(`Expected four CSV fields near line ${line}.`);
      field = ''; closed = false; started = false; continue;
    }
    if (char === '\r' || char === '\n') {
      if (char === '\r' && text[i + 1] === '\n') i++;
      if (row.length || field || started) { row.push(field); yield { row, line }; }
      row = []; field = ''; closed = false; started = false; line++; continue;
    }
    if (closed) throw new Error(`Unexpected content after closing quote near line ${line}.`);
    field += char; started = true;
  }
  if (quoted) throw new Error(`Unclosed CSV quote near line ${line}.`);
  if (row.length || field || started) { row.push(field); yield { row, line }; }
}

function numeric(value, limit) {
  if (!/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(value)) return null;
  const number = Number(value);
  return Number.isFinite(number) && Math.abs(number) <= limit ? number : null;
}

export function validateBytes(bytes, { country, format = 'standard' } = {}) {
  if (!Buffer.isBuffer(bytes) && !(bytes instanceof Uint8Array)) throw new Error('Expected bytes.');
  if (bytes.byteLength > MAX_BYTES) throw new Error('CSV exceeds 32 MiB.');
  if (typeof country !== 'string' || !Object.hasOwn(patterns, country.toUpperCase())) throw new Error('Explicit supported country required.');
  country = country.toUpperCase();
  if (!['standard', 'legacy-de'].includes(format) || (format === 'legacy-de' && country !== 'DE')) throw new Error('Invalid format/country combination.');
  let text;
  try { text = new TextDecoder('utf-8', { fatal: true }).decode(bytes); }
  catch { throw new Error('CSV must contain valid UTF-8.'); }
  const entries = new Map();
  let headerNeeded = format === 'standard', sourceRows = 0, identicalDuplicates = 0, paddedPostcodes = 0;
  const bounds = { min_latitude: 90, max_latitude: -90, min_longitude: 180, max_longitude: -180 };
  for (const { row, line } of rows(text)) {
    if (headerNeeded) {
      if (row.join(';') !== 'postal_code;city;latitude;longitude' || row.length !== 4) throw new Error('Required header: postal_code;city;latitude;longitude');
      headerNeeded = false; continue;
    }
    if (row.length !== 4) throw new Error(`Expected four CSV fields near line ${line}.`);
    let [postal, city, third, fourth] = row.map(value => value.trim());
    if (format === 'legacy-de') {
      if (!/^[0-9]{1,5}$/.test(postal)) throw new Error(`Invalid legacy postcode near line ${line}.`);
      if (postal.length < 5) paddedPostcodes++;
      postal = postal.padStart(5, '0');
      [third, fourth] = [fourth, third];
    }
    postal = postal.toUpperCase();
    if (country === 'NL') postal = postal.replace(/\s/g, '');
    const latitude = numeric(third, 90), longitude = numeric(fourth, 180);
    if (!patterns[country].test(postal) || !city || [...city].length > 150 || /[\x00-\x1f\x7f]/u.test(city) || latitude === null || longitude === null) {
      throw new Error(`Invalid postal/location values near line ${line}.`);
    }
    if (country === 'PL' || country === 'CZ') postal = postal.replace(/[- ]/g, '');
    sourceRows++;
    // Conservatively require identical numeric coordinates. Do not silently average places.
    const normalized = JSON.stringify([city, latitude, longitude]);
    if (entries.has(postal)) {
      if (entries.get(postal) !== normalized) throw new Error(`Conflicting duplicate postcode near line ${line}; source review required.`);
      identicalDuplicates++; continue;
    }
    entries.set(postal, normalized);
    if (entries.size > MAX_RECORDS) throw new Error('CSV exceeds 100000 unique postcodes; split into reviewed parts.');
    bounds.min_latitude = Math.min(bounds.min_latitude, latitude);
    bounds.max_latitude = Math.max(bounds.max_latitude, latitude);
    bounds.min_longitude = Math.min(bounds.min_longitude, longitude);
    bounds.max_longitude = Math.max(bounds.max_longitude, longitude);
  }
  if (!entries.size) throw new Error('No valid locations in CSV.');
  return {
    country, format, bytes: bytes.byteLength,
    sha256: createHash('sha256').update(bytes).digest('hex'),
    source_rows: sourceRows, unique_postcodes: entries.size,
    identical_duplicates: identicalDuplicates, padded_postcodes: paddedPostcodes,
    coordinate_bounds: bounds, database_changed: false,
    accuracy_verified: false, license_verified: false,
  };
}

export function validateFile(file, options) {
  let descriptor;
  try {
    const before = lstatSync(file);
    if (!before.isFile() || before.isSymbolicLink() || before.size > MAX_BYTES) throw new Error('Expected a regular non-symlink CSV up to 32 MiB.');
    descriptor = openSync(file, constants.O_RDONLY | (constants.O_NOFOLLOW ?? 0));
    const opened = fstatSync(descriptor);
    if (!opened.isFile() || opened.dev !== before.dev || opened.ino !== before.ino) throw new Error('Source file changed while opening.');
    const chunks = [];
    let total = 0;
    while (true) {
      const buffer = Buffer.alloc(65536);
      const size = readSync(descriptor, buffer, 0, buffer.length, null);
      if (!size) break;
      total += size;
      if (total > MAX_BYTES) throw new Error('CSV exceeds 32 MiB.');
      chunks.push(buffer.subarray(0, size));
    }
    const after = fstatSync(descriptor);
    if (opened.size !== after.size || opened.mtimeMs !== after.mtimeMs || total !== after.size) throw new Error('Source file changed while reading.');
    return validateBytes(Buffer.concat(chunks), options);
  } finally { if (descriptor !== undefined) closeSync(descriptor); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  try {
    const options = {};
    for (const argument of process.argv.slice(2)) {
      const match = /^--(country|file|format)=(.+)$/.exec(argument);
      if (!match || Object.hasOwn(options, match[1])) throw new Error('Usage: node ops/validate-postcodes.mjs --country=DE --file=ZIP/PLZ.csv --format=legacy-de');
      options[match[1]] = match[2];
    }
    if (!options.file) throw new Error('An explicit --file is required.');
    console.log(JSON.stringify(validateFile(options.file, options), null, 2));
  } catch (error) {
    // Do not echo filesystem paths or dataset field values on I/O failures.
    console.error(error.code ? 'Cannot read the requested source file.' : error.message);
    process.exitCode = 1;
  }
}

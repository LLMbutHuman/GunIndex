import { createHash } from 'node:crypto';
import { lstatSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

function reviewedText(input, expected, limit) {
  const source = lstatSync(input);
  if (!source.isFile() || source.size > limit || !/^[a-f0-9]{64}$/.test(expected)) throw new Error('Expected a regular, checksum-pinned source file.');
  const bytes = readFileSync(input);
  if (bytes.length > limit || createHash('sha256').update(bytes).digest('hex') !== expected) throw new Error('Source SHA-256 mismatch.');
  return new TextDecoder('utf-8',{fatal:true}).decode(bytes);
}

function observedPostcodes(text, year) {
  const [header,...rows] = text.trimEnd().split(/\r?\n/);
  if (header !== 'PNR20;KØN;ALDER;TID;INDHOLD') throw new Error('Unexpected StatBank column names.');
  const codes = new Set();
  for (const row of rows) {
    const [code,sex,age,date,population,...extra] = row.split(';');
    if (!/^\d{4}(?:\d{3})?$/.test(code) || sex !== 'TOT' || age !== 'IALT' || date !== year
      || !/^\d+$/.test(population) || extra.length) throw new Error('Unexpected StatBank postcode data.');
    if (code.length === 7 && Number(population) > 0) codes.add(code.slice(3));
  }
  if (!codes.size) throw new Error('StatBank contained no populated postcodes.');
  return codes;
}

// ArcGIS rings use clockwise exteriors and counterclockwise holes.
function polygonCentroid(rings) {
  if (!Array.isArray(rings) || !rings.length) throw new Error('Missing postal area rings.');
  let area=0, weightedEast=0, weightedNorth=0;
  let minEast=Infinity,maxEast=-Infinity,minNorth=Infinity,maxNorth=-Infinity;
  for (const ring of rings) {
    if (!Array.isArray(ring) || ring.length<4 || ring.some(point => !Array.isArray(point) || point.length!==2
      || !Number.isFinite(point[0]) || !Number.isFinite(point[1]))) throw new Error('Invalid postal area ring.');
    const [east,north]=ring[0];
    if (ring.at(-1)[0]!==east || ring.at(-1)[1]!==north) throw new Error('Postal area rings must close.');
    let twiceArea=0, momentEast=0, momentNorth=0;
    for (let i=1;i<ring.length;i++) {
      minEast=Math.min(minEast,ring[i][0]); maxEast=Math.max(maxEast,ring[i][0]);
      minNorth=Math.min(minNorth,ring[i][1]); maxNorth=Math.max(maxNorth,ring[i][1]);
      const [x1,y1]=[ring[i-1][0]-east,ring[i-1][1]-north];
      const [x2,y2]=[ring[i][0]-east,ring[i][1]-north];
      const cross=x1*y2-x2*y1;
      twiceArea+=cross;
      momentEast+=(x1+x2)*cross;
      momentNorth+=(y1+y2)*cross;
    }
    if (Math.abs(twiceArea)<0.01) continue;
    area+=twiceArea;
    weightedEast+=twiceArea*east+momentEast/3;
    weightedNorth+=twiceArea*north+momentNorth/3;
  }
  if (area>=-0.01) throw new Error('Postal area lacks a valid clockwise exterior.');
  const east=weightedEast/area, north=weightedNorth/area;
  // Several western districts extend far into the North Sea in this layer.
  if (maxEast-minEast>30000 || maxNorth-minNorth>30000 || !(east>400000 && east<950000 && north>6000000 && north<6500000)) return null;
  return [east,north];
}

export function prepareDkPostcodes(geometry, statistics, year) {
  if (!/^20[0-9]{2}$/.test(year)) throw new Error('A four-digit StatBank reference year is required.');
  const observed=observedPostcodes(statistics,year);
  if (geometry?.geometryType!=='esriGeometryPolygon' || geometry.spatialReference?.wkid!==25832
    || geometry.exceededTransferLimit || !Array.isArray(geometry.features) || !geometry.features.length
    || geometry.features.length>2000) throw new Error('Expected one complete collection of Danish EPSG:25832 polygons.');
  const seen=new Set(), points=[];
  let geographyExcluded=0;
  for (const feature of geometry.features) {
    const {Postnummer:code,Navn:town,LandeKode:country,Virkningtil:expires}=feature.attributes;
    const postal=String(code);
    if (!Number.isInteger(code) || !/^[1-9][0-9]{3}$/.test(postal) || seen.has(postal)
      || typeof town!=='string' || !/^[^\u0000-\u001f\u007f;"\ufffd]{1,150}$/u.test(town)
      || (country!==null && country!=='DK') || expires!==null) throw new Error('Unexpected postal district attributes.');
    seen.add(postal);
    if (!observed.has(postal)) continue;
    const centre=polygonCentroid(feature.geometry?.rings);
    if (centre) points.push([postal,town,...centre]);
    else geographyExcluded++;
  }
  if (!points.length) throw new Error('No postcode polygons matched the reference statistics.');
  points.sort(([a],[b])=>a<b?-1:a>b?1:0);
  return {points, source_postcodes:seen.size, populated_postcodes:observed.size, excluded:seen.size-points.length, geography_excluded:geographyExcluded};
}

if (process.argv[1] && import.meta.url===pathToFileURL(resolve(process.argv[1])).href) {
  try {
    const [polygons,statistics,year,polygonHash,statHash,output]=process.argv.slice(2);
    if (process.argv.length!==8 || !output) throw new Error('Usage: node ops/prepare-dk-postcodes.mjs DAGI.json POSTNR2.csv YEAR DAGI_SHA256 STATBANK_SHA256 NEW.json');
    const result=prepareDkPostcodes(JSON.parse(reviewedText(polygons,polygonHash,128000000)),reviewedText(statistics,statHash,1024**2),year);
    writeFileSync(output,JSON.stringify(result.points)+'\n',{flag:'wx'});
    console.log(JSON.stringify({prepared:result.points.length,source_postcodes:result.source_postcodes,populated_postcodes:result.populated_postcodes,excluded:result.excluded,geography_excluded:result.geography_excluded}));
  } catch(error) {
    console.error(error.code?'Cannot read the two source files or create the aggregate file.':error.message);
    process.exitCode=1;
  }
}

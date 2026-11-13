import {readFile, mkdir, copyFile, writeFile} from 'node:fs/promises';
import {createHash} from 'node:crypto';
const root=new URL('../',import.meta.url),source=new URL('node_modules/openpgp/',root),target=new URL('assets/vendor/openpgp/',root);
const pkg=JSON.parse(await readFile(new URL('package.json',source),'utf8'));
if(pkg.name!=='openpgp'||pkg.version!=='6.3.2')throw Error('Unexpected OpenPGP dependency');
const check=process.argv.includes('--check');
if(!check)await mkdir(target,{recursive:true});
const files={'dist/openpgp.min.mjs':'openpgp.min.mjs','LICENSE':'LICENSE'};
const hashes={};
for(const [from,to] of Object.entries(files)){
  const bytes=await readFile(new URL(from,source));hashes[to]=createHash('sha256').update(bytes).digest('hex');
  if(check){if(!bytes.equals(await readFile(new URL(to,target))))throw Error('Vendor mismatch: '+to);}
  else await copyFile(new URL(from,source),new URL(to,target));
}
const provenance=JSON.stringify({package:'openpgp',version:pkg.version,license:pkg.license,
  source:'https://github.com/openpgpjs/openpgpjs/tree/v6.3.2',sha256:hashes},null,2)+'\n';
if(check){if((await readFile(new URL('PROVENANCE.json',target),'utf8')).replace(/\r\n/g,'\n')!==provenance)throw Error('Vendor provenance mismatch');}
else await writeFile(new URL('PROVENANCE.json',target),provenance);
console.log((check?'Verified':'Vendored')+' OpenPGP 6.3.2 with license and SHA256 manifest.');

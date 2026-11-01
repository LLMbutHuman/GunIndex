const fs=require('fs');
function localModule(name,fallback){
  try{return require('./browser-tools/node_modules/'+name)}catch(error){if(error.code!=='MODULE_NOT_FOUND')throw error;}
  try{return require(name)}catch(error){if(error.code!=='MODULE_NOT_FOUND')throw error;}
  return require(fallback);
}
const playwright=localModule('playwright','/opt/codex/cua_node/lib/node_modules/playwright');
function launchOptions(){
 const options={headless:true,args:['--no-sandbox']};
 if(process.env.CHROMIUM_PATH)options.executablePath=process.env.CHROMIUM_PATH;
 else if(fs.existsSync('/usr/bin/chromium'))options.executablePath='/usr/bin/chromium';
 return options;
}
module.exports={playwright,launchOptions,axe:()=>localModule('@axe-core/playwright','./.runtime/a11y-tools/node_modules/@axe-core/playwright').default};

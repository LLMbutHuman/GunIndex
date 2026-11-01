/* Local automated WCAG/keyboard checks; no claim of a complete accessibility audit. */
const assert=require('assert');
const {playwright:pw,launchOptions,axe}=require('./browser_tools');
const AxeBuilder=axe();
const origin=process.env.GUNINDEX_A11Y_URL||'http://127.0.0.1:8081';const parsed=new URL(origin);
assert(parsed.protocol==='http:'&&['127.0.0.1','localhost','[::1]'].includes(parsed.hostname)&&!parsed.username&&!parsed.password,'Only a local test origin is supported');
const paths=['/','/marketplace/search.php','/auth/login.php','/auth/register.php'];
(async()=>{
 const browser=await pw.chromium.launch(launchOptions());const checks=[];
 try{
  for(const width of [1440,390]){
   const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'});const page=await context.newPage();
   for(const path of paths){
    const response=await page.goto(origin+path,{waitUntil:'networkidle'});assert.strictEqual(response.status(),200,'A11y target unavailable: '+path);
    await page.keyboard.press('Tab');const skip=await page.evaluate(()=>({tag:document.activeElement.tagName,href:document.activeElement.getAttribute('href')}));assert(skip.tag==='A'&&skip.href==='#main-content','Keyboard skip link missing: '+path);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']).analyze();
    checks.push({path,width,violations:axe.violations.map(item=>({id:item.id,impact:item.impact,targets:item.nodes.map(node=>node.target)})),manual_review_items:axe.incomplete.length});
   }
   await context.close();
  }
  const failures=checks.filter(item=>item.violations.length);console.log(JSON.stringify({result:failures.length?'FAIL':'PASS',checks,scope:'automated public-page WCAG rules and keyboard skip link; manual screenreader/zoom/content review remains required'},null,2));
  if(failures.length)process.exitCode=1;
 }finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exit(1)});

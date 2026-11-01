/* Real country/language controls; no external services or production data. */
const assert=require('assert');
const {playwright,launchOptions}=require('./browser_tools');
const origin='http://127.0.0.1:8081';
(async()=>{
 const browser=await playwright.chromium.launch(launchOptions());const checks=[];const errors=[];
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'});const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  for(const [locale,country] of [['de','DE'],['en','AT'],['fr','FR'],['nl','NL'],['pl','PL'],['cs','CZ'],['da','DK'],['it','CH']]){
   await page.goto(origin+'/',{waitUntil:'networkidle'});await page.locator('.market-switcher summary').click();const form=page.locator('form[action="/user/language.php"]');await form.locator('[name=locale]').selectOption(locale);await form.locator('[name=market_country]').selectOption(country);
   await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),form.locator('button[type=submit]').click()]);
   assert.strictEqual(await page.locator('html').getAttribute('lang'),locale);assert.strictEqual(await page.locator('[name=market_country]').inputValue(),country);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile overflow for '+locale);checks.push('locale_'+locale+'_'+country);
  }
  await page.goto(origin+'/marketplace/search.php',{waitUntil:'networkidle'});const search=page.locator('form.search-toolbar');await search.locator('[name=scope]').selectOption('international');await search.locator('[name=country_code]').selectOption('DE');
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),search.locator('button[type=submit]').click()]);assert.strictEqual(new URL(page.url()).searchParams.get('scope'),'international');assert.strictEqual(await page.locator('form.search-toolbar [name=scope]').inputValue(),'international');checks.push('international_search_control');
  assert.deepStrictEqual(errors,[]);console.log(JSON.stringify({result:'PASS',checks,js_errors:0,scope:'Eight mobile language/country changes and international search; exact distances covered by licensed synthetic DB tests'}));
 }finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exit(1)});

/* Real paid-publication UI; synthetic local fixtures, no provider requests. */
const assert=require('assert');
const {execFileSync}=require('child_process');
const {playwright,launchOptions}=require('./browser_tools');
const origin='http://127.0.0.1:8081';
const docker=(...args)=>execFileSync('docker',['--host=unix:///var/run/docker.sock',...args],{stdio:['pipe','pipe','pipe']});
function fixture(operation,fields={}){return JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','-i','gunindex-rebuild-app','php','tests/auction_billing_browser_fixture.php'],{input:JSON.stringify({operation,...fields}),stdio:['pipe','pipe','pipe']}).toString());}
(async()=>{
 let data,browser;const errors=[],external=[],checks=[];
 docker('stop','gunindex-rebuild-worker');
 try{
  data=fixture('setup');browser=await playwright.chromium.launch(launchOptions());
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'});
  const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  page.on('request',request=>{if(!request.url().startsWith(origin+'/'))external.push(new URL(request.url()).hostname);});
  await page.goto(origin+'/auth/login.php');await page.locator('[name=login]').fill(data.username);await page.locator('[name=password]').fill(data.password);
  await Promise.all([page.waitForURL('**/user/dashboard.php'),page.locator('main button[type=submit]').click()]);
  async function fill(){
   await page.locator('[name=title]').fill('Synthetic browser folding table');await page.locator('textarea[name=description]').fill('Synthetic ordinary folding table, used only in isolated browser regression tests.');
   await page.locator('[name=category_id]').selectOption(String(data.category));await page.locator('[name=price]').fill('10.00');await page.locator('[name=zip]').fill('10115');await page.locator('[name=city]').fill('Synthetic city');await page.locator('[name=accept_rules]').check();
  }
  async function save(button){await Promise.all([page.waitForURL('**/user/dashboard.php?saved=*'),button.click()]);return Number(new URL(page.url()).searchParams.get('saved'));}
  await page.goto(origin+'/user/create-listing.php');await fill();
  assert(await page.locator('[data-listing-save]').isVisible());assert(await page.locator('[data-auction-publish]').isHidden());
  const fixed=await save(page.locator('[data-listing-save]'));assert.deepStrictEqual(fixture('state',{username:data.username,id:fixed}).charges,[]);checks.push('fixed_publication_without_fee');
  await page.goto(origin+'/user/create-listing.php');await fill();await page.locator('[name=type]').selectOption('auction');await page.locator('[name=status]').selectOption('draft');
  assert(await page.locator('[data-auction-publish]').isHidden());assert.strictEqual(await page.locator('[name=accept_auction_fees]').evaluate(node=>node.required),false);
  const draft=await save(page.locator('[data-listing-save]'));const draftState=fixture('state',{username:data.username,id:draft});assert.strictEqual(draftState.status,'draft');assert.deepStrictEqual(draftState.charges,[]);checks.push('draft_without_fee_or_fee_consent');
  let first=true;
  for(const [locale,label] of Object.entries(data.labels)){
   await page.locator('.market-switcher summary').click();const language=page.locator('form[action="/user/language.php"]');await language.locator('[name=locale]').selectOption(locale);await language.locator('[name=market_country]').selectOption('DE');
   await Promise.all([page.waitForNavigation(),language.locator('button[type=submit]').click()]);
   await page.goto(origin+(first?'/user/edit-listing.php?id='+draft:'/user/create-listing.php'));await fill();
   assert.strictEqual(await page.locator('html').getAttribute('lang'),locale);
   await page.locator('[name=type]').selectOption('auction');await page.locator('[name=status]').selectOption('active');
   const paid=page.locator('[data-auction-publish]');assert(await paid.isVisible());assert(await page.locator('[data-listing-save]').isHidden());assert.strictEqual(await paid.innerText(),label);
   for(const name of ['accept_auction_fees','legal_confirm']){const control=page.locator('[name='+name+']');assert.strictEqual(await control.isChecked(),false);assert.strictEqual(await control.evaluate(node=>node.required),true);await control.check();}
   assert(!/auction_billing\.[a-z_]+/.test(await page.locator('main').innerText()));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile fee form overflow '+locale);
   const id=await save(paid);if(first)assert.strictEqual(id,draft);
   let state=fixture('state',{username:data.username,id});assert.strictEqual(state.charges.length,1);assert.strictEqual(Number(state.charges[0].gross_cents),50);assert.strictEqual(state.status,'active');
   await page.goto(origin+'/user/edit-listing.php?id='+id);assert.strictEqual(await page.locator('[data-auction-publish]').count(),0);await page.locator('[name=accept_rules]').check();await save(page.locator('[data-listing-save]'));
   state=fixture('state',{username:data.username,id});assert.strictEqual(state.charges.length,1);
   await page.goto(origin+'/user/billing.php?order='+state.charges[0].invoice_id);assert((await page.locator('main').innerText()).includes(state.charges[0].invoice_number));
   assert.strictEqual(await page.locator('main input[name=action][value=order],main input[name=action][value=bump]').count(),0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile fee order overflow '+locale);
   checks.push('paid_publish_edit_order_'+locale);first=false;
  }
  assert.deepStrictEqual(errors,[]);assert.deepStrictEqual(external,[]);
  console.log(JSON.stringify({result:'PASS',checks,js_errors:0,external_requests:0,scope:'Synthetic publication and receivables only; no actual payment or provider approval'}));
 }finally{
  try{if(browser)await browser.close();}finally{try{if(data)fixture('restore',{saved:data.saved});}finally{docker('start','gunindex-rebuild-worker');}}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});

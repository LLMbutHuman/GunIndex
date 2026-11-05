/* Real publication/relisting UI; synthetic local fixtures, no provider requests. */
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
  await context.route('**/*',route=>route.request().url().startsWith(origin+'/')?route.continue():route.abort());
  const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  page.on('request',request=>{if(!request.url().startsWith(origin+'/'))external.push(new URL(request.url()).hostname);});
  await page.goto(origin+'/auth/login.php');await page.locator('[name=login]').fill(data.username);await page.locator('[name=password]').fill(data.password);
  await Promise.all([page.waitForURL('**/user/dashboard.php'),page.locator('main button[type=submit]').click()]);
  async function fill(){
   await page.locator('[name=title]').fill('Synthetic browser folding table');await page.locator('textarea[name=description]').fill('Synthetic ordinary folding table, used only in isolated browser regression tests.');
   await page.locator('[name=category_id]').selectOption(String(data.category));await page.locator('[name=price]').fill('10.00');await page.locator('[name=zip]').fill('10115');await page.locator('[name=city]').fill('Synthetic city');await page.locator('[name=accept_rules]').check();
  }
  async function save(button){await Promise.all([page.waitForURL('**/user/dashboard.php?saved=*'),button.click()]);return Number(new URL(page.url()).searchParams.get('saved'));}
  async function language(locale){
   await page.locator('.market-switcher summary').click();const form=page.locator('form[action="/user/language.php"]');
   await form.locator('[name=locale]').selectOption(locale);await form.locator('[name=market_country]').selectOption('DE');
   await Promise.all([page.waitForNavigation(),form.locator('button[type=submit]').click()]);
  }
  const state=(id,locale='de')=>fixture('state',{username:data.username,id,locale});
  async function mobile(scope,locale){
   assert.strictEqual(await page.locator('html').getAttribute('lang'),locale);
   assert(!/(?:relisting|auction_billing)\.[a-z_]+/.test(await page.locator('main').innerText()),'Untranslated key: '+scope);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile overflow: '+scope+' '+locale);
  }
  function unsold(source){
   assert.strictEqual(source.type,'auction');assert.strictEqual(source.status,'ended');
   assert.strictEqual(source.highest_bidder_id,null);assert.strictEqual(source.current_bid_cents,null);assert.strictEqual(source.proxy_count,0);
   assert.deepStrictEqual(source.events,[{event_type:'close_no_bid',idempotency_key:'close-'+source.id,user_id:null,amount_cents:null}]);
  }
  async function relist(source,locale,root,free){
   const copy=data.relisting_labels[locale];unsold(source);
   const dashboard=await page.goto(origin+'/user/dashboard.php');assert.strictEqual(dashboard.status(),200);
   const entry=page.locator('a[href="/user/relist.php?id='+source.id+'"]');assert.strictEqual(await entry.count(),1);
   assert.strictEqual(await entry.innerText(),copy.action);
   const [response]=await Promise.all([page.waitForNavigation(),entry.click()]);assert.strictEqual(response.status(),200);
   assert.strictEqual(await page.locator('h1').innerText(),copy.heading);
   const confirmation=page.locator('[name=confirm_relist]');assert.strictEqual(await confirmation.isChecked(),false);
   assert.strictEqual(await confirmation.evaluate(node=>node.required),true);
   assert((await page.locator('main').innerText()).includes(copy.help));
   assert((await page.locator('main').innerText()).includes(copy.confirm));
   assert(await page.getByText(free?copy.free_available:copy.free_used,{exact:true}).isVisible());
   await mobile('relisting confirmation',locale);
   assert.deepStrictEqual(state(source.id,locale),source,'Reading confirmation changed the source');
   await confirmation.check();
   await Promise.all([
    page.waitForURL(url=>url.pathname==='/user/edit-listing.php'&&url.searchParams.get('relisted')==='1'),
    page.locator('main form:has([name=confirm_relist]) button[type=submit]').click()
   ]);
   const child=Number(new URL(page.url()).searchParams.get('id'));assert(child>source.id);
   assert((await page.locator('main').innerText()).includes(copy.draft_created));
   assert.strictEqual(await page.locator('[name=status]').inputValue(),'draft');
   const draft=state(child,locale);assert.strictEqual(draft.status,'draft');assert.strictEqual(draft.type,'auction');
   assert.deepStrictEqual(draft.charges,[]);assert.deepStrictEqual(draft.events,[]);assert.strictEqual(draft.proxy_count,0);
   assert.strictEqual(draft.contract,null);assert.strictEqual(draft.highest_bidder_id,null);assert.strictEqual(draft.current_bid_cents,null);
   assert.strictEqual(draft.relisting.root_listing_id,root);assert.strictEqual(draft.relisting.parent_listing_id,source.id);
   assert.strictEqual(draft.relisting.child_listing_id,child);assert.strictEqual(draft.relisting.published_at,null);
   assert.strictEqual(draft.relisting.free_available,free);
   assert.strictEqual(draft.quote.tariff.listing_cents,free?0:50);assert.strictEqual(draft.quote.locale,locale);
   assert.deepStrictEqual(draft.quote.tariff.rates,[300,200,100,50]);assert.strictEqual(draft.quote.tariff.cap_cents,2990);
   assert.strictEqual(await page.locator('[name=auction_fee_quote]').inputValue(),draft.quote.hash);
   assert((await page.locator('.auction-fee-contract').innerText()).includes(draft.quote.terms),'Rendered fee quote differs from stored facts');
   assert.strictEqual(draft.images.length,source.images.length);assert(draft.images.length>0);
   for(let index=0;index<source.images.length;index++){
    const original=source.images[index],copied=draft.images[index];
    assert.notStrictEqual(copied.id,original.id);assert.notStrictEqual(copied.key_fingerprint,original.key_fingerprint);
    assert.strictEqual(copied.position,original.position);assert.strictEqual(copied.width,original.width);assert.strictEqual(copied.height,original.height);
    assert(/^[a-f0-9]{64}$/.test(copied.large_sha256)&&/^[a-f0-9]{64}$/.test(copied.thumb_sha256));
   }
   const previews=page.locator('img[src^="/user/listing-image.php?"]');assert.strictEqual(await previews.count(),source.images.length);
   for(let index=0;index<await previews.count();index++)await previews.nth(index).scrollIntoViewIfNeeded();
   await page.waitForFunction(()=>Array.from(document.querySelectorAll('img[src^="/user/listing-image.php?"]')).every(image=>image.complete&&image.naturalWidth>0));
   await mobile('relisted draft and copied images',locale);
   assert.deepStrictEqual(state(source.id,locale),source,'Relisting changed original images, fees or bid history');
   await page.locator('[name=status]').selectOption('active');await page.locator('[name=accept_rules]').check();
   const paid=page.locator('[data-auction-publish]');assert(await paid.isVisible());assert(await page.locator('[data-listing-save]').isHidden());
   assert.strictEqual(await paid.innerText(),data.labels[locale]);
   for(const name of ['accept_auction_fees','legal_confirm']){
    const checkbox=page.locator('[name='+name+']');assert.strictEqual(await checkbox.isChecked(),false);
    assert.strictEqual(await checkbox.evaluate(node=>node.required),true);await checkbox.check();
   }
   await mobile('explicit relisting publication',locale);assert.strictEqual(await save(paid),child);
   const published=state(child,locale);assert.strictEqual(published.status,'active');assert.strictEqual(published.charges.length,1);
   const charge=published.charges[0];assert.strictEqual(charge.kind,'listing');assert.strictEqual(Number(charge.gross_cents),free?0:50);
   if(free){assert.strictEqual(charge.invoice_id,null);assert.strictEqual(charge.invoice_number,null);}
   else{assert(Number(charge.invoice_id)>0);assert(/^GI-[A-F0-9]{20}$/.test(charge.invoice_number));}
   assert.strictEqual(published.contract.tariff.listing_cents,free?0:50);
   assert.deepStrictEqual(published.contract.tariff.rates,[300,200,100,50]);assert.strictEqual(published.contract.tariff.cap_cents,2990);
   assert.strictEqual(published.contract.relisting.free_applied,free);assert.strictEqual(published.contract.relisting.discount_cents,free?50:0);
   assert.strictEqual(published.relisting.free_available,false);assert(published.relisting.published_at);
   assert.strictEqual(published.relisting.consumed_by,free?child:source.relisting.consumed_by);
   assert.deepStrictEqual(published.images,draft.images);assert.deepStrictEqual(state(source.id,locale),source);
   await page.goto(origin+'/user/edit-listing.php?id='+child);assert.strictEqual(await page.locator('[data-auction-publish]').count(),0);
   await page.locator('[name=accept_rules]').check();assert.strictEqual(await save(page.locator('[data-listing-save]')),child);
   const edited=state(child,locale);assert.deepStrictEqual(edited.charges,published.charges);assert.deepStrictEqual(edited.contract,published.contract);
   assert.strictEqual(edited.ends_at,published.ends_at);assert.deepStrictEqual(edited.images,published.images);
   return child;
  }
  await page.goto(origin+'/user/create-listing.php');await fill();
  assert(await page.locator('[data-listing-save]').isVisible());assert(await page.locator('[data-auction-publish]').isHidden());
  const fixed=await save(page.locator('[data-listing-save]'));assert.deepStrictEqual(fixture('state',{username:data.username,id:fixed}).charges,[]);checks.push('fixed_publication_without_fee');
  await page.goto(origin+'/user/create-listing.php');await fill();await page.locator('[name=type]').selectOption('auction');await page.locator('[name=status]').selectOption('draft');
  assert(await page.locator('[data-auction-publish]').isHidden());assert.strictEqual(await page.locator('[name=accept_auction_fees]').evaluate(node=>node.required),false);
  const draft=await save(page.locator('[data-listing-save]'));const draftState=fixture('state',{username:data.username,id:draft});assert.strictEqual(draftState.status,'draft');assert.deepStrictEqual(draftState.charges,[]);checks.push('draft_without_fee_or_fee_consent');
  let first=true;
  for(const [locale,label] of Object.entries(data.labels)){
   await language(locale);
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
  let firstChain;
  for(const locale of Object.keys(data.labels)){
   await language(locale);
   const source=fixture('unsold',{username:data.username,category:data.category,images:true,locale});
   unsold(source);assert.strictEqual(source.charges.length,1);assert.strictEqual(Number(source.charges[0].gross_cents),50);
   assert(Number(source.charges[0].invoice_id)>0);assert.strictEqual(source.images.length,1);
   const child=await relist(source,locale,source.id,true);
   if(!firstChain)firstChain={root:source.id,child,locale};
   checks.push('relist_draft_images_free_publication_edit_'+locale);
  }
  await language(firstChain.locale);
  const endedChild=fixture('expire',{username:data.username,id:firstChain.child,locale:firstChain.locale});
  assert.strictEqual(endedChild.relisting.consumed_by,firstChain.child);
  const repeated=await relist(endedChild,firstChain.locale,firstChain.root,false);
  const repeatState=state(repeated,firstChain.locale);
  assert.strictEqual(repeatState.relisting.consumed_by,firstChain.child);
  assert.strictEqual(repeatState.contract.relisting.root_listing_id,firstChain.root);
  await page.goto(origin+'/user/billing.php?order='+repeatState.charges[0].invoice_id);
  assert((await page.locator('main').innerText()).includes(repeatState.charges[0].invoice_number));
  assert.strictEqual(await page.locator('main input[name=action][value=order],main input[name=action][value=bump]').count(),0);
  await mobile('second relisting fee order',firstChain.locale);checks.push('same_chain_second_relisting_charged_once');
  assert.deepStrictEqual(errors,[]);assert.deepStrictEqual(external,[]);
  console.log(JSON.stringify({result:'PASS',checks,js_errors:0,external_requests:0,scope:'Synthetic publication and receivables only; no actual payment or provider approval'}));
 }finally{
  try{if(browser)await browser.close();}finally{try{if(data)fixture('restore',{saved:data.saved});}finally{docker('start','gunindex-rebuild-worker');}}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});

'use strict';
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { playwright, launchOptions } = require('./browser_tools');
const origin = 'http://127.0.0.1:8081';

function runPhp(...args) {
  return execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','gunindex-rebuild-app','php',...args]);
}

(async () => {
  if (process.platform==='win32') throw new Error('Requires the isolated Linux runtime.');
  const { users, category, tag } = JSON.parse(runPhp('tests/listing_draft_test.php','--browser-fixture'));
  const picture = runPhp('-r','$im=imagecreatetruecolor(200,160); imagefill($im,0,0,imagecolorallocate($im,20,140,170)); imagepng($im);');
  const browser = await playwright.chromium.launch(launchOptions());
  const title = 'Synthetic autosaved desk '+tag;
  const checks=[];
  async function session(user, options={}) {
    const context=await browser.newContext(options);
    const page=await context.newPage();
    await page.goto(origin+'/auth/login.php');
    await page.locator('[name=login]').fill(user.username);
    await page.locator('[name=password]').fill(user.password);
    await Promise.all([page.waitForURL('**/user/dashboard.php'),page.locator('main button[type=submit]').click()]);
    return page;
  }
  const draftPost = response => response.url()===origin+'/user/listing-draft.php'&&response.request().method()==='POST';
  try {
    const seller=await session(users.seller,{viewport:{width:390,height:844}});
    const other=await session(users.other);
    await seller.goto(origin+'/user/create-listing.php');
    const form=seller.locator('[data-listing-form]');
    const stale=await form.evaluate(node=>Object.fromEntries([...new FormData(node)].filter(([,value])=>typeof value==='string')));
    await form.locator('[name=title]').fill(title);
    await form.locator('[name="images[]"]').setInputFiles({name:'synthetic.png',mimeType:'image/png',buffer:picture});
    const saved=await seller.waitForResponse(draftPost);
    assert.equal(saved.status(),200);
    await form.locator('[data-draft-images] img').waitFor();
    const stagedUrl=await form.locator('[data-draft-images] img').getAttribute('src');
    assert.equal((await seller.request.get(origin+stagedUrl)).headers()['content-type'],'image/webp');
    assert.equal((await other.request.get(origin+stagedUrl)).status(),404);
    const denied=await seller.request.post(origin+'/user/listing-draft.php',{form:{...stale,title:'Stale private title',autosave:'1'}});
    assert.equal(denied.status(),409);
    const rejected=await seller.request.post(origin+'/user/listing-draft.php',{form:{...stale,csrf_token:'wrong',autosave:'1'}});
    assert.equal(rejected.status(),403);
    checks.push('incomplete_autosave_returns_webp_and_rejects_stale_or_foreign_access');

    const preview=await seller.goto(origin+'/user/listing-draft.php?editor=0');
    assert.match(preview.headers()['cache-control'],/no-store/);
    assert.match(preview.headers()['x-robots-tag'],/noindex/);
    assert.match(await seller.locator('[data-listing-preview]').innerText(),new RegExp(title));
    await other.goto(origin+'/user/listing-draft.php?editor=0');
    assert.equal((await other.locator('[data-listing-preview]').innerText()).includes(title),false);
    const search=await other.request.get(origin+'/marketplace/search.php?q='+tag);
    assert.equal((await search.text()).includes(title),false);
    await seller.locator('main a[href="/user/create-listing.php"]').click();
    assert.equal(await form.locator('[name=title]').inputValue(),title);
    assert.equal(await form.locator('[name=accept_rules]').isChecked(),false);
    checks.push('restore_draft_and_preview_without_public_visibility_or_consent');

    await form.locator('[name=category_id]').selectOption(String(category));
    await form.locator('textarea[name=description]').fill('A synthetic desk with an image saved before publication.');
    await form.locator('[name=price]').fill('15.25');
    await form.locator('[name=accept_rules]').check();
    await Promise.all([seller.waitForURL('**/user/dashboard.php?saved=*'),form.locator('[data-listing-save]').click()]);
    const id=new URL(seller.url()).searchParams.get('saved');
    await other.goto(origin+'/marketplace/view-item.php?id='+id);
    assert.match(await other.locator('main').innerText(),new RegExp(title));
    const product=await other.request.get(origin+'/marketplace/image.php?id='+id+'&position=0');
    assert.equal(product.headers()['content-type'],'image/webp');
    assert.equal((await seller.request.get(origin+stagedUrl)).status(),404);
    const foreign=await other.request.get(origin+'/user/listing-draft.php?editor='+id);
    assert.equal(foreign.status(),404);
    checks.push('explicit_publication_transfers_image_and_removes_private_copy');

    const native=await session(users.seller,{javaScriptEnabled:false});
    await native.goto(origin+'/user/create-listing.php');
    await native.locator('[data-listing-form] [name=title]').fill('Synthetic native preview '+tag);
    await Promise.all([native.waitForURL('**/user/listing-draft.php?editor=0'),native.locator('button[formaction="/user/listing-draft.php"]:not([name])').click()]);
    assert.match(await native.locator('main').innerText(),/Synthetic native preview/);
    assert.equal((await (await other.request.get(origin+'/marketplace/search.php?q='+tag)).text()).includes('Synthetic native preview'),false);
    await native.locator('[name=discard_draft]').check();
    await Promise.all([native.waitForURL('**/user/dashboard.php'),native.locator('main form[action="/user/listing-draft.php"] button').click()]);
    await native.goto(origin+'/user/create-listing.php');
    assert.equal(await native.locator('[data-listing-form] [name=title]').inputValue(),'');
    checks.push('native_preview_and_confirmed_discard_without_javascript');
    console.log(JSON.stringify({status:'PASS',suite:'listing_draft_browser',checks}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode=1; });

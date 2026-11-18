'use strict';
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { playwright, launchOptions } = require('./browser_tools');
const origin = 'http://127.0.0.1:8081';

(async () => {
  if (process.platform === 'win32') throw new Error('Requires the isolated Linux test runtime.');
  const fixture = JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','gunindex-rebuild-app',
    'php','tests/classification_test.php','--browser-fixture'],{encoding:'utf8'}));
  const { attributes, categories, users, tag } = fixture;
  const browser = await playwright.chromium.launch(launchOptions());
  const title = 'Synthetic classification desk ' + tag;
  const input = (key,id) => `[name="${key}[${id}]"]`;
  const assertions=[];
  async function login(context) {
    const page = await context.newPage();
    await page.goto(origin+'/auth/login.php');
    await page.locator('[name=login]').fill(users.seller.username);
    await page.locator('[name=password]').fill(users.seller.password);
    await Promise.all([page.waitForURL('**/user/dashboard.php'),page.locator('main button[type=submit]').click()]);
    return page;
  }
  try {
    const context = await browser.newContext({viewport:{width:390,height:844}});
    const page = await login(context);
    const unauthorized = await page.goto(origin+'/admin/category-attributes.php?category_id='+categories.main);
    assert.equal(unauthorized.status(),403);
    assertions.push('protected_attribute_management');
    await page.goto(origin+'/user/create-listing.php');
    await page.locator('[name=category_id]').selectOption(String(categories.main));
    await page.locator(input('attributes',attributes.width)).fill('51,125');
    await page.locator(input('attributes',attributes.finish)).selectOption('Oak');
    await page.locator(input('attributes',attributes.folds)).selectOption('0');
    await page.locator('[name=category_id]').selectOption(String(categories.other));
    await page.locator(input('attributes',attributes.width)).waitFor({state:'detached'});
    await page.locator('[name=category_id]').selectOption(String(categories.main));
    await page.locator(input('attributes',attributes.width)).waitFor();
    assert.equal(await page.locator(input('attributes',attributes.width)).inputValue(),'51,125');
    await page.locator('[name=title]').fill(title);
    await page.locator('[name=description]').fill('An ordinary synthetic desk published through the full listing form.');
    await page.locator('[name=price]').fill('23.50');
    await page.locator('[name=accept_rules]').check();
    await Promise.all([page.waitForURL('**/user/dashboard.php?saved=*'),page.locator('[data-listing-save]').click()]);
    const id=new URL(page.url()).searchParams.get('saved');
    await page.goto(origin+'/marketplace/view-item.php?id='+id);
    assert.match(await page.locator('main').innerText(),/51\.125 cm/);
    assertions.push('category_switch_seller_form_and_public_detail');

    await page.goto(origin+'/marketplace/search.php');
    await page.locator('main [name=category_id]').selectOption(String(categories.main));
    await page.locator('main [name=q]').fill(tag);
    await page.locator(input('attribute_min',attributes.width)).fill('51.125');
    await page.locator(input('attribute_max',attributes.width)).fill('52');
    await page.locator(input('attributes',attributes.finish)).selectOption('Oak');
    await Promise.all([page.waitForNavigation(),page.locator('.search-toolbar button[type=submit]').click()]);
    assert.equal(await page.locator('main .listing-card').filter({hasText:title}).count(),1);
    assert.equal(await page.locator(input('attribute_min',attributes.width)).inputValue(),'51.125');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
    assertions.push('combined_filters_and_mobile_layout');

    const basic = await browser.newContext({javaScriptEnabled:false});
    const noJs = await login(basic);
    await noJs.goto(origin+'/user/create-listing.php');
    await noJs.locator('[name=title]').fill('Synthetic unsaved draft');
    await noJs.locator('[name=category_id]').selectOption(String(categories.main));
    await Promise.all([noJs.waitForNavigation(),noJs.locator('[name=load_attributes]').click()]);
    assert.equal(await noJs.locator('[name=title]').inputValue(),'Synthetic unsaved draft');
    assert.equal(await noJs.locator(input('attributes',attributes.width)).count(),1);
    assert.equal(new URL(noJs.url()).pathname,'/user/create-listing.php');
    assertions.push('native_form_loads_fields_without_publishing');
    console.log(JSON.stringify({status:'PASS',suite:'classification_browser',checks:assertions}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode=1; });

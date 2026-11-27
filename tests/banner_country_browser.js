'use strict';
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {playwright,launchOptions,axe} = require('./browser_tools');
const origin='http://127.0.0.1:8081';

function fixture(operation, data = {}) {
  return JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','-i','gunindex-rebuild-app','php',
    'tests/banner_country_fixture.php',operation],{input:JSON.stringify(data),encoding:'utf8'}));
}

(async () => {
  if (process.platform==='win32') throw new Error('Requires the isolated Linux test runtime.');
  const seed=fixture('seed');
  let browser;
  try {
    browser=await playwright.chromium.launch(launchOptions());
    const context=await browser.newContext({locale:'de-DE',viewport:{width:1440,height:1050},reducedMotion:'reduce'});
    const page=await context.newPage();
    const urls=['/','/marketplace/search.php','/marketplace/search.php?type=auction',
      '/marketplace/search.php?category_id='+seed.category,'/marketplace/search.php?scope=international&country_code=AT',
      '/marketplace/profile.php?user_id='+seed.profile];
    const section=page.locator('.banner-ads');
    const links=section.locator('.banner-ad-link');
    const AxeBuilder=axe();
    async function switchMarket(country,locale) {
      await page.locator('.market-switcher summary').click();
      const form=page.locator('.market-switcher form');
      await form.locator('[name=market_country]').selectOption(country);
      await form.locator('[name=locale]').selectOption(locale);
      await Promise.all([page.waitForNavigation(),form.locator('button[type=submit]').click()]);
    }
    await page.goto(origin+'/');
    for (const [country,locale] of [['DE','de'],['PL','pl'],['NL','nl'],['NL','en']]) {
      await switchMarket(country,locale);
      for (const path of urls) {
        assert.equal((await page.goto(origin+path)).status(),200,path);
        assert.equal(await section.count(),1,path);
        assert.equal(await section.getAttribute('data-banner-market'),country,path);
        const actual=await links.evaluateAll(elements=>elements.map(a=>Number(new URL(a.href).searchParams.get('id'))));
        assert.deepEqual(actual,seed.ids[country],path);
        for (const rel of await links.evaluateAll(elements=>elements.map(a=>a.rel))) assert.match(rel,/\bsponsored\b/);
      }
    }
    await page.goto(origin+'/marketplace/search.php');
    for (const [width,columns] of [[1440,3],[820,2],[390,1],[320,1]]) {
      await page.setViewportSize({width,height:850});
      assert.equal(await section.locator('.banner-ad-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length),columns);
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Banner horizontal overflow');
      const hit=await links.first().boundingBox();
      assert(hit&&hit.height>=44&&hit.width>=44,'Banner target too small');
      const rects=await section.locator('.banner-ad-card').evaluateAll(elements=>elements.map(el=>el.getBoundingClientRect().height));
      if (columns===3) assert(rects.every(height=>Math.abs(height-rects[0])<1),'Mismatched banner heights');
    }
    const audit=await new AxeBuilder({page}).include('.banner-ads').withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
    assert.deepEqual(audit.violations.map(v=>[v.id,v.nodes.map(n=>n.target)]),[]);
    const first=seed.ids.NL[0], second=seed.ids.NL[1];
    const old='/marketplace/banner.php?placement=search&country=NL&id='+first;
    assert.equal((await page.request.get(origin+old)).status(),200);
    assert.equal((await page.request.get(origin+old.replace('NL','PL'))).status(),404);
    const external=await page.request.get(origin+'/marketplace/banner-click.php?country=NL&id='+second+'&next=https://invalid.example.org/',{maxRedirects:0});
    assert.equal(external.status(),303);
    assert.equal(external.headers().location,'https://shop.example.org/NL/2');
    await links.first().focus();
    assert.match(await links.first().ariaSnapshot(),/Browser Banner NL 1/);
    await Promise.all([page.waitForURL('**/marketplace/profile.php?user_id='+seed.profile),page.keyboard.press('Enter')]);
    const counts=fixture('stats',seed);
    assert.equal(Number(counts[first].impressions),1);
    assert.equal(Number(counts[first].clicks),1);
    assert.equal(Number(counts[second].clicks),1);
    console.log('PASS three-country banner isolation, shared ordering, responsive layout, keyboard, accessibility and metrics');
  } finally {
    if (browser) await browser.close();
    fixture('restore',seed);
  }
})().catch(error=>{console.error(error.message);process.exitCode=1;});

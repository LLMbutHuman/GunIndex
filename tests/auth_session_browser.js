'use strict';
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { playwright, launchOptions } = require('./browser_tools');
const origin='http://127.0.0.1:8081';

(async () => {
  if (process.platform==='win32') throw new Error('Requires the isolated Linux runtime.');
  const users=JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','gunindex-rebuild-app',
    'php','tests/auth_session_test.php','--browser-fixture'],{encoding:'utf8'}));
  const browser=await playwright.chromium.launch(launchOptions());
  async function login(user, userAgent) {
    const context=await browser.newContext({locale:'de-DE',userAgent});
    const page=await context.newPage();
    await page.goto(origin+'/auth/login.php');
    await page.locator('[name=login]').fill(user.username);
    await page.locator('[name=password]').fill(user.password);
    await Promise.all([page.waitForURL('**/user/dashboard.php'),page.locator('main button[type=submit]').click()]);
    await page.goto(origin+'/user/sessions.php');
    return page;
  }
  try {
    const first=await login(users.owner,'Windows Firefox/124.0 synthetic');
    const second=await login(users.owner,'Android Chrome/124.0 synthetic');
    const outsider=await login(users.other,'macOS Browser synthetic');
    const remote=await second.locator('[data-current-session]').getAttribute('data-session-id');
    const outside=await outsider.locator('[data-current-session]').getAttribute('data-session-id');
    const response=await first.goto(origin+'/user/sessions.php');
    assert.match(response.headers()['cache-control'],/no-store/);
    assert.equal(await first.locator('[data-session-id]').count(),2);
    assert.match(await first.locator('[data-current-session]').innerText(),/Firefox.*Windows/);
    assert.match(await first.locator(`[data-session-id="${remote}"]`).innerText(),/Chrome.*Android/);
    assert.equal(await first.locator(`[data-session-id="${outside}"]`).count(),0);

    // A link, a foreign identifier and a POST without a form token cannot revoke a session.
    await first.goto(origin+'/user/sessions.php?action=revoke&session='+remote);
    const current=first.locator('[data-current-session]');
    const csrf=await current.locator('[name=csrf_token]').inputValue();
    assert.equal((await first.request.post(origin+'/user/sessions.php',{form:{action:'revoke',session:remote}})).status(),403);
    assert.equal((await first.request.post(origin+'/user/sessions.php',{form:{action:'revoke',session:outside,csrf_token:csrf}})).status(),404);
    await second.goto(origin+'/user/sessions.php');
    assert.equal(await second.locator('[data-current-session]').count(),1);

    await Promise.all([first.waitForURL('**/user/sessions.php?saved=1'),first.locator(`[data-session-id="${remote}"] button`).click()]);
    assert.equal(await first.locator('[data-session-id]').count(),1);
    await second.goto(origin+'/user/dashboard.php');
    assert.equal(new URL(second.url()).pathname,'/auth/login.php');
    await outsider.goto(origin+'/user/dashboard.php');
    assert.equal(new URL(outsider.url()).pathname,'/user/dashboard.php');

    await Promise.all([first.waitForURL('**/auth/login.php'),first.locator('[data-current-session] button').click()]);
    await first.goto(origin+'/user/dashboard.php');
    assert.equal(new URL(first.url()).pathname,'/auth/login.php');
    console.log(JSON.stringify({status:'PASS',suite:'auth_session_browser',checks:['separate_devices_and_private_overview','csrf_and_foreign_scope','one_session_revoked','current_session_sign_out']}));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error.message);process.exitCode=1;});

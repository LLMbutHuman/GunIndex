'use strict';
const assert=require('node:assert/strict');
const { createHmac }=require('node:crypto');
const { execFileSync }=require('node:child_process');
const { playwright,launchOptions }=require('./browser_tools');
const origin='http://localhost:8081';
let phase='browser_setup';

function totp(base32) {
  const symbols='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const bytes=[];let value=0,bits=0;
  for(const letter of base32){value=(value<<5)|symbols.indexOf(letter);bits+=5;if(bits>=8){bits-=8;bytes.push((value>>bits)&255);}}
  const input=Buffer.alloc(8);input.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));
  const mac=createHmac('sha1',Buffer.from(bytes)).update(input).digest();
  return String((mac.readUInt32BE(mac[19]&15)&0x7fffffff)%1000000).padStart(6,'0');
}

(async()=>{
  if(process.platform==='win32')throw new Error('Isolated Linux runtime required.');
  const account=JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','gunindex-rebuild-app','php',
    'tests/user_security_test.php','--browser-fixture'],{encoding:'utf8'}));
  const browser=await playwright.chromium.launch(launchOptions());
  const context=await browser.newContext({locale:'de-DE'});
  const page=await context.newPage();
  const cdp=await context.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  await cdp.send('WebAuthn.addVirtualAuthenticator',{options:{protocol:'ctap2',transport:'internal',hasResidentKey:true,
    hasUserVerification:true,isUserVerified:true,automaticPresenceSimulation:true}});
  async function passwordLogin(next) {
    await page.goto(origin+'/auth/login.php');
    await page.locator('[name=login]').fill(account.username);
    await page.locator('main input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL('**'+next),page.locator('main button[type=submit]').click()]);
  }
  async function logout() {
    await page.goto(origin+'/auth/logout.php');
    await Promise.all([page.waitForURL('**/marketplace/index.php'),page.locator('main button[type=submit]').click()]);
  }
  async function passkeyLogin(next) {
    await page.goto(origin+'/auth/login.php');
    await Promise.all([page.waitForURL('**'+next),page.locator('[data-passkey=login] [data-passkey-start]').click()]);
  }
  async function secondary(code,next) {
    await page.locator('[name=factor]').fill(code);
    await Promise.all([page.waitForURL('**'+next),page.locator('main button[type=submit]').click()]);
  }
  try {
    phase='passkey_enroll';
    await passwordLogin('/user/dashboard.php');
    await page.goto(origin+'/user/passkeys.php');
    const add=page.locator('[data-passkey=add]');
    const csrf=await add.locator('[name=csrf_token]').inputValue();
    assert.equal((await page.request.post(origin+'/auth/passkey.php',{headers:{Origin:'https://other.example'},form:{action:'start_add',csrf_token:csrf}})).status(),403);
    await add.locator('[name=label]').fill('Synthetic platform passkey');
    await add.locator('[name=password]').fill(account.password);
    await add.locator('[data-passkey-start]').click();
    await page.getByRole('heading',{name:'Synthetic platform passkey'}).waitFor();
    await logout();
    phase='verified_passkey_login';
    await passkeyLogin('/user/dashboard.php');
    phase='totp_enroll';
    await page.goto(origin+'/user/security.php');
    await page.locator('main [name=password]').fill(account.password);
    await Promise.all([page.waitForNavigation(),page.locator('button[value=start]').click()]);
    const enrollment=await page.locator('#user-totp-key').innerText();
    await page.locator('main [name=factor]').fill(totp(enrollment));
    await Promise.all([page.waitForNavigation(),page.locator('button[value=enable]').click()]);
    const codes=await page.locator('#user-recovery code').allTextContents();
    assert.equal(codes.length,8);
    await logout();
    phase='passkey_preserves_enabled_totp';
    await passkeyLogin('/auth/user-factor.php');
    await secondary(codes[0],'/user/dashboard.php');
    await logout();
    phase='password_and_recovery_replay';
    await passwordLogin('/auth/user-factor.php');
    await page.locator('[name=factor]').fill(codes[0]);
    const response=await Promise.all([page.waitForNavigation(),page.locator('main button[type=submit]').click()]);
    assert.equal(response[0].status(),422);
    await secondary(codes[1],'/user/dashboard.php');
    phase='passkey_revoke';
    await page.goto(origin+'/user/passkeys.php');
    const remove=page.locator('main form').filter({has:page.locator('input[name=passkey]')});
    await remove.locator('[name=password]').fill(account.password);
    await remove.locator('[name=factor]').fill(codes[2]);
    await Promise.all([page.waitForNavigation(),remove.locator('button[type=submit]').click()]);
    assert.equal(await page.getByRole('heading',{name:'Synthetic platform passkey'}).count(),0);
    await logout();
    await page.goto(origin+'/auth/login.php');
    const failure=page.waitForResponse(response=>response.url()===origin+'/auth/passkey.php'&&response.status()===422);
    await page.locator('[data-passkey-start]').click();
    await failure;
    assert.equal(new URL(page.url()).pathname,'/auth/login.php');
    console.log(JSON.stringify({status:'PASS',suite:'user_security_browser',checks:['exact_origin','real_virtual_authenticator_enrollment_and_login','optional_totp','passkey_requires_configured_totp','single_use_recovery','passkey_removal_blocks_authentication']}));
  } finally { await browser.close(); }
})().catch(error=>{console.error(JSON.stringify({status:'FAIL',suite:'user_security_browser',phase,error:error.name}));process.exitCode=1;});

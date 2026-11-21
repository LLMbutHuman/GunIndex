/* Real browser cryptography and UI; Linux-only synthetic fixtures, no providers.
 * No traces, screenshots, HARs, saved recovery codes or plaintext test artifacts.
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const { playwright, launchOptions } = require('./browser_tools');
const origin = 'http://127.0.0.1:8081';
const locales = ['de', 'en', 'fr', 'nl', 'pl', 'cs', 'da', 'it'];
const token = randomBytes(12).toString('hex');
const password = 'Synthetic private device phrase ' + token;
const firstMessage = 'Encrypted browser message ' + token + ' \u{1f4e8}';
const sharedMessage = 'Both active buyer devices receive ' + token;
const futureMessage = 'Only the replacement device receives ' + token;
const newIdentityMessage = 'Verified new account identity receives ' + token;
const lockedDraft = 'Unsent draft cleared during an interrupted refresh ' + token;
const filename = 'private-note-' + token + '.txt';
const fileBytes = Buffer.from('Encrypted file canary ' + token + '\nUnicode: \u00e4 \u{1f4e8}\n', 'utf8');
const probes = new Set([password, firstMessage, sharedMessage, futureMessage, newIdentityMessage, lockedDraft, filename, fileBytes.toString('utf8')]);
const checks = [], pageErrors = [], external = [], wire = [], pendingResponses = [];
const expectedAborts = new WeakSet();
const activeApiRequests = new Set();
let freezeApiRequests = false;
let phase = 'setup';

function invariant(condition, message) { if(!condition){const error=new Error('Browser invariant failed');error.name='AssertionError';error.safeCheck=message;throw error;} }
function fixture(operation, fields = {}) {
  const output = execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'exec', '-i', 'gunindex-rebuild-app', 'php', 'tests/e2ee_browser_fixture.php'], {
    input: JSON.stringify({ operation, ...fields }), stdio: ['pipe', 'pipe', 'pipe'], maxBuffer: 5 * 1024 * 1024,
  });
  return JSON.parse(output.toString('utf8'));
}
function isLocal(url) { return url.startsWith(origin + '/'); }
function isE2eeApi(url) { return url.startsWith(origin + '/user/e2ee-api.php'); }
function privateFree(buffer) {
  for (const probe of probes) {
    const encodings = [probe, encodeURIComponent(probe), Buffer.from(probe).toString('base64'), Buffer.from(probe).toString('base64url'), JSON.stringify(probe).slice(1, -1)];
    invariant(!encodings.some(value => buffer.includes(Buffer.from(value))), 'Private canary escaped encryption');
  }
  for (const marker of ['-----BEGIN PGP PRIVATE KEY BLOCK-----', '-----BEGIN PRIVATE KEY-----', 'identity_private', 'device_private']) {
    invariant(!buffer.includes(Buffer.from(marker)), 'Private-key material escaped its encrypted vault');
  }
}
async function downloadBytes(download) {
  const stream = await download.createReadStream(); invariant(stream, 'Download must be readable');
  const chunks = []; for await (const chunk of stream) chunks.push(chunk);
  const result = Buffer.concat(chunks); await download.delete(); return result;
}
async function label(page, name) {
  return page.locator('#secure-chat').evaluate((node, key) => JSON.parse(node.dataset.labels)['e2ee.' + key], name);
}
async function enabled(page, selector) {
  await page.locator(selector).waitFor({ state: 'visible' });
  await page.waitForFunction(value => { const node = document.querySelector(value); return node && !node.disabled; }, selector);
}
async function status(page, key) {
  const expected = await label(page, key); invariant(typeof expected === 'string' && expected.length > 0, 'Missing translated status');
  await page.waitForFunction(value => document.querySelector('#e2ee-status')?.textContent === value, expected);
}
async function action(page, id, result) {
  await enabled(page, '#e2ee-' + id); await page.locator('#e2ee-' + id).click(); await status(page, result);
}
async function mobile(page, locale) {
  assert.equal(await page.locator('html').getAttribute('lang'), locale);
  const text = await page.locator('#secure-chat').innerText();
  invariant(!/e2ee\.[a-z_]+/.test(text), 'Untranslated E2EE label');
  invariant(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Mobile viewport overflow');
}
async function language(page, locale) {
  const switcher = page.locator('.market-switcher');
  if (!await switcher.evaluate(node => node.open)) await switcher.locator('summary').click();
  const form = page.locator('form[action="/user/language.php"]');
  await form.locator('[name=locale]').selectOption(locale); await form.locator('[name=market_country]').selectOption('DE');
  await Promise.all([page.waitForNavigation(), form.locator('button[type=submit]').click()]);
  await enabled(page, '#e2ee-passphrase');
}
async function login(context, user, path) {
  const page = await context.newPage(); page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(30000);
  page.on('pageerror', error => pageErrors.push(error.name));
  await page.goto(origin + '/auth/login.php');
  await page.locator('[name=login]').fill(user.username); await page.locator('[name=password]').fill(user.password);
  await Promise.all([page.waitForURL('**/user/dashboard.php'), page.locator('main button[type=submit]').click()]);
  const response = await page.goto(origin + path); assert.equal(response.status(), 200);
  await enabled(page, '#e2ee-passphrase'); return page;
}
async function context(browser) {
  const result = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce', acceptDownloads: true });
  await result.route('**/*', route => {
    const request=route.request();
    if (freezeApiRequests&&isE2eeApi(request.url())) {
      expectedAborts.add(request);
      return route.abort();
    }
    return isLocal(request.url()) ? route.continue() : route.abort();
  });
  result.on('request', request => {
    if (!isLocal(request.url())) external.push(new URL(request.url()).protocol);
    wire.push(Buffer.from(request.url()));
    const body = request.postDataBuffer(); if (body) wire.push(body);
    if (!freezeApiRequests&&isE2eeApi(request.url())) activeApiRequests.add(request);
  });
  result.on('requestfinished',request=>activeApiRequests.delete(request));
  result.on('requestfailed',request=>activeApiRequests.delete(request));
  result.on('response', response => {
    if (isE2eeApi(response.url())) {
      const responsePhase=phase;
      const read = response.body().then(body => wire.push(body)).catch(() => {
        if (!expectedAborts.has(response.request())) invariant(false,'Unreadable E2EE response in '+responsePhase);
      });
      // Keep rejection handled until the final explicit assertion.
      read.catch(() => {}); pendingResponses.push(read);
    }
  });
  return result;
}
async function finishPending(page, user, expectedVersion, factor = null) {
  await page.locator('#e2ee-pending').waitFor({ state: 'visible' });
  invariant(!await page.locator('#e2ee-code-saved').isChecked(), 'Saving recovery code requires explicit confirmation');
  invariant(await page.locator('#e2ee-commit').isDisabled(), 'Unconfirmed recovery code must not commit');
  const code = await page.locator('#e2ee-new-code').inputValue();
  invariant(/^GX1\.[1-9][0-9]*\.[1-9][0-9]*\.[a-f0-9]{64}\.[A-Za-z0-9_-]{43}\.[A-Za-z0-9_-]{43}$/.test(code), 'Recovery code structure');
  const pieces = code.split('.'); assert.equal(Number(pieces[1]), user.id); assert.equal(Number(pieces[2]), expectedVersion);
  probes.add(code); probes.add(pieces[5]);
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#e2ee-download-code').click()]);
  invariant(download.suggestedFilename() === 'GunIndex-recovery.txt', 'Explicit recovery download');
  invariant((await downloadBytes(download)).equals(Buffer.from(code + '\n')), 'Downloaded recovery code differs from displayed code');
  await mobile(page, 'de');
  if (factor) {
    await page.locator('#e2ee-account-password').fill(user.password);
    await page.locator('#e2ee-account-factor').fill(factor);
  }
  await page.locator('#e2ee-code-saved').check(); await action(page, 'commit', 'created');
  invariant(await page.locator('#e2ee-pending').isHidden(), 'Committed pending secret must be cleared');
  invariant(await page.locator('#e2ee-new-code').inputValue() === '', 'Recovery textarea must be cleared');
  if (await page.locator('#e2ee-messages').count()) {
    assert.equal(await page.locator('#e2ee-messages article').count(), 0, 'Commit returns to a locked view with no old plaintext');
  }
  const fingerprint = (await page.locator('#e2ee-own-fingerprint').textContent()).trim();
  invariant(/^[a-f0-9]{64}$/.test(fingerprint), 'Full identity fingerprint');
  return { code, fingerprint };
}
async function create(page, user, name, fixtureId, role) {
  await enabled(page, '#e2ee-create');
  await page.locator('#e2ee-device-name').fill(name); await page.locator('#e2ee-passphrase').fill(password);
  await action(page, 'create', 'save_code');
  const before = fixture('status', { fixture: fixtureId }); assert.equal(before.accounts[role], null, 'No server account before recovery-code confirmation');
  return finishPending(page, user, 1);
}
async function unlock(page, result = 'ready') {
  await enabled(page, '#e2ee-passphrase'); await page.locator('#e2ee-passphrase').fill(password);
  await action(page, 'unlock', result);
}
async function verifyPeer(page, fingerprint) {
  invariant((await page.locator('#e2ee-peer-fingerprint').textContent()).trim() === fingerprint, 'Peer identity does not match other participant');
  await enabled(page, '#e2ee-fingerprint-input'); await page.locator('#e2ee-fingerprint-input').fill(fingerprint);
  await action(page, 'verify', 'ready');
}
async function visibleMessage(page, text) {
  const message = page.locator('#e2ee-messages article').filter({ has: page.locator('p', { hasText: text }) });
  await message.waitFor({ state: 'visible' });
  invariant(await message.locator('p').first().textContent() === text, 'Decrypted message text differs'); return message;
}
async function send(page, text, file = false) {
  await enabled(page, '#e2ee-body'); await page.locator('#e2ee-body').fill(text);
  if (file) await page.locator('#e2ee-files').setInputFiles({ name: filename, mimeType: 'text/plain', buffer: fileBytes });
  await action(page, 'send', 'sent'); await visibleMessage(page, text);
}
async function interruptedFirstSend(page, fixtureId) {
  let attempts = 0, original = null, committedId = null, handlerFailure = null;
  const sendPayload = request => {
    const { csrf_token, ...payload } = request.postDataJSON();
    return JSON.stringify(payload);
  };
  const handler = async route => {
    const request = route.request();
    if (request.method() !== 'POST' || request.postDataJSON()?.action !== 'send') return route.fallback();
    try {
      attempts++;
      const payload = sendPayload(request);
      if (attempts === 1) original = payload;
      else invariant(payload === original, 'Retry changed encrypted content, nonce, recipients or attachment binding');
      const response = await route.fetch();
      assert.equal(response.status(), 200, 'Server must accept the encrypted request before response loss');
      wire.push(await response.body());
      const result = await response.json(); invariant(Number.isSafeInteger(result.id), 'Committed message identity');
      if (attempts === 1) {
        committedId = result.id; expectedAborts.add(request);
        await route.abort('failed');
      } else {
        invariant(result.id === committedId, 'Retry created a different message');
        await route.fulfill({ response });
      }
    } catch (error) { handlerFailure = error; await route.abort('failed').catch(() => {}); }
  };
  await page.route('**/user/e2ee-api.php', handler);
  try {
    await enabled(page, '#e2ee-body'); await page.locator('#e2ee-body').fill(firstMessage);
    await page.locator('#e2ee-files').setInputFiles({ name: filename, mimeType: 'text/plain', buffer: fileBytes });
    await action(page, 'send', 'error_default');
    if (handlerFailure) throw handlerFailure;
    invariant(attempts === 1 && committedId !== null, 'The first request must commit exactly once');
    invariant(await page.locator('#e2ee-outbox').isVisible(), 'Lost response must preserve the encrypted retry');
    const committed = fixture('status', { fixture: fixtureId });
    invariant(committed.messages.length === 1 && committed.messages[0].id === committedId, 'Lost response database state');
    await storageIsPrivate(page);
    await page.reload(); await enabled(page, '#e2ee-passphrase'); await unlock(page);
    invariant(await page.locator('#e2ee-outbox').isVisible(), 'Encrypted retry must survive reload');
    invariant(await page.locator('#e2ee-body').isDisabled(), 'Pending retry must not be replaced by a new draft');
    await action(page, 'send', 'sent'); await visibleMessage(page, firstMessage);
    if (handlerFailure) throw handlerFailure;
    invariant(attempts === 2, 'Exactly one explicit retry');
    invariant(await page.locator('#e2ee-outbox').isHidden(), 'Acknowledged retry must be removed');
    const retried = fixture('status', { fixture: fixtureId });
    invariant(retried.messages.length === 1 && retried.envelopes.length === 1 && retried.attachments.length === 1 && retried.messages[0].id === committedId, 'Retry duplicated the message or attachment');
    checks.push('lost_response_reload_retries_identical_ciphertext_once_without_duplicate_message');
  } finally { await page.unroute('**/user/e2ee-api.php', handler); }
}
async function lockDuringHeldRefresh(page) {
  let heldRequest, release, reached, complete, handlerFailure = null;
  const paused = new Promise(resolve => { release = resolve; });
  const intercepted = new Promise(resolve => { reached = resolve; });
  const completed = new Promise(resolve => { complete = resolve; });
  const handler = async route => {
    const request = route.request();
    if (heldRequest || request.method() !== 'GET' || new URL(request.url()).searchParams.get('action') !== 'account') return route.fallback();
    heldRequest = request; expectedAborts.add(request);
    try {
      const response = await route.fetch(); assert.equal(response.status(), 200);
      wire.push(await response.body()); reached(); await paused;
      try { await route.fulfill({ response }); }
      catch (error) { if (!request.failure()) throw error; }
    } catch (error) { handlerFailure = error; reached(); await route.abort('failed').catch(() => {}); }
    finally { complete(); }
  };
  await enabled(page, '#e2ee-body'); await page.locator('#e2ee-body').fill(lockedDraft);
  await page.locator('#e2ee-files').setInputFiles({ name: filename, mimeType: 'text/plain', buffer: fileBytes });
  await enabled(page, '#e2ee-load');
  await page.route('**/user/e2ee-api.php?*', handler);
  try {
    // If the periodic refresh starts in this small window, its pending GET is
    // equally suitable; do not wait for a button disabled by that very request.
    await page.locator('#e2ee-load').evaluate(node => node.click());
    let timer;
    try { await Promise.race([intercepted, new Promise((_, reject) => { timer = setTimeout(() => reject(Error('Expected pending refresh')), 15000); })]); }
    finally { clearTimeout(timer); }
    if (handlerFailure) throw handlerFailure;
    invariant(await page.locator('#e2ee-load').isDisabled() && await page.locator('#e2ee-send').isDisabled(), 'Refresh must prevent concurrent reads and sends');
    const failed = page.waitForEvent('requestfailed', request => request === heldRequest);
    failed.catch(() => {});
    await action(page, 'lock', 'locked');
    assert.equal(await page.locator('#e2ee-messages article').count(), 0, 'Lock clears already rendered plaintext while HTTP is pending');
    invariant(await page.locator('#e2ee-body').inputValue() === '', 'Lock clears the pending plaintext draft');
    assert.equal(await page.locator('#e2ee-files').evaluate(node => node.files.length), 0, 'Lock clears selected files');
    release(); await completed; await failed;
    if (handlerFailure) throw handlerFailure;
    await enabled(page, '#e2ee-passphrase'); await status(page, 'locked');
    assert.equal(await page.locator('#e2ee-messages article').count(), 0, 'Late response must not restore plaintext');
    invariant(await page.locator('#e2ee-body').isDisabled() && await page.locator('#e2ee-send').isDisabled(), 'Late response must not unlock composing');
    checks.push('manual_lock_aborts_pending_http_and_clears_plaintext_without_late_restore');
  } finally { release(); await page.unroute('**/user/e2ee-api.php?*', handler); }
  await unlock(page); await visibleMessage(page, firstMessage);
}
async function peerPin(page, userId, peerId) {
  return page.evaluate(({ userId, peerId }) => new Promise((resolve, reject) => {
    const request = indexedDB.open('gunindex-e2ee-v1');
    request.onerror = () => reject(Error('Unable to read public trust pin'));
    request.onsuccess = () => {
      const db = request.result, read = db.transaction('items').objectStore('items').get(userId + ':pins');
      read.onerror = () => { db.close(); reject(Error('Unable to read public trust pin')); };
      read.onsuccess = () => { db.close(); resolve(read.result?.[peerId] || null); };
    };
  }), { userId, peerId });
}
async function holdStaleSellerTab(sellerContext, path, sellerId, buyerId) {
  const page = await sellerContext.newPage(); page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(30000);
  page.on('pageerror', error => pageErrors.push(error.name));
  let release, reached, heldRequest, replayed = false, handlerFailure = null, disposed = false;
  const paused = new Promise(resolve => { release = resolve; });
  const intercepted = new Promise(resolve => { reached = resolve; });
  let handler;
  async function dispose() {
    if (disposed) return; disposed = true; release();
    if (handler) await page.unroute('**/user/e2ee-api.php?*', handler);
    await page.close();
  }
  try {
    const response = await page.goto(origin + path); assert.equal(response.status(), 200);
    await enabled(page, '#e2ee-passphrase'); await unlock(page);
    const before = await peerPin(page, sellerId, buyerId);
    invariant(before?.version === 2, 'Stale tab must begin with the actually trusted version two');
    const query = new URLSearchParams({ action: 'context', listing_id: new URL(origin + path).searchParams.get('listing_id'), peer_id: buyerId });
    const snapshotResponse = await sellerContext.request.get(origin + '/user/e2ee-api.php?' + query);
    assert.equal(snapshotResponse.status(), 200);
    const snapshot = await snapshotResponse.body(); wire.push(snapshot);
    const context = JSON.parse(snapshot.toString('utf8'));
    invariant(JSON.parse(context.peer.manifest).version === 2 && context.peer.hash === before.hash, 'Capture the real signed version-two peer context');
    handler = async route => {
      const request = route.request(), action = new URL(request.url()).searchParams.get('action');
      if (request.method() !== 'GET') return route.fallback();
      try {
        if (action === 'account' && !heldRequest) {
          heldRequest = request;
          const response = await route.fetch(); assert.equal(response.status(), 200); wire.push(await response.body());
          reached(); await paused;
          await route.fulfill({ response });
        } else if (action === 'context' && heldRequest) {
          replayed = true;
          await route.fulfill({ status: 200, contentType: 'application/json; charset=utf-8', headers: { 'Cache-Control': 'no-store' }, body: snapshot });
        } else await route.fallback();
      } catch (error) { handlerFailure = error; reached(); await route.abort('failed').catch(() => {}); }
    };
    await enabled(page, '#e2ee-load'); await page.route('**/user/e2ee-api.php?*', handler);
    await page.locator('#e2ee-load').evaluate(node => node.click());
    let timer;
    try { await Promise.race([intercepted, new Promise((_, reject) => { timer = setTimeout(() => reject(Error('Expected stale-tab refresh')), 15000); })]); }
    finally { clearTimeout(timer); }
    if (handlerFailure) throw handlerFailure;
    invariant(await page.locator('#e2ee-load').isDisabled(), 'Old tab must remain inside its earlier refresh');
    return {
      dispose,
      finish: async (mainSeller, expectedHash) => {
        try {
          await action(mainSeller, 'load', 'ready');
          const newest = await peerPin(mainSeller, sellerId, buyerId);
          invariant(newest?.version === 3 && newest.hash === expectedHash, 'Main tab must persist the actual signed version-three pin');
          release(); await status(page, 'error_trust');
          if (handlerFailure) throw handlerFailure;
          invariant(replayed, 'The old signed context must reach the stale tab');
          const retained = await peerPin(page, sellerId, buyerId);
          invariant(retained?.version === 3 && retained.hash === newest.hash && retained.identity_key === newest.identity_key, 'A stale tab rolled back the shared trust pin');
          checks.push('cross_tab_stale_context_rejected_without_rolling_back_newer_device_pin');
        } finally { await dispose(); }
      },
    };
  } catch (error) { await dispose(); throw error; }
}
async function receiveFile(page, messageText) {
  const article = await visibleMessage(page, messageText);
  const button = article.locator('button').filter({ hasText: filename });
  const [download] = await Promise.all([page.waitForEvent('download'), button.click()]);
  invariant(download.suggestedFilename() === filename, 'Decrypted filename differs');
  invariant((await downloadBytes(download)).equals(fileBytes), 'Decrypted download differs byte for byte');
  await status(page, 'ready');
}
async function storageIsPrivate(page) {
  const serialized = await page.evaluate(() => new Promise((resolve, reject) => {
    const request = indexedDB.open('gunindex-e2ee-v1');
    request.onerror = () => reject(Error('Unable to inspect local encrypted storage'));
    request.onsuccess = () => {
      const db = request.result; const read = db.transaction('items').objectStore('items').getAll();
      read.onerror = () => { db.close(); reject(Error('Unable to inspect local encrypted storage')); };
      read.onsuccess = () => { db.close(); resolve(JSON.stringify(read.result)); };
    };
  }));
  privateFree(Buffer.from(serialized));
}

(async () => {
  invariant(process.platform !== 'win32', 'Run browser/database tests in the isolated Linux environment');
  let data, browser, staleSeller;
  try {
    data = fixture('setup'); browser = await playwright.chromium.launch(launchOptions());
    const sellerContext = await context(browser), buyerContext = await context(browser);
    const chat = (peer) => '/user/secure-chat.php?listing_id=' + data.listing_id + '&user_id=' + peer;
    phase = 'create_seller_identity';
    const seller = await login(sellerContext, data.users.seller, '/user/secure-chat.php');
    const sellerIdentity = await create(seller, data.users.seller, 'Synthetic seller laptop', data.fixture, 'seller');
    phase = 'create_buyer_identity';
    const buyer = await login(buyerContext, data.users.buyer, chat(data.users.seller.id));
    const buyerIdentity = await create(buyer, data.users.buyer, 'Synthetic buyer laptop', data.fixture, 'buyer');
    const initialDevice = (await buyer.locator('#e2ee-devices li').first().textContent()).match(/\b[a-f0-9]{32}\b/)[0];
    await unlock(buyer); invariant(await buyer.locator('#e2ee-send').isDisabled(), 'Unverified peer cannot receive messages');
    await buyer.locator('#e2ee-fingerprint-input').fill('0'.repeat(64)); await action(buyer, 'verify', 'error_trust');
    invariant(await buyer.locator('#e2ee-send').isDisabled(), 'Wrong fingerprint cannot unlock sending');
    await verifyPeer(buyer, sellerIdentity.fingerprint); checks.push('account_creation_requires_saved_recovery_and_explicit_peer_verification');

    phase = 'buyer_starts_conversation_with_encrypted_file';
    await interruptedFirstSend(buyer, data.fixture);
    const response = await seller.goto(origin + chat(data.users.buyer.id)); assert.equal(response.status(), 200);
    await enabled(seller, '#e2ee-passphrase'); await unlock(seller); await verifyPeer(seller, buyerIdentity.fingerprint);
    await receiveFile(seller, firstMessage); checks.push('buyer_initiates_seller_decrypts_message_and_file');
    const initial = fixture('status', { fixture: data.fixture, plaintext_probe: firstMessage });
    assert.equal(initial.messages.length, 1); assert.equal(initial.envelopes.length, 1); assert.equal(initial.attachments.length, 1);
    invariant(initial.messages.every(message => message.cipher_version === 2 && message.legacy_text_bytes === 0), 'Encrypted messages must have empty legacy text');
    invariant(initial.attachments.every(file => file.message_id !== null && file.private_file_present), 'Attachment must be private and message-bound');
    invariant(!initial.plaintext_found && !initial.private_key_marker_found && initial.cipher2_empty_legacy_text, 'Server-side ciphertext invariant');

    phase = 'manual_lock_during_pending_http';
    await lockDuringHeldRefresh(seller);

    phase = 'second_buyer_device_recovers_history';
    const secondContext = await context(browser);
    const second = await login(secondContext, data.users.buyer, chat(data.users.seller.id));
    invariant(await second.locator('#e2ee-unlock').isHidden(), 'New device must have no local vault');
    await second.locator('#e2ee-recovery summary').click();
    await second.locator('#e2ee-device-name').fill('Synthetic buyer phone'); await second.locator('#e2ee-passphrase').fill(password);
    await second.locator('#e2ee-recovery-input').fill(buyerIdentity.code);
    await action(second, 'prepare-recovery', 'save_code'); const secondIdentity = await finishPending(second, data.users.buyer, 2);
    invariant(secondIdentity.fingerprint === buyerIdentity.fingerprint && secondIdentity.code !== buyerIdentity.code, 'Recovery rotates code while retaining identity');
    await unlock(second); await verifyPeer(second, sellerIdentity.fingerprint); await receiveFile(second, firstMessage);
    phase = 'both_buyer_devices_receive';
    await send(seller, sharedMessage);
    await action(buyer, 'load', 'ready'); await visibleMessage(buyer, sharedMessage);
    await action(second, 'load', 'ready'); await visibleMessage(second, sharedMessage);
    checks.push('new_device_recovers_history_and_two_active_devices_receive');

    phase = 'hold_old_seller_tab_before_revocation';
    staleSeller = await holdStaleSellerTab(sellerContext, chat(data.users.buyer.id), data.users.seller.id, data.users.buyer.id);

    phase = 'revoke_old_device_and_rotate_recovery';
    if (!await second.locator('#e2ee-recovery').evaluate(node => node.open)) await second.locator('#e2ee-recovery summary').click();
    await second.locator('#e2ee-device-name').fill('Synthetic replacement phone'); await second.locator('#e2ee-passphrase').fill(password);
    await second.locator('#e2ee-recovery-input').fill(secondIdentity.code); await second.locator('#e2ee-revoke').selectOption(initialDevice);
    await action(second, 'prepare-recovery', 'save_code'); const replacement = await finishPending(second, data.users.buyer, 3);
    invariant(replacement.fingerprint === buyerIdentity.fingerprint && replacement.code !== secondIdentity.code, 'Revocation rotates recovery code');
    await unlock(second); await visibleMessage(second, firstMessage); await visibleMessage(second, sharedMessage);
    const own = await secondContext.request.get(origin + '/user/e2ee-api.php?action=account'); assert.equal(own.status(), 200);
    const ownBundle = (await own.json()).account, manifest = JSON.parse(ownBundle.manifest);
    assert.equal(manifest.version, 3); assert.equal(manifest.devices.filter(device => !device.revoked).length, 1);
    invariant(manifest.devices.find(device => device.id === initialDevice)?.revoked, 'Old device must be revoked');
    phase = 'cross_tab_monotonic_pin_after_revocation';
    await staleSeller.finish(seller, ownBundle.hash); staleSeller = null;
    phase = 'future_messages_exclude_revoked_device';
    await send(seller, futureMessage, true); await action(second, 'load', 'ready'); await receiveFile(second, futureMessage);
    await buyer.reload(); await enabled(buyer, '#e2ee-passphrase'); await unlock(buyer, 'error_device');
    invariant(await buyer.locator('#e2ee-send').isDisabled(), 'Revoked device cannot send');
    invariant(!(await buyer.locator('#e2ee-messages').innerText()).includes(futureMessage), 'Revoked device exposed a new message');
    checks.push('recovery_rotation_revokes_old_devices_and_new_messages_reach_replacement');

    phase = 'old_recovery_code_rejected';
    if (!await second.locator('#e2ee-recovery').evaluate(node => node.open)) await second.locator('#e2ee-recovery summary').click();
    await second.locator('#e2ee-device-name').fill('Synthetic rejected recovery'); await second.locator('#e2ee-passphrase').fill(password);
    await second.locator('#e2ee-recovery-input').fill(secondIdentity.code); await action(second, 'prepare-recovery', 'error_recovery');
    invariant(await second.locator('#e2ee-pending').isHidden(), 'Stale recovery code must not prepare account update');
    assert.equal(fixture('status', { fixture: data.fixture }).accounts.buyer.version, 3);
    checks.push('stale_recovery_code_rejected_without_server_change');

    phase = 'legacy_plaintext_route_blocked';
    const legacy = '/user/chat.php?listing_id=' + data.listing_id + '&user_id=' + data.users.seller.id;
    const legacyPage = await secondContext.newPage(); const legacyResponse = await legacyPage.goto(origin + legacy); assert.equal(legacyResponse.status(), 200);
    assert.equal(await legacyPage.locator('textarea[name=message]').count(), 0);
    const csrf = await legacyPage.locator('input[name=csrf_token]').first().inputValue();
    const attempted = await secondContext.request.post(origin + legacy, { form: { csrf_token: csrf, action: 'send', message: 'Synthetic forbidden legacy request' }, maxRedirects: 0 });
    assert.equal(attempted.status(), 403); await legacyPage.close(); checks.push('legacy_send_remains_blocked_after_enrollment');

    phase = 'outsider_denied';
    const strangerContext = await context(browser);
    const stranger = await login(strangerContext, data.users.outsider, '/user/secure-chat.php');
    const forbiddenPage = await strangerContext.request.get(origin + chat(data.users.buyer.id)); assert.equal(forbiddenPage.status(), 403);
    const forbiddenHistory = await strangerContext.request.get(origin + '/user/e2ee-api.php?action=history&listing_id=' + data.listing_id + '&peer_id=' + data.users.buyer.id); assert.equal(forbiddenHistory.status(), 403);
    const forbiddenDownload = await strangerContext.request.get(origin + '/user/e2ee-api.php?action=download&id=' + initial.attachments[0].id); assert.equal(forbiddenDownload.status(), 404);
    assert.equal(await stranger.locator('#e2ee-own-fingerprint').textContent(), ''); checks.push('outsider_cannot_open_pair_history_or_download_attachment');

    phase = 'mobile_locales';
    for (const locale of locales) {
      await language(second, locale); await mobile(second, locale);
      await second.locator('#e2ee-recovery summary').click(); await mobile(second, locale);
      await unlock(second); await visibleMessage(second, firstMessage); await visibleMessage(second, futureMessage); await mobile(second, locale);
      await action(second, 'lock', 'locked'); assert.equal(await second.locator('#e2ee-messages article').count(), 0);
      invariant(await second.locator('#e2ee-body').inputValue() === '', 'Lock clears plaintext input');
      await mobile(second, locale); checks.push('mobile_' + locale + '_history_and_lock');
    }

    phase = 'compromised_identity_reset_and_peer_reverification';
    const thirdContext = await context(browser);
    const third = await login(thirdContext, data.users.buyer, chat(data.users.seller.id));
    const { factor } = fixture('reset_factor', { fixture: data.fixture });
    await language(second, 'de');
    await second.locator('#e2ee-device-name').fill('Synthetic new authority'); await second.locator('#e2ee-passphrase').fill(password);
    await second.locator('#e2ee-identity-reset summary').click(); await second.locator('#e2ee-reset-confirm').check();
    await action(second, 'prepare-reset', 'save_code');
    const reset = await finishPending(second, data.users.buyer, 4, factor);
    invariant(reset.fingerprint !== buyerIdentity.fingerprint, 'Reset must generate a new account authority');
    assert.equal((await second.locator('#e2ee-devices li').count()), 1);
    await unlock(second);
    await second.locator('#e2ee-archives summary').click(); await second.locator('#e2ee-archive-passphrase').fill(password);
    await action(second, 'open-archive', 'archive_opened'); await receiveFile(second, firstMessage);
    await action(seller, 'load', 'ready'); await seller.locator('#e2ee-peer-rotation').waitFor({ state: 'visible' });
    invariant(await seller.locator('#e2ee-send').isDisabled(), 'New authority must block sending until independently verified');
    await seller.locator('#e2ee-fingerprint-input').fill(buyerIdentity.fingerprint); await action(seller, 'verify', 'error_trust');
    await verifyPeer(seller, reset.fingerprint); await seller.locator('#e2ee-peer-rotation').waitFor({ state: 'hidden' });
    await visibleMessage(seller, firstMessage);
    await send(seller, newIdentityMessage, true); await action(second, 'load', 'ready'); await receiveFile(second, newIdentityMessage);
    await third.reload(); await enabled(third, '#e2ee-passphrase');
    await third.locator('#e2ee-recovery summary').click(); await third.locator('#e2ee-device-name').fill('Synthetic recovered new authority');
    await third.locator('#e2ee-passphrase').fill(password); await third.locator('#e2ee-recovery-input').fill(reset.code);
    await action(third, 'prepare-recovery', 'save_code'); const recovered = await finishPending(third, data.users.buyer, 5);
    invariant(recovered.fingerprint === reset.fingerprint, 'Recovery must preserve the replacement authority');
    await unlock(third); await verifyPeer(third, sellerIdentity.fingerprint); await receiveFile(third, newIdentityMessage);
    checks.push('identity_reset_requires_revalidation_and_preserves_anchored_history');

    phase = 'server_and_browser_plaintext_absence';
    const finalState = fixture('status', { fixture: data.fixture });
    assert.equal(finalState.messages.length, 4); assert.equal(finalState.envelopes.length, 4); assert.equal(finalState.attachments.length, 3);
    assert.equal(finalState.accounts.seller.version, 1); assert.equal(finalState.accounts.buyer.version, 5); assert.equal(finalState.accounts.outsider, null);
    for (const probe of probes) {
      for (const value of [probe, Buffer.from(probe).toString('base64')]) {
        const state = fixture('status', { fixture: data.fixture, plaintext_probe: value });
        invariant(!state.plaintext_found && !state.private_key_marker_found && state.cipher2_empty_legacy_text, 'Private material found in server storage');
      }
    }
    for (const page of [seller, buyer, second, third]) await storageIsPrivate(page);
    // Stop new API traffic and drain existing responses before closing pages.
    freezeApiRequests=true;
    const drainDeadline=Date.now()+10000;
    while (activeApiRequests.size>0&&Date.now()<drainDeadline) await new Promise(resolve=>setTimeout(resolve,25));
    invariant(activeApiRequests.size===0,'E2EE network audit did not drain');
    await Promise.all(pendingResponses);
    for (const page of [seller, buyer, second, third, stranger]) await page.close();
    for (const bytes of wire) privateFree(bytes);
    assert.equal(pageErrors.length, 0); assert.equal(external.length, 0);
    checks.push('no_private_plaintext_in_http_browser_storage_server_rows_or_attachment_files');
    return { result: 'PASS', checks, js_errors: 0, external_requests: 0, scope: 'Synthetic Chromium UI with real browser OpenPGP; no production or independent cryptographic approval' };
  } finally {
    try { if (staleSeller) await staleSeller.dispose(); if (browser) await browser.close(); }
    finally { if (data) fixture('restore', { fixture: data.fixture }); }
  }
})().then(result => console.log(JSON.stringify(result))).catch(error => {
  // Never print Playwright locator diagnostics, assertion values, DOM, keys,
  // recovery codes, plaintext canaries or request bodies on failure.
  const check=typeof error.safeCheck==='string'&&/^[A-Za-z0-9 _-]{1,160}$/.test(error.safeCheck)?error.safeCheck:undefined;
  console.error(JSON.stringify({ result: 'FAIL', phase, completed_checks: checks, error: error.name || 'Error', check, js_errors: pageErrors.length, external_requests: external.length }));
  const frame = String(error.stack).split('\n').find(line => /^\s+at /.test(line) && !/at invariant /.test(line) && /e2ee_browser\.js:\d+:\d+\)?$/.test(line));
  if (frame) console.error(frame); process.exitCode = 1;
});

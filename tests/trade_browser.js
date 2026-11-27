/* Real synthetic multi-user SSR flows. Linux/container only; no live provider requests. */
'use strict';
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { playwright, launchOptions, axe } = require('./browser_tools');
const origin = 'http://127.0.0.1:8081';
const locales = ['de', 'en', 'fr', 'nl', 'pl', 'cs', 'da', 'it'];
const checks = [], pageErrors = [], external = [];
let phase = 'setup';

function invariant(ok, message) {
  if (!ok) { const error = new Error('Trade browser invariant failed'); error.safeCheck = message; throw error; }
}
function fixture(operation, fields = {}) {
  return JSON.parse(execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'exec', '-i',
    'gunindex-rebuild-app', 'php', 'tests/trade_browser_fixture.php'], {
    input: JSON.stringify({ operation, ...fields }), stdio: ['pipe', 'pipe', 'pipe'], maxBuffer: 1024 * 1024,
  }).toString('utf8'));
}
async function context(browser) {
  const result = await browser.newContext({locale:'de-DE', viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  await result.route('**/*', route => {
    if (route.request().url().startsWith(origin + '/')) return route.continue();
    external.push(new URL(route.request().url()).protocol); return route.abort();
  });
  return result;
}
async function login(browserContext, user) {
  const page = await browserContext.newPage(); page.setDefaultTimeout(20000); page.setDefaultNavigationTimeout(30000);
  page.on('pageerror', error => pageErrors.push(error.name));
  await page.goto(origin + '/auth/login.php');
  await page.locator('[name=login]').fill(user.username); await page.locator('[name=password]').fill(user.password);
  await Promise.all([page.waitForURL('**/user/dashboard.php'), page.locator('main button[type=submit]').click()]);
  return page;
}
async function goto(page, path) {
  const response = await page.goto(origin + path); assert.equal(response.status(), 200);
}
async function submit(page, form, action) {
  await Promise.all([page.waitForNavigation(), form.locator('button[name=action][value="' + action + '"]').click()]);
  invariant(await page.locator('main .trade-error').count() === 0, 'Submitted form returned a domain error');
}
async function consent(form) {
  const legal = form.locator('[name=legal_confirm]');
  if (await legal.count()) await legal.check();
}
async function checkout(page, listing, offer = null) {
  await goto(page, '/user/checkout.php?listing_id=' + listing + '&delivery=shipping' + (offer === null ? '' : '&offer_id=' + offer));
  const form = page.locator('#trade-checkout-form');
  invariant(!await form.locator('[name=confirm_purchase]').isChecked(), 'Purchase confirmation is preselected');
  await consent(form); await form.locator('[name=confirm_purchase]').check(); await submit(page, form, 'purchase');
  const url = new URL(page.url()); assert.equal(url.pathname, '/user/trade.php');
  const id = Number(url.searchParams.get('id')); invariant(Number.isSafeInteger(id) && id > 0, 'Missing resulting trade ID');
  assert.equal(await page.locator('#trade-status').getAttribute('data-trade-status'), 'agreed');
  return id;
}
async function transition(page, id, action, details = 'Synthetic fulfilment information only.') {
  await goto(page, '/user/trade.php?id=' + id);
  const form = page.locator('[data-trade-action="' + action + '"]');
  await form.evaluate(node => { const details = node.closest('details'); if (details) details.open = true; });
  const field = form.locator('[name=details]'); if (await field.count()) await field.fill(details);
  invariant(!await form.locator('[name=confirm_action]').isChecked(), 'Trade action confirmation is preselected');
  await form.locator('[name=confirm_action]').check(); await submit(page, form, action);
}
async function offer(page, listing, amount, parent = null) {
  await goto(page, '/user/offers.php?listing_id=' + listing + (parent === null ? '' : '&parent_id=' + parent));
  const form = page.locator('#trade-offer-form'); await form.locator('[name=amount]').fill(amount);
  await form.locator('[name=delivery]').selectOption('shipping'); await form.locator('[name=hours]').fill('24');
  invariant(!await form.locator('[name=confirm_offer]').isChecked(), 'Offer consent preselected');
  await consent(form); await form.locator('[name=confirm_offer]').check(); await submit(page, form, 'create_offer');
  const match = new URL(page.url()).hash.match(/^#offer-([1-9][0-9]*)$/); invariant(match, 'Offer redirect lacks resulting ID');
  return Number(match[1]);
}
async function offerAction(page, listing, id, action) {
  await goto(page, '/user/offers.php?listing_id=' + listing);
  const form = page.locator('[data-offer-id="' + id + '"] [data-offer-action="' + action + '"]');
  await form.locator('[name=confirm_action]').check(); await submit(page, form, action);
}
async function language(page, locale) {
  const switcher = page.locator('.market-switcher');
  if (!await switcher.evaluate(node => node.open)) await switcher.locator('summary').click();
  const form = switcher.locator('form[action="/user/language.php"]');
  await form.locator('[name=locale]').selectOption(locale); await form.locator('[name=market_country]').selectOption('DE');
  await Promise.all([page.waitForNavigation(), form.locator('button[type=submit]').click()]);
}
async function mobile(page, locale) {
  assert.equal(await page.locator('html').getAttribute('lang'), locale);
  invariant(!/trade\.[a-z_]+/.test(await page.locator('main.trade').innerText()), 'Untranslated trade label');
  invariant(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Trade page overflows mobile viewport');
}
async function accessible(page) {
  const AxeBuilder = axe();
  const result = await new AxeBuilder({ page }).include('main.trade').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  invariant(result.violations.length === 0, 'New trade content has accessibility violations: ' + result.violations.map(v => v.id).join(','));
}
async function rate(page, id, stars, comment) {
  await goto(page, '/user/trade.php?id=' + id); const form = page.locator('#trade-rating-form');
  await form.locator('[name=stars]').selectOption(String(stars)); await form.locator('[name=comment]').fill(comment);
  await form.locator('[name=confirm_action]').check(); await submit(page, form, 'rate');
  invariant(await page.locator('#trade-rating-form').count() === 0, 'Rating can be submitted twice');
}

(async () => {
  invariant(process.platform !== 'win32', 'Run browser regressions in isolated Linux environment');
  const data = fixture('setup'); let browser;
  const state = () => fixture('status', { fixture: data.fixture });
  const tradeState = id => { const row = state().trades.find(row => row.id === id); invariant(row, 'Trade database row absent'); return row; };
  try {
    browser = await playwright.chromium.launch(launchOptions());
    const buyerContext = await context(browser), sellerContext = await context(browser), outsiderContext = await context(browser);
    const buyer = await login(buyerContext, data.users.buyer), seller = await login(sellerContext, data.users.seller), outsider = await login(outsiderContext, data.users.outsider);

    phase = 'eight_locale_checkout_offers_and_list_mobile';
    await goto(buyer, '/user/checkout.php?listing_id=' + data.listings.fixed + '&delivery=shipping');
    for (const locale of locales) {
      await language(buyer, locale);
      for (const path of ['/user/checkout.php?listing_id=' + data.listings.fixed + '&delivery=shipping',
        '/user/offers.php?listing_id=' + data.listings.negotiated, '/user/trades.php']) {
        await goto(buyer, path); await mobile(buyer, locale);
      }
    }
    assert.equal(state().trades.length, 0); assert.equal(state().offers.length, 0);
    await language(buyer, 'de'); checks.push('eight_locales_mobile_no_get_side_effects');

    phase = 'checkout_csrf_and_quote_integrity';
    await goto(buyer, '/user/checkout.php?listing_id=' + data.listings.fixed + '&delivery=shipping');
    await accessible(buyer);
    const fields = await buyer.locator('#trade-checkout-form').evaluate(form => Object.fromEntries(new FormData(form)));
    const noCsrf = { ...fields, action: 'purchase', confirm_purchase: '1' }; delete noCsrf.csrf_token;
    const denied = await buyer.request.post(origin + '/user/checkout.php', { form: noCsrf, maxRedirects: 0 }); assert.equal(denied.status(), 403);
    const forged = await buyer.request.post(origin + '/user/checkout.php', { form: { ...fields, action: 'purchase', confirm_purchase: '1', quote_hash: '0'.repeat(64) }, maxRedirects: 0 });
    assert.equal(forged.status(), 422); assert.equal(state().trades.length, 0);
    checks.push('csrf_and_forged_quote_rejected_without_trade');

    phase = 'fixed_purchase';
    const fixed = await checkout(buyer, data.listings.fixed); let row = tradeState(fixed);
    assert.equal(row.total_cents, 4800); assert.equal(row.price_cents, 4500); assert.equal(row.shipping_cents, 300);
    assert.equal(row.buyer_id, data.users.buyer.id); assert.equal(row.seller_id, data.users.seller.id);
    assert.equal(state().listings.fixed.status, 'sold'); checks.push('real_fixed_purchase_exact_total');
    const version = row.version;
    await goto(seller, '/user/trade.php?id=' + fixed + '&action=dispatch&confirm_action=1');
    assert.equal(tradeState(fixed).version, version); checks.push('get_cannot_transition_trade');
    const hidden = await outsider.goto(origin + '/user/trade.php?id=' + fixed); assert.equal(hidden.status(), 404);
    await goto(outsider, '/user/trades.php');
    invariant(await outsider.locator('a[href="/user/trade.php?id=' + fixed + '"]').count() === 0, 'Other user can discover private trade');
    checks.push('trade_detail_and_list_deny_outsider');

    phase = 'delivery_external_confirmation_and_completion';
    await goto(buyer, '/user/trade.php?id=' + fixed);
    invariant(await buyer.locator('[data-trade-action=dispatch]').count() === 0, 'Buyer sees seller dispatch');
    invariant(await buyer.locator('#trade-rating-form').count() === 0, 'Review form available before completion');
    await transition(seller, fixed, 'dispatch', 'Synthetic parcel reference TRADE-TEST, no real shipment.');
    assert.equal(tradeState(fixed).status, 'dispatched'); await transition(buyer, fixed, 'receive');
    row = tradeState(fixed); assert.equal(row.buyer_confirmed, 1); assert.equal(row.seller_confirmed, 0); assert.notEqual(row.status, 'completed');
    invariant(await buyer.locator('#trade-rating-form').count() === 0, 'Receipt alone unlocks review');
    await transition(seller, fixed, 'settle'); row = tradeState(fixed);
    assert.equal(row.status, 'completed'); assert.equal(row.seller_confirmed, 1);
    assert.equal(row.events.filter(event => event.action === 'dispatch').length, 1); checks.push('dispatch_receipt_and_separate_seller_confirmation');

    phase = 'ratings_and_escaping';
    const canary = '<img src=x onerror="window.tradeInjected=1">Synthetic review';
    await rate(buyer, fixed, 5, canary);
    invariant((await buyer.locator('main.trade').innerText()).includes(canary), 'Authored review was lost');
    invariant(await buyer.evaluate(() => typeof window.tradeInjected === 'undefined'), 'Review executed active HTML');
    await rate(seller, fixed, 4, 'Synthetic seller review after completed trade.');
    row = tradeState(fixed); assert.equal(row.ratings.length, 2);
    for (const rating of row.ratings) assert.equal(Number(rating.receiver_id), Number(rating.sender_id) === data.users.buyer.id ? data.users.seller.id : data.users.buyer.id);
    checks.push('completed_party_reviews_once_and_html_escaped');

    phase = 'eight_locale_trade_detail_mobile_accessibility';
    await goto(buyer, '/user/trade.php?id=' + fixed);
    for (const locale of locales) { await language(buyer, locale); await mobile(buyer, locale); }
    await accessible(buyer); await language(buyer, 'de'); checks.push('eight_locale_trade_details_and_accessibility');

    phase = 'offer_reject_and_withdraw';
    const rejected = await offer(buyer, data.listings.negotiated, '38.00');
    await goto(outsider, '/user/offers.php?listing_id=' + data.listings.negotiated);
    invariant(await outsider.locator('[data-offer-id="' + rejected + '"]').count() === 0, 'Private buyer offer leaked to another buyer');
    await offerAction(seller, data.listings.negotiated, rejected, 'reject');
    assert.equal(state().offers.find(offer => offer.id === rejected).status, 'rejected');
    const withdrawn = await offer(buyer, data.listings.negotiated, '39.00');
    await offerAction(buyer, data.listings.negotiated, withdrawn, 'withdraw');
    assert.equal(state().offers.find(offer => offer.id === withdrawn).status, 'withdrawn');
    checks.push('private_offers_reject_and_withdraw');

    phase = 'counteroffer_acceptance';
    const initial = await offer(buyer, data.listings.negotiated, '39.95');
    const counter = await offer(seller, data.listings.negotiated, '42.00', initial);
    assert.equal(state().offers.find(offer => offer.id === initial).status, 'countered');
    await goto(buyer, '/user/offers.php?listing_id=' + data.listings.negotiated); await accessible(buyer);
    invariant(await buyer.locator('[data-offer-id="' + counter + '"] a[href*="offer_id=' + counter + '"]').count() === 1, 'Counter acceptance link absent');
    const negotiated = await checkout(buyer, data.listings.negotiated, counter); row = tradeState(negotiated);
    assert.equal(row.offer_id, counter); assert.equal(row.price_cents, 4200); assert.equal(row.total_cents, 4500);
    assert.equal(state().offers.find(offer => offer.id === counter).status, 'accepted');
    checks.push('counteroffer_acceptance_exact_negotiated_total');

    phase = 'mutual_cancellation';
    const cancelled = await checkout(buyer, data.listings.cancel);
    await transition(buyer, cancelled, 'cancel_request', 'Synthetic cancellation request.');
    invariant(await buyer.locator('[data-trade-action=cancel_accept]').count() === 0, 'Requester may approve own cancellation');
    await transition(seller, cancelled, 'cancel_decline'); assert.equal(tradeState(cancelled).status, 'agreed');
    await transition(seller, cancelled, 'cancel_request', 'Synthetic mutual cancellation request.');
    await transition(buyer, cancelled, 'cancel_accept'); assert.equal(tradeState(cancelled).status, 'cancelled');
    assert.notEqual(state().listings.cancel.status, 'active'); invariant(await buyer.locator('#trade-rating-form').count() === 0, 'Cancelled trade permits review');
    checks.push('mutual_cancellation_and_decline_do_not_reactivate_item');

    phase = 'dispute_freeze';
    const disputed = await checkout(buyer, data.listings.dispute);
    await transition(buyer, disputed, 'dispute', 'Synthetic dispute: agreed item not received.');
    assert.equal(tradeState(disputed).status, 'disputed');
    for (const page of [buyer, seller]) {
      await goto(page, '/user/trade.php?id=' + disputed);
      invariant(await page.locator('[data-trade-action]').count() === 0, 'Dispute can be self resolved by a party');
      invariant(await page.locator('#trade-rating-form').count() === 0, 'Disputed transaction permits new review');
    }
    checks.push('dispute_freezes_fulfilment_and_ratings');

    assert.deepEqual(pageErrors, []); assert.deepEqual(external, []);
    console.log(JSON.stringify({ status: 'PASS', suite: 'trade_browser', checks, locales: locales.length }));
  } finally {
    if (browser) await browser.close();
    fixture('restore', { fixture: data.fixture });
  }
})().catch(error => {
  console.error(JSON.stringify({ status: 'FAIL', suite: 'trade_browser', phase,
    check: error.safeCheck || error.name, source: (error.stack || '').split('\n').find(line => line.includes('trade_browser.js:'))?.trim() }));
  process.exitCode = 1;
});

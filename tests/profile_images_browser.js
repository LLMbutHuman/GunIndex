'use strict';
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { playwright, launchOptions } = require('./browser_tools');
const origin = 'http://127.0.0.1:8081';
let phase = 'setup';

function php(args, input) {
  return execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'exec', '-i', 'gunindex-rebuild-app', 'php', ...args], {input});
}

function fixture(operation, fields = {}) {
  return JSON.parse(php(['tests/fixture.php'], JSON.stringify({operation, ...fields})));
}

function account(admin = false) {
  const username = 'test_' + randomBytes(8).toString('hex');
  const password = 'Synthetic ' + randomBytes(20).toString('hex');
  return {username, password, ...fixture('seed', {username, password, admin})};
}

async function login(page, user, admin = false) {
  await page.goto(origin + '/auth/login.php');
  await page.locator('[name=login]').fill(user.username);
  await page.locator('main [name=password]').fill(user.password);
  await Promise.all([page.waitForURL(admin ? '**/auth/mfa.php' : '**/user/dashboard.php'), page.locator('main button[type=submit]').click()]);
  if (admin) {
    await page.locator('[name=code]').fill(fixture('code', {username:user.username}).code);
    await Promise.all([page.waitForURL('**/admin/index.php'), page.locator('main button[type=submit]').click()]);
  }
}

(async () => {
  if (process.platform === 'win32') throw new Error('Requires the isolated Linux runtime.');
  const owner = account();
  const reviewer = account(true);
  const marker = 'synthetic_trailing_image_metadata';
  const png = php(['-r', '$im=imagecreatetruecolor(800,500); imagefill($im,0,0,imagecolorallocate($im,20,140,170)); imagepng($im);']);
  const upload = {name:'synthetic.png', mimeType:'image/png', buffer:Buffer.concat([png, Buffer.from(marker)])};
  const avatarEditor = origin + '/user/profile-image.php?kind=avatar';
  const logoEditor = origin + '/user/profile-image.php?kind=logo';
  const publicProfile = origin + '/marketplace/profile.php?user_id=' + owner.id;
  const adminReview = origin + '/admin/campaigns.php?kind=profiles&status=all';
  const browser = await playwright.chromium.launch(launchOptions());
  const editor = await browser.newPage({locale:'de-DE',viewport:{width:390,height:844}});
  const guest = await browser.newPage({locale:'de-DE'});
  const admin = await browser.newPage({locale:'de-DE'});
  async function replaceImage() {
    await editor.locator('input[type=file]').setInputFiles(upload);
    await Promise.all([editor.waitForNavigation(), editor.locator('button[value=save]').click()]);
    return editor.locator('main img').getAttribute('src', {timeout:5000});
  }
  try {
    phase = 'avatar_upload_and_decode';
    await login(editor, owner);
    await editor.goto(avatarEditor);
    const avatar = await replaceImage();
    await guest.goto(publicProfile);
    const publicImage = guest.locator('main header img');
    assert.equal(await publicImage.getAttribute('src'), avatar);
    await publicImage.evaluate(image => image.decode());
    assert.equal(await publicImage.evaluate(image => image.naturalWidth), 400);
    const imageResponse = await guest.request.get(origin + avatar);
    assert.equal(imageResponse.headers()['content-type'], 'image/webp');
    assert.equal(imageResponse.headers()['x-content-type-options'], 'nosniff');
    assert.match(imageResponse.headers()['cache-control'], /no-store/);
    assert.equal((await imageResponse.body()).includes(marker), false);

    phase = 'invalid_upload_and_csrf';
    const token = await editor.locator('form[enctype] [name=csrf_token]').inputValue();
    const svg = {name:'forged.png',mimeType:'image/png',buffer:Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"><script>void 0</script></svg>')};
    assert.equal((await editor.request.post(avatarEditor,{multipart:{action:'save',csrf_token:token,image:svg}})).status(),422);
    const tooWide = {...upload,buffer:php(['-r','imagepng(imagecreatetruecolor(8001,1));'])};
    assert.equal((await editor.request.post(avatarEditor,{multipart:{action:'save',csrf_token:token,image:tooWide}})).status(),422);
    assert.equal((await editor.request.post(avatarEditor,{multipart:{action:'save',csrf_token:'invalid',image:upload}})).status(),403);
    assert.equal((await guest.request.head(origin + avatar)).status(),200);

    phase = 'dealer_profile_and_private_logo';
    await editor.goto(origin + '/user/dealer.php');
    const info = editor.locator('form:has(input[name=action][value=profile])');
    await info.locator('[name=company_name]').fill('Synthetic Profile Image Dealer');
    await info.locator('[name=address_text]').fill('Synthetic business address for fixture');
    await info.locator('[name=contact_email]').fill('dealer@example.invalid');
    await info.locator('[name=legal_notice]').fill('Synthetic dealer profile used in an isolated test.');
    await Promise.all([editor.waitForNavigation(), info.locator('button[type=submit]').click()]);
    await editor.goto(logoEditor);
    const firstLogo = await replaceImage();
    assert.equal((await editor.request.get(origin + firstLogo)).status(),200);
    assert.equal((await guest.request.get(origin + firstLogo)).status(),404);
    await login(admin, reviewer, true);
    await admin.goto(adminReview);
    const review = admin.locator(`form:has(input[name=target_id][value="${owner.id}"])`);
    const previous = await review.locator('[name=revision]').inputValue();
    const preview = await review.locator('xpath=..').locator('img').getAttribute('src');
    assert.equal((await admin.request.get(origin + preview)).headers()['content-type'],'image/webp');
    assert.equal((await editor.request.get(origin + preview)).status(),403);

    phase = 'stale_moderation_and_approval';
    const nextLogo = await replaceImage();
    assert.notEqual(firstLogo,nextLogo);
    assert.equal((await admin.request.get(origin + preview)).status(),404);
    const reviewToken = await review.locator('[name=csrf_token]').inputValue();
    const rejected = await admin.request.post(adminReview,{form:{csrf_token:reviewToken,action:'profile',target_id:String(owner.id),status:'published',reason:'Synthetic review of previous revision',revision:previous}});
    assert.equal(rejected.status(),422);
    await admin.reload();
    await review.locator('[name=reason]').fill('Synthetic approval of current profile and logo');
    await Promise.all([admin.waitForNavigation(),review.locator('button').click()]);
    await guest.goto(publicProfile);
    assert.equal((await guest.request.get(origin + nextLogo)).status(),200);
    assert.equal(await guest.locator('main section img').getAttribute('src'), nextLogo);

    phase = 'localized_mobile_public_profile';
    await guest.setViewportSize({width:390,height:844});
    const profileCopy = {
      de:['Aktuelle Angebote','Bewertungen','Gewerblicher Anbieter','Dieses Profil ist nicht verfügbar.'],
      en:['Current listings','Reviews','Business seller','This profile is unavailable.'],
      fr:['Annonces actuelles','Avis','Vendeur professionnel','Ce profil est indisponible.'],
      nl:['Actuele advertenties','Beoordelingen','Zakelijke verkoper','Dit profiel is niet beschikbaar.'],
      pl:['Aktualne ogłoszenia','Opinie','Sprzedawca profesjonalny','Ten profil jest niedostępny.'],
      cs:['Aktuální inzeráty','Hodnocení','Firemní prodejce','Tento profil není dostupný.'],
      da:['Aktuelle annoncer','Anmeldelser','Erhvervssælger','Denne profil er ikke tilgængelig.'],
      it:['Annunci attuali','Recensioni','Venditore professionale','Questo profilo non è disponibile.'],
    };
    for (const [locale,[listings,reviews,dealer,missing]] of Object.entries(profileCopy)) {
      await guest.locator('.market-switcher summary').click();
      const switcher = guest.locator('form[action="/user/language.php"]');
      await switcher.locator('[name=locale]').selectOption(locale);
      await Promise.all([guest.waitForNavigation(),switcher.locator('button').click()]);
      assert.equal(await guest.locator('html').getAttribute('lang'),locale);
      for (const heading of [listings,reviews,'Synthetic Profile Image Dealer']) {
        assert(await guest.getByRole('heading',{name:heading,exact:true}).isVisible());
      }
      assert(await guest.getByText(dealer,{exact:true}).isVisible());
      assert(await guest.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Profile overflow for ${locale}`);
      const denied = await guest.request.get(origin + '/marketplace/profile.php?user_id=unavailable');
      assert.equal(denied.status(),404);
      assert((await denied.text()).includes(missing));
      assert.equal((await guest.request.get(publicProfile + '&page=invalid')).status(),422);
    }

    phase = 'export_and_remove';
    await editor.goto(origin + '/user/privacy.php');
    const exportToken = await editor.locator('form:has(input[value=export]) [name=csrf_token]').inputValue();
    const response = await editor.request.post(origin + '/user/privacy.php',{form:{csrf_token:exportToken,action:'export',current_password:owner.password}});
    assert.equal(response.status(),200);
    const { profile_images: images } = await response.json();
    assert.deepEqual(images.map(image => image.kind), ['avatar','logo']);
    for (const image of images) assert.equal(Buffer.from(image.base64,'base64').subarray(8,12).toString(), 'WEBP');
    for (const [editorUrl, imageUrl] of [[avatarEditor,avatar],[logoEditor,nextLogo]]) {
      await editor.goto(editorUrl);
      await Promise.all([editor.waitForNavigation(),editor.locator('button[value=remove]').click()]);
      assert.equal((await guest.request.get(origin + imageUrl)).status(),404);
    }
    await guest.goto(publicProfile);
    assert.equal(await guest.getByRole('heading',{name:'Synthetic Profile Image Dealer'}).count(),1);
    console.log('Profile browser checks passed: images, moderation, export, removal and eight mobile locales.');
  } finally { await browser.close(); }
})().catch(error => { console.error(`Profile images browser failure (${phase}): ${error.message}`); process.exitCode = 1; });

/* Real country/language controls; no external services or production data. */
const assert=require('assert');
const {playwright,launchOptions}=require('./browser_tools');
const origin='http://127.0.0.1:8081';
(async()=>{
 const browser=await playwright.chromium.launch(launchOptions());const checks=[];const errors=[];
 const loginCopy={de:['Willkommen zurück.','Anmeldung fehlgeschlagen.'],en:['Welcome back.','Sign-in failed.'],fr:['Bon retour.','Échec de la connexion.'],nl:['Welkom terug.','Aanmelden mislukt.'],pl:['Witaj ponownie.','Logowanie nie powiodło się.'],cs:['Vítejte zpět.','Přihlášení se nezdařilo.'],da:['Velkommen tilbage.','Login mislykkedes.'],it:['Bentornato.','Accesso non riuscito.']};
 const registerCopy={de:['Ein Konto. Neue Möglichkeiten.','Der Benutzername benötigt'],en:['One account. New possibilities.','The username must contain'],fr:['Un compte. De nouvelles possibilités.','Le nom d’utilisateur doit contenir'],nl:['Eén account. Nieuwe mogelijkheden.','De gebruikersnaam moet'],pl:['Jedno konto. Nowe możliwości.','Nazwa użytkownika musi zawierać'],cs:['Jeden účet. Nové možnosti.','Uživatelské jméno musí obsahovat'],da:['Én konto. Nye muligheder.','Brugernavnet skal indeholde'],it:['Un account. Nuove possibilità.','Il nome utente deve contenere']};
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'});const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  for(const [locale,country] of [['de','DE'],['en','AT'],['fr','FR'],['nl','NL'],['pl','PL'],['cs','CZ'],['da','DK'],['it','CH']]){
   await page.goto(origin+'/',{waitUntil:'networkidle'});await page.locator('.market-switcher summary').click();const form=page.locator('form[action="/user/language.php"]');await form.locator('[name=locale]').selectOption(locale);await form.locator('[name=market_country]').selectOption(country);
   await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),form.locator('button[type=submit]').click()]);
   assert.strictEqual(await page.locator('html').getAttribute('lang'),locale);assert.strictEqual(await page.locator('[name=market_country]').inputValue(),country);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile overflow for '+locale);checks.push('locale_'+locale+'_'+country);
   await page.goto(origin+'/auth/login.php',{waitUntil:'networkidle'});assert.strictEqual(await page.locator('h1').textContent(),loginCopy[locale][0]);
   await page.locator('[name=login]').fill('nonexistent-synthetic-'+locale);await page.locator('[name=password]').fill('Synthetic invalid password');
   const response=await Promise.all([page.waitForResponse(r=>r.url()===origin+'/auth/login.php'&&r.request().method()==='POST'),page.locator('main button[type=submit]').click()]);
   assert.strictEqual(response[0].status(),422);await page.waitForLoadState('networkidle');assert((await page.locator('[role=alert]').innerText()).startsWith(loginCopy[locale][1]));
   assert(!/account\.[a-z_]+/.test(await page.locator('main').innerText()));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Login mobile overflow '+locale);
   const denied=await page.request.post(origin+'/auth/login.php',{form:{login:'synthetic-missing-csrf',password:'Synthetic invalid password'}});assert.strictEqual(denied.status(),403);const deniedBody=await denied.text();assert(deniedBody.includes('lang="'+locale+'"')&&!deniedBody.includes('account.csrf'));checks.push('localized_login_and_errors_'+locale);
   await page.goto(origin+'/auth/register.php',{waitUntil:'networkidle'});assert.strictEqual(await page.locator('h1').textContent(),registerCopy[locale][0]);assert.strictEqual(await page.locator('[name=preferred_locale]').inputValue(),locale);
   await page.locator('[name=username]').fill('bad<script>');await page.locator('[name=email]').fill('invalid-name-'+locale+'@example.invalid');await page.locator('[name=zip]').fill('10115');await page.locator('[name=city]').fill('Synthetic city');await page.locator('[name=password]').fill('Synthetic registration password');await page.locator('[name=password_confirm]').fill('Synthetic registration password');
   const registrationResponse=await Promise.all([page.waitForResponse(r=>r.url()===origin+'/auth/register.php'&&r.request().method()==='POST'),page.locator('main button[type=submit]').click()]);assert.strictEqual(registrationResponse[0].status(),422);await page.waitForLoadState('networkidle');
   assert((await page.locator('[role=alert]').innerText()).startsWith(registerCopy[locale][1]));assert(!/account\.[a-z_]+/.test(await page.locator('main').innerText()));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Registration mobile overflow '+locale);checks.push('localized_registration_and_validation_'+locale);
  }
  await page.goto(origin+'/marketplace/search.php',{waitUntil:'networkidle'});const search=page.locator('form.search-toolbar');await search.locator('[name=scope]').selectOption('international');await search.locator('[name=country_code]').selectOption('DE');
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),search.locator('button[type=submit]').click()]);assert.strictEqual(new URL(page.url()).searchParams.get('scope'),'international');assert.strictEqual(await page.locator('form.search-toolbar [name=scope]').inputValue(),'international');checks.push('international_search_control');
  assert.deepStrictEqual(errors,[]);console.log(JSON.stringify({result:'PASS',checks,js_errors:0,scope:'Eight mobile language/country, login, registration and error journeys plus international search; exact distances covered by synthetic DB tests'}));
 }finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exit(1)});

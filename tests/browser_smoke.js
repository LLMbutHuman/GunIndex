/* Real browser workflows, isolated synthetic accounts; no external payments. */
const assert=require('assert');const crypto=require('crypto');const fs=require('fs');const {execFileSync}=require('child_process');
const {playwright:pw,launchOptions}=require('./browser_tools');
const origin='http://127.0.0.1:8081';
function fixture(operation,fields={}){return JSON.parse(execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','-i','gunindex-rebuild-app','php','tests/fixture.php'],{input:JSON.stringify({operation,...fields}),stdio:['pipe','pipe','pipe']}).toString());}
function account(admin=false){const username='test_'+crypto.randomBytes(8).toString('hex');const password='Synthetic '+crypto.randomBytes(20).toString('hex');return {username,password,...fixture('seed',{username,password,admin})};}
async function login(page,user,admin=false){await page.goto(origin+'/auth/login.php');await page.locator('[name=login]').fill(user.username);await page.locator('[name=password]').fill(user.password);await Promise.all([page.waitForURL(admin?'**/auth/mfa.php':'**/user/dashboard.php'),page.getByRole('button',{name:'Anmelden',exact:true}).click()]);if(admin){await page.locator('[name=code]').fill(fixture('code',{username:user.username}).code);await Promise.all([page.waitForURL('**/admin/index.php'),page.getByRole('button',{name:'Sicher anmelden',exact:true}).click()]);}}
(async()=>{
 fixture('reset_limits');const seller=account(),buyer=account(),admin=account(true);const category=fixture('categories').categories[0].id;
 const browser=await pw.chromium.launch(launchOptions());
 const desktop=await browser.newContext({viewport:{width:1440,height:1000}}),mobile=await browser.newContext({viewport:{width:390,height:844}});
 const errors=[],external=[],failures=[],journeys=[];
 const monitor=page=>{page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(!r.url().startsWith(origin+'/'))external.push(r.url())});page.on('response',r=>{if(r.status()>=500)failures.push(new URL(r.url()).pathname)});};
 const page=await desktop.newPage(),phone=await mobile.newPage();monitor(page);monitor(phone);
 try{
  await login(page,seller);journeys.push('login');
  await page.goto(origin+'/user/create-listing.php');const title='Fixture '+crypto.randomBytes(8).toString('hex')+' Browser Schreibtisch';
  await page.locator('[name=title]').fill(title);await page.locator('textarea[name=description]').fill('Synthetischer Testartikel für die echte Browserprüfung, ohne reale personenbezogene Daten.');await page.locator('[name=category_id]').selectOption(String(category));await page.locator('[name=price]').fill('45.00');await page.locator('[name=zip]').fill('10115');await page.locator('[name=city]').fill('Teststadt');await page.locator('[name=accept_rules]').check();
  const picture=execFileSync('docker',['--host=unix:///var/run/docker.sock','exec','gunindex-rebuild-app','php','-r',"$i=imagecreatetruecolor(200,160);imagefill($i,0,0,imagecolorallocate($i,46,160,130));imagepng($i);"]);
  await page.locator('[name="images[]"]').setInputFiles([{name:'synthetic.png',mimeType:'image/png',buffer:picture},{name:'synthetic-second.png',mimeType:'image/png',buffer:picture}]);await Promise.all([page.waitForURL('**/user/dashboard.php?saved=*'),page.getByRole('button',{name:'Inserat speichern',exact:true}).click()]);
  const listing=Number(fixture('find_listing',{username:seller.username,title}).id);assert(listing>0);await page.goto(origin+'/marketplace/view-item.php?id='+listing);await page.waitForFunction(()=>{const i=document.querySelector('[data-gallery-main]');return i&&i.complete&&i.naturalWidth>0;});journeys.push('listing_image');
  await page.goto(origin+'/user/edit-listing.php?id='+listing);
  const imageSelects=page.locator('select[name^="image_positions["]');
  const imageNames=await imageSelects.evaluateAll(nodes=>nodes.map(node=>node.name));assert.strictEqual(imageNames.length,2);
  await imageSelects.nth(0).selectOption('2');await imageSelects.nth(1).selectOption('1');await page.locator('[name=accept_rules]').check();
  await Promise.all([page.waitForURL('**/user/dashboard.php?saved=*'),page.getByRole('button',{name:'Änderungen speichern',exact:true}).click()]);
  await page.goto(origin+'/user/edit-listing.php?id='+listing);assert.deepStrictEqual(await page.locator('select[name^="image_positions["]').evaluateAll(nodes=>nodes.map(node=>node.name)),imageNames.reverse());journeys.push('image_reorder');
  await login(phone,buyer);await phone.goto(origin+'/marketplace/view-item.php?id='+listing);await phone.getByRole('button',{name:'Merkliste ändern'}).click();await phone.waitForURL('**/user/watchlist.php');assert((await phone.locator('main').innerText()).includes(title));journeys.push('watchlist');
  await phone.goto(origin+'/user/personal.php');assert(!(await phone.locator('main').innerText()).includes(title),'Recent history must be opt-in');
  const noCsrf=await phone.request.post(origin+'/user/personal.php',{form:{action:'add',listing_id:String(listing)}});assert.strictEqual(noCsrf.status(),403);
  const badId=await phone.request.post(origin+'/user/personal.php',{form:{action:'add',listing_id:'-1',csrf_token:await phone.locator('[name=csrf_token]').first().inputValue()}});assert.strictEqual(badId.status(),422);
  await phone.locator('form:has([name=action][value=enable]) button').click();await phone.waitForLoadState('networkidle');
  await phone.goto(origin+'/marketplace/view-item.php?id='+listing);await phone.getByRole('button',{name:'Zum Vergleich hinzufügen',exact:true}).click();await phone.waitForURL('**/user/personal.php');
  assert((await phone.locator('main section').nth(0).innerText()).includes(title));assert((await phone.locator('table').innerText()).includes(title));
  assert(await phone.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Personal page mobile overflow');
  await phone.locator('form:has([name=action][value=disable]) button').click();await phone.waitForLoadState('networkidle');
  assert(!(await phone.locator('main section').nth(0).innerText()).includes(title));assert((await phone.locator('table').innerText()).includes(title));
  await phone.locator('form:has([name=action][value=clear_compare]) button').click();await phone.waitForLoadState('networkidle');assert(!(await phone.locator('main').innerText()).includes(title));journeys.push('personal_opt_in_compare_clear');
  await phone.goto(origin+'/marketplace/view-item.php?id='+listing);await phone.getByRole('link',{name:'Verkäufer kontaktieren'}).click();await phone.locator('[name=message]').fill('Synthetische Browsernachricht: Ist der Schreibtisch verfügbar?');await phone.getByRole('button',{name:'Nachricht senden',exact:true}).click();await phone.waitForLoadState('networkidle');assert((await phone.locator('main').innerText()).includes('Synthetische Browsernachricht'));journeys.push('private_chat');
  const auction=fixture('seed_listing',{username:seller.username,title:'Fixture '+crypto.randomBytes(8).toString('hex')+' Browser Bücherpaket',category_id:Number(category)}).id;
  await phone.goto(origin+'/marketplace/view-auction.php?id='+auction);await phone.locator('[name=max_bid]').fill('60.00');await phone.getByRole('button',{name:'Gebot abgeben'}).click();await phone.waitForLoadState('networkidle');assert.strictEqual(Number(fixture('state',{listing_id:Number(auction)}).highest_bidder_id),Number(buyer.id));journeys.push('proxy_bid');
  const profileCopy={
   en:['Everything in its place.','Your profile was saved.','Please enter your current password correctly.'],
   fr:['Tout à sa place.','Votre profil a été enregistré.','Veuillez saisir correctement votre mot de passe actuel.'],
   nl:['Alles op zijn plek.','Je profiel is opgeslagen.','Voer je huidige wachtwoord correct in.'],
   pl:['Wszystko na swoim miejscu.','Twój profil został zapisany.','Wpisz poprawne aktualne hasło.'],
   cs:['Vše na svém místě.','Váš profil byl uložen.','Zadejte správně své aktuální heslo.'],
   da:['Alt på sin plads.','Din profil er gemt.','Indtast din nuværende adgangskode korrekt.'],
   it:['Tutto al suo posto.','Il tuo profilo è stato salvato.','Inserisci correttamente la password attuale.'],
   de:['Alles an seinem Platz.','Dein Profil wurde gespeichert.','Bitte dein aktuelles Passwort korrekt eingeben.']
  };
  const noticeCopy={
   en:['You choose what reaches you.','Your preferences were saved.','Your updates.','Please select valid notification preferences.'],
   fr:['Vous choisissez ce que vous recevez.','Vos préférences ont été enregistrées.','Vos actualités.','Veuillez choisir des préférences de notification valides.'],
   nl:['Jij kiest wat je ontvangt.','Je instellingen zijn opgeslagen.','Je updates.','Kies geldige meldingsinstellingen.'],
   pl:['Ty decydujesz, co otrzymujesz.','Twoje ustawienia zostały zapisane.','Twoje aktualności.','Wybierz prawidłowe ustawienia powiadomień.'],
   cs:['Vy rozhodujete, co dostáváte.','Vaše nastavení bylo uloženo.','Vaše novinky.','Vyberte platné nastavení oznámení.'],
   da:['Du vælger, hvad du modtager.','Dine indstillinger er gemt.','Dine nyheder.','Vælg gyldige indstillinger for notifikationer.'],
   it:['Scegli tu cosa ricevere.','Le impostazioni sono state salvate.','Le tue novità.','Seleziona impostazioni valide per le notifiche.'],
   de:['Du bestimmst, was ankommt.','Die Einstellungen wurden gespeichert.','Deine Neuigkeiten.','Bitte gültige Benachrichtigungseinstellungen wählen.']
  };
  for(const [locale,copy] of Object.entries(profileCopy)){
   await phone.goto(origin+'/user/profile.php');const profileForm=phone.locator('form:has([name=action][value=save])');
   await profileForm.locator('[name=preferred_locale]').selectOption(locale);
   const saved=await Promise.all([phone.waitForNavigation({waitUntil:'networkidle'}),profileForm.locator('button[type=submit]').click()]);
   assert.strictEqual(saved[0].status(),200);assert.strictEqual(await phone.locator('html').getAttribute('lang'),locale);
   assert.strictEqual(await phone.locator('h1').textContent(),copy[0]);assert.strictEqual(await phone.locator('[role=status]').textContent(),copy[1]);
   // A rejected sensitive action must retain the session and selected language.
   const revoke=phone.locator('form:has([name=action][value=revoke])');await revoke.locator('[name=current_password]').fill('Incorrect synthetic password');
   const denied=await Promise.all([phone.waitForNavigation({waitUntil:'networkidle'}),revoke.locator('button[type=submit]').click()]);
   assert.strictEqual(denied[0].status(),422);assert.strictEqual(await phone.locator('[role=alert]').textContent(),copy[2]);
   assert(!/account\.[a-z_]+/.test(await phone.locator('main').innerText()));assert(await phone.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Profile mobile overflow '+locale);
   await phone.goto(origin+'/user/notification-settings.php');assert.strictEqual(await phone.locator('h1').textContent(),noticeCopy[locale][0]);
   const preferences=phone.locator('main form'),inapp=locale!=='en'&&locale!=='pl',mail=locale==='fr'||locale==='it';
   await preferences.locator('input[type=checkbox][name=inapp_enabled]').setChecked(inapp);await preferences.locator('input[type=checkbox][name=mail_enabled]').setChecked(mail);
   await Promise.all([phone.waitForNavigation({waitUntil:'networkidle'}),preferences.locator('button[type=submit]').click()]);
   assert.strictEqual(await phone.locator('[role=status]').textContent(),noticeCopy[locale][1]);
   assert(await phone.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Notification preferences mobile overflow '+locale);
   await phone.reload();assert.strictEqual(await preferences.locator('input[type=checkbox][name=inapp_enabled]').isChecked(),inapp);assert.strictEqual(await preferences.locator('input[type=checkbox][name=mail_enabled]').isChecked(),mail);
   const csrf=await preferences.locator('[name=csrf_token]').inputValue();
   const invalidPreferences=await phone.request.post(origin+'/user/notification-settings.php',{form:{csrf_token:csrf,inapp_enabled:'invalid',mail_enabled:'1'}});
   assert.strictEqual(invalidPreferences.status(),422);assert((await invalidPreferences.text()).includes(noticeCopy[locale][3]));
   const missingCsrf=await phone.request.post(origin+'/user/notification-settings.php',{form:{inapp_enabled:'1',mail_enabled:'1'}});assert.strictEqual(missingCsrf.status(),403);
   await phone.reload();assert.strictEqual(await preferences.locator('input[type=checkbox][name=inapp_enabled]').isChecked(),inapp);assert.strictEqual(await preferences.locator('input[type=checkbox][name=mail_enabled]').isChecked(),mail);
   const invalidNotice=await phone.request.post(origin+'/user/notifications.php',{form:{csrf_token:csrf,id:'-1'}});assert.strictEqual(invalidNotice.status(),422);assert((await invalidNotice.text()).includes('lang="'+locale+'"'));
   await phone.goto(origin+'/user/notifications.php');assert.strictEqual(await phone.locator('h1').textContent(),noticeCopy[locale][2]);
   assert(!/account\.[a-z_]+/.test(await phone.locator('main').innerText()));assert(await phone.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Notifications mobile overflow '+locale);
  }journeys.push('profile_save_and_protected_action_eight_mobile_locales');
  journeys.push('notification_preferences_and_validation_eight_mobile_locales');
  await page.goto(origin+'/');for(const width of [1440,768,390]){await page.setViewportSize({width,height:1000});await page.goto(origin+'/',{waitUntil:'networkidle'});const size=await page.evaluate(()=>({inner:innerWidth,scroll:document.documentElement.scrollWidth}));assert(size.scroll<=size.inner,'Horizontal overflow at '+width);await page.evaluate(async()=>{document.documentElement.style.scrollBehavior='auto';for(let y=0;y<document.documentElement.scrollHeight;y+=500){scrollTo(0,y);await new Promise(r=>setTimeout(r,110));}scrollTo(0,0);});await page.waitForTimeout(350);await page.screenshot({path:__dirname+'/.runtime/browser-'+width+'.png',fullPage:true});}journeys.push('responsive');
  await page.locator('.theme-toggle').click();const theme=await page.locator('html').getAttribute('data-theme');await page.reload();assert.strictEqual(await page.locator('html').getAttribute('data-theme'),theme);journeys.push('theme');
  await desktop.clearCookies();await login(page,admin,true);journeys.push('admin_mfa');
  for(const locale of ['en','fr','nl','pl','cs','da','it','de']){
   await page.goto(origin+'/admin/setup.php');await page.locator('.market-switcher summary').click();const language=page.locator('form[action="/user/language.php"]');await language.locator('[name=locale]').selectOption(locale);
   await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),language.locator('button[type=submit]').click()]);
   assert.strictEqual(await page.locator('html').getAttribute('lang'),locale);assert.strictEqual(await page.locator('section[id^=setup-]').count(),18);
   assert(!/setup\.[a-z]+\./.test(await page.locator('main').innerText()),'Untranslated setup key');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Setup mobile overflow '+locale);
  }journeys.push('admin_setup_eight_mobile_locales');
  // Configure a synthetic required document through the real protected admin UI.
  const legalCode='browser-'+crypto.randomBytes(8).toString('hex');
  await page.goto(origin+'/admin/legal.php');const draft=page.locator('form:has([name=action][value=create])');
  await draft.locator('[name=code]').fill(legalCode);await draft.locator('[name=title]').fill('Synthetic browser contract');
  await draft.locator('[name=body]').fill('Synthetic browser document. No actual legal review or production approval. '.repeat(8));
  await Promise.all([page.waitForURL('**/admin/legal.php?id=*'),draft.locator('button[type=submit]').click()]);
  const approval=page.locator('form:has([name=action][value=approve])');await approval.locator('[name=reviewer]').fill('Synthetic isolated browser test only');
  await approval.locator('[name=effective_at]').fill(new Date().toISOString().slice(0,10));
  for(let n=1;n<=5;n++)await approval.locator('[name=check_'+n+']').check();
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),approval.locator('button[type=submit]').click()]);
  await page.goto(origin+'/admin/settings.php');const originalLegalCode=await page.locator('[name=legal_registration_documents]').inputValue();
  const originalPublication=await page.locator('input[type=checkbox][name=legal_publication_enabled]').isChecked();
  const registrationContext=await browser.newContext({viewport:{width:390,height:844}});
  try{
   await page.locator('[name=legal_registration_documents]').fill(legalCode);await page.locator('input[type=checkbox][name=legal_publication_enabled]').check();
   await page.getByRole('button',{name:'Änderungen speichern',exact:true}).click();await page.waitForLoadState('networkidle');
   const registration=await registrationContext.newPage();monitor(registration);await registration.goto(origin+'/auth/register.php');
   const legalCheckbox=registration.locator('[name=legal_confirm]');assert.strictEqual(await legalCheckbox.isChecked(),false);
   const documentLink=registration.locator('a[href*="version_id="]').first();assert((await documentLink.getAttribute('href')).includes(legalCode));
   const username='test_'+crypto.randomBytes(8).toString('hex');const password='Synthetic browser '+crypto.randomBytes(16).toString('hex');
   await registration.locator('[name=username]').fill(username);await registration.locator('[name=email]').fill(username+'@example.invalid');
   await registration.locator('[name=zip]').fill('10115');await registration.locator('[name=city]').fill('Synthetic city');
   await registration.locator('[name=password]').fill(password);await registration.locator('[name=password_confirm]').fill(password);
   assert.strictEqual(await legalCheckbox.evaluate(node=>node.checkValidity()),false);
   assert(await registration.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Registration consent mobile overflow');
   await legalCheckbox.check();await Promise.all([registration.waitForURL('**/user/dashboard.php'),registration.getByRole('button',{name:'Konto erstellen',exact:true}).click()]);
   journeys.push('version_bound_registration');
  }finally{
   await registrationContext.close();await page.goto(origin+'/admin/settings.php');await page.locator('[name=legal_registration_documents]').fill(originalLegalCode);
   await page.locator('input[type=checkbox][name=legal_publication_enabled]').setChecked(originalPublication);
   await page.getByRole('button',{name:'Änderungen speichern',exact:true}).click();await page.waitForLoadState('networkidle');
  }
  await page.goto(origin+'/admin/settings.php');const previous=await page.locator('[name=brand_name]').inputValue();
  const name='Testmarkt '+crypto.randomBytes(4).toString('hex');await page.locator('[name=brand_name]').fill(name);await page.getByRole('button',{name:'Änderungen speichern',exact:true}).click();await page.waitForLoadState('networkidle');await phone.goto(origin+'/');assert((await phone.locator('title').textContent()).includes(name));
  await page.goto(origin+'/admin/settings.php');await page.locator('[name=brand_name]').fill(previous);await page.getByRole('button',{name:'Änderungen speichern',exact:true}).click();await page.waitForLoadState('networkidle');await page.goto(origin+'/admin/categories.php');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Admin table must scroll inside its panel');await page.screenshot({path:__dirname+'/.runtime/admin-categories.png',fullPage:true});journeys.push('admin_brand_categories');
  await phone.goto(origin+'/user/billing.php');assert((await phone.locator('main').innerText()).includes('noch nicht aktiviert'));journeys.push('manual_payment_gate');
  const reduced=await browser.newContext({reducedMotion:'reduce',viewport:{width:390,height:844}});const calm=await reduced.newPage();monitor(calm);await calm.goto(origin+'/');assert(await calm.evaluate(()=>matchMedia('(prefers-reduced-motion: reduce)').matches));await reduced.close();journeys.push('reduced_motion');
  assert.deepStrictEqual(errors,[]);assert.deepStrictEqual(failures,[]);assert.deepStrictEqual(external,[]);console.log(JSON.stringify({result:'PASS',journeys,js_errors:0,server_errors:0,external_requests:0}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});

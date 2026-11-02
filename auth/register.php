<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
if (site_setting($pdo,'registration_enabled','1') !== '1') { abort_request(503,'Neue Registrierungen sind vorübergehend deaktiviert.'); }
if (current_user($pdo)) { redirect('/user/dashboard.php'); }
$error = ''; $values = ['username' => '', 'email' => '', 'zip' => '', 'city' => '', 'country_code'=>'DE','preferred_locale'=>'de'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); auth_rate_limit('register-ip', auth_ip(), 12, 3600);
    try {
        if ($_FILES !== []) { throw new InvalidArgumentException('Bei der Registrierung werden keine Dokumente angenommen.'); }
        foreach ($values as $key => $default) { $values[$key] = in_array($key,['country_code','preferred_locale'],true) && !array_key_exists($key,$_POST) ? $default : auth_input($_POST,$key); }
        $password = auth_input($_POST, 'password', false); $confirmation = auth_input($_POST, 'password_confirm', false);
        if (!preg_match('/\A[A-Za-z0-9_\-]{3,50}\z/', $values['username'])) { throw new InvalidArgumentException('Der Benutzername benötigt 3–50 Buchstaben, Zahlen, Unterstriche oder Bindestriche.'); }
        if (strlen($values['email']) > 190 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
        $values['country_code'] = function_exists('geo_country') ? geo_country($values['country_code']) : 'DE';
        $postalValid = function_exists('geo_validate_postcode') ? geo_validate_postcode($values['country_code'],$values['zip']) : (bool)preg_match('/\A[0-9]{5}\z/',$values['zip']);
        if (!$postalValid || $values['city'] === '' || strlen($values['city']) > 100) throw new InvalidArgumentException('Bitte eine zum Land passende Postleitzahl und einen Ort eingeben.');
        if (function_exists('geo_normalize_postcode')) $values['zip']=geo_normalize_postcode($values['country_code'],$values['zip']);
        $locales=function_exists('i18n_locales')?i18n_locales():['de'=>'Deutsch'];
        if (!isset($locales[$values['preferred_locale']])) throw new InvalidArgumentException('Bitte eine unterstützte Sprache auswählen.');
        if (!hash_equals($password, $confirmation)) { throw new InvalidArgumentException('Die Passwörter stimmen nicht überein.'); }
        $hash = auth_hash($password);
        $pdo->beginTransaction();
        $contractDocuments=consent_check($pdo,'registration',$_POST,i18n_locale());
        $query = $pdo->prepare('INSERT INTO users (username,email,password_hash,zip,city,country_code,preferred_locale) VALUES (?,?,?,?,?,?,?)');
        $query->execute([$values['username'], $values['email'], $hash, $values['zip'], $values['city'],$values['country_code'],$values['preferred_locale']]);
        $query = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $query->execute([(int)$pdo->lastInsertId()]);
        $created=$query->fetch();
        consent_record($pdo,(int)$created['id'],'registration',null,$contractDocuments);
        account_token_request($pdo,$created['email'],'verify');
        $pdo->commit();auth_issue_session($created);$_SESSION['locale']=$created['preferred_locale'];redirect('/user/dashboard.php');
    } catch (InvalidArgumentException|DomainException $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $exception->getMessage(); http_response_code(422); }
    catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception->getCode() !== '23000') { throw $exception; }
        $error = 'Mit diesen Angaben kann kein Konto erstellt werden. Bitte andere Angaben wählen oder anmelden.'; http_response_code(422);
    } catch (Throwable) {if ($pdo->inTransaction()) $pdo->rollBack();$error='Das Konto konnte gerade nicht angelegt werden. Bitte später erneut versuchen.';http_response_code(503);}
}
$contractReady=true;
try{$contractDocuments=consent_documents($pdo,'registration',i18n_locale());}catch(InvalidArgumentException|DomainException $e){$contractReady=false;$error=$e->getMessage();http_response_code(503);}
$pageTitle = 'Registrieren'; require __DIR__ . '/../includes/header.php';
?>
<main class="auth-shell container"><section class="panel"><p class="eyebrow">Dabei sein</p><h1>Ein Konto. Neue Möglichkeiten.</h1>
<p>Für die Registrierung benötigen wir keine Identitätsdokumente.</p>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if($contractReady):?><form method="post" class="form-grid"><?= csrf_field() ?>
<label class="field"><?=h(t('Benutzername'))?><input name="username" value="<?= h($values['username']) ?>" autocomplete="username" minlength="3" maxlength="50" required></label>
<label class="field"><?=h(t('E-Mail'))?><input type="email" name="email" value="<?= h($values['email']) ?>" autocomplete="email" maxlength="190" required></label>
<label class="field"><?=h(t('Land'))?><select name="country_code"><?php foreach((function_exists('geo_countries')?geo_countries():['DE'=>'Deutschland']) as $code=>$label):?><option value="<?=h($code)?>" <?= $values['country_code']===$code?'selected':'' ?>><?=h($label)?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Sprache'))?><select name="preferred_locale"><?php foreach((function_exists('i18n_locales')?i18n_locales():['de'=>'Deutsch']) as $code=>$label):?><option value="<?=h($code)?>" <?= $values['preferred_locale']===$code?'selected':'' ?>><?=h(is_array($label)?($label['label']??$code):$label)?></option><?php endforeach;?></select></label>
<label class="field"><?=h(t('Postleitzahl'))?><input name="zip" value="<?= h($values['zip']) ?>" autocomplete="postal-code" inputmode="text" maxlength="12" required></label>
<label class="field"><?=h(t('Ort'))?><input name="city" value="<?= h($values['city']) ?>" autocomplete="address-level2" maxlength="100" required></label>
<label class="field"><?=h(t('Passwort'))?><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="1024" required><small>Mindestens zwölf Zeichen. Ein langer, eigener Passwortsatz ist geeignet.</small></label>
<label class="field"><?=h(t('Passwort wiederholen'))?><input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="1024" required></label>
<?php consent_form($contractDocuments,i18n_locale());?><button class="button" type="submit"><?=h(t('Konto erstellen'))?></button></form><?php endif;?><p>Bereits registriert? <a href="/auth/login.php"><?=h(t('Anmelden'))?></a></p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

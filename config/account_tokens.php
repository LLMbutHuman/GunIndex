<?php
declare(strict_types=1);

function account_email_hash(string $email): string { return hash('sha256', strtolower($email)); }

/** Canonical security templates, selected by the recipient rather than sender. */
function account_mail_text(string $locale, string $purpose): array
{
    $templates = [
        'de'=>[
            'verify'=>['E-Mail-Adresse bestätigen','Bitte bestätige deine E-Mail-Adresse über diesen Link. Der Link ist 24 Stunden gültig.'],
            'reset'=>['Passwort zurücksetzen','Du hast das Zurücksetzen deines Passworts angefordert. Der Link ist 30 Minuten gültig.'],
            'security'=>['Sicherheitsänderung an deinem Konto','Zugangsdaten oder Sitzungen deines Kontos wurden geändert. Falls du das nicht warst, kontaktiere umgehend den Betreiber.'],
            'footer'=>'Falls du diese Anfrage nicht gestellt hast, ignoriere diese Nachricht. Gib den Link oder Code niemals an andere weiter.'
        ],
        'en'=>[
            'verify'=>['Confirm your email address','Please confirm your email address using this link. It expires after 24 hours.'],
            'reset'=>['Reset your password','You requested a password reset. This link expires after 30 minutes.'],
            'security'=>['Security change to your account','Your account credentials or sessions were changed. If this was not you, contact the operator immediately.'],
            'footer'=>'If you did not request this, ignore this message. Never share this link or code with anyone.'
        ],
        'fr'=>[
            'verify'=>['Confirmez votre adresse e-mail','Veuillez confirmer votre adresse e-mail avec ce lien. Il expire après 24 heures.'],
            'reset'=>['Réinitialisez votre mot de passe','Vous avez demandé une réinitialisation du mot de passe. Ce lien expire après 30 minutes.'],
            'security'=>['Modification de sécurité de votre compte','Les identifiants ou les sessions de votre compte ont été modifiés. Si vous ne l’avez pas fait, contactez immédiatement l’exploitant.'],
            'footer'=>'Si vous n’avez pas fait cette demande, ignorez ce message. Ne partagez jamais ce lien ou ce code.'
        ],
        'nl'=>[
            'verify'=>['Bevestig je e-mailadres','Bevestig je e-mailadres via deze link. De link verloopt na 24 uur.'],
            'reset'=>['Stel je wachtwoord opnieuw in','Je hebt een nieuw wachtwoord aangevraagd. Deze link verloopt na 30 minuten.'],
            'security'=>['Beveiligingswijziging in je account','De inloggegevens of sessies van je account zijn gewijzigd. Neem direct contact op met de beheerder als jij dit niet was.'],
            'footer'=>'Negeer dit bericht als je dit niet hebt aangevraagd. Deel deze link of code nooit met anderen.'
        ],
        'pl'=>[
            'verify'=>['Potwierdź adres e-mail','Potwierdź adres e-mail za pomocą tego linku. Link wygasa po 24 godzinach.'],
            'reset'=>['Zresetuj hasło','Poproszono o zresetowanie hasła. Ten link wygasa po 30 minutach.'],
            'security'=>['Zmiana zabezpieczeń konta','Zmieniono dane logowania lub sesje Twojego konta. Jeśli to nie Ty, niezwłocznie skontaktuj się z operatorem.'],
            'footer'=>'Jeśli nie wysłano takiej prośby, zignoruj tę wiadomość. Nigdy nie udostępniaj tego linku ani kodu innym osobom.'
        ],
        'cs'=>[
            'verify'=>['Potvrďte e-mailovou adresu','Potvrďte svou e-mailovou adresu pomocí tohoto odkazu. Odkaz vyprší za 24 hodin.'],
            'reset'=>['Obnovte své heslo','Požádali jste o obnovení hesla. Tento odkaz vyprší za 30 minut.'],
            'security'=>['Bezpečnostní změna vašeho účtu','Přihlašovací údaje nebo relace vašeho účtu byly změněny. Pokud jste to nebyli vy, ihned kontaktujte provozovatele.'],
            'footer'=>'Pokud jste o to nepožádali, tuto zprávu ignorujte. Tento odkaz ani kód nikdy nikomu nesdělujte.'
        ],
        'da'=>[
            'verify'=>['Bekræft din e-mailadresse','Bekræft din e-mailadresse via dette link. Linket udløber efter 24 timer.'],
            'reset'=>['Nulstil din adgangskode','Du har anmodet om at nulstille din adgangskode. Dette link udløber efter 30 minutter.'],
            'security'=>['Sikkerhedsændring på din konto','Dine loginoplysninger eller sessioner er blevet ændret. Kontakt straks operatøren, hvis det ikke var dig.'],
            'footer'=>'Ignorer beskeden, hvis du ikke har anmodet om dette. Del aldrig dette link eller denne kode med andre.'
        ],
        'it'=>[
            'verify'=>['Conferma il tuo indirizzo e-mail','Conferma il tuo indirizzo e-mail tramite questo link. Il link scade dopo 24 ore.'],
            'reset'=>['Reimposta la tua password','Hai richiesto di reimpostare la password. Questo link scade dopo 30 minuti.'],
            'security'=>['Modifica di sicurezza al tuo account','Le credenziali o le sessioni del tuo account sono state modificate. Se non sei stato tu, contatta subito il gestore.'],
            'footer'=>'Se non hai richiesto questa operazione, ignora il messaggio. Non condividere mai questo link o codice con altre persone.'
        ]
    ];
    $selected=$templates[$locale]??$templates['de'];
    if (!isset($selected[$purpose]) || !is_array($selected[$purpose])) throw new InvalidArgumentException('Unknown account message purpose.');
    return ['subject'=>$selected[$purpose][0],'body'=>$selected[$purpose][1],'footer'=>$selected['footer']];
}

/** Same public response for unknown, inactive, verified and existing addresses. */
function account_token_request(PDO $pdo, string $email, string $purpose): void
{
    if (!in_array($purpose,['verify','reset'],true) || !mail_address_valid($email)) return;
    try { $origin = mail_origin(); } catch (RuntimeException) { return; }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1 FOR UPDATE'); $query->execute([$email]); $user = $query->fetch();
        if (!$user || ($purpose === 'verify' && $user['email_verified_at'] !== null)) { if ($ownsTransaction) $pdo->commit(); return; }
        // Discard stale requests before minting a replacement; no raw token persists.
        $pdo->prepare('UPDATE market_auth_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose=? AND used_at IS NULL')->execute([$user['id'],$purpose]);
        $pdo->prepare("UPDATE market_mail_queue SET status='expired',payload_ciphertext='' WHERE user_id=? AND dedupe_key LIKE ? AND status IN ('pending','failed')")
            ->execute([$user['id'],'account:'.$purpose.':%']);
        $token = bin2hex(random_bytes(32)); $hash = hash('sha256',$token); $seconds = $purpose === 'verify' ? 86400 : 1800;
        $query = $pdo->prepare('INSERT INTO market_auth_tokens(user_id,purpose,token_hash,email_hash,auth_version,expires_at) VALUES (?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))');
        $query->execute([$user['id'],$purpose,$hash,account_email_hash($user['email']),(int)$user['auth_version'],$seconds]);
        $url = $origin . '/auth/' . ($purpose === 'verify' ? 'verify-email' : 'reset-password') . '.php?token=' . $token;
        $copy=account_mail_text((string)($user['preferred_locale']??'de'),$purpose);
        $body=$copy['body']."\n\n".$url."\n\n".$copy['footer'];
        mail_enqueue($pdo, (int)$user['id'], $user['email'], $copy['subject'], $body, 'account:'.$purpose.':'.$hash);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $error) { if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function account_token_consume(PDO $pdo, string $token, string $purpose, ?string $newPassword = null): bool
{
    if (!preg_match('/\A[a-f0-9]{64}\z/',$token) || !in_array($purpose,['verify','reset'],true)) return false;
    $newHash = null;
    if ($purpose === 'reset') {
        if ($newPassword === null) throw new InvalidArgumentException('Ein neues Passwort ist erforderlich.');
        $newHash = auth_hash($newPassword);
    }
    $hash = hash('sha256',$token);
    $query = $pdo->prepare('SELECT user_id FROM market_auth_tokens WHERE token_hash=? AND purpose=?'); $query->execute([$hash,$purpose]); $id = $query->fetchColumn();
    if (!$id) return false;
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE'); $query->execute([$id]); $user = $query->fetch();
        $query = $pdo->prepare('SELECT * FROM market_auth_tokens WHERE token_hash=? AND purpose=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE'); $query->execute([$hash,$purpose]); $record = $query->fetch();
        if (!$user || !$record || (int)$user['is_active'] !== 1 || (int)$user['auth_version'] !== (int)$record['auth_version']
            || !hash_equals($record['email_hash'],account_email_hash($user['email']))) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE market_auth_tokens SET used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$record['id']]);
        $pdo->prepare("UPDATE market_mail_queue SET status='expired',payload_ciphertext='' WHERE dedupe_key=? AND status IN ('pending','failed')")
            ->execute(['account:'.$purpose.':'.$hash]);
        if ($purpose === 'verify') {
            $pdo->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at,UTC_TIMESTAMP()) WHERE id=?')->execute([$id]);
            audit_log($pdo,(int)$id,'account.email_verified',(int)$id);
        } else {
            $pdo->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([$newHash,$id]);
            $pdo->prepare('UPDATE market_auth_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare("UPDATE market_mail_queue SET status='expired',payload_ciphertext='' WHERE user_id=? AND dedupe_key LIKE 'account:%' AND status IN ('pending','failed')")->execute([$id]);
            audit_log($pdo,(int)$id,'account.password_reset',(int)$id);
            $copy=account_mail_text((string)($user['preferred_locale']??'de'),'security');
            mail_enqueue($pdo,(int)$id,$user['email'],$copy['subject'],$copy['body'],'password-reset:'.$record['id']);
        }
        $pdo->commit(); return true;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

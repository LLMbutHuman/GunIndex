# E-Mail-Verifikation, Passwortreset und Benachrichtigungen

## Kontoabläufe

Die Registrierung legt ein unbestätigtes Konto an und bereitet bei gültiger
Versandkonfiguration eine Bestätigung vor. Der Nachweis steht ausschließlich in
`users.email_verified_at`: Er bestätigt den Zugriff auf diese E-Mail-Adresse,
keine Identität, kein Alter und keine rechtliche Erwerbsberechtigung. Die
Identitätskennzeichnung `is_verified` wird dadurch nicht gesetzt.

Bestätigungslinks gelten 24 Stunden, Wiederherstellungslinks 30 Minuten. Zufällige
32-Byte-Tokens erscheinen nur im Link und im verschlüsselten Mailpayload; die
Token-Tabelle enthält SHA-256-Hashes. Zweck, aktuelle E-Mail-Adresse und
`auth_version` sind gebunden. Links sind einmalig, neue Anfragen ersetzen alte,
und Änderungen an Zugangsdaten widerrufen ausstehende Fähigkeiten. Das Öffnen
eines Links per GET verändert nichts; die Bestätigung erfordert POST und CSRF.

Unter `/auth/forgot-password.php` ist die öffentliche Antwort unabhängig vom
Bestehen eines Kontos. IP-/Adresslimits und eine zeitlich angeglichene Antwort
erschweren Enumeration und Missbrauch. Ein erfolgreicher Reset ändert den Hash,
erhöht die Sessionversion und widerruft bestehende Sitzungen; er meldet niemanden
automatisch an und entfernt insbesondere keinen Admin-Zweitfaktor.

E-Mail-/Passwortänderungen im Profil verlangen weiterhin das aktuelle Passwort.
Sie erzeugen eine Sicherheitsnachricht an die bisherige Adresse. Eine neue
Adresse verliert ihren alten Bestätigungsstatus und erhält einen eigenen Link.
Diese Schritte und der Token-/Queuezustand werden transaktional gespeichert.

Registrierung und Profil unterstützen Länder und bevorzugte Sprache über die
gemeinsamen Geo-/I18N-Validatoren. Eine gewählte Marktauswahl ist von der
Wohnsitzadresse getrennt. Die Staff-MFA-Erkennung berücksichtigt neue privilegierte
Rollen; eine Passwortwiederherstellung umgeht deren zweiten Faktor nicht.

## Lokaler Versand

Benötigte Prozesskonfiguration, ohne Geheimniswerte:

- `APP_URL=https://eigene-domain.example`: eigener Ursprung ohne Pfad, Query,
  Zugangsdaten oder Fragment. Öffentliches HTTP wird abgewiesen. Loopback-HTTP
  ist ausschließlich für die isolierte `_test`-Datenbank im Entwicklungsbetrieb
  erlaubt.
- `MAIL_TRANSPORT=none`: sicherer Standard ohne Zustellung.
- `MAIL_TRANSPORT=sendmail`: lokales kompatibles MTA-Programm, standardmäßig
  `/usr/sbin/sendmail`; ein eigener Pfad ist mit `MAIL_SENDMAIL_PATH` möglich.
- `MAIL_FROM`: geprüfte Absenderadresse der eigenen Domain.
- `APP_KEY`: bestehender sicherer 32-Byte-Anwendungsschlüssel als Base64. Maildaten
  erhalten einen getrennten, per Standard-HKDF abgeleiteten Zweckschlüssel und
  libsodium-AEAD-Verschlüsselung. `APP_KEY_PREVIOUS` unterstützt kontrollierte
  Entschlüsselung während einer Rotation. Schlüssel nicht in Logs oder Git geben.
- `MAIL_QUEUE_RETENTION_DAYS`: technischer Restaufbewahrungszeitraum 1–30 Tage,
  Standard sieben; keine steuerliche Aufbewahrungsregel für Rechnungen.

Der Worker wird unabhängig von Webaufrufen ausgeführt:

```sh
php ops/mail-worker.php --once
```

Ein lokales Postfix-/Sendmail-/msmtp-kompatibles System übernimmt SMTP und dessen
TLS-Konfiguration. Die Anwendung startet es mit einem Argumentarray, ohne Shell,
und begrenzt Laufzeit und Eingabe. Keine fremde Mail-API ist erforderlich.
SPF, DKIM, DMARC, PTR, öffentliche Zustellbarkeit, Relaygrenzen und ein tatsächlicher
Test an eine eigene Adresse bleiben Aufgaben der konkreten Servereinrichtung.
Ein lokal erfolgreiches MTA-Handoff beweist keinen Eingang im Empfängerpostfach.

Die Datenbankqueue speichert Adresse, Betreff und Text gemeinsam verschlüsselt.
Eindeutige Dedupeschlüssel verhindern doppelte lokale Aufträge. Worker verwenden
Zeilensperren mit `SKIP LOCKED`, zweiminütige Leases, maximal acht Versuche und
exponentielle Pausen. Fehlende Versandkonfiguration verbraucht kein Retrybudget.
Ausgaben enthalten ausschließlich Zähler oder technische Fehlercodes.

Nach erfolgreichem Handoff wird der sensible Payload sofort gelöscht; Status
und Dedupeschlüssel bleiben erhalten. Ersetzte/verbrauchte/abgelaufene
Bestätigungs- und Resetmails werden nicht später als noch gültig zugestellt.
Alte ausstehende oder fehlgeschlagene Payloads werden am konfigurierten technischen
Limit redigiert; abgelaufene Tokenhashes werden bereinigt.

SMTP ist kein atomarer Bestandteil einer SQL-Transaktion. Ein Prozessabsturz nach
MTA-Annahme, aber vor Speicherung des Zustellstatus kann eine Wiederholung
erzeugen. Eine stabile Message-ID hilft bei Empfängerdeduplizierung; es wird
keine garantierte Exactly-once-Zustellung außerhalb der Anwendung behauptet.

## Interne Hinweise und Benutzerpräferenzen

`notify_user()` kann innerhalb einer bestehenden Geschäfts-Transaktion aufgerufen
werden. Ein eindeutiger Schlüssel pro Benutzer verhindert doppelte Hinweise.
In-App ist standardmäßig an, zusätzliche E-Mails sind standardmäßig aus. Eine
optionale Mail erfordert sowohl Ereigniswunsch als auch Nutzer-Opt-in und eine
bestätigte Adresse. Notwendige Sicherheitsmails und ausdrücklich angeforderte
Kontolinks sind davon unabhängig.

`/user/notifications.php` ist nach Benutzer abgegrenzt und paginiert.
Gelesen-Markierungen erfordern POST/CSRF. Präferenzen werden unter
`/user/notification-settings.php` gespeichert. Links bleiben auf relative eigene
Routen beschränkt; Inhalte werden escaped. Private Chattexte gehören nicht in
Hinweismails, lediglich ein allgemeiner Hinweis und ein geschützter Link.

## Tests und Grenzen

`tests/accounts_mail_test.php` verweigert HTTP und andere Datenbanken als
`gunindex_rebuild_test`. Es erzeugt ausschließlich synthetische Konten und prüft
Tokenzwecke, Ablauf, Wiederholung, Adresse/Version, Sessionwiderruf, Admin-MFA,
Transaktionsrollback, verschlüsselte Queue, Header-/URL-Validierung, Opt-in,
Deduplizierung, lokale MTA-Argumente/Fehler und Retry-/Redigierungszustände.

`MAIL_TRANSPORT=test` bestätigt ausschließlich das lokale Queueprotokoll und
ist auf `_test` plus `APP_ENV=development` beschränkt. Es versendet keine Mail.
Ein temporärer lokaler MTA-Stub prüft die Programmschnittstelle ohne Netzwerk.
Ein echter Postfix-/SMTP-/Empfängerpostfachtest ist eine separate Betriebsabnahme.
Passwort- und Tokenwerte dürfen nicht im Testprotokoll ausgegeben werden.

Bestätigungs-, Reset- und Sicherheitsmails besitzen lokale Vorlagen für Deutsch,
Englisch, Französisch, Niederländisch, Polnisch, Tschechisch, Dänisch und Italienisch.
Die Empfängerpräferenz bestimmt ihre Sprache. Feldlabels nutzen die gemeinsamen
Locale-Kataloge; noch nicht übersetzte erklärende Texte fallen auf Deutsch zurück.
Eine vollständige sprachliche Abnahme aller Kontooberflächen bleibt gesondert
zu prüfen.

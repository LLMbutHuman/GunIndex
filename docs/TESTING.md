# Tatsächlicher Testnachweis

## Auktionsgebühren, 2026-11-03: lokal bestanden, Linux-Ausführung ausstehend

`auction_billing_test.php`: 16 Testgruppen, einschließlich 25 unabhängiger
Cent-Erwartungswerte, Tarifgrenzen, Rundung, Deckel und expliziter Preis-/Dokument-
zustimmung. Dazu 14 bestehende Consent-Fälle und sieben Geo-/Sprachfälle bestanden;
betroffene PHP-Dateien und `assets/js/app.js` syntaxgeprüft.

Neue sequenzielle MySQL-/HTTP-Suite `auction_billing_integration_test.php` im
Linux-Runner registriert, einschließlich echter konkurrierender Sofortkäufe,
Nullgebühren, Preisänderungen, Rollbacks, Zahlung, Erlass, Erstattungen und
Berechtigung/CSRF. Bis zum CI-Ergebnis ist dies Testumfang, kein Erfolgsnachweis.
Keine Providerzahlung und keine lokale Windows-Ausführung des Linux-Runners.

`auction_billing_browser.js` ergänzt echte Chromium-Journeys für alle acht Sprachen
mit eigener synthetischer CLI-Fixture. Verändert Testkonfiguration nur bei gestopptem
Worker und stellt vorherige Werte/Steuer/Länderstatus im `finally` wieder her.
PHP-/Node-Syntax bestanden; Ausführung erst im folgenden CI-Lauf.

## Abschließender Nachweis dieser Sitzung, 2026-11-02

Code `2042549d5cec2bc39f713ebc18a76f8ab30fc3af`,
[CI 41527131692](https://github.com/LLMbutHuman/GunIndex/actions/runs/41527131692):
Windows- und Linuxjob vollständig erfolgreich, einschließlich PHP/MySQL,
Installer-/Backupregression, Composer-Audit, Browser/Internationalisierung und
automatisierter öffentlicher Accessibility. Sieben Sprachlink-Unitfälle,
28 Konto-/Mailfälle, 21 Finanzfälle sowie neue achtsprachige Profil-/Recovery-/
Benachrichtigungs-/Dashboard-/Footerfälle bestanden. Browser meldet keine
JavaScript-/Serverfehler und keine externen Requests.

Auch die direkten Vorgänger `505ffa1` (CI 41525630270) und `ad5ed5f`
(CI 41526420094) haben erfolgreiche eigene Läufe. Keine bekannten roten
Anwendungsprüfungen dieser drei Etappen. Lokaler Python-Store-Alias und WSL-
Speicherblocker bleiben Umgebungsprobleme. Frühere Ausstehend-Vermerke unten
beschreiben Zwischenstände. Kein Test belegt E2EE, Treuhand oder reale Provider-
abnahmen; diese Funktionen/Nachweise sind weiterhin offen. Gesamtbewertung:
[PROJECT_STATUS.md](PROJECT_STATUS.md).

## Sprachwahl per E-Mail, Benachrichtigungen und Footer: lokal geprüft

Sieben neue `account_link_locale_test.php`-Fälle erfolgreich; im Linux-Runner
registriert. Drei zusätzliche DB-/HTTP-Fälle in `accounts_mail_test.php` prüfen
echte generierte Links in frischen Sessions, bereits gewählte Sprachen und
alte/manipulierte Parameter. Ihre Ausführung steht vor dem folgenden CI-Lauf aus.
PHP-Syntax aller 25 neuen/geänderten PHP-Dateien und sieben Geo-/Sprachfälle
erfolgreich. Node-Syntax beider Browserregressionen bestanden. Python-Runnersyntax
lokal nicht ausführbar (Windows-Store-Alias ohne installierten Interpreter).

Browserprüfungen ergänzt: Präferenzspeicherung und Persistenz, CSRF-Ablehnung,
ungültige Formularwerte/Benachrichtigungs-IDs, übersetzter Footer und mobile Breite
in acht Sprachen. Noch nicht ausgeführt. Vorherige Kontoetappe `505ffa1` ist gepusht,
[CI 41525630270](https://github.com/LLMbutHuman/GunIndex/actions/runs/41525630270) noch aktiv.

## Kontoübersetzungen: aktueller Stand, 2026-11-02

Letzter vollständiger CI-Nachweis: Registrierung `35b99fe89c07ba88b499edcf05abe0958b488ab8`,
[CI 41484172147](https://github.com/LLMbutHuman/GunIndex/actions/runs/41484172147),
beide Jobs erfolgreich. Auch Login/MFA `b5677bd` hat einen eigenen erfolgreichen Lauf.

Neue lokale Ergänzung: Wiederherstellung, Verifikation und Profil in acht Sprachen,
82 Konto-Schlüssel pro Sprache. Sieben Geo-/Sprach- und 17 Auth-Prüfungen mit Sodium
bestanden; PHP-Syntax und Node-Syntax beider geänderter Browserdateien erfolgreich.
Die neuen mobilen Profil-/Reset-/Verifikationsabläufe sind noch nicht ausgeführt.
Nachfolgende ältere Ausstehend-Vermerke dokumentieren jeweils Zwischenstände.

## Wiederherstellung/Verifikation: lokale Prüfung

63 Konto-Schlüssel je Sprache; globale Schlüssel-/Platzhalterparität bestanden.
Vier geänderte PHP-Dateien ohne Syntaxfehler, Browserregression syntaktisch gültig.
Neue mobile Tests prüfen Anfrageantwort sowie ungültige Reset-/Verifikationslinks
in acht Sprachen. Deren CI-Ausführung steht noch aus.
Login-/MFA-Commit `b5677bd`, CI `41483417303`: vollständig erfolgreich, auch die
acht neuen mobilen Login-/Fehler-/CSRF-Fälle. Registrierung separat in CI.

## Registrierungsübersetzungen: lokal geprüft

42 Konto-Schlüssel in allen acht Sprachen vorhanden; globale Sprach-/Platzhalter-
parität bestanden. Geänderte Registrierung besteht PHP-Syntax, erweiterte Browser-
regression Node-Syntax. Acht neue mobile Registrierungs-/Validierungsabläufe
ergänzt. Ausführung bis zum folgenden CI-Lauf ausstehend.

## Kontozugang in acht Sprachen – lokale Prüfung

17 Auth-Prüfungen mit Sodium, sieben Geo-/Sprachprüfungen, 14 Consent-Prüfungen
und 187 PHP-Syntaxprüfungen bestanden. Node-Syntaxprüfung erfolgreich. Neue
internationale Browserfälle für Login, Fehler, CSRF und mobile Breite sind
implementiert; deren tatsächliche CI-Ausführung steht noch aus.

## Letzter vollständiger Code-/CI-Nachweis

`0194d59cac1bad2cd42ab67d409f2ceccdf5d7be`, [CI 41481388425](https://github.com/LLMbutHuman/GunIndex/actions/runs/41481388425):
beide Jobs erfolgreich. Zusätzlich besteht der echte Capture-/Webhook-Paralleltest
mit getrennten PHP-Prozessen, genau einer Rechnung und genau einer Cash-Buchung.
Damit sind alle sechs neuen Finanzfälle, die übrigen PHP-/MySQL-/Vertragsfälle,
Browser-/internationalen-/Accessibilityprüfungen und Composer-Audit erfolgreich.
Alle Providerantworten dabei synthetisch; keine tatsächliche Providerabnahme.
Ältere Ausstehend-Vermerke unten beziehen sich auf die jeweiligen Zwischenstände.

## Finanzabschluss: vollständige CI bestätigt

`699c35beb69fc29356f44cc22dc7fdd4c475fb6a`, [CI 41480002310](https://github.com/LLMbutHuman/GunIndex/actions/runs/41480002310):
Windows- und Linux-Job erfolgreich, einschließlich fünf neuer Adapter-/Datenbank-
Finanzfälle sowie bestehender Browser-/Accessibilityprüfungen. Die nachfolgende
zusätzliche echte Parallelprüfung gehört zum nächsten Commit und ist davon noch
nicht erfasst. 20 lokale Zahlungs-Unitfälle und sieben Geo-/Sprachfälle bestehen
auch nach Übersetzung der neuen Snapshot-Fehlermeldung in alle acht Sprachen.

## Ergänzung: echte Parallelverarbeitung

Zusätzlich zu den fünf neuen Finanzfällen ist ein sechster Test implementiert:
getrennte PHP-Prozesse verarbeiten denselben synthetischen Capture gleichzeitig
über Capture- und Webhook-Einstieg. Erwartet werden dieselbe Dokument-ID und
genau eine Cash-Buchung. Native PHP-Syntaxprüfung bestanden; Linux-/MySQL-
Ausführung dieses zusätzlichen Falls bis zum folgenden CI-Lauf noch ausstehend.

## Vertragsbindung: vollständige CI bestätigt

`dc046aed7cb3232386c186ced3ff15c95d7c0713`, [CI 41478575081](https://github.com/LLMbutHuman/GunIndex/actions/runs/41478575081):
beide Jobs erfolgreich. Migration 011, elf neue Vertrags-DB-/HTTP-Fälle,
14 Consent-Unitfälle und neuer mobiler Registrierungsablauf durch die tatsächliche
Admin-Oberfläche bestanden, ebenso bestehende Browser-/Accessibilityregressionen.

## Automatischer Finanzabschluss: lokale Prüfung

20 Zahlungs-Unitfälle bestanden einschließlich neuer Snapshot-Identitäts-/Tarif-/
Summenprüfung. Fünf zusätzliche Fälle in finance_test.php prüfen Capture/Webhook-
Wiederholungen, Sandbox-Abgrenzung, ungültige Signatur/Beträge, beschädigte Snapshots
und nachträgliche Betragsänderung. Ausschließlich synthetische injizierte REST-
Antworten, kein Netzwerk/kein echtes Geld. DB-Ausführung bis zum nächsten CI-Lauf
ausstehend; keine reale Providerabnahme behauptet.

## Vertragsbindung, 2026-11-02: lokal geprüft, CI noch ausstehend

14 Consent-Unitfälle, sieben Admin- und sieben Geo-Prüfungen (einschließlich
globaler Sprachparität) bestanden. 179 Projekt-PHP-Dateien bestanden Syntax.
Der Browser-Smoke-Test besteht die Node-Syntaxprüfung. Neue synthetische
DB-/HTTP-Prüfungen testen fehlende/veraltete/manipulierte Zustimmung, getrennte
Auftragsbelege, Idempotenz und exakte historische Downloads. Ein neuer Browserfall
konfiguriert ein synthetisches Dokument über die Admin-Oberfläche und registriert
ein Konto mit ausdrücklicher Versionszustimmung bei mobiler Breite. Diese neuen
Integrations-/Browserprüfungen sind vor dem nächsten CI-Lauf noch nicht ausgeführt.

## Persönliche Listen: CI bestätigt

Commit `126d4008df06f95be2892a280c33cebd776c6588`, [CI 41420258463](https://github.com/LLMbutHuman/GunIndex/actions/runs/41420258463):
beide Jobs erfolgreich. Die nachfolgend ursprünglich als ausstehend beschriebenen
vier MySQL-Prüfungen und Browserabläufe wurden inzwischen erfolgreich ausgeführt.
Dies ist keine Provider-, Rechts- oder vollständige Produktionsabnahme.

## Persönliche Listen, lokale Prüfung am 2026-11-02

15 datenbankfreie Prüfungen in `personal_test.php` bestanden (Opt-in, Limits,
Deduplizierung, Fristen, Kontowechsel, Abmeldung, ungültige Aktionen). Globale
Sprach-/Platzhalterparität besteht weiterhin; 168 PHP-Dateien ohne Syntaxfehler.
Erweiterter Browser-Smoke-Test besteht die Node-Syntaxprüfung. Vier neue MySQL-
Sichtbarkeitsprüfungen und Browserabläufe für persönliche Listen, Bildsortierung
und mobile Setup-Seiten in acht Sprachen sind ergänzt, aber noch nicht ausgeführt.

## Zweite lokale Etappe, 2026-11-02

Nachtrag: [CI-Lauf für 2d496a8](https://github.com/LLMbutHuman/GunIndex/actions/runs/41418425597)
ist vollständig erfolgreich abgeschlossen. Damit sind die unten als lokal offen
aufgeführten neuen DB-/HTTP-Suites nun in der isolierten GitHub-Linux-Laufzeit
erfolgreich ausgeführt, ebenso der vorhandene Browserumfang und Composer-Audit.
Spätere persönliche Listen und erweiterte Browserfälle benötigen einen eigenen Lauf.

Offizielle portable PHP-CLI 8.4.27 mit geprüfter Download-SHA-256, ausschließlich
Syntax-/Unitprüfungen unter Windows (keine native Ausführung des Linux-Runners):

| Suite | Tatsächlich bestanden |
| --- | ---: |
| payments_unit.php, synthetische Transportantworten | 19 |
| auth_test.php | 17 |
| admin_unit.php | 7 |
| image_order_test.php | 11 |
| seo_test.php ohne Datenbank | 11 |
| setup_test.php, einschließlich acht Sprachfassungen | 11 |
| geo_test.php ohne Datenbank, einschließlich globaler Katalogparität | 7 |
| **PHP-Einzelprüfungen** | **83** |

157 Projekt-PHP-Dateien bestanden `php -n -l`; `git diff --check` ohne Fehler.
Der zwischenzeitliche Paritätsfehler während des Schreibens der Setup-Kataloge
ist nach vollständigen acht Katalogen behoben. Der Node-PLZ-Validator bestand
zehn synthetische Testgruppen. Auch der bestehende PHP-Importer wurde tatsächlich
mit `--country=DE --file=ZIP/PLZ.csv --format=legacy-de --dry-run` ausgeführt:
8.289 Einträge, null Dubletten, erwarteter SHA-256; keine DB-Änderung.

Zusätzlich erstellt und syntaktisch geprüft, aber lokal noch nicht ausgeführt:
neun Setup-DB-/HTTP-Fälle, elf Bildreihenfolge-DB-/HTTP-Fälle und die SEO-
MySQL-Prüfung mit mehrseitigen Sitemaps und Sichtbarkeitsfiltern. Diese laufen
im bestehenden sequenziellen Linux-Runner. WSL meldet Neustartbedarf und bei
der Folgeprüfung unzureichenden freien Speicher; Docker ist lokal nicht nutzbar.
Kein aktueller Browser-/Provider-/Restore-Nachweis für diese neuen Änderungen.

Die vorherige erste Etappe wurde hingegen nachträglich extern bestätigt:
[GitHub-Actions-Lauf für 754adf1](https://github.com/LLMbutHuman/GunIndex/actions/runs/41356788528)
meldet `completed/success`. Dies ist kein Erfolg für spätere lokale Änderungen.

## Lokale Fortsetzung am 2026-11-01

Auf Windows PowerShell 5.1 bestanden die zehn Prüfungen in
`tests/windows_preflight_test.ps1`: WSL2-/WSL1-Unterscheidung, Ubuntu-Auswahl,
NUL-Ausgabe, Erfolgs-/Fehlerstatus, Prozesszeitlimit und fehlendes Programm.
`ops/check-windows.ps1 -Json` meldete erwartungsgemäß Exitcode 1:
Git und Node erreichbar, keine bestätigte Ubuntu-WSL2-/Linux-Docker-Laufzeit.
Python ist lokal nur als nicht ausführbarer WindowsApps-Alias vorhanden.
Die neuen Python-Plattformtests sind in CI eingebunden, lokal noch nicht ausgeführt.
Backend-, PHP-, DB- und Browsertests wurden in dieser Sitzung nicht ausgeführt.

## Historischer Nachweis vor der lokalen Fortsetzung

Der vollständige Runner `python3 tests/environment.py test` wurde auf der isolierten
synthetischen MySQL-Datenbank ausgeführt. Er pausiert alle fünf Hintergrundworker,
führt die Suites nacheinander aus und startet die Dienste im `finally` wieder.
Produktivdaten und historische persönliche Exporte werden nicht als Fixtures verwendet.

Der vollständige Lauf bestand mit **235 Prüfungen und 137 PHP-Syntaxprüfungen**.
Anschließend wurde eine zusätzlich gefundene Rolleninkonsistenz in der Länder-
Administration behoben: Die delegierte Administratorrolle verwendet dort jetzt
`site.manage`. Die Governance-Suite wurde mit einem neuen echten HTTP-/MFA-Fall
wiederholt; alle 17 Fälle bestanden. Damit sind **236 unterschiedliche Prüfungen**
des aktuellen Stands nachgewiesen. Keine Tests wurden übersprungen oder Assertions
zur Herstellung eines Erfolgs abgeschwächt.

| Bereich | Bestanden |
| --- | ---: |
| Kern-HTTP/DB: Konten, Inserate, Bilder, Gebote, Admin, Worker | 34 |
| Manuelle Zahlungen, Bumps, private Nachrichten, Bewertungen | 14 |
| Händler-/Banner-/CSV-HTTP-Abläufe | 9 |
| PayPal-Transportverträge und exakte Gebühren | 19 |
| Authentifizierung, RFC-TOTP, Passwortgrenzen | 17 |
| Admin-/Kategorievalidierung | 7 |
| Kontowiederherstellung, verschlüsselte Mailqueue, Präferenzen und HTTP | 25 |
| Länder, PLZ, Entfernung, Lizenzgrenzen und Sprachkataloge | 9 |
| Gesuche, lokales Matching, Sofortalarme und tägliche Digests | 22 |
| Premiumplätze, tatsächlicher Parallelzugriff, Banner und Massenverwaltung | 10 |
| Rollen, Moderation, Rechtsdokumente, Export, Löschung und Dokumentenschutz | 17 |
| Rechnungssnapshots, echte PDFs, Zugriff, Storno und Erstattungen | 15 |
| AEAD, Schlüsselwechsel, Chatblockaden, Verlauf und privates Polling | 9 |
| Ubuntu-Installer und Konfigurationsschutz | 10 |
| Legacy-Kontenimport in neue Testschemata | 6 |
| Sicherung und nachträgliche idempotente Chatverschlüsselung | 1 |
| Tatsächliche PHP/HKDF/HMAC-Manifestprüfungen | 8 |
| Archivintegrität und tatsächlicher SQL-Restore | 4 |
| **Unterschiedliche automatisierte Prüfungen ohne Lint** | **236** |

Lokale Protokolle liegen ignoriert unter `tests/.runtime/phase2-validation.log`
und `tests/.runtime/governance-final.log`. Neue Aufgaben müssen nach eigenen
Änderungen eigene aktuelle Nachweise erzeugen. Die Tests hinterlassen eindeutig
synthetische Daten beziehungsweise frische Scratch-Schemata; keine bestehende
Produktivdatenbank wird überschrieben.

Besonders geprüft sind getrennte gleichzeitige PHP-Prozesse bei Geboten und beim
letzten knappen Premiumplatz, unveröffentlichte private Höchstgebote, Quarantäne
widersprüchlicher Auktionszustände, einmalige Zahlungsfreigabe, unveränderte
Gebühren-/Adress-/Steuersnapshots, kumulierte Cent-Steuerkorrekturen bei kleinen
Teilerstattungen und die Ablehnung fremder Nachrichten, Rechnungen und Dokumente.

Die Chatkonvertierung erfolgt erst nach einer tatsächlich verschlüsselten,
entschlüsselten und per Digest verifizierten Sicherung. Ein alter Klartext, der
zufällig mit dem Cipherpräfix beginnt, bleibt durch die separate Versionsspalte
korrekt lesbar. Nachrichtentext, Datum und Lesestatus bleiben erhalten; ein zweiter
Konvertierungslauf verändert nichts mehr.

Der SQL-Restore importiert tatsächlich in ein neues Schema und vergleicht alle
geordneten Daten sowie Tabellen-/Spalten-/Index-/FK-/Zeichensatzmetadaten und den
ungecachten AUTO_INCREMENT-Stand. Manipulierte Archive, falsche Schlüssel oder
fehlender authentifizierter Abschluss müssen ohne Klartextausgabe scheitern.
Manifesttests prüfen zusätzlich eine unabhängige Standard-HKDF/HMAC-Berechnung,
Manipulation, Schlüsselrechte, Größenlimit und Pfadversuche vor jedem DB-Zugriff.

## Browser und Barrierefreiheit

```sh
npm ci --ignore-scripts --prefix tests/browser-tools
tests/browser-tools/node_modules/.bin/playwright install --with-deps chromium
node tests/browser_smoke.js
node tests/international_smoke.js
node tests/accessibility_smoke.js
```

Diese Befehle nacheinander und nach dem Backendlauf ausführen, da die Suites die
selbe synthetische Datenbank verwenden. Ein vorhandenes `/usr/bin/chromium` oder
`CHROMIUM_PATH` wird verwendet; sonst die zu Playwright passende installierte Version.
Die Browsertest-Abhängigkeiten sind separat gelockt und keine öffentlichen Appressourcen.

- Elf echte Chromiumabläufe bestanden: Login, Inserat mit Bild, Merkliste, Chat,
  Gebot, responsive Ansicht, Theme-Persistenz, Admin-MFA, Markenänderung/Kategorien,
  manuelle Zahlungssperre und reduzierte Bewegung. Breiten 1440/768/390 Pixel,
  keine JavaScriptfehler, keine 5xx-Antworten und keine externen Assetrequests.
- Neun weitere Prüfungen bestanden: acht tatsächliche mobile Sprach-/Marktwechsel
  und die internationale Suchsteuerung. Alle Sprachen passen bei 390 Pixel Breite.
- Axe/WCAG-Regeln sowie Tastatur-Sprunglink bestanden auf Startseite, Suche, Login
  und Registrierung bei 1440 und 390 Pixeln: acht Kombinationen ohne gemeldeten
  Regelverstoß. Dies ist keine vollständige manuelle Barrierefreiheitsabnahme.

Anfangs gefundene Fehler wurden behoben: Der mobile Kontolink hatte keinen
zugänglichen Namen; kleine Authlinks brauchten ausreichend Trefferfläche und
sichtbare Unterstreichung. Ein wiederholbarer Händler-HTTP-Test benötigt eine
je Lauf eigene Kategorie, damit bezahlte synthetische Platzierungen aus alten
Läufen keine Kapazität des nächsten Laufs belegen. Die Produktionssperren blieben erhalten.

## Build und begrenzte Messung

Tatsächlich erfolgreich waren der Produktions-PHP-FPM-Build mit gelockter Dompdf-
Bibliothek, FPM als nicht privilegierter Benutzer auf read-only-Dateisystem,
nginx-Konfigurationsprüfung mit synthetischem TLS-Zertifikat, lokaler Postfix ohne
Netzwerk, Compose-Konfigurationsprüfung und ein Installer-Probelauf im offiziellen
Ubuntu-22.04-Container. Ein Rechtefehler bei den kopierten PHP-/FPM-Dateien wurde
gefunden und mit ausdrücklich lesbaren COPY-Dateimodi behoben.

`python3 tests/load_smoke.py --requests 40 --concurrency 4` lieferte lokal 40/40
HTTP 200, Median 27,54 ms, p95 43,11 ms und Maximum 48,52 ms. Das ist eine kleine
Stichprobe des Entwicklungsservers, keine Produktionskapazität, Lighthouse- oder
Core-Web-Vitals-Messung.

Composer-Plattformanforderungen bestanden. `npm audit` der gelockten Browsertools
meldete null Schwachstellen. `composer audit --locked` konnte wegen HTTP 403 für
`packagist.org/api/security-advisories/` im aktuellen Cloud-Netz nicht abgeschlossen
werden; die Domain ist für die wiederverwendbare Konfiguration vorgesehen. Der
CI-Schritt für diesen Audit ist eingerichtet, ein gehosteter CI-Lauf wurde hier
nicht beobachtet. Es wird kein erfolgreicher PHP-Schwachstellenaudit behauptet.

## Nicht nachgewiesen

Keine echte PayPal-Standardbutton-/Sandbox-/Livezahlung, kein automatischer
Bitcoin-Eingang, keine echte Mailzustellung bis zum Posteingang, kein produktiver
Altdatenimport, keine öffentliche Zertifikatsausstellung und kein Offsite-Transfer.
Der vollständige Produktions-Compose-Backup-/Restoreprozess, getrennte
Laufzeit-DB-Rechte, DNS-/SMTP-Betrieb, Screenreaderprüfung und unabhängige
Security-/ASVS-Abnahme müssen auf dem echten Zielsystem gesondert erfolgen.

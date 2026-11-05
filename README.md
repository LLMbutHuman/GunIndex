# GunIndex.eu

Selbst hostbarer allgemeiner Marktplatz mit PHP und MySQL. Der aktive Katalog enthält allgemeine Waren; historische spezialisierte Inserate und private Uploads werden nicht öffentlich übernommen. Der Entwicklungsstand liegt auf `feature/general-marketplace-rebuild`.

Vorhanden sind:

- Konten mit E-Mail-Verifikation, Passwortreset, Sitzungswiderruf und verpflichtender Personal-MFA.
- Hierarchische Kategorien, Inserate mit aufbereiteten Bildern, Suche, Merklisten, Bewertungen und Proxyauktionen mit unabhängigem Abschlussworker.
- Kostenlose Gesuche, lokales Matching, gespeicherte Suchen, Suchalarme und In-App-/optionale E-Mail-Hinweise.
- Elf Ländermärkte, internationale Suche, lokale PLZ-/Radiusfilter und acht Sprachen für wesentliche Oberflächen. Teile der Administration, Geschäfts- und Rechtstexte verwenden weiterhin Deutsch.
- Inseratsbezogener Chat mit Teilnehmerprüfung, Blockieren, Melden und serverseitiger AEAD-Verschlüsselung gespeicherter neuer Nachrichten. Das ist keine Ende-zu-Ende-Verschlüsselung.
- Händlerprofile, eigene Kennzahlen, CSV-/Massenverwaltung, begrenzte Premiumplätze und landesspezifische Bannerkampagnen.
- Manuelle PayPal-Zahlungsaufträge mit festem Betrag/Verwendungszweck, tatsächlicher Personalprüfung, Bumps und versionierten Buchungsbedingungen. Nach gesonderter Finanzfreigabe entstehen unveränderliche Rechnungsbelege/PDFs und dokumentierte Korrekturen.
- Berechtigungsbasierte Administration, Meldungen und Beschwerden, eigener Datenexport, kontrollierte Deaktivierung/Anonymisierung sowie technisch vorbereitete, standardmäßig deaktivierte Identitätsprüfung.
- Zwanzig versionierbare deutsche Rechtsentwürfe, lokale Hintergrundqueues, Betriebsüberwachung und ein Ubuntu-22.04-Installer mit verschlüsselten Sicherungs-/Restorewerkzeugen.

Die Plattform ist **noch nicht produktionsfreigegeben**. Zahlungs-, Werbe-, Finanz-, Identitäts- und Rechtstextveröffentlichung benötigen die jeweiligen expliziten Konfigurationen und tatsächlichen fachlichen Freigaben. Bitcoin, Banküberweisung und Treuhand bleiben deaktiviert. Auktions-Einstell- und Erfolgsforderungen sind integriert und isoliert geprüft; automatische Providererstattungen und weitere Korrekturen bleiben offen. Automatisierte PayPal-API-Abläufe wurden mit lokalen Testantworten geprüft; ein echter Provider-Sandbox-/Live-Nachweis steht aus.

Entwicklung mit ausschließlich synthetischen Daten:

```sh
python3 tests/environment.py up
python3 tests/environment.py test
node tests/browser_smoke.js
```

Voraussetzungen und Browserwerkzeuge stehen in [TESTING.md](docs/TESTING.md). Der Webroot ist ausschließlich `public/`; Konfiguration und vertrauliche Dateien bleiben außerhalb. Die isolierte Entwicklungsumgebung verwendet PHP 8.4 mit GD/mbstring/libsodium, einen privaten MySQL-Socket und unabhängige Worker. Historische Exporte nicht ungeprüft importieren.

Unter Windows zuerst die [WSL2-Vorprüfung](docs/WINDOWS_DEVELOPMENT.md) ausführen;
den Linux-Testrunner nicht nativ in Windows starten.

Neue Module und genaue Prüfgrenzen: [Admin-Einrichtung](docs/ADMIN_SETUP.md),
[Bildreihenfolge](docs/IMAGE_ORDER.md), [SEO](docs/SEO.md),
[PLZ-Quellen](docs/POSTCODE_SOURCES.md). Aktuelle Etappen stehen in
[IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md).

- [Ubuntu 22.04: Befehle und Installationsdatei](docs/UBUNTU_22_04.md), [Installation](docs/INSTALLATION.md), [Deployment](docs/DEPLOYMENT.md)
- [Implementierungsbericht](docs/IMPLEMENTATION_REPORT.md), [Architektur](docs/ARCHITECTURE.md), [Datenbank/Migrationen](docs/DATABASE.md)
- [Administration](docs/ADMIN_GUIDE.md), [Authentifizierung](docs/AUTH.md), [E-Mail und Benachrichtigungen](docs/EMAIL_NOTIFICATIONS.md)
- [Länder, Sprachen und PLZ](docs/INTERNATIONAL.md), [Gesuche und Suchalarme](docs/DISCOVERY.md), [Händler und Werbung](docs/BUSINESS.md)
- [Manuelle PayPal-Prüfung](docs/MANUAL_PAYMENTS.md), [API-Zahlungen](docs/PAYMENTS.md), [Finanzen](docs/FINANCE.md), [Gebühren](docs/FEES.md)
- [Rollen, Datenschutz und Rechtstexte](docs/GOVERNANCE.md), [Betreibergrenzen](docs/COMPLIANCE.md), [Sicherheit](docs/SECURITY.md)
- [Kontrollierte Altkontenübernahme](docs/LEGACY_MIGRATION.md), [Monitoring](docs/MONITORING.md), [Backups](docs/BACKUP_RESTORE.md)
- [Tatsächliche Tests und Grenzen](docs/TESTING.md), [Release-Checkliste](docs/RELEASE_CHECKLIST.md)

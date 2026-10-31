# Umsetzungsbericht

Arbeitsgrundlage ist der tatsächlich wiederhergestellte Repository-Stand `7e66f55`. Der frühere lokale Modernisierungsstand war im neuen Snapshot nicht enthalten. Dieser Bericht beschreibt ausschließlich den neu aufgebauten Branch `feature/general-marketplace-rebuild` und dessen tatsächliche Tests.

## Implementiert und getestet

- Kontrollierter Webroot, umgebungsbasierte PDO-Konfiguration, sichere Sessions/CSRF, generische Fehler, lokale Assets.
- Registrierung/Login/Profil/Passwortänderung, persistente Login-Limits, Sessionrotation/-widerruf/-fristen und obligatorische Admin-TOTP mit verschlüsseltem Secret und Wiederholungsschutz. Sicherer interaktiver CLI-Erstadministrator ohne Standardpasswort.
- Eigenständiges Ink/Mint/Amber-Design mit responsivem Layout, Light/Dark Mode, animierter Hero-Grafik, Scroll-/Hover-Bewegung, Besucherpause und Vorrang reduzierter Bewegung.
- Admin-Dashboard mit echten Kennzahlen, zentralen Marken-/Text-/Farb-/Animations-/Registrierungs-/Gebühreneinstellungen, Konflikterkennung paralleler Änderungen, Kategorienbaum, Benutzer-Sitzungswiderruf, Inserats-/Auktionsprüfung, Bewertungsmoderation und Audit.
- Allgemeine Kategorien, Inserate mit Entwurf/Bearbeiten/Pause/Verkauft/Archivierung, Standort, Übergabe, exakte Centpreise, paginierte Suche und private sichere WebP-Bildergalerie.
- Proxygebote, feste Schritte, deterministischer Gleichstand, Anti-Sniping, Sofortkauf vor dem ersten Gebot, echte Datenbanksperren, eigenständiger Abschlussjob und isolierte Prüfzustände bei Konflikten.
- Private paargebundene Nachrichten mit CSRF/Escaping/Limits, Merklisten und öffentliche sparsame Profile. Bewertungen nur durch Parteien abgeschlossener Auktionen, einmalig und moderierbar.
- Exakter zentraler Gebührenrechner, Preisversionierung und sichere automatische PayPal-Adapter als deaktivierte Grundlage.
- Manueller PayPal-Zahlungsauftrag für die tatsächlich implementierten Bumps: serverseitiger Preis und Zweck, gebundener Besitzer, Steuer-/Bedingungssnapshot, Admin-MFA-Prüfung des realen Eingangs, eindeutige Transaktion, einmalige Guthabengutschrift und dokumentierter Leistungsbeginn. Kein Screenshot oder Kundenhinweis schaltet etwas frei.
- Einzel-/Dreier-Bump-Guthaben, transaktionssicherer Verbrauch,72-Stunden-Sperre, eigene Sortieraktualität und Historie ohne Änderung von Geboten/Endzeit.
- Gepinnte Docker-Entwicklung, zwei additive versionierte Migrationen mit Sicherung, authentifizierte Backupstreams und tatsächlicher Restore synthetischer Daten.

95 Prüfungen und 69 PHP-Syntaxprüfungen bestanden. Elf Chromiumabläufe sowie der tatsächliche Schedulercheck bestanden. Details und Grenzen: TESTING.md. Es wurden keine echten Zahlungen ausgeführt.

## Bestand und Migration

Alte spezialisierte Inserate werden nicht in den allgemeinen Katalog aufgenommen. Die neue Marktlogik nutzt ausschließlich separate `market_*`-Tabellen. Historische SQL-Exporte und Uploads wurden nur aus dem Git-Index genommen; lokale Dateien bleiben erhalten. Die History wurde nicht umgeschrieben und benötigt weiter eine Datenschutz-/Rotationsentscheidung.

Das strukturelle Bootstrap ist nur für leere Datenbanken vorgesehen. Eine vollständige Migration beliebiger alter Benutzer-/Nachrichten-/Bewertungsschemata ist offen. Die hier tatsächlich ausgeführten additiven Migrationen ergänzen manuelle Abrechnung/Bumps und korrigieren ausschließlich exakt bekannte falsch codierte Seednamen; veränderte Kategorien bleiben erhalten.

## Vorbereitet und deaktiviert

Alle Zahlungswege sind ohne explizite Konfiguration aus. Der manuelle Button benötigt keine API-Schlüssel, aber tatsächliche Eignung des Empfängerkontos und geprüfte Geschäfts-/Steuer-/Bestellprozesse. Ein Privatkonto wird nicht entgegen PayPal-Vorgaben als Händlerkonto ausgegeben. API-Automatisierung bleibt separat deaktiviert. Bitcoin, Banküberweisung und Treuhand werden nicht als verfügbar dargestellt. Identitätsdokumentensammlung bleibt gesperrt.

## Noch offen aus dem Master-Prompt

Der ganze Master-Prompt ist noch nicht vollständig umgesetzt. Offen sind insbesondere Gesuche/Matching/Suchalarme, Benachrichtigungsqueue/SMTP, automatische Gebührenausstellung, vollständige steuerliche Rechnungen/PDF/Storno/Refunds, knappe Premiumplätze, Händlerbanner/Kampagnen und Händler-Massenverwaltung. Hinzu kommen E-Mail-Verifikation/Passwortreset, feinere Rollen, Datenexport/-löschung, formale Rechtsklassifizierung und vollständige Meldungs-/Beschwerdeverfahren.

Die Regelseite und gespeicherten Bedingungen sind keine vollständige anwaltlich freigegebene Rechtstextsammlung. Betreiberangaben, Rechtstext-Redaktion/Freigabe, Verbrauchercheckout-Abnahme, vollständige SEO-/Core-Web-Vitals-/Lastnachweise, produktiver Webserver, Monitoring, Offsite-Schlüssel-/Backup-/Recoverybetrieb sind weitere Arbeitsschritte. Keine Aussage „perfekt“, „höchstmöglich sicher“ oder „produktionsfertig“ wird daraus abgeleitet.

## Auslieferung

Wiederholbarer Start/Test: INSTALLATION.md. Manuelle Zahlungen: MANUAL_PAYMENTS.md. Betriebsvoraussetzungen: DEPLOYMENT.md und RELEASE_CHECKLIST.md. Commits und tatsächlicher Remote-Upload werden im Git-Verlauf bzw. Abschluss genannt; eine gespeicherte Cloud-Konfiguration ist kein Push oder Produktivdeployment.

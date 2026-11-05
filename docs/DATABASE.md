# Datenbank und Migration

`sql/schema.sql` ist das Basisschema für **leere** Datenbanken ohne privilegierte Beispieldaten. Neue Installationen benötigen anschließend sämtliche versionierten Migrationen. Das Schema ist kein Importskript für bestehende Produktivtabellen. `sql/structure.sql` enthält keinen vordefinierten Admin und verweist auf die kontrollierte Installation.

MySQL/InnoDB, UTF8MB4, UTC-Zeitwerte, Fremdschlüssel und eindeutige Referenzen sichern den aktiven allgemeinen Marktplatz. Geld wird in Integer-Cent gespeichert; Steuersätze verwenden Basispunkte. Auf Transaktionspfaden kommen Zeilensperren zum Einsatz. Die Legacy-Tabellen werden nicht automatisch als öffentliche Angebote eingebunden.

## Versionierte Änderungen

| Migration | Ergänzung |
| --- | --- |
| `001_manual_billing.sql` | Manuelle Zahlungsaufträge, Bump-Guthaben und Aktivierungen |
| `002_seed_encoding.sql` | Eng begrenzte Korrektur der bekannten ursprünglichen Kategorie-Fehlkodierungen |
| `003_accounts_mail.sql` | E-Mail-Bestätigung, Kontotokens, Hinweise, Präferenzen und verschlüsselte Mailqueue |
| `004_international_geo.sql` | Konten-/Angebotsländer, bevorzugte Sprache, Märkte, PLZ-Quellen/Punkte und Inhaltsübersetzungen |
| `005_discovery.sql` | Gesuche, gespeicherte Suchen, Veröffentlichungsevents, Treffer und Digestdeduplizierung |
| `006_business.sql` | Händlerprofile, Kampagnen, Länderinventar, Platzbuchungen und Werbemetriken |
| `007_governance.sql` | Personalrollen, Meldungen/Beschwerden, Löschanträge, Identitätsdokumente und Rechtsversionen/-zustimmungen |
| `008_finance.sql` | Rechnungsanschriften, Ländersteuerregeln, unveränderliche Snapshots/Belege, Sequenzen, Erstattungen und Finanzereignisse |
| `009_chat_privacy.sql` | Nachrichten-Verschlüsselungsversion, Blockierungen und Gesprächsindex |
| `010_legacy_import.sql` | Eindeutige Zuordnung kontrolliert übernommener Altkonten |
| `011_contract_receipts.sql` | Unveränderliche Dokumentbindung bei Registrierung und Leistungsbestellung |
| `012_auction_billing.sql` | Gebührenverträge, Zustimmungen und eindeutige Einstell-/Erfolgsforderungen |
| `013_maintenance.sql` | Persistente Wartungsfenster und Auktionsfrist-Snapshots |
| `014_relisting.sql` | Ursprungsketten, eindeutige Nachfolger und einmaliger Einstellgebührenrabatt |

Migrationen bis 014 wurden auf der isolierten synthetischen Entwicklungsdatenbank
geprüft; CI 42055817743 bestätigt auch die neue Wiedereinstellungs-Etappe.
Den aktuellen Laufnachweis dokumentiert `IMPLEMENTATION_PROGRESS.md`. Bereits ausgeführte Dateien
sind unveränderlich; spätere Änderungen benötigen eine neue Migration.
Das ist kein Nachweis einer ausgeführten Produktivmigration.

```sh
php ops/migrate.php --backup-confirmed
```

Version und Prüfsumme werden unter einem MySQL-Migrationslock gespeichert. Der Runner prüft vorhandene Strukturen; beispielsweise ergänzt er `bumped_at` nur bei fehlender Spalte. Migration 002 verändert nur exakt bekannte ursprüngliche Slug-/Hexwertkombinationen und überschreibt keine inzwischen bearbeiteten Kategorien.

`--backup-confirmed` bestätigt einen **zuvor tatsächlich geprüften** verschlüsselten Backup- und Wiederherstellungsplan. Der Schalter erzeugt selbst keinen Produktivbackupnachweis. Die Entwicklungsumgebung sichert ausschließlich ihre synthetische Testdatenbank automatisch. [Ubuntu-Betrieb](UBUNTU_22_04.md) und [Monitoring/Sicherungen](MONITORING.md) beschreiben die neu hinzugekommenen Betriebswerkzeuge.

MySQL-DDL kann implizit committen. Ein unterbrochener Lauf erfordert Prüfung und gezielte Vorwärtskorrektur; es gibt keinen automatischen Reset einer Produktivdatenbank. Das Einspielen des leeren Basisschemas über einen bestehenden Bestand ist kein Rollbackverfahren.

## Aktive Datenbereiche

Kernangebote, Bilder, Gebote, Merklisten, Nachrichten, Bewertungen und Zahlungen verwenden `market_*`; Benutzer, Settings und Auditlog sind gemeinsame Strukturen. Neue Tabellen begrenzen unter anderem:

- Tokens auf Zweck, Adresse und Sessionversion; Hinweise auf Benutzer und Ereignisschlüssel.
- Discovery-Treffer auf Suchprofil und Angebot sowie tägliche Hinweise auf Benutzer und UTC-Tag.
- Werbeinventar auf Land, Dienst, Kategorie und tatsächlich vorhandenen Slot; Reservierung und Zahlung sind getrennte Zustände.
- Rechnungsbelege auf feste Sequenz-/Dokumentkennungen, Hash und verschlüsselten Snapshot; Erstattungen auf eindeutige reale Rückzahlungsreferenzen.
- Dokumentenzugänge auf berechtigte Prüfer, Fall und kurze Gültigkeit; Rechtszustimmungen auf konkrete Version und Hash.
- PLZ-Punkte auf Land und normalisierte PLZ; Entfernungsabfragen verwenden nur aktivierte Quellen mit dokumentierter Rechteprüfung.

Automatische Auktionsgebührenforderungen sind mit Veröffentlichung und Abschluss
integriert. Vertrag, Zustimmung und Forderung gehören jeweils zur selben Transaktion.
Wartungsfenster sichern die einmalige Fristfortsetzung. Wiedereinstellungs-Ketten
begrenzen den Gratisanspruch; sie schreiben keine ursprünglichen Gebote um.
Weitere Zahlungs-/Korrekturarten und reale Providerabnahmen bleiben offen.

## Bestehende Daten und Datenschutz

Ein kontrolliertes CLI-Werkzeug für **Altkonten-Stammdaten** ist vorhanden: [LEGACY_MIGRATION.md](LEGACY_MIGRATION.md). Es liest eine getrennte Quelle ausschließlich mit SELECT, startet als Dry-run und führt Idempotenz-/Kollisionsprüfungen. Eine optionale ID-Neuzuordnung wird ausdrücklich protokolliert. Alte Adminrechte, Identitätsnachweise, Sitzungen und Passwörter werden nicht als vertrauenswürdige Berechtigungen übernommen.

Die tatsächliche Bestandsprüfung und Produktivübernahme stehen aus. Historische spezialisierte Inserate, Gebote, Nachrichten, Dokumente und Geschäftsvorfälle benötigen jeweils gesonderte fachliche Zuordnung; sie werden nicht durch den Benutzerimport aktiviert. Widersprüchliche historische Schemata dürfen nicht mit dem neuen Bootstrap überschrieben werden.

Vorhandene allgemeine Klartextnachrichten lassen sich mit `php ops/encrypt-chats.php --apply --backup-confirmed` kontrolliert in die neue AEAD-Ablage überführen; ohne `--apply` wird nur der noch offene Umfang gezählt. Migration 009 allein verschlüsselt keine bestehenden Texte. Schlüssel und Wiederherstellung sind vor dem Lauf zu prüfen.

Datenexport und kontrollierte Anonymisierung sind implementiert. Unveränderliche Geschäfts-/Finanznachweise werden bei einer Kontolöschung nicht pauschal mitgelöscht; tatsächliche Fristen und gesetzliche Aufbewahrung bestimmt der Betreiber.

Historische Exporte und Uploads wurden aus der neuen Git-Indexversion entfernt, lokale Dateien bleiben erhalten. Alte Git-History enthält diese weiterhin und wurde nicht umgeschrieben. Retention, Berechtigungen, bekannte Geheimnisse und eine mögliche koordinierte History-Bereinigung bleiben gesonderte Betreiberaufgaben.

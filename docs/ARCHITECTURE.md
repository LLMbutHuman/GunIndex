# Architektur

Serverseitig gerendertes PHP, PDO/MySQL mit InnoDB und UTF8MB4 sowie lokale CSS-/JavaScript-Ressourcen bilden die Anwendung. Die Oberfläche benötigt keine SPA und verwendet keine externen Chat-, Such-, Übersetzungs- oder Trackingdienste. Dompdf ist als lokal installierte, über Composer-Lock gebundene PDF-Bibliothek eingebunden.

## HTTP und Dienste

`public/index.php` besitzt eine explizite Routen- und Assetliste. Konfiguration, Git, Tests, SQL, private Medien und Uploadverzeichnisse werden nicht direkt ausgeliefert. `config/bootstrap.php` lädt Settings, Kryptografie, Geo/I18N, Kategorien/Geld, Governance/Authentifizierung, Mail/Kontotokens, Bilder/Marktplatz, Discovery, Händlergeschäft, Gebühren/Zahlungen/Finanzen sowie Chat/Datenschutz.

| Bereich | Dienst und Zuständigkeit |
| --- | --- |
| Konten | `auth.php`, `account_tokens.php`: Passwörter, Sessionversion, MFA und zweckgebundene Einmaltokens |
| Mail/Hinweise | `mail.php`: deduplizierte In-App-Hinweise, Präferenzen und verschlüsselte lokale Versandqueue |
| Länder/Sprache | `geo.php`, `i18n.php`: aktive Märkte, lizenzierte lokale Koordinaten, lokale Wörterbücher |
| Angebote/Auktionen | `catalog.php`, `market.php`: Allgemeinwaren, Besitzerrechte, Gebote und Abschlusszustände |
| Gesuche | `discovery.php`: normalisiertes lokales Matching, Suchkriterien, Ereignisse und tägliche Zusammenfassungen |
| Händler/Werbung | `business.php`: Profile, begrenztes Länderinventar, Reservierungen und Kampagnen |
| Abrechnung | `fees.php`, `payments.php`, `manual_payments.php`, `finance.php`: Centbeträge, Providerzustände, bestätigte Eingänge und unveränderliche Belege |
| Personal/Datenschutz | `governance.php`, `privacy.php`: konkrete Rechte, Fälle/Beschwerden, Export, Retention, Identitätsprüfung und Rechtsversionen |
| Vertrauliche Inhalte | `crypto.php`, `chat.php`: zweckgetrennte AEAD-Verfahren und teilnehmergebundener Chat |

Die aktuellen Datenbankrechte und `auth_version` werden bei geschützten Anfragen erneut geprüft. Personalrollen benötigen erfolgreiche MFA; objektbezogene Prüfungen begrenzen Nachrichten, Rechnungen, Dokumente und Fallansichten. Das initiale Superadministrationskonto wird ausschließlich kontrolliert per CLI eingerichtet.

## Daten und Transaktionen

Aktive Geschäftsdaten liegen in eigenen `market_*`-Tabellen; lokale Postleitzahlen und deren Rechteherkunft in `geo_*`. Historische spezialisierte Angebote sind nicht Teil des aktiven Katalogs. Geldbeträge werden in Integer-Cent, Zeitpunkte in UTC gespeichert. Versionierte Migrationen ergänzen das Basisschema, ohne Altbestände ungeprüft zu überschreiben.

Gebote, Zahlungsprüfungen, Bumps und begrenzte Werbeinventare verwenden Transaktionen und Zeilensperren. Private Proxy-Maxima werden nicht öffentlich angezeigt. Eindeutige Referenzen verhindern doppelte lokale Buchungen, Hinweise und Suchtreffer. Finanzdokumente bewahren bei aktivierter Finanzfunktion den verschlüsselten Betreiber-/Kunden-/Steuersnapshot; Änderungen erfolgen durch zugeordnete Korrekturbelege.

Die Gebührenberechnung ist zentral vorhanden. Die automatische Erzeugung von Einstell- und Abschlussforderungen aus der Auktionslogik ist noch nicht vollständig angebunden. Eine vorhandene Gebührenformel ist deshalb kein Nachweis eines vollständigen Auktionsabrechnungsprozesses.

## Hintergrundverarbeitung

Auktionsabschluss, Mailqueue, Discovery, Werbelaufzeiten und private Dokumentenfristen besitzen unabhängige CLI-Worker. Produktionscontainer führen die Jobs unabhängig von Seitenbesuchen aus und schreiben private Heartbeats. Fehler eines Jobs sollen die übrigen Jobs nicht anhalten; CLI-Healthchecks zeigen Queue- und Workerprobleme. Details: [Monitoring](MONITORING.md).

Listing-Veröffentlichungen und relevante Änderungen erzeugen Discovery-Ereignisse innerhalb ihrer Geschäfts-Transaktion. Begrenzte Batches verarbeiten diese zusätzlich zum vorhandenen Angebotsbestand. Gesuche/gespeicherte Suchen zeigen nachvollziehbare Treffer; sofortige Hinweise und einmalige tägliche Zusammenfassungen respektieren die Kanalpräferenzen. Ein lokales MTA übernimmt die tatsächliche SMTP-Zustellung. Ein erfolgreicher MTA-Aufruf beweist keinen Postfacheingang und garantiert keine externe Exactly-once-Zustellung.

## Länder, Sprache und Oberfläche

Marktkontext, Kontowohnsitz und bevorzugte Sprache sind getrennte Werte. Elf Märkte und acht lokale Kernwörterbücher sind angelegt. Internationale Suche erweitert das Suchgebiet; Werbeinventare bleiben dem ausgewählten Markt zugeordnet. Entfernungen sind ungefähre Luftlinien zwischen freigegebenen lokalen PLZ-Punkten. Ohne passende Rechte-/Koordinatenbasis werden keine Entfernungen erfunden.

Ink, Mint und Amber, Light/Dark Mode, progressive Animationen und wiederverwendbare SSR-Komponenten bestimmen den Auftritt. Reduzierte Bewegung und die Besucherpause haben Vorrang. Name, Markentexte, Akzentfarben, Animationsintensität, Kategorien, Märkte, ausgewählte Übersetzungen und Geschäftsparameter sind administrierbar. Teilweise deutsche Rückfalltexte bleiben sichtbar; Nutzernachrichten und Angebote werden nicht automatisch übersetzt.

## Betrieb und Grenzen

[Ubuntu 22.04](UBUNTU_22_04.md) beschreibt Installer, Container, privaten Datenbankzugriff, lokales Postfix, HTTPS, Schlüssel, Worker und verschlüsselte Sicherungen. Konfigurationsgeheimnisse sind Prozess-/Serverkonfiguration und keine frei editierbaren Websitefelder.

Neue Chattexte, Mailqueue, Identitätsbilder und Finanzsnapshots erhalten serverseitige Verschlüsselung mit getrennten Zweckschlüsseln. Der Server kann berechtigte Inhalte entschlüsseln; eine Ende-zu-Ende-Verschlüsselung wird nicht behauptet. Für vorhandene allgemeine Klartextchats existiert eine kontrollierte CLI-Konvertierung.

Manuelle PayPal-Freigabe benötigt einen tatsächlich geprüften Zahlungseingang. Provider-, Steuer-, Finanz-, Werbe-, Identitäts- und Rechtstextfreigaben bleiben eigenständige Gates. Banküberweisung, Bitcoin und Treuhand sind deaktiviert; reale Infrastruktur-/Providerabnahmen und tatsächliche Produktivdatenübernahmen sind nicht durch lokale Tests ersetzt.

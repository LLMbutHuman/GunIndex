# GunIndex: Gesamtstatus und Restaufwand

Stand: 2. November 2026, 22:25 Uhr, Europe/Berlin. Bewertet wird der vollständige Ultimate-
Masterauftrag für einen allgemeinen Warenmarktplatz, einschließlich E2EE,
Einrichtung und Betriebsabnahme. Keine Produktivfreigabe.

**Gesamteinschätzung: etwa 40 % fertig, mit großer Unsicherheit und einer
Planungsspanne von 30–45 %. Entsprechend fehlen etwa 60 %, plausibel 55–70 %.**
Das ist eine technische Expertenschätzung, keine gemessene Kennzahl und keine
Testabdeckung. Die bestehende Basis ist deutlich weiter als der vollständige
Masterumfang. Mehrere besonders aufwendige Systeme fehlen fast vollständig.

**Verbleibender interner Aufwand bis zu einer technisch releasefähigen Version
mit dem gesamten vereinbarten Umfang: realistisch rund 2.500–4.300 Arbeitsstunden.**
Ein belastbarer Fertigstellungstermin lässt sich aus den bisherigen Sitzungen
nicht ableiten. Die Zahlen sind Planungsbudgets, keine Zusagen für Codex-Laufzeit.

## 1. Was tatsächlich geprüft wurde

Lokalen Git-Status, Branch, Commitverlauf und Remote-SHA gelesen. Gezielt die
aktiven Implementierungen in `config/market.php`, `fees.php`, `payments.php`,
`finance.php`, `chat.php`, `auth.php`, `account_tokens.php`, `mail.php`,
`discovery.php`, `business.php`, `seo.php`, `setup.php`, `consent.php`, den
Kontooberflächen, SQL-Schemata/Migrationen, Workern und Betriebswerkzeugen
mit dem Auftrag verglichen. Vorhandene Testdateien, Runner und aktuelle
GitHub-Actions-Ergebnisse einschließlich Joblogs kontrolliert.

README, Fortsetzungsauftrag, Fortschritts-, Architektur-, Sicherheits-, Release-,
Test-, PLZ- und Abnahmedokumentation ergänzen den Codebefund. Das ist eine
gezielte Bestandsaufnahme; keine vollständige neue Sicherheitsprüfung aller
Quellzeilen und keine Wiederholung sämtlicher Tests nur für diesen Bericht.

Wichtige konkrete Befunde:

- `fees_quote()` berechnet Einstell- und Erfolgsgebühren, doch die Forderung ist
  nicht automatisch an Veröffentlichung/Zuschlag angebunden. Ein Gebührenrechner
  ist kein fertiger Abrechnungsprozess.
- `MarketPayPal::refund()` ist eine vorhandene REST-Methode. Ein vollständiger
  automatischer Erstattungsablauf mit Abstimmung und Abnahme fehlt weiterhin.
- `payments_methods()` setzt Bank und Bitcoin ausdrücklich auf nicht verfügbar.
  `escrow_preview` berechnet lediglich eine Vorschau; ein Treuhand-Zustandsmodell
  samt Auszahlung, Streitfällen und Fristen ist nicht implementiert.
- `chat_send()` verschlüsselt auf dem Server, `chat_history()` entschlüsselt dort.
  E2EE und verschlüsselte Chatdateien sind damit nicht erfüllt.
- `maintenance_enabled` ist ein Wartungshinweis und schließt SEO-Indexierung.
  Es ist keine durchgängige HTTP-/API-/Worker-/Zahlungssperre.
- `market_search()` besitzt Textsuche mit LIKE, Länder-/Preis-/Kategorie-/Radius-
  filtern. Das Relevanzmatching für Gesuche ersetzt kein vollständiges öffentliches
  Suchranking, Autocomplete oder strukturierte Kategorieattribute.
- Admin-Setup sammelt echte Zustandsdiagnosen für 18 Bereiche. Es konfiguriert
  noch nicht alle Bereiche über den geforderten geführten Assistenten.
- Elf additive Migrationen sind vorhanden. Alte Angaben zu zehn Migrationen
  und ungeklärter Nutzerberechtigung an der deutschen PLZ-Datei sind überholt:
  die Rechtebestätigung liegt als Nutzerangabe vor; der reale Import fehlt noch.

Begriffe: **implementiert** = ausführbarer Code; **integriert** = an tatsächliche
Geschäftsabläufe angeschlossen; **getestet** = benannter Nachweis erfolgreich;
**produktionsbereit** = zusätzlich reale Infrastruktur, Betriebs-/Sicherheits-
und notwendige externe Abnahmen erfüllt. Keine dieser Stufen ersetzt die nächste.

## 2. Fortschritt und Restaufwand je Bereich

Alle Prozentangaben sind unsichere Bandbreiten bezogen auf den vollen Auftrag,
nicht nur auf bereits vorhandene Funktionen. Besonders unsicher sind E2EE,
Treuhand, Provideranbindung und Betriebsabnahme. Die Stunden enthalten Entwicklung,
Integration und modulbezogene Tests; die separate QA-Zeile enthält zusätzliche
systemweite Prüfung. Wartezeiten externer Stellen sind nicht enthalten.

Das Gesamtmodell gewichtet nach erwarteter technischer Komplexität statt nach
Anzahl von Anforderungen oder Dateien. Die Gewichte W sind offengelegte
Planungsannahmen, keine erfassten Ist-Aufwände. Sie ergeben zusammen 100 und mit
den folgenden Bereichen grob 32–44 %; wegen Modellunsicherheit nach außen auf
30–45 % verbreitert. Kleine fertige Funktionen können große fehlende Systeme
nicht rechnerisch ausgleichen. Gewichte ändern sich bei verbindlicher Zerlegung.

| Bereich | Geschätzt fertig; W | Tatsächlich implementiert/integriert | Wesentlicher Rest | Realistischer Rest bis C, Stunden |
| --- | --- | --- | --- | ---: |
| **Gesamtprojekt** | **30–45 %; 100** | Funktionsfähige getestete Kernmodule, CI, Entwicklungsbranch | Vollständiger Masterumfang und Abnahme | **2.500–4.300** |
| Marktplatz und Inserate | 60–75 %; 10 | CRUD, Entwürfe, Bilder/Reihenfolge, Eigentümerschutz, Merkliste, Verlauf/Vergleich | Autosave, vollständige Vorschau, strukturierte Produkte, Wiedereinstellen, Verhandeln, vollständige Festpreisgeschäfte | 180–280 |
| Auktionen und Gebühren | 35–50 %; 8 | Proxygebote, Gleichstand, Anti-Sniping, Sofortkauf vor Geboten, Abschlussworker, exakte Gebührenformeln | Unveränderlicher Gebührenauftrag, Einstell-/Erfolgsforderungen, Korrekturen, Gratis-Wiedereinstellung | 160–260 |
| PayPal und Zahlungsintegration | 45–60 %; 6 | Zentrale manuelle Empfängeradresse, eindeutige Referenzen, verifizierte manuelle Freigabe, REST-Capture/Webhook, Wiederholungsschutz | Vollständige automatische Leistungserfüllung, Erstattungs-/Abstimmungsworkflow, echte Providerabnahme | 100–180 |
| Rechnungen und Finanzen | 60–75 %; 6 | Centbeträge, Steuerregeln, verschlüsselte Snapshots, PDF, Nummern, manuelle Teil-/Vollerstattung/Korrektur, automatischer Finanzabschluss | Gebührenforderungen für Auktionen, weitere Kaufarten, Bestandsfälle, Buchhaltungs-/Fachabnahme | 60–120 |
| Treuhand | 0–5 %; 10 | Deaktivierung und Gebührenvorschau; kein vollständiger Workflow | Angebote/Annahme, Verbindlichkeiten, Zahlung, Versand, Empfang, Streitfall, Fristen, zulässige Auszahlung, Audit | 260–440 |
| Bitcoin und Banküberweisung | 0–5 %; 7 | Deaktivierte Zahlungsoptionen | Node/Bestätigungen/Reorgs/Kursregeln; Bankreferenzen, Import/API, Zuordnung, Abstimmung, Erstattung | 200–340 |
| E2EE-Chat und verschlüsselte Anhänge | 0–5 %; 14 | Teilnehmergeschützter Chat und serverseitige AEAD als angrenzende Grundlage | Etabliertes E2EE-Protokoll, Geräteidentitäten, Verifikation, Mehrgeräte, Widerruf/Recovery, Anhänge/Quoten/Integrität | 360–600 |
| Benutzerkonten und Sicherheit | 60–75 %; 7 | Auth, Personal-MFA, Reset/Verifikation, Passwortwechsel, globaler Sessionwiderruf, Rollen, Datenschutz-/Identitätsgrundlagen | Einzelgeräte/-sessions, Nutzer-2FA, Passkeys, Avatar/Logo, weitergehende Missbrauchs-/Recoveryprüfung | 120–200 |
| Händlerfunktionen | 35–50 %; 5 | Moderierte Profile, CSV/Massenverwaltung, Statistiken, Banner/Premiuminventar | Mitarbeiter/Rollen je Händler, Abonnements, Warenwirtschaft, autorisierte API und Tokens | 180–300 |
| Übersetzungen aller acht Sprachen | 35–50 %; 5 | Kernoberflächen, Navigation, Kontoabläufe, neue Benachrichtigungs-/Footer-/Dashboardmodule, Teile Systemmails | Alle übrigen Altseiten, Admin/Finanzen/Checkout, Validierungen, acht Rechtsfassungen und sprachliche Abnahme | 160–280 |
| Suche und internationale PLZ-Daten | 50–65 %; 4 | Länder-/Radiusfilter, lokales Suchmatching/Alarme, elf Länderformate, Importer, DE-Datei validiert | Autocomplete, öffentliches Ranking/Attribute, tatsächlicher DE-Import, lizenzierte Nachbarlanddaten und Aktualisierung | 100–180 |
| SEO und Performance | 50–65 %; 3 | Canonicals, Sitemap, robots, Product-Daten, standardmäßig gesperrte Indexierung, responsive Oberfläche | hreflang/Sprach-URLs, repräsentative Last, Core Web Vitals und gezielte Optimierung | 60–120 |
| Mailserver und Benachrichtigungen | 45–60 %; 4 | Verschlüsselte Queue, MTA-Übergabe, Retry/Dedupe, Präferenzen, Konto-/Gebots-/Chat-/Rechnungs-/Suchereignisse | SMTP-/DNS-Assistent, echte Zustellung, Preis-/Vorablaufereignisse und vollständige Ereignismatrix | 90–160 |
| Admin-Dashboard und Einrichtungsassistent | 40–55 %; 3 | MFA/RBAC, Settings, Moderation, Finanzen, 18 echte Setup-Diagnosen | Geführte Konfiguration aller 18 Bereiche, sichere Secrets-/Testabläufe, vollständige Bedienung/Übersetzung | 90–160 |
| Automatische Installation | 40–55 %; 3 | Ubuntu-Skript, Compose/PHP-FPM/nginx/MySQL/Postfix, Konfiguration, Schlüssel, Worker, Backupwerkzeuge | Echte frische Zielinstallation, Updates/Neustart/Rollback, Assistent, Rechte-/Offsite-/Restore-Abnahme | 100–180 |
| Tests und Sicherheitsprüfungen | 45–60 %; 3 | Breite synthetische PHP/MySQL/HTTP-/Browser-/Parallel-/Backupregression, Composer-Audit | Tests aller neuen Systeme, Fehler-/Nebenläufigkeits-/Lastmatrix, unabhängiges Audit, manuelle Accessibility | 180–320 |
| Vorbereitung der produktiven Veröffentlichung | 15–30 %; 2 | Checklisten, Betriebsdoku, private Speicherstruktur, deaktivierte Gates, Legal-Handoff-Entwurf | Releasekandidat, Staging/Disaster-Recovery, reale Provider/DNS, Betriebsverantwortung und Schlussabnahmen | 80–140 |

Die Einzelbudgets summieren sich zu 2.480–4.260 Stunden, auf 2.500–4.300 gerundet.
Gemeinsame Arbeiten sind einem Hauptbereich zugeordnet: etwa Backend-Kategorie-
modell beim Marktplatz, Suchanbindung bei Suche, technische String-Anbindung beim
jeweiligen Modul und flächendeckende Sprach-/Rechtstextprüfung bei Übersetzungen.
Trotzdem bleibt Überlappungs-/Nacharbeitsunsicherheit; es gibt keine historische
Zeiterfassung, aus der ein exakter Gesamtfortschritt errechnet werden könnte.

### Prüfstand und Hindernisse zu denselben Bereichen

T1 bezeichnet den vollständig erfolgreichen Lauf
[41525630270 / 505ffa1](https://github.com/LLMbutHuman/GunIndex/actions/runs/41525630270).
T2 bezeichnet [41526420094 / ad5ed5f](https://github.com/LLMbutHuman/GunIndex/actions/runs/41526420094),
T3 [41527131692 / 2042549](https://github.com/LLMbutHuman/GunIndex/actions/runs/41527131692).
T1, T2 und T3 sind beim abschließenden Abruf vollständig erfolgreich; jeweils
Windows- und Linuxjob einschließlich Browser-/Internationalisierungsprüfungen.
T3 bestätigt zusätzlich sieben Sprachlink-Unitfälle, 28 Konto-/Mailfälle und
die neuen Benachrichtigungs-/Dashboard-/Footerabläufe. Keine echten Providerzahlungen.

| Bereich | Nachweislich bestanden | Offen/nicht ausgeführt | Technische Hindernisse |
| --- | --- | --- | --- |
| Gesamtprojekt | T1: beide Jobs, PHP/MySQL, Browser, Internationalisierung, Accessibility, Dependency-Audit | Keine vollständige Master-/Produktionsabnahme | Umfangreiche fehlende Module; lokale Linux-Laufzeit |
| Marktplatz/Inserate | T1: 34 allgemeine HTTP-Fälle, Bildsortierung 11 Unit + 11 Integration, reale Bilder/Sortierung/Verlauf im Browser | Neue Transaktionen, Autosave, neue Attribute und Relisting noch ohne Implementierung/Tests | Daten-/Zustandsmodell erweitern |
| Auktionen/Gebühren | T1: Gebots-/Worker-/Nebenläufigkeitsfälle; 20 Zahlungs-/Gebühren-Unitfälle; Browsergebot | End-to-End-Gebührenforderung, Gratisanspruch und Korrekturen fehlen | Gebührenvertrag je Durchlauf, alte Auktionen nicht rückwirkend belasten |
| PayPal | T1: synthetische Capture/Webhook-, Signatur-/Betrags-/Replay- und Parallelprüfungen | Echte Sandbox/Live, Erstattungsworkflow | Geeignetes Konto, Credentials/Webhook; erhebliche Integration bleibt intern |
| Finanzen | T1: 21 Finanzfälle, einschließlich konkurrierendem Capture/Webhook; PDF/Snapshot/Korrektur | Auktionsforderungen, weitere Kaufarten, echte Steuer-/Buchhaltungsabnahme | Fachregeln und unveränderliche historische Grundlage |
| Treuhand | Kein Workflowtest; Nichtverfügbarkeit ist kein Funktionsnachweis | Gesamter technische Workflow, Provider/Disputes/Payout | Implementierung fehlt; geeignete Infrastruktur/Verträge zusätzlich extern |
| Bitcoin/Bank | Kein Ende-zu-Ende-Nachweis; nur Ablehnung nicht verfügbarer Methoden | Node-/Bankabgleich und alle Fehlerzustände | Implementierung fehlt; Nodes/Konten/Schnittstellen zusätzlich extern |
| E2EE/Anhänge | T1: 9 Crypto-/Chatfälle betreffen ausschließlich Serververschlüsselung/Zugriff | Keine E2EE-, Geräte- oder verschlüsselten Chatdateitests | E2EE-Architektur/Client-Keyverwaltung fehlen |
| Konten/Sicherheit | T1: 17 Auth-, 25 Konto/Mail-, 17 Governancefälle; acht mobile Login/Registrierung/Reset/Profilabläufe | Passkeys/Nutzer-2FA/Einzelsessions/Profilmedien; echte unabhängige Prüfung | Neue Protokolle/Recovery; lokales WSL/Docker nicht fertig |
| Händler | T1: 10 Business- und 9 Business-HTTP-Fälle, konkurrierende letzte Platzreservierung | Mitarbeiter-/Abo-/Warenwirtschafts-/APItests | Mandantentrennung und Abomodelle fehlen |
| Acht Sprachen | T1–T3: Schlüssel-/Platzhalterparität, acht mobile Konto-/Setup-/Benachrichtigungs-/Dashboard-/Footerabläufe | Übrige Seiten, Rechtsübersetzungen/Review | Keine vollständige Seiteninventur/Sprachabnahme; neue Funktionen erhöhen Übersetzungsumfang |
| Suche/PLZ | T1: 9 Geo-, 22 Discoveryfälle, PLZ-Validator; DE-Dry-run 8.289 Zeilen, keine Dubletten | Realdatenimport und grenzüberschreitende Abdeckung, Autocomplete/Attributsuche | Rechte/Qualität externer Quellen; DE-Rechte sind als Nutzerangabe vorhanden |
| SEO/Performance | T1: 11 SEO-Fälle einschließlich Datenbank-Sitemap, Browserbreiten | Kein repräsentativer Last-/CWV-Nachweis; hreflang fehlt | Repräsentativer Datenbestand/Zielhardware fehlt |
| Mail/Hinweise | T3: 28 Konto/Mailfälle, sieben Sprachlinkfälle, Präferenzbrowser; Such-/Handelsereignisse und synthetische MTA-Annahme | DNS/TLS/realer Posteingang; fehlende Events | Mailassistent fehlt, echte Domain/Relay-Konfiguration |
| Admin/Setup | T1: 7 Admin-, 11 Setup-Unit-, 9 Setup-Integrationsfälle, acht mobile Setup-Sprachen | Vollständig konfigurierender Assistent und alle Adminseiten | Diagnoseansicht muss zur geführten Einrichtung erweitert werden |
| Installation | T1: 10 Installer-/Konfigurationsfälle, Manifestprüfungen und 4 Backupfälle inkl. echtem synthetischen MySQL-Restore | Kein vollständiger Ubuntu-Produktivinstallations-/Offsite-Compose-Restore | Zielserver/DNS/Root-Aktionen und lokale Laufzeit fehlen |
| Tests/Sicherheit | T1 erfolgreich; konkrete Negativ-/Berechtigungs-/Racefälle und Audit | Vollständige Angriffs-/Last-/E2EE-/Provider-/manuelle A11y-Abnahme | Unabhängige Fachprüfung und neue Funktionen fehlen |
| Releasevorbereitung | Synthetischer Restore und vorhandene CI dokumentiert | Produktiver Disaster-Recovery, reale Alarmierung/Rotation, Schlussabnahme | Infrastruktur, Betreiberprozesse, externe Freigaben |

Es werden keine Testergebnisse einer vorhandenen Teilfunktion auf ein noch
fehlendes System übertragen. Automatische Accessibility prüft ausgewählte
öffentliche Seiten und ersetzt keinen manuellen Screenreader-/Zoomtest.

## 3. Arbeitsstunden: vier unterschiedliche Ziele

Kumulative Reststunden ab diesem Stand, **nicht die Zeilen addieren**.
Optimistisch: passende Bibliotheken/Provider, wenig Nacharbeit, klarer Umfang.
Realistisch: normale Integrations-/Review-/Fehlerkorrekturschleifen.
Pessimistisch: Protokoll-/Datenmodelländerungen, Providerprobleme und erhebliche
Auditnacharbeit. Auch die pessimistische Spanne ist keine garantierte Obergrenze.

| Ziel | Optimistisch | Realistisch | Pessimistisch | Bedeutung |
| --- | ---: | ---: | ---: | --- |
| A. Technisch funktionsfähige Basisplattform | 80–140 h | 180–320 h | 400–650 h | Begrenzte bestehende Kernabläufe reproduzierbar in Staging betreiben, kritische Betriebslücken schließen; E2EE/Treuhand/Bank/Bitcoin und nicht fertige Dienste ausdrücklich nicht anbieten. Kein vollständiger Masterabschluss. |
| B. Alle 26 Anforderungen plus E2EE/Setup vollständig implementiert und in isolierter Entwicklungsumgebung getestet | 1.400–2.200 h | 2.100–3.500 h | 4.000–6.500 h | Neue Module und lokale Funktions-/Negativtests. Echte Provider bleiben ohne externe Voraussetzungen gesperrt. Lokale WSL-Laufzeit muss zusätzlich tatsächlich funktionieren. |
| C. Technisch releasefähig, vollständiger Masterumfang, Integration und Dokumentation | 1.700–2.700 h | 2.500–4.300 h | 5.000–8.000 h | Staging, vollständige Regression, Betriebs-/Installations-/Restoreprüfung, technische Auditbefunde, Übergabe. Fehlende externe Livefreigaben weiterhin als geschlossene Gates. |
| D. Tatsächlicher produktionsfähiger Betrieb | 1.800–2.900 h | 2.600–4.550 h | 5.200–8.500 h | C plus interne Aktivierungs-/Betriebsabnahme. **Externe Prüfungsarbeit und deren Wartezeit nicht enthalten und nicht seriös terminierbar.** |

Die größten Unsicherheiten sind E2EE-/Recoverydesign, Treuhandprovider und seine
Zustandsregeln, steuerliche/vertragliche Anforderungen, internationale
Rechtsübersetzungen, Datenmigrationen und Befunde aus unabhängigen Prüfungen.
Nicht jedes Problem wird durch Zugangsdaten lösbar: Bank, Bitcoin und Treuhand
besitzen noch erhebliche interne Implementierungslücken.

Arbeitsstunden sind produktive Entwicklungs-/Integrations-/Prüfarbeit eines
qualifizierten Teams. Sie sind weder Tokenverbrauch noch verstrichene Codex-Zeit.
Ohne gemessenen Durchsatz vergleichbar schwieriger, abgenommener Arbeitspakete
kann keine belastbare KI-Beschleunigung oder ein Fertigstellungsdatum berechnet
werden. Commitzahl und mehrere grüne Sprachtests liefern diesen Durchsatz nicht.

Nur als Rechenbeispiel: 2.500–4.300 Stunden bei 100 tatsächlich produktiven
Teamstunden/Woche entsprechen 25–43 Wochen; bei 25 Stunden/Woche 100–172 Wochen.
Das ist **keine Prognose für dieses Projekt**. Abhängigkeiten, Einarbeitung,
Parallelisierungsgrenzen und externe Wartezeiten verlängern Kalenderzeit.
Realistisch sind etwa **24–36 weitere überprüfbare Entwicklungsetappen**, mit
mehreren Teilabnahmen für E2EE, Handels-/Zahlungsmodelle und Betrieb.

## 4. Priorisierter Restumfang und Fertigstellungsnachweise

P0 = kritisch für sicheren Betrieb der jeweils aktivierten Funktionen.
P1 = notwendig für den vereinbarten vollständigen Umfang, auch wenn vorerst
deaktiviert. P2 = zusätzliche Optimierung. BLOCKIERT = die bezeichnete externe
Abnahme benötigt Voraussetzungen; unabhängig implementierbare Teile bleiben frei.
Die Nummerierung folgt Kapitel 9 des neueren Ultimate-Masterprompts; die ältere
Fortsetzungsdatei fasst einzelne Punkte anders zusammen.

### Zuerst: technische Startblocker

| Priorität | Aufgabe | Erforderlicher Fertigstellungsnachweis |
| --- | --- | --- |
| P0 | Entwicklungs-/Staging-Laufzeit fertigstellen: Windows-Speicher, ausstehende WSL-Aktion, Ubuntu/Docker, echte Python/Node/PHP/Composer/Browserwerkzeuge | Isolierten Runner im vorgesehenen Linux/WSL erfolgreich ausführen, Daten/Volumes erhalten, Schritte dokumentieren |
| P0 | Echter Wartungsmodus und Wiederanlauf | HTTP/API schreibgeschützt/gesperrt, Adminausnahme geprüft, Worker-/Webhooks definiert behandelt, faire Auktionsfortsetzung und keine verlorene Zahlung |
| P0 | Gebühren-/Vertrags-/Transaktionsmodell vor Aktivierung kostenpflichtiger neuer Abläufe | Kein rückwirkender Tarif, exakte Zustimmung/Snapshots, konkurrierende Wiederholungen erzeugen genau eine Forderung, korrekte Korrekturbelege |
| P0 | Schlüssel-/Backup-/Releasebetrieb | Neuinstallation und Update auf Staging, Wiederanlauf nach Neustart, überprüfter externer Restore inklusive Schlüssel/Dateien/Schema, Alarmzustellung |
| P0 | Historische Secrets/Daten und Laufzeitrechte prüfen | Rotation/Retention nach Betreiberentscheidung, keine geheimen Daten in neuen Commits/Logs, minimale DB-/Dateirechte. Keine ungeprüfte History-Umschreibung |
| P0 | Sicherheits- und Fachabnahme des Releasekandidaten | Alle relevanten Tests auf exakt freizugebendem Commit, keine offenen kritischen Befunde, dokumentierte Betreiber-/Provider-/Rechtsfreigaben |

### Alle 26 Anforderungen einzeln

| Nr. | Priorität/Stand | Noch zu erledigen | Technischer Fertigstellungsnachweis |
| --- | --- | --- | --- |
| 1 | P0 vor Gebührenaktivierung; offen | Einstellgebühr an autorisierten Auktionsdurchlauf binden | Veröffentlichung/Retry/Rollback/Parallelität: genau eine korrekte Forderung; keine Altauktion nachbelasten |
| 2 | P0 vor Gebührenaktivierung; offen | Erfolgsprovision bei gültigem Abschluss/Sofortkauf | Centgenauer Zuschlagssnapshot; kein Zuschlag/Review/Abbruch erzeugt keine falsche Gebühr |
| 3 | P0; teilweise | Gebühren mit unbezahlten Forderungen, Korrekturen, Steuern und Rechnungen verbinden | Buchungs-/Korrekturhistorie vollständig, keine eigenmächtige Stornierung erbrachter Gebühren, PDF/Export konsistent |
| 4 | P0; teilweise getestet | Dokumentbindung auf neue Kaufarten/Bestandskonten vervollständigen | Fehlende, veraltete oder manipulierte Fassungen verweigert; unveränderliche Zustimmung je Vorgang |
| 5 | P1; teilweise getestet | Alle acht Sprachen auf sämtlichen Seiten/Validierungen/Mails/Rechtsdokumenten | Seiteninventar, keine fehlenden Keys/gemischten Systemtexte, mobile Fehler-/Erfolgsabläufe; Rechtsreview separat |
| 6 | P1; offen | Administrierbare typisierte Kategorieattribute | Typ-/Pflicht-/Optionsvalidierung, Rollenprüfung, additive Migration und Bestandsfälle |
| 7 | P1; offen | Strukturierte Produktklassifizierung durchgängig | Klassifikation in Erfassung, Detail, Suche und Import konsistent; ungültige Kombinationen verweigert |
| 8 | P1; teilweise | Autocomplete, öffentliches Relevanzranking, Attributfilter | Kombinierte Länder-/Preis-/Attribut-/Radiusabfragen, Rankingszenarien und Missbrauchslimits |
| 9 | P1; teilweise | Autosave und vollständige Vorschau | Verlust-/Konflikt-/Mehrtabtests, kein Fremdzugriff, Vorschau entspricht Veröffentlichung |
| 10 | A: erledigt | Bildsortierung nur in späteren Regressionen erhalten | Bestehende 11 Unit + 11 Integrationsfälle und echter Browserbildtausch grün |
| 11 | P1; offen | Verlängern, Wiedereinstellen, genau einmal kostenlos nach erfolgloser Auktion | Durchlaufmodell, Fristen, Gebühren-/Gratisanspruch, paralleler Doppelversuch verweigert |
| 12 | P1; offen | Verhandlungsmodus mit Angeboten/Gegenangeboten | Authentisierte Zustände, Ablauf, Annahme nur einmal, unveränderliche Konditionen |
| 13 | P1; teilweise | Vollständige Festpreis-Transaktion statt bloßem Inserat/Verkäuferstatus | Reservierung/Kauf/Abbruch/Erfüllung/Streitfall; konkurrierende Käufer ohne Doppelverkauf |
| 14 | P1; teilweise | Bewertungen auf alle tatsächlichen Transaktionsarten erweitern | Nur berechtigte Parteien abgeschlossener Vorgänge; einmalig, keine Eigen-/Fremdbewertung |
| 15 | P1; teilweise | Preisänderungs-, Ablauf- und Vorablaufereignisse, fehlende Statushinweise | Ereignismatrix, Dedupe, Opt-in, Empfängersprache, begrenzte Worker, atomare Wiederholung |
| 16 | P1; teilweise | Geräte-/Sitzungsübersicht und Einzelwiderruf | Aktives Gerät erkennbar, fremde Sessions unsichtbar, widerrufenes Gerät bei nächster Anfrage gesperrt |
| 17 | P1; teilweise | Nutzer-TOTP, Passkeys, Enrollment, Recovery, Widerruf | Challenge-/Origin-/Replay-/Recoverytests; bestehende verpflichtende Personal-MFA bleibt wirksam |
| 18 | P1; offen | Profilbilder und Händlerlogos | Reencoding, Typ/Größe/Quoten, Rechte, sichere Auslieferung/Löschung; aktive Inhalte verweigert |
| 19 | A: erledigt | Sitzungsbasierter Verlauf/Vergleich nur in Regressionen erhalten | Opt-in, Nutzertrennung, Löschung/TTL, höchstens vier Vergleichsartikel; keine erfundene Langzeitsynchronisierung |
| 20 | P1; teilweise | Händlerpersonal, Rollen, Abos, Warenwirtschaft, API | Mandantentrennung, Bestand/Races, Abo-Lebenszyklus, Tokenwiderruf/Scopes/Rate-Limits |
| 21 | P1, Optimierung P2; teilweise | Internationale URL-/hreflang-Regeln und SEO-Abnahme | Nur öffentliche Inhalte indexierbar, Sprachzuordnung, Sitemapgrenzen, reale Struktur-/Crawlerprüfung |
| 22 | P0; offen | Wartungssperre gemäß Startblocker | HTTP/API/Worker/Auktionen/Zahlungen samt Wiederanlauf vollständig geprüft |
| 23 | P1 + BLOCKIERT für Providerabnahme | Automatische PayPal-Erstattung über vorhandene REST-Methode integrieren | Idempotente Teil-/Vollerstattung, asynchrone Status-/Fehlerabstimmung, echte Sandboxbelege |
| 24 | P1 + BLOCKIERT für Nodebetrieb | Bitcoin-Zahlungsworkflow | Testnetz/Node: Betrag, Ablauf, Bestätigungen, Reorg, Doppelereignisse; keine privaten Walletkeys im Webprozess |
| 25 | P1 + BLOCKIERT für Bankabnahme | Bankabgleich mit echten Referenzen | Import/API-Dedupe, falsche/Teil-/Mehrfachbeträge, keine Zahlung nur aufgrund einer Behauptung |
| 26 | P1 + BLOCKIERT für Echtgeldbetrieb | Vollständiger gesperrter Treuhandworkflow | Alle zehn Kundenschritte, Fristen/Storno/Streit/Teillieferungsregeln, getrennte Verbindlichkeiten, genau einmal zulässige Auszahlung |

### Zusätzliche verbindliche Masterbereiche

| Priorität | Aufgabe | Fertigstellungsnachweis |
| --- | --- | --- |
| P1, P0 vor E2EE-Versprechen | Echte E2EE samt verschlüsselten Anhängen, Schlüssel-/Geräteverwaltung und Migration der Historie | Zwei Benutzer/mehrere Geräte; Server erhält keinen Klartext/Dateischlüssel; Verifikation, Widerruf, Schlüsselwechsel, Recovery, Manipulation, Limits und sichere Dateiansicht geprüft; unabhängiger Kryptografie-Review |
| P1 | SMTP-/Mailassistent | Lokaler MTA/Relay, geschützte Credentials, TLS, DNS/SPF/DKIM/DMARC-Hinweise, Testannahme und bestätigter Posteingang getrennt; Fehlerdiagnose ohne Secrets |
| P1 | Geführtes Admin-Setup aller 18 Bereiche | Echte Eingabe-/Prüfschritte statt nur Statusdiagnosen, RBAC/MFA/CSRF, keine Shellausführung, kein öffentlicher Setupzugang |
| P1 | Einfacher Checkout/Rechnungsfluss | Kunde sieht Preis/Gesamtbetrag/Referenz/Status/PDF und nötige Zustimmungen; keine Eingabe technischer IDs; alle neuen Transaktionsarten integriert |
| P1 | DE-Koordinatenimport und weitere Länder | Geprüfter Hash, protokollierter Import, Quelle/Lizenz/Datenstand/Attribution, reale Länder-/Radiusfälle, wiederholbares Update ohne erfundene Punkte |
| P0/P1 | Installer, private Speicherorte, Backup/Restore, Scheduler/Monitoring | Frische VM und Updateinstallation, Erhalt bestehender Keys, Neustart, geprüfte Offsite-Wiederherstellung, tatsächliche Alarmierung |
| P1 + BLOCKIERT für Fachfreigaben | Vollständige Legal-Team-Übergabe | Tatsächliche Daten-/Zahlungs-/Steuer-/Länder-/Identitäts-/Vertragsprozesse und deaktivierte Dienste dokumentiert; geprüfte Rechtsfassungen/Zustimmungen |
| P2, gemessene Probleme ggf. P0 | Performance und umfassende Accessibility | Repräsentatives Lastprofil/CWV, mobile Breiten, Tastatur/Screenreader/Zoom, keine kritischen Zugänglichkeitsfehler |
| P0 vor Launch | Gesamtabnahme und Betriebshandbuch | Exakter Releasecommit, Feature-Gates, Runbooks, Verantwortliche, Incident-/Rollbackplan, technische und externe Freigaben getrennt |

## 5. Was der Betreiber bereitstellen oder freigeben muss

1. Genügend lokalen Speicher sowie die erforderliche Windows-/WSL-/Docker-
   Administratoraktion. Die letzte protokollierte WSL-Installation scheiterte
   mit MSI1603 bei etwa 465 MB frei und etwa 1,56 GB benötigtem Installationsplatz;
   das ist keine Aussage über den insgesamt nötigen Docker-Speicher. Nichts
   wurde ungefragt gelöscht oder neu gestartet. Native PHP-Tests funktionieren,
   Python zeigt derzeit nur den Store-Alias; vollständige Linux-Tests laufen in CI.
2. Für Staging/Produktion: Server, Domain/DNS-Zugriff, HTTPS-/Mail-/Relaydaten,
   Backupziel samt getrenntem Schlüsselzugang, Alarmempfänger und Betriebszuständigkeit.
3. Betreiberidentität, Steuer-/Rechnungsregeln, Gebühren-/Länder-/Handelsregeln
   und Prüfung durch Legal-/Steuerteam. Rechtsübersetzungen nicht automatisch freigeben.
4. Geeignetes PayPal-Geschäftskonto, echte Sandbox-Credentials/Webhook und spätere
   Providerfreigabe; separate Bankdaten/Schnittstelle und Bitcoin-Test-/Nodezugang.
   Geheimnisse gehören in private Konfiguration, nicht in Git oder diesen Bericht.
5. Für Treuhand: tatsächlich geeigneter Zahlungsdienstleister, Vertrags-/Freigabe-
   nachweise und definierte Streit-/Auszahlungsregeln. Ein privates PayPal-Konto
   ersetzt diese Voraussetzungen nicht. Lokale Workflowentwicklung bleibt möglich.
6. Budget/Zeit für unabhängige Sicherheits-/Kryptografieprüfung, Sprach-/Rechts-
   abnahme und reale Betriebsprobe. Zusätzliche Länderquellen benötigen eigene
   Lizenzprüfung; die deutsche Datei benötigt keine erneute pauschale Rechtefrage.

## 6. GitHub, Tests und genaue Wiederaufnahme

Repository: `LLMbutHuman/GunIndex`; Branch: `feature/general-marketplace-rebuild`.
Kein Reset, Force-Push, Merge nach `main`, produktiver Datenimport oder Livegeldfluss.

| Commit | Sicherung | Teststand beim letzten Abruf |
| --- | --- | --- |
| `35b99fe89c07ba88b499edcf05abe0958b488ab8` | Gepusht | CI 41484172147 vollständig erfolgreich |
| `505ffa19f0f1b5f29089cfe69435e41f655f980b` | Gepusht, Remote bestätigt | T1 vollständig erfolgreich, Logs geprüft |
| `ad5ed5f95386005470c15c7db2e1b5c4c8efd5a2` | Gepusht, Remote bestätigt | T2 vollständig erfolgreich |
| `2042549d5cec2bc39f713ebc18a76f8ab30fc3af` | Letzter Codecommit lokal und remote, SHA identisch | T3 vollständig erfolgreich; zusätzlich lokale Syntax-/Sprachprüfungen bestanden |

Unmittelbar vor Erstellung dieses Berichts zeigte `git status --short` nur die
absichtlich private, unversionierte `GunIndex_Codex_Fortsetzung.md`. Alle begonnenen
Codeänderungen einschließlich der vom Nutzungslimit unterbrochenen Dashboard-
Etappe wurden erhalten, geprüft und gesichert. Bericht/Abgleichdokumente werden
danach separat committed. Der Dokumentationscommit kann seine eigene SHA nicht
vorab enthalten; sein Identifikator steht in Git und der abschließenden Antwort.

Die lokale Patchsicherung und portable PHP-Werkzeuge unter `tests/.runtime/`
bleiben ignoriert. Sie sind keine GitHub-Sicherung privater Betriebsdaten und
werden nicht hochgeladen. Die historische automatische Freigabeprüfung war
vorübergehend durch Nutzungslimit blockiert; reguläre Freigabe und Push waren
anschließend wieder möglich, ohne Umgehung.

Bekannte Ausfälle: lokale WSL-Installation/Speicher, native Python-Ausführung
wegen Store-Alias und zeitweilige Agent-/Freigabequoten. Keine fehlgeschlagene
Anwendungsregression in T1/T2/T3; alle drei Läufe sind vollständig bestätigt.
Das belegt vorhandene geprüfte Funktionen, nicht den noch fehlenden Masterumfang.

**Wiederaufnahme in dieser Reihenfolge:**

1. Aktuellen Branch/HEAD/Remote/Arbeitsbaum prüfen; private Auftragsdatei erhalten.
   T1/T2/T3 sind bereits erfolgreich abgeschlossen; nicht ohne Anlass wiederholen.
   Den nachfolgenden reinen Berichtcommit bei Bedarf abgleichen. Keine bereits
   abgeschlossenen Kontoseiten/Setup-/Bild-/Verlaufsmodule neu schreiben.
2. Lokale Linux-Laufzeit mit den notwendigen Betreiberaktionen fertigstellen;
   solange möglich die vorhandene isolierte GitHub-CI verwenden.
3. Nächste große Implementierung: unveränderlicher Auktions-Gebührenauftrag
   je Durchlauf mit vorab akzeptiertem Tarif/Steuer-/Rechtssnapshot. Danach
   Veröffentlichungs-/Abschluss-/Sofortkauf-Hooks, idempotente Forderungen und
   Korrekturen. `finance_prepare_order()` benötigt für Erfolgsgebühren die
   tatsächliche Verkaufsgrundlage; keine Ergänzung alter Aufträge mit heutigen Daten.
4. Wartung/Wiederanlauf und vollständige Transaktionsmodelle nach den P0/P1-
   Abhängigkeiten umsetzen. Preis-/Ablaufbenachrichtigungen wurden nur als nächste
   mögliche Aufgabe identifiziert; dafür ist noch kein neuer Code vorhanden.
5. Nach jeder funktionalen Etappe gezielte Tests, Diff-/Geheimnisprüfung,
   Commit/Push und aktualisierte tatsächliche Nachweise. Provider bleiben gesperrt.

## 7. Direkte Antworten

- **Insgesamt fertig:** ungefähr 40 %, unsicherer Bereich 30–45 % des vollen
  technischen Masterumfangs; keine Aussage, dass 40 % produktionsfreigegeben wären.
- **Fehlen:** ungefähr 60 %, plausibel 55–70 %.
- **Entwicklungsstunden:** bis C realistisch 2.500–4.300; eine begrenzte Basis A
  deutlich früher mit 180–320 Stunden. Externe Abnahmen kommen getrennt hinzu.
- **Fünf größte Restpakete:** E2EE/Geräte/Anhänge; vollständige Kauf-/Auktions-
  und Gebührenabrechnung; Treuhand; Bank-/Bitcoin-/Providerabstimmung; vollständige
  Einrichtung und Betriebs-/Sicherheitsabnahme. Händler- und Übersetzungsumfang
  bleiben daneben ebenfalls erhebliche eigenständige Pakete.
- **Technischer Release verhindert durch:** fehlende verbindliche Funktionen,
  nicht sperrenden Wartungsmodus, fehlende Ende-zu-Ende-Abnahme neuer Zahlungs-/
  Kaufarten, unvollständige Installation/Restore/Sprach-/Sicherheitsprüfung.
  Fehlende Zugangsdaten sind nicht die einzige Ursache.
- **Persönlich erforderlich:** lokale Betriebssystemaktionen, Server/DNS/Backup,
  Betreiber-/Steuer-/Rechtsangaben, geeignete Providerkonten und externe Reviews
  gemäß Abschnitt 5. Routineentwicklung ist bereits autorisiert.
- **Weitere Etappen:** ungefähr 24–36; Größe und Zahl nach den ersten großen
  Zustandsmodell-/E2EE-Arbeiten neu schätzen.
- **Fertigstellung bei gleicher Geschwindigkeit:** derzeit kein seriöses Datum.
  Es fehlt eine belastbare Messung abgenommener Arbeit vergleichbarer Komplexität.
  Nutzungslimits lassen sich nicht in einen Fertigstellungstermin umrechnen.

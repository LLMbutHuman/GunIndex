# Implementierungsfortschritt

## Registrierung: weitere Übersetzungsumsetzung

Registrierungsseite, zugehörige Validierungen und Fehler in acht Sprachen
implementiert. Die voreingestellte Kontosprache folgt jetzt der gewählten
Oberflächensprache. Dokumente und Benutzereingaben werden nicht übersetzt.
Der Kontokatalog umfasst jetzt 42 neue Schlüssel pro Sprache. Globale
Schlüssel-/Platzhalterparität, PHP-Syntax der Änderung und Node-Syntax bestanden.
Der internationale Browserlauf prüft zusätzlich alle acht Registrierungsseiten,
die voreingestellte Sprache, eine echte ungültige Anmeldung zum Konto und mobile
Breiten. Vollständiger CI-Nachweis für diese Registrierungserweiterung ausstehend.

Login-/MFA-Lokalisierung ist bereits als `b5677bd8f15c0ee71b9e9f05d6dd0c72ca2defb4`
gepusht (Remote-SHA bestätigt), CI `41483417303` zum Zeitpunkt dieses Eintrags
noch in Arbeit. Wiederherstellung, E-Mail-Verifikation und Profilseiten bleiben
im vollständigen Übersetzungsauftrag offen.

## Neue Etappe: lokalisierter Kontozugang

Anmeldung und bestehende MFA-Seite vollständig auf Übersetzungsschlüssel
umgestellt. 26 neue Texte in acht Sprachen. Auch Auth-Eingabe-/Passwort-/Rate-
Limitmeldungen und allgemeine Formular-/CSRF-Fehler verwenden die gewählte
Sprache; Fehlerdokumente erhalten das passende HTML-Sprachattribut.
Keine Änderungen an MFA-Protokoll, Zugriffsschutz oder Ratelimits.

Lokal bestanden: 17 Auth-Unitfälle mit Sodium, sieben Geo-/Sprachfälle, 14 Consent-
Fälle, 187 PHP-Syntaxprüfungen und Node-Syntaxprüfung. Internationaler Browsertest
prüft nun je Sprache echte fehlgeschlagene Anmeldung, lokalisierte Fehlermeldung,
CSRF-Ablehnung und mobile Breite. Diese neuen Browserfälle bis zum folgenden
CI-Lauf noch ausstehend. Registrierung, Wiederherstellung und weitere Konto-
seiten bleiben im vollständigen Übersetzungsauftrag separat offen.

## Zuletzt vollständig geprüfter Code: 0194d59

Stand 2026-11-02: Branch `feature/general-marketplace-rebuild`, Repository
`LLMbutHuman/GunIndex`, letzter Codecommit
`0194d59cac1bad2cd42ab67d409f2ceccdf5d7be`. Push und identische Remote-SHA bestätigt.
[CI 41481388425](https://github.com/LLMbutHuman/GunIndex/actions/runs/41481388425)
vollständig erfolgreich: Windows und Linux, PHP/MySQL, sechs neue automatische
Finanzfälle einschließlich zwei konkurrierender Prozesse, bestehende Vertrags-/
Browser-/internationalen-/Accessibilityprüfungen und Composer-Audit.
Die nachfolgenden älteren „noch ausstehend“-Vermerke dokumentieren Zwischenstände.

Seit der letzten Wiederaufnahme gesichert: `126d400` persönliche Listen;
`dc046ae` Rechtsdokumentbindung; `699c35b` automatischer Finanzabschluss;
`0194d59` echte Parallelregression, Übersetzung und Abnahmeliste.
Alle vier Codestände besitzen erfolgreiche eigene CI-Läufe. Keine Live-Zahlungen.
Aktuelle Gesamtbewertung: TECHNICAL_ACCEPTANCE.md; fachliche Übergabe als
unfertiger Entwicklungsstand: LEGAL_HANDOFF.md. Gesamtauftrag bleibt offen.

### Konkrete Fortsetzung

1. Auktionsgebühren: unveränderlicher Gebührenauftrag/Tarif je neuer Auktion,
   Vorabzustimmung und ursprüngliche Rechnungsdaten sichern; keine nachträgliche
   Belastung bestehender Auktionen. Dann Gebühren in Veröffentlichung und beide
   Abschlusswege atomar verbinden. Unbezahlte Forderungen und Korrekturen benötigen
   ein eigenes Modell; normales Nutzerstorno darf erbrachte Gebühren nicht löschen.
2. `finance_prepare_order()` benötigt für Erfolgsgebühren eine tatsächliche
   Verkaufsgrundlage statt `fees_quote(service, null)`. Bestehende Altaufträge ohne
   Snapshot dürfen nicht stillschweigend mit heutigen Rechnungsdaten ergänzt werden.
3. Automatische Erfüllung neuer Leistungsarten, Wiedereinstellung/Gratisanspruch
   und vollständige Transaktionsabläufe anschließen; aktuelle Prüfungen erhalten.
4. Übrige Masterpunkte gemäß TECHNICAL_ACCEPTANCE.md modular umsetzen. E2EE,
   vollständige Altseitenübersetzungen, Wartung, Installer und SMTP-Assistent sind
   weiterhin echte Entwicklungsaufgaben, keine allein externen Freigaben.
5. Lokal muss vor weiteren WSL-/Docker-Installationen ausreichend Speicherplatz
   vorhanden sein und die ausstehende Windows-Aktion abgeschlossen werden.
   Keine Dateien/Volumes ungefragt löschen oder den Rechner neu starten.

Die private Auftragsdatei `GunIndex_Codex_Fortsetzung.md` bleibt absichtlich
unversioniert. Portable Testwerkzeuge liegen ignoriert in `tests/.runtime/`.
Keine Resets, Änderungen an `main`, historischen Imports oder alten Cloud-Workspaces.

## 2026-11-02: Fortsetzung der begonnenen zweiten Etappe

Gesicherte Ausgangsbasis: `754adf167823dbaf986a39a8a35f209686d2e5be`.
Alle begonnenen lokalen Änderungen erhalten. Die erste Etappe ist inzwischen
auch durch [GitHub Actions 41356788528](https://github.com/LLMbutHuman/GunIndex/actions/runs/41356788528)
für `754adf1` mit `success` bestätigt; dies gilt noch nicht für die neuen Dateien.

### Laufzeit

WSL-Installer meldete Installation der Virtual Machine Platform, WSL und Ubuntu
sowie einen erforderlichen Windows-Neustart. Kein Neustart ausgelöst.
Anschließende WSL-Prüfung außerhalb der Sandbox scheitert am Speicherplatz:
1.556.019 KB benötigt, etwa 465.096 KB frei auf C:, MSI-Fehler 1603.
Vor dem nächsten Versuch Platz schaffen und ausstehende Windows-Aktion
abschließen. Keine privaten Dateien oder Volumes zum Platzgewinn gelöscht.
Docker-/Linux-Laufzeit lokal weiterhin nicht einsatzfähig. Linux-Runner nicht
nativ unter Windows ausgeführt; neue DB-/HTTP-Suites für CI ergänzt.

Offizielle portable PHP-CLI 8.4.27-1 im ignorierten `tests/.runtime/tools/`,
geladen von `https://downloads.php.net/~windows/releases/php-8.4.27-1-nts-Win32-vs17-x64.zip`.
SHA-256 gegen offizielle Release-Metadaten geprüft:
`dbae154a0a89791f36c3d48e68bae797878333e7d6cb5721e74617de59b63ab7`.
Nur für Syntax- und datenbankfreie PHP-Prüfungen verwendet.

### Neue Funktionen, vollständige Integration noch zu bestätigen

- MFA-/berechtigungsgebundene Admin-Einrichtungsübersicht mit 18 tatsächlichen
  Konfigurations-/Betriebsdiagnosen und Links zu bestehenden Einstellungen.
  Übersetzung aller neuen Texte in acht Sprachen. SMTP-Eingabeassistent und
  tatsächliche Testzustellung bleiben eigenständige offene Anforderungen.
- Bildreihenfolge im Inseratsspeicherprozess: Permutations-/Revisionsprüfung,
  Listing-Sperre, atomare Positionswechsel, Besitz-/Gebots-/Ablaufschutz und
  private Vorschaubilder. Sechs neue Texte in acht Sprachen. MySQL-/HTTP-
  Regression mit elf Fällen ergänzt; keine Änderung bestehender Bilddateien.
- SEO-Grundfunktionen mit standardmäßig geschlossenem Indexierungsgate,
  Canonicals, öffentlichen Product-Daten, robots und begrenzten Sitemapseiten.
  Versteckte Inserate, inaktive Länder/Kategorien/Verkäufer und abgelaufene
  Auktionen ausgeschlossen. Mehrsprachige URLs/hreflang bleiben offen.
- Lesender PLZ-Validator, synthetische Tests und belegtes Quellenregister für
  Nachbarländer. Keine externen Daten oder historischen Datensätze importiert.

### Ausgeführte Prüfungen und nächste Schritte

19 Zahlungs-/Gebühren-Unitfälle mit synthetischem Transport, 17 Auth-, sieben
Admin-, elf Bildreihenfolge-, elf SEO-, elf Setup- und sieben Geo-Unitfälle
bestanden: insgesamt 83 PHP-Prüfungen. Alle 157 gefundenen Projekt-PHP-Dateien
bestanden die Syntaxprüfung. Die nach vollständiger Lokalisierung erneut
ausgeführte globale Schlüssel-/Platzhalterparität besteht für alle acht Sprachen;
der vorübergehende Fehler des Zwischenstands ist behoben.
Zehn Node-PLZ-Testgruppen bestanden. Bestehender PHP-Importer real im Dry-run:
8.289 eindeutige Einträge, null Dubletten, SHA-256 wie unten dokumentiert.

Neue Suites sind im sequenziellen Runner verbunden. Fortsetzung: selektiver
Commit/Push, CI-Ergebnisse prüfen und Fehler beheben. Keine vollständige technische Abnahme
dieser zweiten Etappe behaupten, bevor die neuen Integrationsprüfungen bestehen.

### Bestätigte Sicherung und CI-Abnahme der zweiten Etappe

Commit `2d496a880b6e8ebb431f258646359adf858395ba` wurde auf den vereinbarten
Entwicklungsbranch gepusht; Remote-SHA bestätigt. [CI-Lauf 41418425597](https://github.com/LLMbutHuman/GunIndex/actions/runs/41418425597)
hat beide Jobs erfolgreich abgeschlossen: Windows-Vorprüfung, Linux-PHP-/MySQL-
Suites einschließlich neuer neun Setup-HTTP-/DB-Fälle, elf Bildsortierungs-HTTP-/DB-
Fälle und SEO-Sichtbarkeits-/Paginationstest, Composer-Audit sowie die bisherigen
Browser-, internationalen und automatisierten Accessibilityprüfungen.
Die lokale WSL-Speicherplatz-/Neustartvoraussetzung bleibt davon unberührt.

### Dritte Etappe: persönliche Listen

Neu implementiert: `/user/personal.php` mit explizit aktivierbarem Verlauf und
Vergleich von maximal vier Angeboten. Sitzung an Benutzer gebunden, keine Kopie
von Angebotsinhalten, Verlauf auf 20 Einträge begrenzt, sechs Stunden logische
Gültigkeit, Lösch-/Abschaltfunktionen und Bereinigung bei Konto-/Sitzungswechsel.
Öffentliche Sichtbarkeit bei jeder Anzeige neu geprüft. Alle neuen Texte in acht
Sprachen; Dokumentation in PERSONAL_LISTS.md. Neue DB- und Browserprüfungen sind
ergänzt und werden nach lokalen Unit-/Syntaxprüfungen durch CI bestätigt.
Der erweiterte Browsertest prüft zusätzlich echte Bildsortierung und alle acht
Sprachen der Setup-Seite bei mobiler Breite. Diese Erweiterungen sind noch
nicht vom erfolgreichen CI-Lauf für `2d496a8` abgedeckt.

Lokaler Nachweis der dritten Etappe: 15 Personal-Unitprüfungen, sieben Geo-
Prüfungen inklusive globaler Sprachparität und 168 PHP-Syntaxprüfungen bestanden.
Node-Syntaxprüfung des erweiterten Browsertests ebenfalls erfolgreich.

Bestätigte Sicherung: `126d4008df06f95be2892a280c33cebd776c6588`, Remote identisch.
[CI 41420258463](https://github.com/LLMbutHuman/GunIndex/actions/runs/41420258463)
hat beide Jobs erfolgreich abgeschlossen. Die neuen vier DB-Prüfungen für
persönliche Listen sowie Browserabläufe für Bildsortierung, Verlauf/Vergleich
und acht mobile Setup-Sprachen sind damit tatsächlich geprüft.
Im anschließenden Review wurde die Kartenklasse des Verlaufs auf das bestehende
responsive `cards` korrigiert; diese Änderung gehört zur nächsten Etappe.

### Vierte Etappe: Rechtsdokumentbindung als Gebührenvoraussetzung

Konfigurierbare Pflichtdokumente für Registrierung und bestehende Leistungs-
bestellungen, exakte Sprach-/ID-/Hash-Prüfung, erneute Prüfung unter Datenbank-
sperre, atomare Konto-/Auftragsbelege und exakte Versionslinks implementiert.
Neue additive Migration 011, eigene Belege im geschützten Datenauszug und in der
Zahlungsauftragsansicht. Acht Übersetzungen für die neue Bedienung. Leere
Pflichtdokumentlisten bleiben ausdrücklich „nicht eingerichtet“; keine erfundenen
Rechtsfreigaben oder nachträglichen Zustimmungen. Setup prüft beide Vorgänge in
allen acht Sprachen. Details und Grenzen: CONTRACT_BINDING.md.

Lokal bisher: 14 Consent-Unitfälle, sieben Admin- und sieben Geo-/Sprachprüfungen
sowie 179 PHP-Syntaxprüfungen erfolgreich. Neue DB-/HTTP-Fälle und Browserablauf
sind ergänzt; deren tatsächliche Ausführung steht bis zum CI-Lauf noch aus.

Gebührenanalyse bestätigt zusätzliche offene Abhängigkeiten: unveränderlicher
Auftrag je Auktionsdurchlauf; Rechnungsmodell für entstandene, unbezahlte Forderungen;
Stornoschutz bereits erbrachter Leistungen; gemeinsame idempotente Finanzbuchung
nach automatischer Capture-/Webhook-Verifikation. Bestehende Auktionen dürfen
ohne vorherigen Gebührenauftrag nicht rückwirkend belastet werden. Finanzsnapshots
bei späterer Aktivierung der Rechnungsstellung müssen gesondert behandelt werden.

### Bestätigte Vertragsbindung und nächste Finanzetappe

Vierte Etappe gesichert als `dc046aed7cb3232386c186ced3ff15c95d7c0713`,
Remote-SHA identisch. [CI 41478575081](https://github.com/LLMbutHuman/GunIndex/actions/runs/41478575081)
vollständig erfolgreich: beide Jobs, Migration 011, elf neue Vertrags-DB-/HTTP-
Prüfungen, 14 Consent-Unitfälle, Registrierung über echte Admin-/Browserabläufe,
bestehende Regressionen und Accessibilityprüfungen bestanden.

Nächste kleine Finanzetappe: gemeinsame idempotente Rechnung/Cash-Buchung nach
verifiziertem Capture/Webhook sowie Identitäts-/Referenz-/Tarif-/Summenbindung an
den unveränderlichen Snapshot implementiert. Vor automatischem Live-Auftrag oder
Capture werden fehlende/beschädigte Snapshots blockiert. Sandbox erzeugt keine
echte Finanzbuchung. Lokal 20 Zahlungs-Unitfälle bestanden; fünf zusätzliche
Datenbank-/Adapterfälle ergänzt, noch nicht ausgeführt. Keine echten Provideraufrufe.
Weiterhin offen: Forderungsmodell/Auktionsgebühren, historische Auftragsbehandlung,
automatische Leistungserfüllung für neue Diensttypen und externe Providerabnahme.

### Bestätigter Finanzabschluss und Parallelprüfung

`699c35beb69fc29356f44cc22dc7fdd4c475fb6a` erfolgreich gepusht, Remote identisch.
[CI 41480002310](https://github.com/LLMbutHuman/GunIndex/actions/runs/41480002310)
vollständig erfolgreich: fünf neue Capture-/Webhook-/Finanzfälle, bestehende
Regressionen, Browser und Accessibility. Keine externen Provideraufrufe.
Zusätzlich nun echte Konkurrenz zwischen zwei getrennten PHP-Prozessen ergänzt;
dieser sechste Fall steht vor dem nächsten CI-Lauf noch aus. Neue Snapshot-
Fehlermeldung in allen acht Sprachen vorhanden, lokale Zahlungs-/Sprachtests bestanden.
Aktueller Abgleich sämtlicher Masterpunkte: TECHNICAL_ACCEPTANCE.md.

## Historischer Einstieg, 2026-11-01


Arbeitsgrundlage: `GunIndex_Codex_Fortsetzung.md`, vollständig gelesen; dazu
README, IMPLEMENTATION_REPORT, TESTING, RELEASE_CHECKLIST, ARCHITECTURE,
DATABASE, INSTALLATION und INTERNATIONAL. Keine AGENTS.md im Checkout gefunden.

- Branch: `feature/general-marketplace-rebuild`.
- Lokaler Ausgangscommit und per `git ls-remote` bestätigter Remote:
  `1636914f33f0e4495b92eec1936dc54e110d7576`.
- Zu Beginn ausschließlich die vom Nutzer kopierte Auftragsdatei unversioniert.
  Sie bleibt erhalten und wird nicht automatisch veröffentlicht.
- Kein Reset, kein Altimport, keine Migration, kein Produktivzugriff.
- Git erreichbar; Node v18.2.0 vorhanden. Windows-Python nur nicht ausführbarer
  Alias. Docker/PHP nicht im PATH. WSL-Abfragen bestätigen keine nutzbare
  Ubuntu-WSL2-Distribution. Docker Desktop nicht am Standardpfad gefunden.

### Etappe 1: implementiert, begrenzt geprüft

Read-only-Vorprüfung `ops/check-windows.ps1` mit Text-/JSON-Ausgabe,
Fehlerstatus, begrenzten Prozesslaufzeiten und echter Ubuntu-WSL2-/Linux-Docker-
Prüfung. Keine Installer-Downloads oder automatische Administration.
Linux-Testrunner weist native Windows-Aufrufe vor Docker-/Dateiaktionen ab;
auch importierte Docker-Hilfsfunktionen sind geschützt.
Windows-Anleitung und CI-Prüfungen ergänzt.

Tatsächlich ausgeführt: zehn PowerShell-Prüfungen erfolgreich;
Vorprüfung meldet die fehlende Linux-Laufzeit mit Exitcode 1; `git diff --check`
ohne Befund. Python-Plattformtests ergänzt, aber lokal mangels Interpreter
nicht ausgeführt. CI-Lauf nach Push separat beobachten. Keine aktuellen
Anwendungs-/Browser-/Providerergebnisse behauptet.

Commit: `56d1158ab0aa9b79db5dcc0a12fc1d08e45af249`
(`build: add Windows development preflight and runtime guards`).
Nach der ausdrücklichen Vorgabe von Name/E-Mail ist die lokale Git-Identität
gesetzt. Die zehn PowerShell-Prüfungen bestanden erneut. Git bestätigte den
Push von `1636914` auf `56d1158` ausschließlich zum Entwicklungsbranch.
Ein erfolgreicher CI-Lauf ist damit noch nicht nachgewiesen.
Push-/Release-Nachweise stehen in `RELEASE_OPERATIONS.md`.

### Anforderungsabgleich und konkrete Restarbeiten

Dies ist die erste Bestandsbewertung anhand Dokumentation und gezielter
Codeprüfung, keine vollständige Funktionsabnahme. Alle folgenden Punkte bleiben
offen, soweit die vollständige Umsetzung und aktuelle Tests nicht nachgewiesen sind.

| Nr. | Vorhandene Grundlage | Noch zu implementieren/prüfen |
| --- | --- | --- |
| 1 | fees.php, market.php | Einstellforderung am richtigen Ereignis, Idempotenz |
| 2 | Gebührenrechner, Abschlussworker | Automatische Erfolgsforderung mit Zuschlagsbindung |
| 3 | finance.php, Snapshots/Korrekturbelege | Vollständige Gebührenhistorie und Auktionskorrekturen |
| 4 | governance.php, Rechtsversionen | Vollständige Pflichtdokumentbindung bei Registrierung/Checkout |
| 5 | Acht locales-Kataloge, Benachrichtigungsschlüssel | Alle Seiten, Fehler, E-Mails und Rechtsfassungen übersetzen |
| 6 | Kategorienverwaltung | Typisierte administrierbare Attribute und Migration |
| 7 | Allgemeiner Katalog | Durchgängige strukturierte Produktklassifizierung |
| 8 | discovery.php, Länder-/Radiusfilter | Autocomplete und kombinierte strukturierte Filter |
| 9 | Entwurfsstatus und Bearbeitung | Autosave und vollständige Veröffentlichungsvorschau |
| 10 | Sichere Bildaufbereitung | Komfortable Sortierung und Rechte-/Limitprüfung |
| 11 | Auktionszustände | Verlängern/Wiedereinstellen und einmaliger Gratisanspruch |
| 12 | Festpreis/Auktion | Eigenständige Verhandlung und Angebotsstatus |
| 13 | Auktionsbewertungen | Festpreiskauf, Konflikte, transaktionsgebundene Bewertung |
| 14 | Mailqueue, Präferenzen, Discovery-Deduplizierung | Preis-/Ablauf-/Vorablaufereignisse ergänzen |
| 15 | Sitzungsversionswiderruf | Einzelgeräte-/Sitzungsübersicht und Einzelwiderruf |
| 16 | Personal-TOTP | Optionale Nutzer-TOTP, Passkeys und Recovery |
| 17 | Inseratsbilder | Profil-/Logo-Uploads mit Quoten und Schutz |
| 18 | Serverseitige Chat-AEAD | Keine E2EE vorhanden; geprüftes Protokoll, Geräteverwaltung und Anhänge fehlen |
| 19 | Merkliste | Zuletzt angesehen, Vergleich und Löschverhalten |
| 20 | Händlerprofile, CSV/Massenverwaltung | Mitarbeiterrollen, Abos, API und Tokenlimits |
| 21 | Explizite öffentliche Routen | Sitemap, Canonical, strukturierte Daten und Noindex-Abnahme |
| 22 | maintenance_enabled ist nur ein Hinweis | Sperre über HTTP/API/Worker/Finanzen und Wiederanlauf |
| 23 | PayPal-Transport inkl. refund(), manuelle Korrekturen | Vollständiger automatischer Refund-Ablauf und echte Providerabnahme |
| 24 | Zahlungsmethode sicher nicht verfügbar | Watch-only-Infrastruktur und bestätigungsgebundene Zahlung |
| 25 | Zahlungsmethode sicher nicht verfügbar | Echte Kontodaten, Referenzen und Reconciliation |
| 26 | Nicht verfügbar, Gebührenvorschau | Gesperrter vollständiger Treuhandworkflow und getrennte Verbindlichkeiten |

Zusätzlich offen: MFA-Admin-Setup-Assistent, SMTP-/DNS-/Posteingangsnachweise,
geführter Installer über die Vorprüfung hinaus, tatsächlicher Restore/Offsite,
Länderkoordinatenquellen und fachliche Freigaben. Externe Zahlungsfreigaben
bleiben unverändert geschlossen. Keine historische Spezialware aktivieren.

### PLZ-Nutzerangabe

Der Nutzer erklärt ausdrücklich ausreichende Rechte an `ZIP/PLZ.csv`.
Das ist eine Nutzerangabe, kein unabhängiger Lizenznachweis und keine Freigabe
anderer Quellen. Lokaler SHA-256:
`3f8a041a81caceb948ba30b285ba80281d464cd22fc4a049eba4bebdd8aab59b`.
Bestehender Importer unterstützt `--format=legacy-de`, Quellen-/Lizenznotiz,
Hash, Dry-run und transaktionalen Import. Aktueller PHP-Dry-run und DB-Import
stehen mangels Laufzeit aus. Keine Änderung am Datensatz.

### Nächste Schritte

1. Ubuntu WSL2 samt Docker-Integration bereitstellen (Windows-Administration/
   gegebenenfalls Neustart und interaktive Linux-Ersteinrichtung erforderlich).
2. Plattformtests, isolierten Backend-Basislauf und Browserprüfungen ausführen.
3. Gebühren-/Rechtsbindung mit additiven Migrationen und echten transaktionalen
   Regressionstests zuerst umsetzen; danach die Tabelle modular abarbeiten.
4. Unabhängig davon Übersetzungsumfang, PLZ-Quellen und Admin-Setup konkretisieren.

Gesamtauftrag nicht abgeschlossen. Diese Etappe stellt den sicheren lokalen
Einstieg bereit und ersetzt keine fehlende Fach- oder Produktionsabnahme.

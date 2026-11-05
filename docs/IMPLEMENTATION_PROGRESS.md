# Implementierungsfortschritt

## Wiedereinstellung integriert, Prüfungen laufen, 2026-11-05

Auf Nutzerauftrag tatsächliche Umsetzung fortgesetzt. Bestehendes Relisting-
Backend und Migration 014 erhalten, geprüft und mit Bootstrap, Sprachkatalogen,
Gebührenquote/Veröffentlichung, Dashboard, Bestätigungsseite und Editor verbunden.
Neuer Durchlauf hat eigene ID/Bilder; einmaliger Rabatt wird bei Veröffentlichung
atomar verbraucht, nicht beim Entwurf oder bei ohnehin kostenlosen Tarifen.
Paralleles Nachlesen auf aktuelle DB-Sicht korrigiert; veröffentlichte Nachfolger
behalten auch bei gebührenfreier Pause/Fortsetzung ihre ursprüngliche Endzeit.

Lokal bestanden: PHP-Syntax sämtlicher neuer/geänderter PHP-Dateien, 22 neue
Relisting-Regelgruppen, 16 bestehende Gebühren-, 14 Zustimmungs- und sieben
Geo-/Sprachgruppen; Browserdatei Node-syntaxgeprüft. 14 DB-/HTTP-Gruppen einschließlich
echter Prozesskonkurrenz, spätem Rollback und deterministischem altem RR-Snapshot
ergänzt. Neun Browserabläufe für acht mobile Erst-Wiedereinstellungen und einen
bezahlten Folgeversuch ergänzt; tatsächlicher Linux-/Browsernachweis noch ausstehend.
Die DB-Suite läuft sequenziell im bestehenden isolierten Runner. Unabhängige
Agentenarbeiten nach Nutzungslimit durch Root geprüft und integriert.
Details und verbleibender Umfang: [RELISTING.md](RELISTING.md).
Keine Live-Aktivierung, kein Eingriff in ursprüngliche Auktionen/Schlüssel/Volumes.

## Restaufwand geprüft und Implementierung für Statusauskunft pausiert, 2026-11-04

Auf aktuelle Nutzerfrage gezielter Code-/Git-/CI-Abgleich statt Neuimplementierung.
Remote erneut direkt geprüft: letzter Codecommit `4add338349ba3865e51f4333f0ece515ccadbef8`;
CI 41666927670 samt Joblog vollständig erfolgreich. Aktuelle gewichtete grobe
Planung und offene Pakete: [PROJECT_STATUS.md](PROJECT_STATUS.md), Neubewertung
vom 4. November. Kein abgeschlossener Gesamtumfang und keine Produktivfreigabe.

Relisting-Zwischenstand bleibt lokal erhalten: Backend, Migration 014, acht
Sprachdateien und Integrationstestgerüst uncommittet. Das Gerüst enthält noch
keine Testfälle; reine Tests fehlen. Backend/SQL noch ohne Syntax-/Laufzeitnachweis,
Sprachdateien zuvor syntaxgeprüft. Keine Bootstrap-, Markt-/Gebühren- oder
Oberflächenanbindung; Browserdateien noch unverändert. Nicht in den Runner
aufnehmen oder als erfolgreich ausgeben, bevor diese Lücken geschlossen sind.

Nächster Schritt: bestehende Backendfunktionen prüfen, Kettensperre vor
Inseratsperre integrieren, Rabatt vor Zustimmungsprüfung in Quote/Hash aufnehmen,
Anspruch bei tatsächlich rabattierter Veröffentlichung atomar verbrauchen;
Bestätigungsseite/Dashboard/Edit-Hinweis und fehlende Testgruppen ergänzen.
Danach isolierte sequenzielle DB- und echte Browserprüfung, Fehlerbehebung,
geprüfter Codecommit/Push. Nur Nachweisdokumente werden jetzt gesichert;
die private Auftragsdatei und unfertigen Codeänderungen bleiben unverändert.

## Wiederaufnahme und bestätigter Stand, 2026-11-04

Lokal und auf dem Entwicklungsbranch `4add338349ba3865e51f4333f0ece515ccadbef8`;
Arbeitsbaum vor Fortsetzung sauber bis auf die private, unversionierte Auftragsdatei.
CI [41666927670](https://github.com/LLMbutHuman/GunIndex/actions/runs/41666927670)
vollständig erfolgreich: 16 reine Gebühren- und 26 Gebühren-DB-/HTTP-Gruppen,
zwölf reine Wartungs-, neun DB-/Worker- und 14 HTTP-/RBAC-Gruppen. Gebührenhinweise
in acht Empfängersprachen, verschlüsselte Queue und Kanalpräferenzen bestanden.
Zehn Gebühren-Browser-Journeys einschließlich acht mobiler Sprachen sowie gesamte
bestehende Regression, Composer-Audit, Internationalisierung und Accessibility grün.
Auch Wartungsdiagnose-Commit `2c070d6`, CI `41665892946`, vollständig erfolgreich.
Die unten stehenden Ausstehend-Vermerke sind damit historische Zwischenstände.

Nächste Implementierung begonnen: sichere Wiedereinstellung erfolglos beendeter
Auktionen als neuer Entwurf mit eigener ID und unabhängigen Bilddateien. Einmaliger
Gratisanspruch pro Ursprungskette, Verbrauch erst bei tatsächlicher Veröffentlichung;
Erfolgsprovision bleibt bestehen. Ursprüngliche Gebote und Verträge bleiben erhalten.
Backend, Übersetzungen und Integrationstests werden getrennt parallel bearbeitet.
Diese neue Etappe ist noch nicht fertig oder getestet. Produktionsschalter bleiben aus.

## Gebührenhinweise und Abschlussprüfung, 2026-11-03

CI `41664657641` für Wartungscommit `de42a91` vollständig erfolgreich, einschließlich
zwölf Regel-, acht DB-/Worker- und 14 HTTP-/RBAC-Gruppen sowie gesamter Browser-
regression. `2c070d6fa60a39ac42288285e2cab34ae486aea3` gesichert; CI `41665892946`
bestätigt bereits PHP/MySQL samt zusätzlicher neunter Wartungsgruppe und Healthcheck,
Browserlauf noch aktiv.

Neue Gebührenhinweise für Forderung und vollständigen Erlass in acht Sprachen
ergänzt, atomar und dedupliziert mit dem Finanzereignis. Kanaleinstellungen und
verifizierte Mailadresse werden respektiert; Nullgebühren erzeugen keine Aufforderung.
Zwei DB-Gruppen für Sprache, verschlüsselte Queue, Wiederholung und Präferenzen
ergänzt (Gebührensuite jetzt 26 Gruppen). PHP-Syntax, 16 reine Gebührenfälle und
sieben Sprach-/Geo-Gruppen lokal bestanden; neue DB-/Queue-Fälle noch ausstehend.

## Ergänzte Betriebsdiagnose und bestätigte Browserfälle, 2026-11-03

`9303510e59cf67eb4465a17acffaa75c0211b40f`: CI `41663795718` vollständig erfolgreich.
Zehn zusätzliche Browser-Journeys bestanden: kostenlose Festpreis-/Entwurfsanlage
und Veröffentlichung/Bearbeitung/Zahlungsansicht in allen acht mobilen Sprachen;
keine JavaScriptfehler oder externen Requests. Der frühere Selektorfehler ist behoben.

`de42a9139ba56868b89b8d27e81eb58ef7c895bf` auf Entwicklungsbranch gesichert, Remote-SHA
bestätigt. CI `41664657641`: Windows und Linux-PHP/MySQL inklusive neuer Wartungs-
tests bestanden; vollständige Browserregression noch aktiv. Anschließend ergänzt:
Healthcheck meldet aktive Pause und Worker-Modus; inkonsistente offene Fenster
sperren Zugriff und werden als fehlerhaft diagnostiziert. Einstellungsbeschreibung
jetzt in allen acht Sprachen. Neunte DB-Testgruppe für widersprüchliche Fenster
ergänzt; diese Ergänzungen bisher nur syntax-/sprachgeprüft, nächster CI-Lauf nötig.

## Wartungssperre und Auktionsfristen: nächste lokale Etappe, 2026-11-03

HTTP-Gate jetzt mit Frontcontroller verbunden; Migration 013 und persistente
Wartungsfenster ergänzt. Öffentliche Requests und fünf Scheduler-Batches halten
eine separate DB-Lesesperre, damit Aktivierung auf laufende Arbeit wartet.
Marktmutationen/neue Zahlungen zusätzlich innerhalb der Transaktion gesperrt.
Noch nicht abgelaufene aktive/pausierte Auktionen erhalten nach Wartung genau
einmal ihre Ausfallzeit zurück; abgeschlossene oder vorher fällige Auktionen nicht.
MFA-Administration und geprüfte bestehende Zahlungsereignisse bleiben zugänglich.
[MAINTENANCE.md](MAINTENANCE.md) beschreibt Grenzen, insbesondere keinen
vollständigen Backup-Schreibstopp und noch offenen Ausgleich von Werbezeiten.

Zwölf reine Wartungs-Testgruppen und Syntaxprüfung bestanden. Neue acht DB-/Worker-
gruppen und 14 HTTP-/RBAC-Gruppen vorbereitet und sequenziell im Runner registriert;
deren tatsächlicher Linux-Lauf steht noch aus. Agentenlimit unverändert, Integration
durch Root fortgesetzt. Wartungsfunktion bisher nicht produktiv aktiviert.

## Auktionsgebühren: Integration zur Linux-Prüfung, 2026-11-03

Update: CI `41662133685` für `7d5b25a` vollständig erfolgreich: 16 Gebühren-Unit-
und 24 Integrationsgruppen inklusive echter konkurrierender Sofortkäufe; bestehende
Browser-, Sprach- und Accessibilityprüfungen ebenfalls bestanden. `f6668be` ist
gepusht; dessen zusätzlicher Browserlauf `41662757710` scheiterte am Testselektor
`[name=description]` (Textarea und SEO-Metatag). Auf `textarea` eingegrenzt;
neuer Browsernachweis noch ausstehend. Keine Anwendungsfehler dadurch behauptet.

Commit `7d5b25ad380d987231bcafe6b43d718d0e6c714c` auf Entwicklungsbranch gepusht,
Remote-SHA bestätigt. CI `41662133685` läuft; Windows-Prüfung bereits erfolgreich,
Linux/MySQL noch aktiv. Zusätzlich echte Browserfälle für die neue Oberfläche
ergänzt: kostenlose Festpreis-/Entwurfsanlage, gebührenpflichtige Veröffentlichung,
Bearbeitung ohne zweite Gebühr und Zahlungsansicht in acht mobilen Sprachen.
Fixture und Browserdatei lokal syntaxgeprüft; tatsächlicher Browserlauf steht aus.

Ausgangspunkt `784a9871139195a78c103c6c49ae7badaf7ecdad` unverändert erhalten.
Neue additive Migration 012, verschlüsselter Gebührenvertrag je Auktionsdurchlauf,
exakte Preis-/Rechtszustimmung und eigener kostenpflichtiger Veröffentlichen-Button.
Atomare Einstell- und Erfolgsforderungen bei Veröffentlichung, Zuschlag bzw.
Sofortkauf; vorhandene gebührenfreie Durchläufe bleiben gebührenfrei. Einmalige
Zahlungsbestätigung, Rechnungsbeleg und Bargeldbuchung über bestehenden manuellen
Prüfweg; kein Bump-/Premiumguthaben für Gebühren. Unbezahlte Gebühren sind nur mit
Finanzberechtigung und Begründung vollständig erlassbar. Umfang und verbleibende
Korrektur-/Providerarbeiten: [AUCTION_BILLING.md](AUCTION_BILLING.md).

Tatsächlich lokal bestanden: 16 neue reine Gebühren-Testgruppen, 14 bestehende
Zustimmungsfälle, sieben Geo-/Sprachfälle, PHP-Syntax der betroffenen Dateien
und Node-Syntax des Formularskripts. Datenbank-/HTTP-Testdatei ergänzt, darunter
zwei tatsächlich parallel gestartete Sofortkaufprozesse und der SQL-NULL-Fall.
Diese DB-/HTTP-Fälle sind noch NICHT ausgeführt; nächste Prüfung ist die isolierte
Linux-CI nach Entwicklungs-Push. Kein Gebühren-/Zahlungsschalter live aktiviert.

Unabhängige Wartungsarbeiten liegen weiterhin unversioniert vor: HTTP-Gate,
acht Sprachkataloge sowie Unit-/Integrationstests. Noch keine Routenverdrahtung,
Worker-Barriere oder Auktionsuhr-Pause; nicht als fertiger Wartungsmodus verwenden.
Die parallelen Agenten haben ihr Nutzungslimit erreicht. Ihre vorhandenen Dateien
bleiben erhalten; die Root-Fortsetzung prüft und integriert sie.

Wiederaufnahme: zuerst neuen CI-Lauf und etwaige Fehler prüfen; dann bezahlte
Veröffentlichung im echten mobilen Browser testen. Danach Wartungsfenster mit
transaktionaler Marktbarriere und einmaliger Fristverlängerung implementieren,
HTTP-Gate/Worker anbinden und testen. Private Fortsetzungsdatei bleibt unversioniert.

## Verbindlicher Gesamtbericht und Wiederaufnahme, 2026-11-02, 22:25 Uhr

Aktueller Gesamtstatus mit allen 26 Anforderungen, gewichteten Schätzbereichen,
Arbeitsstunden, Blockern, Abnahmekriterien und konkreter Fortsetzung:
[PROJECT_STATUS.md](PROJECT_STATUS.md). Plattform weiterhin nicht fertig.

Alle begonnenen Codeänderungen gesichert. Letzter Codecommit lokal und remote:
`2042549d5cec2bc39f713ebc18a76f8ab30fc3af`, ausschließlich Entwicklungsbranch.
CI `41525630270` (`505ffa1`), `41526420094` (`ad5ed5f`) und `41527131692`
(`2042549`) vollständig erfolgreich. Letzter Lauf bestätigt auch sieben neue
Sprachlink-Unitfälle, 28 Konto-/Mailfälle und mobile Benachrichtigungs-/Dashboard-
und Footerprüfungen. Keine tatsächlichen Providerzahlungen oder Produktivfreigaben.

Unversioniert bleibt ausschließlich die private Fortsetzungsdatei; lokale
Runtime-/Patchsicherungen bleiben ignoriert. Folgender Commit betrifft nur
Gesamtbericht und Nachweise. Nächste Implementierung: Auktions-Gebührenvertrag
je Durchlauf mit eingefrorener Verkaufs-/Steuer-/Rechtsgrundlage, anschließend
atomare Forderungen/Korrekturen. Lokale WSL-/Docker-Voraussetzungen weiter offen.
Keine neue Preis-/Ablaufbenachrichtigung implementiert. Ältere offene CI-Vermerke
unten sind historische Zwischenstände und durch diesen Nachweis ersetzt.

## Statusaufnahme und Sicherung, 2026-11-02

Benutzer priorisiert jetzt Gesamtstatus und Sicherung vor weiteren Funktionen.
`505ffa19f0f1b5f29089cfe69435e41f655f980b`: CI `41525630270` vollständig erfolgreich
(Windows, PHP/MySQL, Browser einschließlich neuer Profil-/Recoveryfälle,
Internationalisierung, Accessibility, Composer-Audit).
`ad5ed5f95386005470c15c7db2e1b5c4c8efd5a2`: gepusht, Remote-SHA bestätigt;
CI `41526420094` noch aktiv. Keine bekannten fehlgeschlagenen Anwendungstests
dieser Etappen zum Zeitpunkt dieses Eintrags.

Die bereits begonnene Dashboard-Lokalisierung ist im Arbeitsbaum erhalten und
nun vollständig an das feste Sprachmodul angebunden (37 Schlüssel je Sprache).
Zehn PHP-Syntaxprüfungen, sieben Geo-/Sprachprüfungen und Node-Syntax bestanden.
Acht mobile Dashboardprüfungen ergänzt, deren CI-Ausführung steht noch aus.
Keine weitere Funktionsetappe begonnen. Der verbindliche Wiederaufnahmepunkt
und die Gesamtbewertung werden in PROJECT_STATUS.md dokumentiert.

## Folgende Etappe: Sprachwahl aus E-Mail und Benachrichtigungen

Commit `505ffa19f0f1b5f29089cfe69435e41f655f980b` ist auf dem richtigen Branch
gesichert; Remote-SHA bestätigt. CI `41525630270` läuft noch. Der frühere
Freigabe-/Kontingentblocker ist behoben, ohne die Prüfung zu umgehen.

Neu lokal umgesetzt: Verifikations-/Resetlinks enthalten die erlaubte
Empfängersprache. Nur eine fehlende oder ungültige Session-Sprachwahl wird
initialisiert; vorhandene bewusste Auswahl bleibt erhalten. Kein Tokenverbrauch
oder Kontowechsel durch GET. Alte Links und manipulierte Sprachparameter sind
weiterhin sicher behandelbar. Sieben neue reine PHP-Fälle bestanden; drei neue
DB-/HTTP-Fälle für alle acht Sprachen sind in den vorhandenen Runner eingebunden
und bis zur CI-Ausführung noch offen.

Benachrichtigungsübersicht und -einstellungen vollständig auf 15 zusätzliche
Konto-Schlüssel umgestellt (jetzt 97 je Sprache), einschließlich Fehlern,
Lesestatus und Paginierung. Bestehende gespeicherte Ereignistexte werden nicht
nachträglich umgeschrieben. Ungültige negative Benachrichtigungs-IDs werden
abgewiesen. Browserfälle prüfen Speichern/Wiederladen der Präferenzen, CSRF,
ungültige Eingaben und mobile Breite in allen acht Sprachen.

Gemeinsamer Footer mit fünf neuen Texten je Sprache lokalisiert; individuelle
Betreibertexte bleiben unverändert. Lokale PHP-Syntax, sieben globale Sprach-/
Geo-Fälle und Node-Syntax bestanden. Python-Runnersyntax konnte lokal nicht
ausgeführt werden: `python` verweist auf einen nicht eingerichteten Windows-
Store-Alias. Keine vollständige lokale Linux-/MySQL- oder Browserprüfung behauptet.

## Kontowiederherstellung und Profil: lokaler Stand, 2026-11-02

Wiederherstellung, E-Mail-Verifikation und Profil einschließlich Validierungen
in acht Sprachen umgesetzt; Kontokatalog jetzt 82 Schlüssel je Sprache. Mobile
Browserfälle prüfen pro Sprache echte Formularantworten, ungültige Reset-/
Verifikationslinks sowie Profilspeicherung und abgewiesene Sitzungswiderrufe
mit falschem Passwort. PHP-Syntax, sieben Geo-/Sprachprüfungen, 17 Auth-Prüfungen
und Node-Syntax beider Browserdateien bestanden. Die neuen Browserfälle müssen
noch in der isolierten Linux-CI ausgeführt werden.

Zusätzliche lesende Codeprüfung ohne blockierende Regression. Nächste konkrete
Sprachlücke: E-Mail-Links übernehmen in einem neuen Browser die Empfängersprache
noch nicht. Gemeinsamer Footer und weitere Konto-/Finanz-/Adminseiten sind offen.

Die reguläre Freigabeprüfung ist wieder verfügbar (Node-Syntaxprüfung erfolgreich).
Der nachfolgende Kontingentfehler beschreibt den vorherigen Zwischenstand; bis
zum tatsächlichen Commit/Push bleiben die aktuellen Änderungen nur lokal gesichert.

## Vorübergehender Sicherungsblocker, 2026-11-02

Die automatische Freigabeprüfung für den angeforderten `git add`-Schritt konnte
wegen ihres ausgeschöpften Nutzungskontingents nicht abgeschlossen werden.
Der Aufruf wurde **nicht ausgeführt**; es liegt keine Unsicherheitsentscheidung
über die Änderungen vor. Kein Umgehen der Prüfung. Wiederherstellungs-/E-Mail-
Übersetzungen sind lokal erhalten, nicht gestagt, nicht committed und nicht gepusht.
Zusätzliche lokale Patchsicherung unter dem ignorierten `tests/.runtime/checkpoints/`.
Schreibzugriff auf den Arbeitsbaum und lokale PHP-Prüfungen sind weiterhin möglich.

Letzter gesicherter Code: `35b99fe89c07ba88b499edcf05abe0958b488ab8`.
[CI 41484172147](https://github.com/LLMbutHuman/GunIndex/actions/runs/41484172147)
ist inzwischen vollständig erfolgreich: auch alle acht mobilen Registrierungs-
und Validierungsabläufe. `b5677bd` besitzt ebenfalls erfolgreichen eigenen CI-Lauf.
Git-Mutationen dürfen ausschließlich mit regulärer Freigabeprüfung erfolgen.

## Wiederherstellung und E-Mail-Verifikation lokalisiert

Passwortanfrage, Passwortreset und E-Mail-Bestätigung einschließlich Erfolgs-,
Fehler- und Zustandsmeldungen jetzt in acht Sprachen umgesetzt. Der Kontokatalog
enthält 63 Schlüssel pro Sprache. Verifikations-/Resetlinks bleiben unverändert
an ausdrückliche POST-/CSRF-Verarbeitung gebunden. Es wird keine erfolgreiche
Posteingangszustellung oder Identitätsprüfung behauptet.

PHP-Syntax aller vier betroffenen Dateien, sieben Geo-/Sprachprüfungen und
Node-Syntax bestanden. Der Browser prüft je Sprache die mobile Anfrageansicht,
einheitliche Wiederherstellungsantwort sowie echte Ablehnung ungültiger Reset-
und Verifikationslinks. Diese Erweiterung ist vor dem folgenden CI-Lauf noch
nicht vollständig ausgeführt. Profil-/Finanz-/Admin-Altseiten bleiben offen.

Login-/MFA-Commit `b5677bd` ist durch CI `41483417303` vollständig erfolgreich
bestätigt. Registrierungscommit `35b99fe89c07ba88b499edcf05abe0958b488ab8` wurde
gepusht, Remote-SHA stimmt überein; CI `41484172147` ist noch in Arbeit.

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

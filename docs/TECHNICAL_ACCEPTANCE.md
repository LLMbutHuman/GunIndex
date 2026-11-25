# Technischer Abnahmestand – 2026-11-25

Gesamtplattform **nicht fertig und nicht produktiv abgenommen**.
Allgemeiner Warenmarktplatz; keine Aktivierung historischer Spezialkategorien.

**A:** benannte Funktion implementiert und aktuell getestet.
**B:** Integration implementiert; externer Nachweis fehlt.
**C:** Gesamtanforderung offen, teilweise implementiert oder nicht vollständig geprüft.

## Belegte Teilfunktionen

- **A:** E2EE-Kern mit OpenPGP.js 6.3.2, Geräteschlüsseln, Fingerabdruckprüfung,
  Mehrgeräten, Geräte-Recovery/Widerruf, verschlüsselten Dateien, Quoten und Export
  in `7ba5dfd`, CI 43648359381: zwölf echte Kryptografiegruppen, 15 Servergruppen
  und 19 Chromium-Browserfälle einschließlich acht mobiler Sprachen bestanden.
  **A:** Chromium mit Firefox und Chromium mit WebKit bestanden in Linux-CI.
  [Browser-Engine-Tests](https://github.com/LLMbutHuman/GunIndex/actions/runs/45623624368)
  **B:** unabhängige Sicherheits- und Zielserverabnahme sowie native Safari-/iOS-Tests.
  **A:** Austausch einer kompromittierten Kontoautorität mit frischer MFA,
  neuem Wiederherstellungscode und erneuter Fingerabdruckprüfung bestätigt.
  [E2EE-Regression](https://github.com/LLMbutHuman/GunIndex/actions/runs/44951559532)
  Kein Forward-Secrecy-Versprechen; Produktionsgate standardmäßig aus.

- **A:** Auktions-Gebührenverträge, Einstell-/Erfolgsforderungen, Zuschlagsbindung,
  Wiederholungsschutz, manuelle Zahlungsprüfung, vollständiger Erlass unbezahlter
  Forderungen und verifizierte Erstattung: `7d5b25a`, CI 41662133685, 16 Unit- und
  24 DB-/HTTP-Gruppen einschließlich paralleler Sofortkäufe. Zehn zusätzliche
  Gebühren-Browser-Journeys in `9303510`, CI 41663795718, einschließlich acht mobiler
  Sprachen bestanden. Freigaben standardmäßig aus.
- **A:** Teilkorrektur unbezahlter Auktionsgebühren und Klärung ersetzter
  Zahlungsreferenzen. `e1859f6`, [CI 44525631491](https://github.com/LLMbutHuman/GunIndex/actions/runs/44525631491).
  31 MySQL-/HTTP-Gruppen einschließlich Parallelkorrektur, Rückzahlungsnachweis
  und korrigiertem PDF sowie die Browserregressionen bestanden.
- **A:** Wartungszugriff, Worker-Barriere, Auktionsfrist-Pause und Healthdiagnose:
  durch `4add338`, CI 41666927670 erneut bestätigt; zwölf reine, neun DB-/Worker-
  und 14 HTTP-/RBAC-Gruppen bestanden. Gesamtanforderung 22 bleibt wegen der unten
  genannten Betriebsfolgen teilweise offen. Gebührenhinweise ebenfalls bestätigt;
  Gebührensuite inzwischen 16 reine und 26 DB-/HTTP-Gruppen.
- **A:** Wiedereinstellung eigener erfolgloser Auktionen: neue ID und Bilddateien,
  einmaliger transaktionaler Einstellgebührenrabatt, unveränderte Erfolgsprovision,
  Dashboard-/Bestätigungs-/Editorablauf. `7b423f8`, CI 42055817743 vollständig grün:
  22 reine und 14 DB-/HTTP-Gruppen sowie neun zusätzliche Browserabläufe inklusive
  acht mobiler Sprachen. Masterpunkt 11 umfasst weitere noch offene Fälle.

- **A:** Lokalisierte Konto-/Recovery-/Profil-/Benachrichtigungs-/Dashboard-
  und Footerabläufe in acht Sprachen; Empfängersprache in Mail-Links für frische
  Browser. `2042549`, CI 41527131692 vollständig erfolgreich. Kein Nachweis
  einer vollständigen Übersetzung sämtlicher Plattformseiten.

Aktuelle Gesamt- und Aufwandsbewertung: [PROJECT_STATUS.md](PROJECT_STATUS.md).

- **A:** Admin-Setup-Diagnosen (18 Bereiche), Bildsortierung mit Revisions-/
  Eigentümerschutz, SEO-Grundfunktionen und PLZ-Validator: `2d496a8`, CI 41418425597.
- **A:** Sitzungsspezifischer Opt-in-Verlauf und Produktvergleich: `126d400`,
  CI 41420258463; ebenfalls echte Bildsortierung und acht mobile Setup-Sprachen.
- **A:** Dokumentbindung bei Registrierung und bestehenden Leistungsbestellungen:
  `dc046ae`, CI 41478575081; Migration 011, 14 Unit-, elf DB-/HTTP-Fälle und
  mobiler Browserablauf. Benötigt konfigurierte, fachlich freigegebene Fassungen.
- **B:** PayPal-REST-Anbindung; synthetische Adaptertests vorhanden, echte
  Sandbox-/Providerabnahme und Live-Freigabe fehlen.
- **A:** Automatischer Finanzabschluss mit synthetischem Provideradapter:
  `699c35b`, CI 41480002310 vollständig erfolgreich. Auch der echte Paralleltest
  mit zwei getrennten Prozessen besteht: `0194d59`, CI 41481388425.
  Keine reale Providerabnahme.
- **C:** Lokale WSL-/Docker-Laufzeit: Speicherplatz/Neustart ausstehend.
  Die externe Linux-CI ist funktionsfähig.

## Die 26 Masteranforderungen

Die Einstufung bezieht sich auf den **gesamten** Punkt. C bedeutet nicht, dass
keine Grundlagen vorhanden sind. Korrekte bestehende Implementierungen bleiben erhalten.

| Nr. | Anforderung | Stand | Restumfang |
| --- | --- | --- | --- |
| 1 | Auktions-Einstellgebühr | A | Neuer bestätigter Durchlauf atomar berechnet; Aktivierungsfreigaben und tatsächliche Zahlungen extern |
| 2 | Auktions-Erfolgsprovision | A | Zuschlag/Sofortkauf samt Buchung und Wiederholungsschutz geprüft; Providerabnahme extern |
| 3 | Gebühren, Korrekturen, Rechnungen | A | Centgenauer Durchlauf mit Verlängerung, Zuschlag, Teilkorrektur, verifizierter Zahlung und PDF im isolierten MySQL/HTTP geprüft. Die Belegübersicht bindet ursprüngliche und korrigierte Aufträge an die Auktion. Bestandsauktionen erhalten keine rückwirkenden Gebühren. Automatische Providerzahlungen werden separat abgenommen. [CI 44839394997](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839394997) |
| 4 | Rechtsdokumentbindung | B | Registrierung, Checkout und ausdrückliche Pflichtzustimmung für Bestandskonten versioniert und in MySQL/HTTP geprüft. Fachliche Freigabe der tatsächlichen Dokumente und Sprachfassungen steht aus. [CI 44794702907](https://github.com/LLMbutHuman/GunIndex/actions/runs/44794702907) |
| 5 | Vollständige Übersetzungen | C | Acht Sprachfassungen für die geprüften Module, einschließlich Kontoverwaltung, Händlern, Rechtsmasken und öffentlichen Informationsseiten. Fachliche Rechtsfassungen sowie der vollständige sprachliche Seiten- und Formatabgleich bleiben offen. [Mobiltest](https://github.com/LLMbutHuman/GunIndex/actions/runs/45616262092) |
| 6 | Kategorieattribute | A | Kategoriespezifische Text-, Zahlen-, Auswahl- und Ja/Nein-Felder, MFA-Verwaltung, Pflichtfelder und Deaktivierung geprüft |
| 7 | Produktklassifizierung | A | Validierte Werte in Inserat, Produktseite, Wiedereinstellung und Kaufnachweis. Bestehende Inserate beginnen ohne erfundene Werte |
| 8 | Autocomplete/Relevanz/Filter | A | Öffentliche Titelvorschläge, Relevanzsortierung und kombinierte Merkmals-, Preis-, Länder- und Umkreissuche in MySQL und Chromium geprüft |
| 9 | Entwurfs-Autosave/Vorschau | A | Privater Zwischenstand, Bildübernahme, Versionsprüfung, Formular ohne JavaScript sowie Veröffentlichungs- und Zugriffstests grün |
| 10 | Bildreihenfolge | A | Implementiert und geprüft |
| 11 | Wiedereinstellen/Verlängern | A | Kostenloser Nachfolger nach Abschluss ohne Gebot, neue Auktion nach Zuschlag/Sofortkauf mit bestätigtem Bestand, Kopie geschlossener Festpreis-/Verhandlungsinserate und begrenzte einmalige Laufzeitverlängerung in MySQL/HTTP geprüft. [CI 44836432968](https://github.com/LLMbutHuman/GunIndex/actions/runs/44836432968) |
| 12 | Verhandlungsmodus | A | Private Angebote, Gegenangebote, Annahme, Ablehnung, Rückzug, Fristen und unveränderliche Konditionen in MySQL und Chromium geprüft |
| 13 | Festpreis-Transaktion | B | Verkäuferfreigabe, konkurrierende Käufe, Centbeträge, Versand/Abholung, Abschluss, Storno und Streitfälle geprüft. Zahlungen und Treuhand separat in 23–26 |
| 14 | Transaktionsbewertungen | A | Bewertungen nur durch echte Parteien nach Abschluss, einmal pro Partei. MySQL- und Browserprüfung grün |
| 15 | Benachrichtigungen | A | Preis, Vorablauf und Ablauf in MySQL geprüft, getrenntes Opt-out im Browser und Warteschlangenstopp im Mail-Testtransport |
| 16 | Geräte/Einzelsitzungen | A | Private Übersicht, Einzelwiderruf und Abmeldung mit Konto- und CSRF-Grenzen in MySQL/Chromium geprüft. Passwortwechselregression grün |
| 17 | Benutzer-2FA/Passkeys | A | Optionale TOTP-Anmeldung, einmalige Recovery-Codes und WebAuthn mit echtem Chromium-Authenticator-Verfahren grün. Zielsystem und physische Authenticatoren zur Betriebsabnahme |
| 18 | Profilbilder/Händlerlogos | A | Größenbegrenzter WebP-Upload, Profilanzeige, revisionsgebundene Logo-Freigabe, Datenschutzexport und Entfernung in MySQL und Chromium geprüft |
| 19 | Verlauf/Produktvergleich | A | Innerhalb dokumentierter Sitzungsgrenzen geprüft |
| 20 | Händlerfunktionen | B | Teamrollen, Warenbestand, Checkout-Buchung, API und vorausbezahlte Abos mit CSV-Export integriert geprüft. Live-Abrechnung, Preis und Bedingungen für das Abonnement sind freizugeben. [CI 44793558506](https://github.com/LLMbutHuman/GunIndex/actions/runs/44793558506) |
| 21 | Vollständiges SEO | B | Acht Sprachpfade, Canonicals, hreflang, strukturierte Daten und Sitemap-Freigabe lokal, in MySQL und HTTP geprüft. Prüfung am öffentlichen HTTPS-Ziel ausstehend. [CI 44788077123](https://github.com/LLMbutHuman/GunIndex/actions/runs/44788077123) |
| 22 | Wartungsmodus | A | HTTP- und Worker-Sperre, Auktionsfristen, Werbelaufzeiten und Fristen bezahlter Buchungen in isoliertem MySQL geprüft. Eine simulierte neuntägige Unterbrechung deckt Zahlungseingänge während der Pause, Wiedereröffnung und Ablauf ab. [CI 44835645014](https://github.com/LLMbutHuman/GunIndex/actions/runs/44835645014) |
| 23 | Automatische Rückerstattungen | B | Freigabe, persistente Aufträge, Worker, verifizierter Statusabgleich und Korrekturbelege in MySQL/HTTP geprüft. Echte Sandbox- und Live-Anbieterabnahme offen. [CI 44540263687](https://github.com/LLMbutHuman/GunIndex/actions/runs/44540263687) |
| 24 | Bitcoin | B | Isolierte Bitcoin-Core-Wallet, exakte Satoshis, Bestätigung und Reorganisation geprüft. Quote, einmalige Verbuchung und Sperre verbleibender Leistungen im MySQL-Test grün. Mainnet-Infrastruktur und Freigabe ausstehend. [CI 44579766951](https://github.com/LLMbutHuman/GunIndex/actions/runs/44579766951) |
| 25 | Banküberweisung | B | Eindeutige Referenzen, camt.053.001.08-Import, Abgleich mit separater Eingangsprüfung und einmaliger Buchung getestet. Originalexport des Betreiberkontos und Produktivfreigabe offen. [CI 44577373006](https://github.com/LLMbutHuman/GunIndex/actions/runs/44577373006) |
| 26 | Treuhand | C | Simulierter Ablauf von Angebot bis Auszahlung, Streit, Erstattung und Rückbuchung in MySQL, HTTP und im Paralleltest bestätigt. Live-Adapter und freigegebene Infrastruktur fehlen. [CI 44788077123](https://github.com/LLMbutHuman/GunIndex/actions/runs/44788077123) |

Der Mail-Assistent mit verschlüsselter Relay-Konfiguration, acht Sprachfassungen,
DNS-Prüfung und simulierten Testaufträgen ist in MySQL und HTTP bestätigt. Ein
lokaler SMTP-Server prüfte STARTTLS, SMTPS und TLS-Fehler. Externe Zustellung
steht aus. [CI 44581824454](https://github.com/LLMbutHuman/GunIndex/actions/runs/44581824454)

Die isolierte Installationsprobe hat das Produktionsimage, die Fortsetzung
einer unterbrochenen Migration, ein verschlüsseltes Datenbank- und Dateibackup,
die Wiederherstellung sowie sieben Worker-Heartbeats geprüft.
[CI 44838740876](https://github.com/LLMbutHuman/GunIndex/actions/runs/44838740876)

Ein weiterer isolierter Ubuntu-Lauf bestätigte SFTP mit einem eingeschränkten
OpenSSH-Dienst auf der Loopback-Schnittstelle, Ablehnung eines anderen
Hostschlüssels, Rückleseprüfung, Schutz vor Überschreiben und Restore aus der
zurückgeladenen Kopie. Offsite-Ziel, Schlüsselverwahrung und Wiederanlaufprobe
auf Betreiberinfrastruktur stehen aus.
[SFTP- und Restore-Test](https://github.com/LLMbutHuman/GunIndex/actions/runs/45625737690)

Die bereitgestellte deutsche CSV wurde bei der Installationsprobe mit 8.289
PLZ importiert. Länderauswahl und Umkreissuche sind mit tatsächlichen Koordinaten
geprüft. [CI 44839813273](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839813273)

Der ausführbare Installer wurde auf Ubuntu 22.04 mit Neuinstallation und
In-Place-Update geprüft. Die Probe bestätigt TOTP-Einrichtung, geschützten
Admin-Assistenten, lokale Timer, den HTTPS-Deploy-Hook mit Prüfzertifikat
und unveränderte Schlüssel, Konten und private Testdaten beim Update.
[Ubuntu-Test](https://github.com/LLMbutHuman/GunIndex/actions/runs/44957109960)
Der zusätzliche La-Poste-Import bestand samt Herkunftshinweis im Suchablauf.
[PLZ-CI](https://github.com/LLMbutHuman/GunIndex/actions/runs/44956675099)
Der Installationslauf mit sechs fest versionierten Länderdateien bestätigt
zusätzlich 2.200 österreichische, 3.177 Schweizer, 13 liechtensteinische
und 4.068 Luxemburger PLZ-Punkte. Die Tests prüfen echte Umkreissuchen,
Attribution und Ländertrennung.
[Ubuntu-Installer mit sechs Länderdateien](https://github.com/LLMbutHuman/GunIndex/actions/runs/45622583277)
Der nächste Installer-Lauf bestätigt zusätzlich 2.675 aggregierte tschechische
PLZ-Punkte, den Prager Umkreis, die Suche mit formatierten PLZ und den
Quellenhinweis. Ein Update behält die sieben vorhandenen Länderimporte bei.
[Ubuntu-Installer mit Tschechien](https://github.com/LLMbutHuman/GunIndex/actions/runs/45628098335)

Der Wechsel einer kompromittierten E2EE-Kontoautorität ist mit frischer MFA,
neuem Wiederherstellungscode und erneuter Fingerabdruckprüfung in PHP/MySQL
und Chromium geprüft. Alte Anhänge bleiben bei vorhandenen passenden Schlüsseln
und verifiziertem Manifest lesbar. [CI 44951559532](https://github.com/LLMbutHuman/GunIndex/actions/runs/44951559532)

Zusätzlich offen sind die unabhängige E2EE-Sicherheitsprüfung und Browsermatrix,
weitere Länderimporte mit geprüften
Quellen, die Betriebsabnahme auf dem vorgesehenen Ubuntu-Host einschließlich
DNS und TLS sowie die fachliche Prüfung mit dem Legal-Team. Alte serverseitige
Chatverschlüsselung bleibt **keine E2EE**; der neue Modus ist in E2EE_PROTOCOL.md
getrennt beschrieben. Neue Sprachmodule sind nicht mit einer
vollständigen Übersetzung sämtlicher Seiten gleichzusetzen.

Fachliche Rechts-/Steuer-/Länderprüfung, Providerzulassung, echte DNS-/Posteingangs-
zustellung und produktive Backup-/Restoreziele können synthetische Tests nicht
beweisen. Details: CONTRACT_BINDING.md, FINANCE.md, POSTCODE_SOURCES.md,
IMPLEMENTATION_PROGRESS.md und RELEASE_OPERATIONS.md.

Fachlicher Übergabestand: [LEGAL_HANDOFF.md](LEGAL_HANDOFF.md). Er beschreibt
vorhandene Implementierungen und Grenzen, nicht eine fertige Plattform.

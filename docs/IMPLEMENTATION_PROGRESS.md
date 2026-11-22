# Implementierungsfortschritt

## Merkliste in deaktivierten Ländermärkten

Die Merkliste verwendet dieselbe Ländersperre wie die öffentlichen
Angebotsseiten. Gespeicherte Inserate erscheinen nach dem erneuten Aktivieren
des Marktes wieder. Anzeigen, leere Zustände und Fehler sind in acht Sprachen
verfügbar. Der HTTP-Test prüft Deaktivierung und Wiederanzeige über die
regulären geschützten Oberflächen.

## Ländermärkte, Standortquellen und redaktionelle Übersetzungen

Die geschützte Länderübersicht zeigt Standortquellen, Marktzustände und
Formularprüfungen in acht Sprachen. Sie erklärt den automatischen Import
der überprüften deutschen und französischen Bestände. Die Quellennotizen
und vom Betreiber eingegebenen Übersetzungen bleiben unverändert. Der
HTTP-Test prüft konkrete Eingabefehler und die einmalige Bestätigung
einer gespeicherten Änderung.
Lokal bestanden neun PHP-Syntaxprüfungen und 32 Textschlüssel
einschließlich Platzhaltern in allen acht Sprachen.

## Auktionsbewertungen und Abmeldung

Auktionsbewertungen, die geschützte Bewertungsmoderation und der
Abmeldedialog sind in acht Sprachen verfügbar. Der HTTP-Test prüft die
private Bestätigung, CSRF und das Ausblenden einer Bewertung, deren
gespeicherter Nachweis erhalten bleibt.
Lokal bestanden 19 PHP-Syntaxprüfungen sowie 19 Textschlüssel und
Platzhalter in allen acht Sprachfassungen.

## Nachrichtenübersicht und älterer Chatverlauf

Die Nachrichtenübersicht, der ältere Verlauf und seine Rückmeldungen sind in
allen acht Sprachen verfügbar. Der ältere Verlauf kennzeichnet seine
serverseitige Lesbarkeit und verweist auf den sicheren Chat. Seine
Lesemarkierung betrifft ausschließlich ältere Nachrichten. Der isolierte
HTTP-Test vergleicht die Lesestatus beider Nachrichtenarten innerhalb
derselben Unterhaltung.
Lokal bestanden 28 Textschlüssel je Sprache, 13 PHP-Syntaxprüfungen,
JavaScript-Syntax und alle 13 echten OpenPGP-Prüfgruppen.

## Website-Einstellungen und Personalrollen

Die geschützten Einstellungen zeigen Betreiberangaben, PayPal-Freigaben,
Rechnungsdaten, Preise und Validierungsfehler in acht Sprachen. Die Rollenmaske
lokalisiert Rechte, Status und MFA-Voraussetzungen. Eine Rollenentscheidung
wird erst nach Speicherung einmalig bestätigt. Die Prüfungen decken
Übersetzungsschlüssel, Formularrevision, Datenschutz und MFA-Grenzen ab.
Lokal bestanden acht Admin-Testgruppen mit 70 neuen Einstellungsbegriffen
je Sprache. Die Rollenmaske bestand 45 Schlüssel und 96 lokale CGI-Fälle.
Der vollständige HTTP-, MySQL- und Chromium-Lauf war erfolgreich.
[Regression](https://github.com/LLMbutHuman/GunIndex/actions/runs/44958704895)

## Sicherung und Wiederherstellung im Einrichtungsassistenten

Der Assistent zeigt Zeitpunkte tatsächlich verifizierter lokaler Backups
und Wiederherstellungen. Der Backup-Prozess schreibt nur den Zeitpunkt nach
erfolgreicher Integritätsprüfung in ein schreibgeschützt eingebundenes
Statusverzeichnis. Der PHP-Container erhält dabei weder Sicherungsinhalt noch
Archivschlüssel. Der Nachweis einer Offsite-Kopie bleibt eine Prüfung am Ziel.
Lokal bestanden dreizehn Setup-Gruppen einschließlich Sprachparität und
Ablehnung fehlender, ungültiger und zukünftiger Zeitstempel.
Beide isolierten Betriebsjobs bestätigten den Sicherungs- und Restore-Nachweis
über die schreibgeschützte Einbindung.
[Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44958201913)

## Meldungen und Beschwerden für Kunden

Meldeformular und Fallübersicht sind in allen acht Sprachen verfügbar.
Persönliche Fälle und Formularantworten tragen eine private Cache-Sperre.
Lokal bestanden zehn PHP-Syntaxprüfungen und 72 CGI-Fälle mit synthetischen
Daten. Der HTTP-Test prüft den Ablauf vom Melden über die Moderation bis
zur Beschwerde sowie die Sperre für unbeteiligte Konten.

## Einstellungen für bestehende Rechtszustimmungen

Die Einstellung für die Pflichtzustimmung von Bestandskonten ist jetzt auf
der geschützten Einstellungsseite sichtbar. Ein unvollständiges Formular
übernimmt vorhandene Werte für ausgelassene Felder. Explizite Änderungen,
CSRF, Formularrevision, Validierung und Protokoll bleiben wirksam. Der
HTTP-Test speichert zunächst die Pflichtzustimmung und ändert anschließend
allein den Markenclaim.

## Konto- und Datenanfragen

Die Kontoseite erklärt in acht Sprachen den passwortgeschützten Datenexport
und den Löschantrag mit sofortiger Deaktivierung. Nach dem Antrag erscheint
eine einmalige Bestätigung in der Sitzung. Der HTTP-Test prüft Export nach
erneuter Passworteingabe, private Download-Header, Statusmeldung und
anschließende Bearbeitung durch einen berechtigten Administrator.

## Moderation und Beschwerden

Die geschützte Moderationsseite zeigt acht Sprachfassungen für Fälle,
Meldungsarten, Status, Aktionen und bekannte Fehlermeldungen. Freiwillige
Zusatzangaben werden erst nach begründeter und protokollierter Einsicht
angezeigt. Lokal bestanden neun Syntaxprüfungen, 51 Schlüssel samt
Platzhalterparität und acht isolierte Renderprüfungen. Ein HTTP-Test prüft
Login-Schutz, Sprachwechsel und Vertraulichkeit von Zusatzangaben.

## Datenschutzprüfung

Die geschützte Seite für Löschanträge zeigt Bearbeitungsstatus,
Aufbewahrungsprüfung und Ablehnungsgründe in acht Sprachen. Die Bestätigung
erscheint einmalig nach einer gespeicherten Entscheidung. Der HTTP-Test
deckt Sprachwechsel, Zugriffsgrenzen, die erforderliche Prüfung vor
Anonymisierung und einen echten Statuswechsel ab.
Lokal bestanden elf PHP-Syntaxprüfungen und 19 Übersetzungsschlüssel samt
Platzhaltern in allen acht Sprachen.

## Französische Postleitzahlen

Der Installer kann zusätzlich 6.051 Postleitzahlen des französischen
Mutterlands inklusive Korsika einsetzen. Sie stammen aus dem La-Poste-Export
vom 8. November 2026. Geprüfte Hashes, reproduzierbare Umwandlung,
Abdeckungsgrenzen und Quellennennung stehen in [INTERNATIONAL.md](INTERNATIONAL.md).
Bestehende Quellen bleiben bei der Erstbefüllung unberührt. Die Suchseite
verlinkt Herausgeber und Lizenz, wenn der mitgelieferte Bestand aktiv ist.
Lokal bestanden der Konverterlauf, die Prüfsumme aller 6.051 Importdatensätze
und vier PHP-Syntaxprüfungen.
Der Ubuntu-Lauf bestätigte Import, führende Nullen, Korsika, korrekte
Quellenanzeige im HTTPS-Suchablauf und den Ausschluss von Monaco und Übersee.
[Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44956675099)

## Manuelle Zahlungsprüfung in acht Sprachen

Die geschützte Prüfseite lokalisiert Empfängerkonto, Zahlungsstatus,
Entscheidungsformular, PayPal-Hinweise und bekannte Ablehnungsgründe. Referenzen
und Beträge bleiben unverändert. Der zusätzliche HTTP-Test wechselt alle acht
Sprachen und prüft, dass eine ungültige Transaktionskennung keinen Zahlungsstatus
ändert. Lokal sind zehn PHP-Syntaxprüfungen, 33 Schlüssel je Sprache mit
Platzhalterparität und 40 isolierte Konfigurationsfälle bestätigt.

## Ausführbarer Ubuntu-Installer

`ops/install-ubuntu-22.04.sh` prüft Datenbank, privaten Speicher und sieben
Worker-Heartbeats vor dem Abschluss. Er aktiviert den lokalen Monitor-Timer,
plant verschlüsselte Backups optional ein und kann den ersten Administrator
interaktiv mit bestätigtem TOTP anlegen. `--prepare-only` hält die Website
geschlossen. `--finish-tls` nimmt die vorbereitete Installation mit ihrer
konfigurierten Domain ohne erneuten Image-Bau oder Migration in Betrieb.

Gezielt lokal geprüft wurden Bash-Syntax, PHP-Syntax sowie der TLS-Dry-Run.
Die neue isolierte CI-Probe auf Ubuntu 22.04 läuft über den ausführbaren
Installer. Im ersten Lauf blockierte Docker Compose den Lesenachweis für das
CA-Bündel außerhalb des Build-Kontexts. Der Installer baut die gepinnten
Images jetzt mit einem gezielt eingebundenen CA-Bündel vor der Update-Pause.
[Erster CI-Lauf](https://github.com/LLMbutHuman/GunIndex/actions/runs/44955709343)
Die Korrektur bestand auf einem frischen Ubuntu-22.04-Runner inklusive
HTTP-Sperre, HTTPS-Deploy-Hook mit isoliertem Testzertifikat,
Login-Schutz des Assistenten und Erhalt angepasster Timer-Dateien.
[Erfolgreiche Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44955864941)

Die freigegebene Datei `ZIP/PLZ.csv` wird bei einer frischen deutschen
Datenbasis automatisch importiert. Die transaktionale Wiederholung lässt
vorhandene und deaktivierte Quellen bestehen. Der lokale Probelauf prüfte den
SHA-256 und alle 8.289 PLZ ohne Schreibzugriff. Die Betriebsprobe prüft außerdem
die TOTP-Einrichtung des ersten Administrators über ein isoliertes Terminal.
Beide Betriebsjobs bestanden einschließlich TOTP-Anlage, Ablehnung eines
zweiten Erstkontos, bestätigtem PLZ-Bestand und Schutz einer deaktivierten
Quelle vor erneuter Erstbefüllung.
[Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44956070324)

Der Monitor liest die verschlüsselte Mailkonfiguration des Admin-Assistenten.
Eine gültige Konfiguration beseitigt die Warnung für deaktivierte Zustellung.
Nicht lesbare oder ungültige Einstellungen werden als Fehler gemeldet. Die
Probe läuft ohne Mailversand und rollt ihre Datenbankschreibvorgänge zurück.
[Erfolgreiche Maildiagnose und Installer-Probe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44956214430)

Die Ubuntu-Probe führt außerdem ein echtes Installer-Update durch. Sie
vergleicht den vorherigen und neuen Release und prüft den Erhalt von
Datenbankzugängen, Verschlüsselungsschlüsseln, Administratorkonto, PLZ-Quellen
und einer synthetischen privaten Datei. Der Ubuntu-Job war erfolgreich.
[Update-Probe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44957109960)

Der direkte Aufruf ohne Parameter startet die geführte Installation. Die
Vorschau prüft die Eingaben zu Domain und Zertifikatskontakt über ein Terminal
und plant auf frischen Installationen die Erstanlage des Administrators ein.

Zielserver, öffentliche DNS-/TLS-Prüfung, Mailzustellung und
Offsite-Wiederherstellung benötigen Betreiberinfrastruktur.
[Installationsanleitung](UBUNTU_22_04.md)

## Administratives Änderungsprotokoll in acht Sprachen, 2026-11-21

Das geschützte Protokoll zeigt jetzt auch automatische Ereignisse ohne
Mitarbeiterkonto. Beispielsweise bleiben PLZ-Importe in der Übersicht sichtbar.
Die Oberfläche kennzeichnet solche Zeilen als Systemereignisse, behandelt
Protokolldaten als Text und liefert die Übersicht mit privater Cache-Sperre aus.

Elf PHP-Syntaxprüfungen bestanden lokal. Der isolierte HTTP-Lauf bestätigte
Sprachwechsel, Darstellung eines Systemereignisses und Ablehnung von POST.
Beide CI-Jobs einschließlich der vollständigen Chromium-Regression bestanden.
[CI 44951839888](https://github.com/LLMbutHuman/GunIndex/actions/runs/44951839888)

## Austausch einer kompromittierten E2EE-Kontoautorität, 2026-11-21

Ein normales Kundenkonto mit eingerichtetem zweiten Faktor kann die
Chat-Identität mit Kontopasswort, zweitem Faktor und bestätigtem neuen
Wiederherstellungscode ersetzen. Der neue Schlüsselstand sperrt frühere
Geräte für neue Nachrichten. Kontakte müssen den neuen Fingerabdruck über
einen unabhängigen Kanal prüfen. Historische Manifeste, Nachrichten und Dateien
bleiben gespeichert. Dieser Browser kann das alte verschlüsselte Gerätearchiv
mit der früheren Passphrase öffnen, soweit verifizierbare Schlüssel vorliegen.

Die neue Historienprüfung verlangt einen vorhandenen Vertrauensanker und bei
älteren Manifesten eine Hash-Kette zu diesem Anker. Neue verschlüsselte Anhänge
binden beide aktuellen Manifestversionen. PHP-Syntax, JavaScript-Syntax,
Sprachparität sowie 13 echte OpenPGP-Testgruppen bestanden lokal. PHP hat
zusätzlich einen vom Browser-Code generierten neuen Schlüsselstand überprüft.
Migration, Produktionsimage, verschlüsselte Wiederherstellung und PHP-/MySQL-
Prüfungen bestanden. [Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44950707212)
Der erste Chromium-Lauf stoppte an der Sprachannahme für das dritte Gerät.
Das Gerät war zuvor mit dem noch italienischen Kontoprofil angemeldet worden.
Der Test setzt seine Sprache explizit. [Erster Lauf](https://github.com/LLMbutHuman/GunIndex/actions/runs/44950707264)
Die vollständige Wiederholung bestand mit 13 OpenPGP-, 17 PHP/MySQL- und
20 Chromium-Gruppen. Chromium bestätigte den Austausch, das Öffnen des alten
Anhangs, die erneute Kontaktprüfung, die Sperre vor der Prüfung und die
Wiederherstellung auf einem weiteren Gerät. Die unabhängige Sicherheitsabnahme
steht aus. [Erfolgreicher CI-Lauf 44951559532](https://github.com/LLMbutHuman/GunIndex/actions/runs/44951559532)
Privilegierte Konten benötigen zusätzlich eine verifizierte Mitarbeiteranmeldung
und einen frischen Code, der den bestehenden MFA-Zähler verwendet. Der HTTP-Test
prüft Ablehnung eines bereits verwendeten Codes und eine idempotente Wiederholung.

## Gemeinsame Admin-Navigation in acht Sprachen, 2026-11-21

Die Administration verwendet lokalisierte Linktexte und zugängliche
Bereichsnamen. Die vorhandenen Berechtigungsfilter und die Kennzeichnung der
aktuellen Seite bleiben wirksam. Lokal bestanden 19 Begriffe pro Sprache und
elf PHP-Syntaxprüfungen. Der isolierte Finanz-HTTP-Test prüft die sichtbare
Navigation und `aria-current` in allen acht Sprachen.
Beide CI-Jobs waren erfolgreich. [CI 44841239433](https://github.com/LLMbutHuman/GunIndex/actions/runs/44841239433)

## Wiederaufnahmepunkt, 2026-11-21

Die Abnahmematrix zählt 17 technisch geprüfte, sieben extern zu prüfende und
zwei offene oder teilweise umgesetzte nummerierte Anforderungen. Die beiden
offenen Nummern betreffen die vollständige Sprachabdeckung und einen realen
Treuhandadapter. Der Identitätswechsel im Chat ist ab `b4cf0d2` in der isolierten
Regression bestätigt. Die Änderung `71cfb65` erweitert das Auditprotokoll.

Als Nächstes die übrigen Finanz-, Moderations- und Datenschutzmasken sowie
Konto-/Supporttexte lokalisieren. Der Einstieg ist `/admin/payments.php`.
Rechtsfassungen benötigen fachliche Freigabe. Reale Treuhand, zusätzliche
Länderquellen, unabhängige E2EE-Sicherheitsprüfung und Zielsystemabnahme haben
externe Voraussetzungen. Die erneute Windows-Prüfung fand keine installierte
WSL-Distribution und keinen lokalen Docker-Desktop-Dienst.
[Abnahmematrix](TECHNICAL_ACCEPTANCE.md) · [Legal-Team](LEGAL_HANDOFF.md)

## Zahlungs- und Workerdiagnose im Admin-Setup, 2026-11-21

Der geschützte Einrichtungsassistent zeigt den Konfigurationsstatus von PayPal
Checkout, PayPal-Erstattungen, Banküberweisung, Bitcoin und Treuhand einzeln an.
Der Assistent bewertet vorhandene Konfiguration und Freigabeschalter anhand
der bestehenden Zahlungsprüfungen. Die Treuhand-Live-Funktion steht auf
deaktiviert. Admin-Setup und Healthcheck verwenden die gleiche Prüfung der
sieben Heartbeats; gleichzeitige, neuere oder ungültige Fehlermeldungen führen
zu einer Warnung.

Lokal bestanden zwölf Setup-Gruppen, Übersetzungsprüfung in acht Sprachen,
14 PHP-Syntaxprüfungen und Bash-Syntax. HTTP- und Produktionsimage-Läufe
prüfen die sichtbaren Statusangaben und den Healthcheck mit sieben Workern.
Die isolierte Betriebsprobe mit allen sieben Workerzuständen war erfolgreich.
[CI 44840320988](https://github.com/LLMbutHuman/GunIndex/actions/runs/44840320988)
Auch PHP/MySQL und die Chromium-Regression waren erfolgreich.
[CI 44840320951](https://github.com/LLMbutHuman/GunIndex/actions/runs/44840320951)

## Prüfsummengebundener PLZ-Import, 2026-11-21

Der Importer kann die eingelesene CSV an einen erwarteten SHA-256-Wert binden.
Validierung, Quellnachweis und Import beziehen sich auf dieselbe Momentaufnahme.
Lokal ergab die bereitgestellte Datei 8.289 eindeutige PLZ. Eine abweichende
Prüfsumme wurde vor einem Datenbankzugriff abgewiesen.

Die isolierte Installationsprobe importiert die vollständige Benutzerdatei mit
ihrer dokumentierten Rechtebestätigung für diesen Test. Sie prüft führende Nullen,
Landesgrenzen, exakte PLZ und Umkreissuche mit den tatsächlichen Koordinaten der
Datei. Der isolierte MySQL-Import und alle Suchprüfungen bestanden.
Weitere Länderquellen benötigen eigene Nachweise.
[CI 44839813273](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839813273)

## Belege und Korrekturen je Auktion, 2026-11-21

Der Link auf einem Gebührenauftrag öffnet die Rechnungsübersicht für die
zugehörige Auktion. Dort erscheinen Einstellforderung, Erfolgsprovision,
ersetzte Aufträge, Belege und Erstattungsanfragen. Abfragen und Aktionen
prüfen den Eigentümer und die konkrete Auktion. Die allgemeine Übersicht
bleibt über einen eigenen Link erreichbar.

Lokal bestanden drei PHP-Syntaxprüfungen und 16 reine Gebührenprüfungen.
Der HTTP-/MySQL-Test verbindet Laufzeitverlängerung, Teilkorrektur,
Zuschlag, PDF-Downloads und Zugriffsschutz. Beide CI-Jobs bestanden,
einschließlich 32 Gebühren-Integrationsgruppen und Chromium.
[CI 44839394997](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839394997)

## Werbeplatzbuchung in acht Sprachen, 2026-11-21

Reservierung, Kontingente, Fehlermeldungen, Zahlungslinks sowie Start- und
Endfristen für Premiumplätze verwenden die gewählte Sprache. Die Meldung
nach Zahlungsprüfung folgt der gespeicherten Empfängersprache. Die bisherigen
Vertragstexte für Bestellung und sofortigen Start sind als deutsche Texte
markiert. Die Buchung und die ausdrückliche Startfreigabe sind getrennte Schritte.

Lokal bestanden 13 Syntaxprüfungen, sieben Geo-/Sprachgruppen sowie Schlüssel-
und Platzhalterprüfung für 37 Texte in acht Sprachen. Die isolierten Tests
prüfen die echte Buchungsseite je Sprache und eine einzelne französische
Bestätigung nach wiederholter Zahlungsprüfung. Beide Jobs bestanden,
einschließlich zwölf Werbe- und 38 Finanzprüfgruppen sowie Chromium.
[CI 44838930427](https://github.com/LLMbutHuman/GunIndex/actions/runs/44838930427)

## Isolierte Installations- und Wiederherstellungsprobe, 2026-11-21

Die Installations-CI verwendet das reale Produktionsimage, eine frische
MySQL-Instanz und private Volumes. Sie prüft Bootstrap, verschlüsselte
Sicherung, Wiederherstellung in eine neue Datenbank, Dateiintegrität und
Heartbeats der sieben Worker. Der Migrationsrunner erkennt eine bereits
angelegte, exakt passende ENUM-Spalte nach einer Unterbrechung und verbucht
die Migration nach der Fortsetzung. Die SQL-Datei 038 bleibt unverändert.
Der erste isolierte Lauf hat das Produktionsimage gebaut und das verschlüsselte
Backup verifiziert. Beim simulierten Wiederanlauf fiel die mehrzeilige Anweisung
aus der bestehenden Erkennung für `ADD COLUMN`; MySQL meldete eine doppelte Spalte.
Die Erkennung prüft jetzt auch mehrzeilige Anweisungen. PHP-Syntax und Erkennung
der konkreten Migration bestanden lokal. Die erneute Probe hat das
Produktionsimage, Einrichtung, Sicherung, Wiederherstellung, zweimaligen
Migrationslauf und sieben Worker-Heartbeats erfolgreich geprüft.
[Erster CI-Lauf 44838139417](https://github.com/LLMbutHuman/GunIndex/actions/runs/44838139417)
[Erfolgreicher CI-Lauf 44838740876](https://github.com/LLMbutHuman/GunIndex/actions/runs/44838740876)

## Finanzverwaltung in acht Sprachen, 2026-11-21

Belege, Steuerregeln, Erstattungsformulare und Validierungen der Finanzverwaltung
verwenden die ausgewählte Sprache. Der Steuersatz muss eine ganze Anzahl von
Basispunkten zwischen 0 und 10000 sein. Fachlich verfasste Steuererläuterungen
und Begründungen werden in ihrer ursprünglichen Form gespeichert und angezeigt.

Der Status einer abgelehnten Erstattungsanfrage und sein Audit-Eintrag werden
gemeinsam gespeichert. Eine identische Wiederholung nutzt die vorhandene
Entscheidung. Lokale Prüfungen bestanden für 35 Übersetzungsschlüssel je Sprache,
13 PHP-Dateien sowie 21 Zahlungs-, 16 Gebühren- und sieben Geo-/Sprachgruppen.
Der isolierte HTTP-Test prüft zusätzlich MFA, CSRF, alle Sprachfassungen,
unveränderte Steuerregeln bei ungültiger Eingabe und die einmalige Protokollierung.
Beide CI-Jobs bestanden, darunter 38 Finanzprüfgruppen und Browserregression.
[CI 44837672082](https://github.com/LLMbutHuman/GunIndex/actions/runs/44837672082)

## Reproduzierbares Ubuntu-Release-Archiv, 2026-11-21

Der Installer verpackt den ausgewählten Git-Commit. Der vorherige allgemeine
`vendor`-Ausschluss entfernte auch das für den Browserchat benötigte OpenPGP-Modul.
Das neue Archiv enthält das Modul und schließt private lokale Dateien durch den
Commit-Export aus. Der Installer prüft den Inhalt vor dem Anhalten der laufenden
Dienste. Auch das Produktionsimage verlangt das Modul im Build-Kontext.
Zielpfade werden nach Auflösung symbolischer Links erneut geprüft.

Beide Bash-Syntaxprüfungen bestanden. Ein lokaler Export des tatsächlichen Branches
enthielt das OpenPGP-Modul mit identischem SHA-256 und weder `.git` noch lokale
Auftrags- oder Laufzeitdateien. Die isolierte Linux-Testsuite enthält zusätzlich
einen temporären Git-Export mit lokalen Änderungen und Testgeheimnissen.
Ein separater CI-Job baut das Produktionsimage aus diesem Archiv und prüft
die OpenPGP-Prüfsumme sowie PHP-Erweiterungen und Composer-Bibliotheken im
Image. Der Build und der anschließende Test im Container waren erfolgreich.
[CI 44837028753](https://github.com/LLMbutHuman/GunIndex/actions/runs/44837028753)

## Neuer Auktionsdurchlauf nach Verkauf, 2026-11-21

Der Verkäufer kann eine per Gebot oder Sofortkauf abgeschlossene Auktion
nach ausdrücklicher Bestätigung eines weiteren Exemplars erneut einstellen.
Abschlussereignis, Gewinner und Preis müssen zusammenpassen. Der neue Entwurf
behält die Versandkosten und verwendet bei Veröffentlichung den aktuellen
Tarif. Der vorhandene Rabatt für einen späteren Abschluss ohne Gebote bleibt
verfügbar. Bestandsverknüpfungen bleiben durch Migration 038 kompatibel.

Lokal bestanden 13 PHP-Syntaxprüfungen und 24 reine Wiedereinstellungsgruppen.
Der isolierte MySQL-/HTTP-Test prüft beide Verkaufswege samt Bestätigung,
Gebühr und unveränderter Vorgeschichte. Beide CI-Jobs waren erfolgreich.
[CI 44836432968](https://github.com/LLMbutHuman/GunIndex/actions/runs/44836432968)

## Werbelaufzeiten bei Wartung, 2026-11-21

Die Wiedereröffnung erhält die Restlaufzeit laufender bezahlter Werbung und
die verbleibende Startfrist bezahlter Buchungen. Während der Wartung bestätigt
eingegangene Zahlungen erhalten ab Wiedereröffnung bis zu sieben Tage zum Start.
Unbezahlte Reservierungen laufen nach ihrer ursprünglichen Frist ab. Abgelaufene
oder stornierte Werbelaufzeiten werden nicht reaktiviert. Der Ablaufworker prüft
die Wartungssperre auch bei einem direkten Aufruf, und die Wiedereröffnung
protokolliert die Anzahl der verlängerten Fristen.

Lokal bestanden drei Syntaxprüfungen und zwölf reine Wartungsprüfungen.
Der isolierte MySQL-Test für eine simulierte neuntägige Unterbrechung prüft
zusätzlich Eingangsbestätigung, Fristfortsetzung, erneutes Öffnen und Audit.
Beide CI-Jobs bestanden, einschließlich elf Werbe- und neun Auktions-/Worker-
Prüfgruppen sowie Browser und Barrierefreiheit.
[CI 44835645014](https://github.com/LLMbutHuman/GunIndex/actions/runs/44835645014)

## PDF-Beschriftung mit unveränderlichem Sprachstand, 2026-11-21

Neue Rechnungsaufträge speichern die Beschriftungen der Bestellsprache im
geprüften Snapshot. Die PDFs verwenden auch nach einem späteren Sprachwechsel
die gespeicherten Texte. Das gilt für neue Standard- und Auktionsaufträge.
Ältere Snapshots behalten ihr Layout. Beträge und vom Betreiber verfasste
Steuer- und Rechtstexte werden aus dem ursprünglichen Nachweis übernommen.
Beide CI-Jobs bestanden. Die 37 Finanzgruppen enthalten die echte PDF-Erzeugung
mit stabiler Beschriftung nach einem Sprachwechsel in allen acht Sprachen.
[CI 44834934106](https://github.com/LLMbutHuman/GunIndex/actions/runs/44834934106)

## Manuelle Zahlungsaufträge und Guthaben in acht Sprachen, 2026-11-21

Die manuelle Auftragsseite, Guthabenauswahl, Zahlungshinweise und
Freigabegründe verwenden die gewählte Sprache. Die kanonischen und
bereits gespeicherten Vertragsbedingungen bleiben in ihrer Originalsprache.
Die HTTP-Prüfung umfasst auch die fehlende ausdrückliche Bestätigung.
Der vollständige Lauf einschließlich Browser/Barrierefreiheit war erfolgreich.
[CI 44834451740](https://github.com/LLMbutHuman/GunIndex/actions/runs/44834451740)

## Rechnungsübersicht und Rechnungsanschrift in acht Sprachen, 2026-11-21

Rechnungsübersicht, Dokumentarten, Storno- und Erstattungsformulare,
Rechnungsanschrift und häufige Validierungsfehler verwenden acht Sprachen.
Laufende HTTP-Prüfungen decken die Ansichten und ungültige Eingaben je Sprache
ab. Die bereits gespeicherten Rechnungsnachweise behalten ihre Inhalte. Beide
Jobs für PHP/MySQL und Browser/Barrierefreiheit waren erfolgreich.
[CI 44833857986](https://github.com/LLMbutHuman/GunIndex/actions/runs/44833857986)

## Zahlungsübersicht und PayPal-Rückkehr in acht Sprachen, 2026-11-20

Die Zahlungsübersicht, Statusbezeichnungen, Preisvorschau und PayPal-Rückkehr
verwenden acht Sprachfassungen. Auch die häufigen PayPal-Konfigurations- und
Antwortfehler sind lokalisiert. Die isolierten HTTP-Tests wechseln durch alle
acht Sprachen und prüfen, dass Aufruf und Abbruchhinweis den Zahlungszustand
unberührt lassen. Beide CI-Jobs bestanden. Weitere ältere Finanzmasken sind offen.
[CI 44833175209](https://github.com/LLMbutHuman/GunIndex/actions/runs/44833175209)

## Versionierte Zustimmung für Bestandskonten, 2026-11-20

Die geschützte Einstellung verbindet neue Marktplatzvorgänge bestehender
Konten mit den aktuell freigegebenen Pflichtdokumenten. Rechnungen,
Datenschutz, Abmeldung und laufende Vertragsabwicklung bleiben erreichbar.
Die Bestätigungsseite verwendet die vorhandenen versionierten Hash- und
Zustimmungsbelege. Sie speichert ausdrücklich bestätigte Fassungen als
`updated_terms` und lässt ältere Nachweise unverändert. Standardmäßig ist
die Pflicht für bestehende Konten deaktiviert. Die isolierte HTTP-Prüfung
bestätigte die aktuelle Version, CSRF, Wiederholung ohne Doppelnachweis und
weiter zugängliche Rechnungs-, Datenschutz- und bestehende Vertragsfunktionen.
[CI 44794702907](https://github.com/LLMbutHuman/GunIndex/actions/runs/44794702907)
für `1da9548` ist vollständig grün. [Vertragsbindung](CONTRACT_BINDING.md)

## Händler-Abonnements und CSV-Export, 2026-11-20

Migration 037 bindet ein vorausbezahltes Händler-Abo an den unveränderlichen
Rechnungsauftrag und die bestätigte Rechtsdokumentversion. Es umfasst 30 Tage
Bestandsexport und kann ausdrücklich verlängert werden. Nur der verifizierte
Rechnungsabschluss beginnt oder erweitert die Laufzeit. Vollständige
Erstattung oder ein Bitcoin-Klärfall entziehen die betroffene Laufzeit.
Die Konfiguration beginnt ohne Verkaufspreis und mit geschlossener Freigabe.
[CI 44793558506](https://github.com/LLMbutHuman/GunIndex/actions/runs/44793558506)
für `17951b2` ist vollständig grün. Migration 037 und 35 Finanzgruppen
einschließlich synthetisch geprüftem Bankeingang, Laufzeitverlängerung,
Erstattungen und der tatsächlichen CSV-Ausgabe bestanden.
[Händler-Abonnement](DEALER_SUBSCRIPTION.md)

## Warenbestand im Checkout, 2026-11-20

Migration 036 verbindet einen Händlerbestand mit ausgewählten Festpreis- oder
Verhandlungsinseraten. Die Bestätigung bindet die Verknüpfung und bucht genau
ein Stück im selben Datenbankabschluss wie den Kauf. Vor Versand kann ein
Storno automatisch ausgleichen. Nach Versand oder an der Mengengrenze prüft
das Lagerteam die Rückgabe. Öffentliche Angebotsseiten zeigen leeren Bestand.
[CI 44792170434](https://github.com/LLMbutHuman/GunIndex/actions/runs/44792170434)
für `1d1f79b` ist vollständig grün. Migration 036, paralleler Erwerb des letzten
Stücks, wiederholte Anfragen, Storno vor und nach Versand sowie die vollständige
Browserregression bestanden.

## Händler-API, 2026-11-20

Migration 035 ergänzt begrenzte, widerrufbare API-Schlüssel. Der Inhaber
erzeugt den Schlüssel nach Passwortprüfung und sieht das Geheimnis einmal.
Der private JSON-Endpunkt liefert Bestand und Bestellübersicht und verbucht
autorisierte, wiederholbar abgesicherte Bestandsbewegungen. Mandant und Scope
stammen aus dem Schlüssel. Der Endpunkt ist standardmäßig deaktiviert.
Die vollständige [CI 44790906063](https://github.com/LLMbutHuman/GunIndex/actions/runs/44790906063)
für `cd96a1b` ist grün. Migration 035, Schlüsselprüfung, Mandantentrennung,
Scope-Entzug, Widerruf, Ablauf und wiederholte HTTP-Buchungen sind bestätigt.
[Händler-API](DEALER_API.md)

## Händlerteams und Warenbestand, 2026-11-20

Migrationen 033 und 034 ergänzen Einladungen, getrennte Rollen und einen
Bestand je freigegebenem Händlerbetrieb. Die Mitglieder nehmen persönlich an.
Der Bestandsverlauf speichert Zu- und Abgänge mit Revisionsschutz und
Wiedererkennung identischer Anfragen. Die Bestellübersicht zeigt nur Referenz,
Betrag, Status und Versandart. Team- und Bestandsseiten sind achtsprachig.

Der erste Bestandslauf erwartete die Mitarbeiterkennung fälschlich in der
Leseansicht. `2ee9798` prüft sie im internen Bewegungsprotokoll und ihre
Abwesenheit im Leseergebnis. Fünf MySQL-/HTTP-Gruppen einschließlich CSRF,
Rollen, Prüfpfad und Mandantentrennung sind mit der Händler-API in
[CI 44790906063](https://github.com/LLMbutHuman/GunIndex/actions/runs/44790906063)
vollständig grün. [Händlerteam und Bestand](ARCHITECTURE.md#händlerteam-und-bestand)

## Sprachpfade, Canonicals und Sitemap, 2026-11-20

Die acht Sprachen sind für Start- und öffentliche Angebotsseiten über feste
Pfade erreichbar. Sprachwechsel und Angebotsweiterleitungen erhalten die
gewählte Sprache. Canonicals, Product-URL und HTML-/XML-Alternativen verwenden
denselben Pfad. Indexierung bleibt freigabepflichtig. Texte aus Inseraten
bleiben in der vom Verkäufer verfassten Sprache. [SEO](SEO.md)

13 lokale SEO-Fälle, fünf HTTP-Gruppen und MySQL-Sitemapabgrenzung bestanden.
[CI 44788077123](https://github.com/LLMbutHuman/GunIndex/actions/runs/44788077123)
für `104030e` ist vollständig grün. Dazu gehören beide Windows-/Linux-Jobs und
die Chromium-Regression. Vor öffentlicher Indexierung steht die Abnahme am
HTTPS-Ziel aus.

## Treuhandablauf im Testsystem, 2026-11-19

Migration 032 verbindet Chatangebote, veränderliche Gegenangebote und feste
Zahlungsreferenzen mit einem isolierten Treuhandverlauf. Die Parteien pflegen
getrennte verschlüsselte Testkontakte. Annahme, Versand, Empfang, Streitfälle,
Freigabe, Storno, Erstattung, Rückbuchung und Fristen sind rollen- und
versionsgebunden. Der Anbieterablauf verwendet ausschließlich den internen
Simulator im isolierten Entwicklungsschema. [Treuhandablauf](ESCROW.md)

Der HTTP-Test der Vorläufer `78269a8` und `fa988cf` fand eine Namenskollision
mit dem gemeinsamen Layout. `4d24dc7` behebt sie. Der Gesamtlauf für
`104030e` ist vollständig grün. Er bestätigt Migration 032, die
Zustandsregeln, vier MySQL-Gruppen, beide Chatansichten und die Finanzansicht
über HTTP sowie zwei gleichzeitig ausgeführte Annahmen. Genau eine reserviert
den Artikel. Für Live-Betrieb fehlen eine echte Providerintegration und die
freigegebenen Treuhandbedingungen. [CI 44788077123](https://github.com/LLMbutHuman/GunIndex/actions/runs/44788077123)

## Mail-Einrichtung und SMTP-Relay, 2026-11-19

Migration 031 speichert eine verschlüsselte Konfiguration für lokalen Versand
oder ein authentifiziertes TLS-Relay. Die geschützte Seite ist in acht Sprachen
verfügbar und mit der Mailwarteschlange und dem Admin-Assistenten verbunden.
SPF, DKIM und DMARC werden am DNS geprüft. Ein Testauftrag geht an die
Adresse des angemeldeten Administratorkontos, gebunden an die Konfiguration.
Die Transportannahme und die tatsächliche Empfängerzustellung sind getrennt.

Lokale PHP-Syntax, Sprachparität, Setup- und DNS-Prüfungen sowie sechs
SMTP-Logik- und vier echte SMTP-TLS-Tests bestanden.
[CI 44581824454](https://github.com/LLMbutHuman/GunIndex/actions/runs/44581824454)
für `cd0f2b0` ist vollständig grün. Windowsjob `135764197510` und Linuxjob
`135764196816` bestanden. MySQL bestätigte Migration 031, Rollenprüfung,
Verschlüsselung, Revisionsschutz und Warteschlangenbindung. Die HTTP-Tests
prüften MFA, CSRF, Speicherung und Versand an das eigene Konto. STARTTLS,
SMTPS, Fehlerfälle, Composer-Audit und Browserregression bestanden. Für die
Produktivprüfung werden Betreiber-Domain, Mailanbieter und DNS benötigt.
[Mail-Einrichtung](MAIL_SETUP.md)

## Bitcoin-Eingänge und Reorganisationen, 2026-11-19

Migration 030 speichert Bitcoin-Aufträge mit Kursfrist, Satoshis und
Netzwerk. Die Empfangsadresse stammt aus einer Watch-only-Wallet. Der
Worker liest den Eingang von Bitcoin Core, prüft die aktive Kette und
verbucht genau einen passenden bestätigten Ausgang. Abweichungen und
Reorganisationen erscheinen im Finanzbereich. Regtest erzeugt keine
Echtbuchung oder Leistung. Mainnet bleibt durch gesonderte Freigaben gesperrt.

Lokale PHP-/Shell-Syntax und zehn RPC-Adapterfälle bestanden. Der Lauf
[CI 44579766951](https://github.com/LLMbutHuman/GunIndex/actions/runs/44579766951)
für `e69c32f` ist vollständig grün. Windowsjob `135756928616` und Linuxjob
`135756928016` bestanden. Migration 030, 34 Finanzprüfungen, Bitcoin Core
30.4 mit isolierter Watch-only-Wallet, exaktem Satoshi-Betrag, aktiver
Kettenbestätigung und echter Reorganisation sowie die Browserregression
bestanden. Ein tatsächlicher Mainnet-Betrieb bleibt gesondert abzunehmen.

## Banküberweisungen und Kontenabgleich, 2026-11-19

Migration 029 verknüpft freigegebene EUR-Aufträge mit einer eindeutigen
Überweisungsreferenz und dem gespeicherten Empfängerkonto. Der neue
Bankabgleich verarbeitet camt.053.001.08 mit geschütztem Import und
separater Prüfung im tatsächlichen Bankkonto. Betragsabweichungen,
Dubletten und Rückbuchungen werden vor der Freigabe geprüft.
Die Buchung verwendet die bestehenden Rechnungs- und Leistungsabläufe.
Extern ausgeführte Bankrückzahlungen können getrennt dokumentiert werden.

Lokale Syntax, acht Kontoauszugstests, 21 Zahlungs-, 16 Gebühren- und
sieben Geo-/Sprachprüfungen bestanden.
[CI 44577373006](https://github.com/LLMbutHuman/GunIndex/actions/runs/44577373006)
für `84b78ab` ist vollständig grün. Windowsjob `135748401790` und
Linuxjob `135748401103` bestanden. Migration 029, 30 Finanzprüfungen und
acht camt.053-Prüfungen bestanden. Im HTTP-Fall wurden Auswahl im
Checkout, echter Multipart-Upload, getrennte Eingangsbestätigung und
Kontoberechtigungen geprüft. Der Chromium-Regressionslauf ist grün.
Die Abnahme des tatsächlichen Bankkontos und seines Originalexports steht aus.

## Automatisierte PayPal-Erstattungen, 2026-11-19

Migration 028 speichert die vom Finanzteam freigegebene Erstattung mit
Capture-Bezug, Betrag, PayPal-Anfrageschlüssel und festem Konto-/Moduskontext.
Ein Worker übermittelt sie und gleicht ausstehende Status ab. Signierte
Webhooks lösen eine validierte PayPal-Statusabfrage aus. Unklare Ergebnisse
verwenden ein begrenztes Wiederholungsfenster mit derselben Anfragereferenz
und bleiben danach zur Klärung gesperrt.

Die bestehende Rechnungs- und Ledgerlogik bucht bestätigte Live-Erstattungen.
Der Sandbox-Ablauf ist als Testvorgang sichtbar. Rollen, CSRF, Finanzfreigabe
und die zusätzlichen standardmäßig deaktivierten Erstattungsschalter
begrenzen den Einstieg. Bedienung und Status sind in acht Sprachen verfügbar.

PHP-Syntax, Shell-Syntax und 21 Zahlungs-, 16 Gebühren- sowie sieben Geo- und
Sprachprüfungen sind lokal bestanden.
[CI 44540263687](https://github.com/LLMbutHuman/GunIndex/actions/runs/44540263687)
für `02c2818` ist vollständig grün. Windowsjob `135616379371` und Linuxjob
`135616378700` bestanden. Der Linuxjob bestätigte Migration 028, 21
Zahlungs-Unitprüfungen, 27 Finanzprüfungen, 31 Gebühren-Integrationstests und
den Chromium-Lauf einschließlich Mobil- und Barrierefreiheitsprüfungen.
Die neuen Fälle prüften getrennte Freigaben, sicheren Wiederanlauf,
signierten Statusabgleich, genau einen Korrekturbeleg, getrennte Sandbox-Buchung,
Freigabe nach endgültigem Abbruch sowie MFA und CSRF. Providerantworten waren
synthetisch. Die Abnahme mit einem echten PayPal-Sandboxkonto und die
produktive Anbieterfreigabe stehen aus.

## Teilkorrektur unbezahlter Auktionsgebühren, 2026-11-19

Migration 027 verknüpft den ersetzten Auftrag, den verbleibenden Centbetrag
und die neue Zahlungsreferenz. Die ursprüngliche Gebühr, der verschlüsselte
Rechnungsnachweis und die Rechtszustimmungen bleiben erhalten. Finanzpersonal
mit MFA kann offene Gebühren begründet mindern oder vollständig erlassen.
Wiederholungen und konkurrierende Teilkorrekturen sind über Auftragssperren und
eindeutige Nachfolger abgesichert.

Ein verifizierter Eingang auf eine ersetzte Referenz wird als separater
Klärungsfall erfasst. Bis zur dokumentierten vollständigen Rückzahlung ist
die korrigierte Zahlung gesperrt. Die Plattform protokolliert dabei
tatsächlich extern abgeschlossene Rückzahlungen. Der Kunde erhält Verweise
zwischen den Aufträgen und die korrigierte Rechnung nach bestätigter Zahlung.

Lokal bestanden PHP-Syntax, 16 Gebühren-, 20 Zahlungs- und sieben Geo- und
Sprachprüfungen. Der vollständige
[CI-Lauf 44519911880](https://github.com/LLMbutHuman/GunIndex/actions/runs/44519911880)
für `5b582f6` ist grün. Linuxjob `135545561521` bestätigte Migration 027,
31 Gebühren-Integrationstests und den Chromium-Regressionslauf. Darunter
sind zwei konkurrierende Korrekturen, unveränderte historische Nachweise,
Rückzahlung ohne doppelte Buchung, PDF und Kontogrenzen. Die Nachweise sind
synthetisch.

`239fb47` bindet Zahlungshinweise an denselben Klärungsstatus und ermöglicht
eine erneute, unbestätigte Meldung nach Ablehnung. MySQL und HTTP bestanden in
beiden Versuchen von [CI 44521029120](https://github.com/LLMbutHuman/GunIndex/actions/runs/44521029120).
Chromium war durch HTTP 503 beim Ubuntu-Paketdownload blockiert. Auch ein
Lauf mit dem offiziellen Ubuntu-Archiv scheiterte beim Paketindex.
Die CI installiert die gesperrte Playwright-Browserversion und prüft den
Start mit den Browseroptionen der Tests. Systempakete werden bei einem
fehlgeschlagenen Start nachgeladen. Der erfolgreiche Runner verwendete
`/usr/bin/chromium`, wie im vorhandenen Browser-Helfer vorgesehen.

[CI 44525631491](https://github.com/LLMbutHuman/GunIndex/actions/runs/44525631491)
für `e1859f6` ist vollständig grün. Windowsjob `135565319284` und
Linuxjob `135565318524` bestanden. Der Linuxjob bestätigte die 31
Gebühren-Integrationstests einschließlich der zusätzlichen HTTP-Fälle für
Zahlungshinweise und den Chromium-Regressionslauf mit Mobil- und
Barrierefreiheitsprüfungen.

## Profilbilder und Händlerlogos, 2026-11-19

Migration 026 ergänzt je Konto ein Profilbild und ein Händlerlogo. Der Upload
übernimmt nur gültige JPEG-, PNG- oder WebP-Dateien bis 5 MiB und 20 Megapixel.
Gespeichert wird ein neu kodiertes WebP von höchstens 400 Pixeln pro Seite und
512 KiB. Originale werden verworfen. Die Bedienung ist in acht Sprachen verfügbar.

Profilbilder sind am öffentlichen Profil sichtbar. Bei einem neuen Händlerlogo
wird das Händlerprofil erneut zur Prüfung vorgelegt. Eigentümer und berechtigtes
Personal mit MFA können das eingereichte Logo vorab ansehen. Die Entscheidung
bindet die geprüfte Logorevision. Entfernen blendet das Bild sofort aus. Der
Kontoexport enthält die eigene Bilddatei, bei Anonymisierung wird sie entfernt.

PHP- und JavaScript-Syntax, Sprachschlüssel und lokale Bildreihenfolge-Tests
sind geprüft. Der erste Browserlauf auf `74577eb` scheiterte beim Uploadformular,
weil die beiden Routen im öffentlichen Dispatcher fehlten. `c8d7d02` ergänzt
sie sowie den HTTP-Test für den geschützten Einstieg.

[CI 44512195727](https://github.com/LLMbutHuman/GunIndex/actions/runs/44512195727)
ist für `c8d7d02` vollständig grün. Windowsjob `135518980260` und Linuxjob
`135518979518` bestanden. MySQL prüfte Migration 026 und Löschung bei
Anonymisierung. Chromium bestätigte WebP-Neukodierung, Bildgrenzen,
CSRF-Sperre, private Logo-Vorschau, Ablehnung einer veralteten Freigabe sowie
Freigabe, Export und sofortiges Entfernen. Die übrigen Regressionen bestanden.

Die Chromium-Einrichtung dauerte im erfolgreichen Lauf rund zwölf Minuten.
Die zwischenzeitliche Zehn-Minuten-Grenze aus `0e82d16` wurde nach der
Zeitmessung zurückgenommen. Die bestehende Jobgrenze von 30 Minuten gilt.

## Optionale Benutzer-MFA und Passkeys, 2026-11-19

Migrationen 024 und 025 ergänzen optionale TOTP-Anmeldung, einmalige
Wiederherstellungscodes und mehrere registrierbare Passkeys. Der TOTP-Schlüssel
ist verschlüsselt. Recovery-Codes werden einmal angezeigt und als Hash
gespeichert. Aktivierung, Wechsel und Widerruf erneuern die aktuelle Sitzung
und sperren ältere Sitzungen. Der bestehende Passwortreset entfernt keinen
zweiten Faktor. Die Pflicht-MFA für Personal bleibt erhalten.

Passkeys verwenden `lbuchs/webauthn` 2.2.0 aus dem Composer-Lockfile.
Serverseitig werden exakter Origin, RP-ID, einmalige Challenge, Besitz der
registrierten Credential und Benutzerverifikation geprüft. Passkey-Anmeldung
führt bei aktivem TOTP ebenfalls zur zweiten Prüfung. In Produktion müssen
Browseradresse und HTTPS-`APP_URL` übereinstimmen. Der isolierte Browser-Test
verwendet `http://localhost:8081` und einen virtuellen Authenticator.

Für `9b4bea4` war [CI 44510526788](https://github.com/LLMbutHuman/GunIndex/actions/runs/44510526788)
vollständig grün. Windowsjob `135513280952` und Linuxjob `135513281279`
bestanden. MySQL prüfte Migrationen 024–025, TOTP-Aktivierung, einmalige
Recovery-Codes, Rotation, Versionsschutz und Entfernen. Chromium prüfte
Origin-Sperre, echte Registrierung und Anmeldung mit virtuellem Authenticator,
TOTP nach Passkey-Anmeldung, Recovery-Replay und Passkey-Widerruf. Der
Composer-Audit meldete keine bekannten Sicherheitshinweise. Die Prüfung mit
physischer Hardware und den Browsern am Zielsystem steht zur Betriebsabnahme aus.

## Private Inseratentwürfe und Vorschau

Der gemeinsame Abschlusslauf
[CI 44406392996](https://github.com/LLMbutHuman/GunIndex/actions/runs/44406392996)
für `0c6c04f530a5e89d62b5bceba7b99a69c63393da` ist vollständig grün.
Windowsjob `135162635719` und Linuxjob `135162636332` sind bestanden.
Geprüft sind Migrationen 021–023, Entwürfe mit Bildübernahme, Preis- und
Ablaufmeldungen, Einzelwiderruf und Passwortwechsel, neun neue Chromium-Fälle
sowie die bestehenden E2EE-, Handels- und Betriebsregressionen.

Migration 021 ergänzt getrennte private Arbeitsstände für neue und bestehende
Inserate. Formulare speichern auch unvollständige Eingaben und aufbereitete
Produktbilder automatisch. Ein Arbeitsstand pro Editor, maximal zwölf je Konto
und 24 MiB für Zwischenbilder begrenzen den Speicherbedarf. Die Versionsprüfung
erkennt konkurrierende Tabs sowie Änderungen am gespeicherten Ausgangsinserat.
Zwischenstände sind im Konto wieder aufrufbar und einzeln verwerfbar.

Die private Vorschau zeigt Bilder, Beschreibung, Merkmale, Preis, Lieferart und
Laufzeit. Ein manuelles Vorschauformular funktioniert ohne JavaScript. Die
Veröffentlichung übernimmt die Bilder im bestehenden Inseratvorgang. Erst hier
greifen die Pflichtfelder, ausdrückliche Zustimmungen und Auktionsgebühren.
Private Vorschauen und Bilder erfordern das Eigentümerkonto und werden nicht
gecached. Die neue Bedienung ist in acht Sprachen verfügbar.

Lokale Syntax-, Sprach- und Dienstprüfungen sind erledigt. In
[CI 44401294604](https://github.com/LLMbutHuman/GunIndex/actions/runs/44401294604)
für `f7c693c` bestanden Windows, Migration 021, PHP/MySQL und vier
Chromium-Fälle einschließlich Bildübernahme, fremdem Zugriff und der nativen
Vorschau ohne JavaScript. Beim geprüften Anonymisieren werden die
privaten Zwischenstände samt Bildern entfernt. Der Eigentümerexport enthält
Zwischenstände und aufbereitete Bilder.

## Preis- und Ablaufbenachrichtigungen

Migration 022 ergänzt eine transaktionale Warteschlange für Preisänderungen,
einen Hinweis innerhalb der letzten 24 Stunden und Meldungen beim Ablauf.
Gemerkte Inserate, Empfängersprache und Kanalpräferenzen werden berücksichtigt.
Die Verarbeitung läuft in begrenzten Batches mit Wiederaufnahmepunkt. Überholte
Preise und kurze Fristverschiebungen erzeugen keine neuen Folgenachrichten.
Ein Opt-out vor dem Mailversand stoppt auch bereits eingereihte Inseratmeldungen.
Lokale Syntax- und Sprachprüfungen grün. In
[CI 44402842458](https://github.com/LLMbutHuman/GunIndex/actions/runs/44402842458)
für `4440985` sind Migration 022, MySQL-Prüfungen mit Mail-Testtransport,
Opt-out im Browser und bestehende Regressionen grün.

## Geräte und einzelne Sitzungen

Migration 023 registriert authentifizierte Sitzungen. Bereits angemeldete
Nutzer werden innerhalb ihrer bisherigen Zeitgrenzen erfasst. Eine private
Übersicht zeigt die aktuelle Sitzung, grobe Browser- und Plattformangaben und
Zeitpunkte. Der Widerruf gilt beim nächsten authentifizierten Zugriff der
betroffenen Sitzung. Bestehender TOTP-, Passwort- und Kontowiderruf bleibt
wirksam. Rohdaten zum Gerät, IP-Adressen und PHP-Sitzungskennungen werden im
Register nicht gespeichert. Der Datenschutzexport enthält nur Sitzungsmetadaten.
Lokale Syntax-, Sitzungs-, Sprach- und Authentifizierungsprüfungen sind grün.
[CI 44404157331](https://github.com/LLMbutHuman/GunIndex/actions/runs/44404157331)
für `be03fd2` hat Migration 023 angewendet. Die HTTP-Regression beim
Passwortwechsel fand einen alten Aufruf zum Anlegen der Folgesitzung.
Profiländerungen mit Sitzungswechsel verwenden jetzt ebenfalls das Register.
[CI 44404747860](https://github.com/LLMbutHuman/GunIndex/actions/runs/44404747860)
für `906f284` bestand daraufhin Passwortwechsel, Sitzungs-MySQL-Tests und alle
vier Sitzungsabläufe in Chromium. Der spätere E2EE-Netzwerktest konnte einen
Antwortkörper beim Abschluss des Browserlaufs nicht mehr lesen. Der Test
stoppt jetzt neue Anfragen und liest bereits erhaltene Antworten vor dem
Schließen der Browserseiten. Der Abschlusslauf bestätigt den vollständigen
E2EE-Netzwerknachweis mit allen 19 Browserfällen.
Wiederaufnahmepunkt: optionale Benutzer-2FA und Passkeys.

## Kategorieattribute und Produktklassifizierung, 2026-11-18

Code `26564a8fa6fc26d4c21e324dcd06e157603069cc` auf dem Entwicklungsbranch.
[CI 44343065557](https://github.com/LLMbutHuman/GunIndex/actions/runs/44343065557)
ist vollständig grün. Im Log bestätigt: Migrationen 019 und 020, PHP- und
MySQL-Prüfungen, internationale Umkreissuche, vier Chromium-Fälle für Rechte,
Kategorieumschaltung, Produktseite, Vorschläge, mobile Filter und das native
Formular ohne JavaScript. Bestehende Regressionen und Abhängigkeitsaudits grün.

Migration 019 ergänzt typisierte Attribute pro Kategorie und validierte
Inseratwerte. Die Kategorieverwaltung erfordert `categories.manage` und MFA.
Text, Dezimalzahl mit Einheit, Auswahl und Ja/Nein sind verfügbar. Pflichtfelder
gelten beim Veröffentlichen und erneuten Veröffentlichen. Bestehende Inserate
bleiben sichtbar und erhalten keine automatisch erzeugten Produktangaben.
Deaktivierte Merkmale behalten ihre Werte für eine spätere Reaktivierung.

Artikelbearbeitung, öffentlicher Artikel, kombinierte Suchfilter, Wiedereinstellung
und Checkout sind verbunden. Der Kauf speichert die tatsächlich angezeigten
Merkmale mit dem unveränderlichen Vorgang. Die neue Bedienung ist in acht
Sprachen verfügbar. Bezeichnungen und Optionen werden von der Verwaltung verfasst.

Die Suche kombiniert strukturierte Felder mit Preis, Wortsuche, Land und
Umkreis. Relevanz bevorzugt exakte Titel und Titeltreffer. Die Vorschläge
verwenden einen Präfixindex (Migration 020) und berücksichtigen sichtbare
Inserate, Konten, Kategorien und Länder. Eingaben erzeugen vorbereitete
SQL-Parameter. Lokale PHP-, JavaScript- und Sprachprüfungen sind erfolgreich.
Die Vorläufe 44341375406 und 44342407048 stoppten im Chromium-Test am
mehrdeutigen Selektor für die Beschreibung. Der korrigierte Selektor wurde
im grünen Abschlusslauf ausgeführt. Titelvorschläge sind auf acht öffentliche
Präfixtreffer begrenzt. Die Relevanzsuche bevorzugt Titel ohne externe Dienste.

Wiederaufnahmepunkt: automatisches Speichern von Entwürfen
und vollständige private Veröffentlichungsvorschau.

## Handelsabläufe und Inseratslaufzeit, 2026-11-17

Codecommit `100fde78cc060558bd7b6defb0ab90ed92567ed4` auf dem Entwicklungsbranch,
Remote-SHA bestätigt. [CI 44174212303](https://github.com/LLMbutHuman/GunIndex/actions/runs/44174212303)
mit Windowsjob `134361091171` und Linuxjob `134361091744` vollständig grün.
Im Joblog bestätigt: Migrationen 016–018; 14 Handelsprüfungen ohne DB und
15 MySQL-Handelsgruppen; elf Laufzeitprüfungen ohne DB und zwölf MySQL-/HTTP-
Laufzeitgruppen; zwölf Chromium-Handelsprüfungen einschließlich acht mobiler
Sprachen und automatischer Barrierefreiheitsprüfung. Auch die vorhandenen
Regressionen und Abhängigkeitsaudits sind grün.

Festpreiskäufe und private Preisverhandlungen für allgemeine Einzelartikel
verwenden verbindliche serverseitige Preis- und Lieferdaten in Cent. Die
Migrationen 016 und 017 ergänzen Kaufverträge, Angebotsversionen,
Zustimmungsnachweise, Zustandsereignisse sowie Kopien und Verlängerungen.
Käufer und Verkäufer sehen den Vorgang im Konto und können Übergabe, Empfang,
externe Abwicklung, Stornierung und Streitfall dokumentieren. Bewertungen sind
an abgeschlossene Vorgänge und deren Parteien gebunden. Die bestehenden
Auktionsgebühren und der einmalige Rabatt je Auktionskette gelten weiterhin.

Neue befristete Festpreis- und Verhandlungsinserate laufen standardmäßig 30 Tage.
Migration 018 hält den bisherigen Kontaktverkauf für Bestandsinserate verfügbar.
Verbindlicher Checkout und bindende Angebote erfordern die ausdrückliche
Freigabe des Verkäufers mit den für das Inserat geltenden Übergabe- und Versanddaten.
Die einmalige kostenlose Verlängerung setzt ein aktives, befristetes Inserat
ohne Gebote oder Kauf voraus. Geschlossene allgemeine Inserate lassen sich als
separate Entwürfe kopieren. Für verkaufte Ware sind verfügbarer Bestand und
eine ausdrückliche Bestätigung erforderlich. Der Ablaufworker berücksichtigt
auch allgemeine Inserate und offene Verhandlungen.

Die Handelsbestätigung dokumentiert Vereinbarung und Erfüllung zwischen den
Parteien. Plattformzahlungen, Treuhand und Erstattungen bleiben gesonderte
Arbeitspakete mit ihren bestehenden Freigabesperren.

Die Vorläufe 44172593727 und 44173303639 stoppten an fehlenden Sprachdateien
bzw. einer fehlerhaften Testabfrage. Beide Punkte sind im geprüften Stand behoben.

Wiederaufnahmepunkt: typisierte Kategorieattribute mit `categories.manage`,
additiver Migration und Validierung für Bestands- und Neuinserate implementieren.
Formular, Produktdarstellung und kombinierte Suchfilter daran anschließen.
Die grünen Handelsprüfungen erst beim nächsten Integrationspunkt erneut ausführen.

## E2EE-Kern vollständig im isolierten System geprüft, 2026-11-14

Codecommit `7ba5dfd79315da10f5d6f3282a9a4e7c246b5656`,
[CI 43648359381](https://github.com/LLMbutHuman/GunIndex/actions/runs/43648359381),
Windowsjob 132611100512 und Linuxjob 132611100770 erfolgreich; Joblog geprüft.
Remote-SHA beim Push bestätigt. Die mobile Umbruchkorrektur behebt den vorherigen
Browserabbruch. Private Auftragsdatei weiterhin unversioniert und unangetastet.

**Tatsächlich bestanden:** zwölf echte OpenPGP-Kryptografiegruppen; 15
E2EE-HTTP-/DB-/Konkurrenzgruppen; 19 Chromium-Browserfälle. Browsernachweise:
explizit gesicherter Recoverycode und Fingerabdruckvergleich, zwei Benutzer,
mehrere unabhängige Geräte, historische Wiederherstellung, neue Recoverycodes,
Gerätewiderruf und Ausschluss von zukünftigen Nachrichten/Dateien; bytegenauer
verschlüsselter Download; verlorene Versandantwort mit Reload und identischer
Wiederholung ohne doppelte Nachricht; Sperren bei hängendem HTTP ohne spätere
Klartextanzeige; kein Rücksetzen neuer Trust-Pins durch alten Tab; fremde Zugriffe
gesperrt; kein Legacy-Downgrade; acht mobile Sprachen. Kein Testklartext oder
privates Schlüsselmaterial in HTTP, IndexedDB, Serverzeilen oder Dateiblobs
gefunden. Keine JavaScriptfehler oder externen Browserrequests.

Auch bestehende Markt-/Finanz-/Auktions-/Relisting-/Konten-/Wartungs- und
Betriebsregressionen sowie internationale Oberflächen und öffentliche automatische
Accessibilityprüfungen grün. npm- und Composer-Audit ohne gemeldete Advisories.
Keine echten Zahlungen, keine Produktivdaten und keine Aktivierung von E2EE im
Produktionsprofil. Das ist ein technischer Nachweis für die benannten Abläufe,
keine unabhängige Kryptografie-, Screenreader- oder Produktionsabnahme.

**Verbleibender Chatumfang:** Austausch einer kompromittierten Kontoautorität
ist noch nicht implementiert; Gerätewiderruf rotiert nicht diesen Autoritätsschlüssel.
Separate unabhängige Sicherheitsprüfung, Firefox-/Safari-/reale Mobilbrowsermatrix
und tatsächlicher Zielserver-Restore bleiben offen. E2EE_ENABLED bleibt 0 im
Produktionsbeispiel. Grenzen und A/B/C-Liste in `E2EE_PROTOCOL.md`.

**Exakter Wiederaufnahmepunkt:** geprüften Chat-Kern erhalten; als nächste
Chat-Erweiterung einen expliziten, erneut authentifizierten Identitätswechsel
für kompromittierte Autorität mit verpflichtender neuer Teilnehmerverifikation,
erhaltener historischer Prüfbarkeit und eigenen Angriffstests ausarbeiten und
implementieren. Keine stille Root-Ersetzung oder automatische Pinfreigabe.
Danach weitere Browser und Zielbetrieb abnehmen. Den eben grünen Gesamtlauf
ohne neue Änderung oder konkreten Anlass nicht erneut ausführen.

## E2EE-Serverabnahme bestanden, Browserkorrektur, 2026-11-14

`c9cde62b32699ee1e1407dcda07b12e21130b2e5` ist auf dem Entwicklungsbranch
gesichert; Remote-SHA beim Push geprüft. [CI 43436883236](https://github.com/LLMbutHuman/GunIndex/actions/runs/43436883236)
hat Windows, Kryptografie, Migration 015, sämtliche PHP-/MySQL-Prüfungen und
Abhängigkeitsaudits bestanden. Die 15 neuen E2EE-Servergruppen sind tatsächlich
grün, einschließlich Export ohne Recoverygeheimnisse, Quoten, Lesestatus,
Uploadisolation und parallelem Manifest-CAS. Bestehende allgemeine und
Gebühren-/Relisting-Browserabläufe ebenfalls erfolgreich.

**Nicht vollständig grün:** E2EE-Browserlauf bei `create_buyer_identity` durch
Assertion abgebrochen, ohne JavaScriptfehler oder externe Requests. Der lange
synthetische Teilnehmername besitzt in der Gesprächszeile keinen Umbruch;
CSS korrigiert. Diagnostik gibt nun ausschließlich feste Prüfnamen und sichere
Stackpositionen aus. Zusätzlich realer Mehr-Tab-Test gegen Rücksetzen eines
neueren Gerätestands ergänzt. Folge-CI erforderlich; keine vorzeitige
Chat-Fertigmeldung. Spätere Sprach-/Accessibilityschritte wurden im fehlgeschlagenen
Lauf nicht erreicht. Nächster Schritt: Folgecommit prüfen/pushen, Browserfehler
beheben bis der gesamte Lauf besteht; anschließend Nachweise aktualisieren.

## E2EE-Chat integriert, Systemabnahme läuft, 2026-11-13

Aktueller Nutzerauftrag priorisiert ausdrücklich den Chat vor weiteren
Marktplatzmodulen. Letzter vorheriger gesicherter Stand: `f8b1a6c`.
Die privaten Auftragsdateien bleiben unversioniert.

Neu: lokale OpenPGP.js-6.3.2-Bibliothek mit gesperrter npm-Abhängigkeit,
Lizenz und überprüften SHA256-Dateien; unabhängige Geräteschlüssel,
Ed25519-signierte Geräteverzeichnisse, expliziter Fingerabdruckvergleich,
passwortverschlüsselter lokaler Tresor und getrennte Wiederherstellungscodes.
Geräteaufnahme und Widerruf rotieren Wiederherstellungsempfänger und Code.
Verschlüsselte Nachrichten und Dateien werden ausschließlich auf Endgeräten
entschlüsselt. Migration 015 bewahrt den alten Verlauf; nach E2EE-Einrichtung
ist der alte Versandweg dauerhaft gesperrt, auch bei später deaktiviertem Gate.
Private Downloads, Quoten, atomare Dateibindung, idempotenter Versand,
Wiederholung nach Verbindungsabbruch, Datenexport und Oberflächen sind verbunden.

Tatsächlich lokal bestanden: 12 echte Kryptografie-Prüfgruppen mit portablem
Node 22.23.4, inklusive Mehrgerätebetrieb, Wiederherstellung, direktem
Entschlüsselungsfehlschlag widerrufener Schlüssel für zukünftige Nachrichten
und Dateien, Manipulations-/Kontextabwehr und 3-MiB-Grenzfall. PHP-Syntax der
neuen/geänderten PHP-Dateien und JavaScript-Syntax geprüft; Vendorbytes gegen
die gesperrte Paketinstallation verglichen. DB-/HTTP-/Browsertests werden in
der isolierten Linux-CI ausgeführt; ihr Erfolg ist hier noch nicht behauptet.

**Wiederaufnahme:** E2EE-Browser- und Datenbanktests abschließen, Linux-CI
ausführen, Fehler beheben und Commit/Run-Nachweise hier ergänzen. Anschließend
die Abnahmeliste in `E2EE_PROTOCOL.md` aktualisieren. Noch keine produktive
Aktivierung: `E2EE_ENABLED` bleibt standardmäßig 0. Unabhängige Protokoll- und
Anwendungssicherheitsprüfung, Zielserver-/Backupabnahme sowie Browsermatrix
bleiben gesonderte Nachweise. Keine Forward-Secrecy-/Key-Transparency-Garantie.
Keine Zahlungseinstellungen oder historischen privaten Dateien geändert.

Am 13. November zusätzlich geprüft: npm-Audit ohne gemeldete Schwachstellen,
acht Sprachdateien mit identischen 59 Schlüsseln, PHP-Syntax erfolgreich.
Zweiter Code-Review fand und korrigierte eine verlorene Widerrufsauswahl,
Sperrverzögerung während Netzwerkaufrufen, nicht monotone Trust-Pin-Speicherung
zwischen Tabs und fehlende Behandlung des Browser-Zurück-Caches. Sperren bricht
laufende Requests ab; Pin-Merge erfolgt atomar in IndexedDB. Gezielte Browserfälle
für verlorene Versandantwort und Sperren bei hängender Verbindung ergänzen die
Systemabnahme. Die automatische Ausführungsprüfung scheiterte am 12. November
am Nutzungslimit; am 13. November war sie wieder verfügbar. Kein Umgehen der
Freigabe und kein Erfolg für den damals nicht ausgeführten Lauf behauptet.

## Wiedereinstellung vollständig geprüft und gesichert, 2026-11-05

Codecommit `7b423f88b592592139d37b6faa3c0c94e64ace6b` auf
`feature/general-marketplace-rebuild` gepusht, Remote-SHA bestätigt.
[CI 42055817743](https://github.com/LLMbutHuman/GunIndex/actions/runs/42055817743)
vollständig erfolgreich, Windows- und Linuxjob samt Joblog geprüft.
Migration 014 angewendet; 22 Relisting-Regelgruppen und 14 DB-/HTTP-Gruppen
bestanden, einschließlich unabhängiger Bilder, fremder Zugriffe, CSRF,
Wartung, später Rollbacks, zweier echter paralleler Prozesse und altem
Repeatable-Read-Snapshot vor konkurrierender Veröffentlichung.

Neun zusätzliche echte Browserabläufe erfolgreich: acht mobile Sprachansichten
vom Dashboard über Bestätigung/Entwurf/Bildkopien bis zur kostenlosen
Veröffentlichung und Bearbeitung; weiterer kostenpflichtiger Nachfolger derselben
Kette genau einmal berechnet. Gebühren-Browsersuite nun 19 Abläufe insgesamt;
keine JavaScriptfehler oder externen Requests. Bestehende Gebühren-/Wartungs-,
Finanz-, Konto-, Betriebs-, Browser-, Sprach- und Accessibilityregression sowie
Composer-Audit ebenfalls erfolgreich. Keine tatsächliche Zahlung oder Livefreigabe.

Der Arbeitsbaum enthält nach Sicherung der Nachweisdokumente nur die absichtlich
private, unversionierte Auftragsdatei. Alle bisherigen Relisting-Zwischenstände
sind jetzt im geprüften Codecommit enthalten; keine ungesicherten Agentenfragmente.
Details: [RELISTING.md](RELISTING.md). Masterpunkt 11 bleibt insgesamt offen für
weitere Inseratarten und explizite Verlängerungen; der einmalige Gratisablauf
erfolgloser Auktionen ist abgeschlossen. Keine pauschale neue Prozentrechnung.

**Nächster konkreter Arbeitspunkt:** Wiedereinstellen/Verlängern für die übrigen
zulässigen Inseratzustände definieren und mit neuer Durchlauf-ID, bestehendem
Rechte-/Gebührenmodell sowie Frist-/Nebenläufigkeitstests ergänzen. Erfolgreiche,
verkaufte und konfliktbehaftete Auktionen nicht als erfolglose Gratisquellen
umdeuten. Danach Entwurfs-Autosave und vollständige Vorschau (Masterpunkt 9).
Die gerade bestandene Regression ohne neue Änderung nicht unnötig wiederholen.

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

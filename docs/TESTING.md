# Tatsächlicher Testnachweis

`python3 tests/environment.py test` startet ausschließlich die isolierte synthetische MySQL-Datenbank und pausiert ihren Auktionsscheduler während der Suites. Der Quellcode und PHP-Worker laufen mit privaten Volumes. Keine alten Benutzerexporte oder Identitätsdateien werden importiert. Der Runner erhält Fehlercodes und startet den Scheduler im finally wieder.

| Suite | Bestanden |
| --- | ---: |
| PHP-Syntax | 69 Dateien |
| HTTP-/DB-Regressionen | 34 |
| Manuelle Zahlungen, Bumps, private Nachrichten, Bewertungen | 14 |
| Archivintegrität und tatsächlicher SQL-Restore | 4 |
| PayPal-Transportverträge und Gebühren | 19 |
| Authentifizierung, RFC-TOTP, AEAD, Passwortgrenzen | 17 |
| Admin-/Kategorievalidierung | 7 |
| **Automatisierte Prüfungen ohne Lint** | **95** |

Alle 95 Prüfungen bestanden, ohne übersprungene Fälle. Das aktuelle lokale Protokoll liegt ignoriert unter `tests/.runtime/validation.log`. Diese Datei ist kein eingechecktes Artefakt; neue Aufgaben müssen eigene aktuelle Läufe erzeugen.

Enthalten sind echte Multipart-Uploads, CSRF/Objektbesitz/Sessionwiderruf, replaygeschützte Admin-MFA, unveränderte Passwörter mit Sonderzeichen, parallele Gebote aus getrennten PHP-Prozessen, aktive Kategorievorfahren, Auktionenschluss und Quarantäne eines Konflikts bei erfolgreicher Verarbeitung weiterer Angebote. Maximalgebote dürfen nicht im öffentlichen HTML erscheinen.

Manuelle Zahlungsprüfungen müssen exakten Betrag/Referenz und eindeutige Transaktionskennung erfordern. Nutzerhinweise und Browserparameter dürfen weder bezahlt setzen noch Guthaben freigeben. Wiederholte Freigabe erzeugt genau eine Gutschrift, fremde Aufträge bleiben unsichtbar und Gebührenänderungen verändern keine alten Beträge. Ein Bump benötigt bezahltes Guthaben und den ausdrücklichen sofortigen Leistungsbeginn. Die 72-Stunden-Sperre wird geprüft. Der bestehende Bump-Test wurde zusätzlich gezielt für eine Auktion mit vorhandenem Gebot ausgeführt: Erstellungsdatum, Endzeit und Höchstgebot blieben erhalten.

Der Restore wird wirklich in ein frisches Scratch-Schema importiert. Sämtliche geordneten Daten, Tabellen-/Spalten-/Index-/FK-Metadaten einschließlich Zeichensätzen und uncached AUTO_INCREMENT stimmen überein; die Quelle bleibt unverändert. Falsche Schlüssel oder beschädigte Archive müssen ohne Klartextausgabe scheitern.

## Browser

`node tests/browser_smoke.js` bestand elf tatsächliche Chromiumabläufe: Login, Inserat mit Bild, Merkliste, Chat, Gebot, responsive Ansicht, Theme-Persistenz, Admin-MFA, Markenänderung/Kategorien, manuelle Zahlungssperre und reduzierte Bewegung. Breiten 1440/768/390 Pixel, keine JavaScriptfehler, keine 5xx-Antworten und keine externen Assetrequests. Der mobile Home-Überlauf wurde an der dekorativen Orbitgrafik behoben; Admin-Tabellen scrollen innerhalb ihrer Panels. Die neue UTF8-Regression prüft die korrigierten Umlautkategorien.

Der tatsächlich laufende 15-Sekunden-Scheduler beendete eine synthetische Auktion nach 8,51 Sekunden mit genau einem Schlussereignis und null HTTP-Anfragen. Dieser Betriebscheck und die elf Browserabläufe werden nicht zu den 95 Prüfungen addiert.

Frühe Diagnoseläufe fanden eine zu lange MySQL-Lockkennung, falsche Seed-Zeichenkodierung und den mobilen Überlauf. Die Ursachen wurden behoben. Ein Test holte nach dem letzten Zahlungsabschluss irrtümlich einen neuen CSRF-Token aus einer dann formularfreien Liste; der Replaytest sendet jetzt dieselbe noch gültige Formularanfrage erneut und prüft unverändert die einmalige Buchung. Keine Sicherheitsassertionen wurden entfernt.

## Grenzen

Keine echte PayPal-Privatkonto-/Standardbutton-/Sandbox-/Livezahlung. API-Tests verwenden injizierte Providerantworten. Der offizielle Dokumentationsabruf wurde vom Netzwerk mit403 abgewiesen; Kontoeignung und aktuelle Anbieterregeln müssen am tatsächlichen Konto geprüft werden. Kein produktiver Datenimport oder Offsite-Restore, kein Last-/Lighthouse-/vollständiger ASVS-/Screenreader-Nachweis. Die vorhandene CI wurde lokal mit demselben Runner vorbereitet; ein entfernter CI-Lauf ist getrennt zu prüfen.

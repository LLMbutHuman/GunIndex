# Tatsächlicher Testnachweis

Der vollständige Runner `python3 tests/environment.py test` wurde auf der isolierten
synthetischen MySQL-Datenbank ausgeführt. Er pausiert alle fünf Hintergrundworker,
führt die Suites nacheinander aus und startet die Dienste im `finally` wieder.
Produktivdaten und historische persönliche Exporte werden nicht als Fixtures verwendet.

Der vollständige Lauf bestand mit **235 Prüfungen und 137 PHP-Syntaxprüfungen**.
Anschließend wurde eine zusätzlich gefundene Rolleninkonsistenz in der Länder-
Administration behoben: Die delegierte Administratorrolle verwendet dort jetzt
`site.manage`. Die Governance-Suite wurde mit einem neuen echten HTTP-/MFA-Fall
wiederholt; alle 17 Fälle bestanden. Damit sind **236 unterschiedliche Prüfungen**
des aktuellen Stands nachgewiesen. Keine Tests wurden übersprungen oder Assertions
zur Herstellung eines Erfolgs abgeschwächt.

| Bereich | Bestanden |
| --- | ---: |
| Kern-HTTP/DB: Konten, Inserate, Bilder, Gebote, Admin, Worker | 34 |
| Manuelle Zahlungen, Bumps, private Nachrichten, Bewertungen | 14 |
| Händler-/Banner-/CSV-HTTP-Abläufe | 9 |
| PayPal-Transportverträge und exakte Gebühren | 19 |
| Authentifizierung, RFC-TOTP, Passwortgrenzen | 17 |
| Admin-/Kategorievalidierung | 7 |
| Kontowiederherstellung, verschlüsselte Mailqueue, Präferenzen und HTTP | 25 |
| Länder, PLZ, Entfernung, Lizenzgrenzen und Sprachkataloge | 9 |
| Gesuche, lokales Matching, Sofortalarme und tägliche Digests | 22 |
| Premiumplätze, tatsächlicher Parallelzugriff, Banner und Massenverwaltung | 10 |
| Rollen, Moderation, Rechtsdokumente, Export, Löschung und Dokumentenschutz | 17 |
| Rechnungssnapshots, echte PDFs, Zugriff, Storno und Erstattungen | 15 |
| AEAD, Schlüsselwechsel, Chatblockaden, Verlauf und privates Polling | 9 |
| Ubuntu-Installer und Konfigurationsschutz | 10 |
| Legacy-Kontenimport in neue Testschemata | 6 |
| Sicherung und nachträgliche idempotente Chatverschlüsselung | 1 |
| Tatsächliche PHP/HKDF/HMAC-Manifestprüfungen | 8 |
| Archivintegrität und tatsächlicher SQL-Restore | 4 |
| **Unterschiedliche automatisierte Prüfungen ohne Lint** | **236** |

Lokale Protokolle liegen ignoriert unter `tests/.runtime/phase2-validation.log`
und `tests/.runtime/governance-final.log`. Neue Aufgaben müssen nach eigenen
Änderungen eigene aktuelle Nachweise erzeugen. Die Tests hinterlassen eindeutig
synthetische Daten beziehungsweise frische Scratch-Schemata; keine bestehende
Produktivdatenbank wird überschrieben.

Besonders geprüft sind getrennte gleichzeitige PHP-Prozesse bei Geboten und beim
letzten knappen Premiumplatz, unveröffentlichte private Höchstgebote, Quarantäne
widersprüchlicher Auktionszustände, einmalige Zahlungsfreigabe, unveränderte
Gebühren-/Adress-/Steuersnapshots, kumulierte Cent-Steuerkorrekturen bei kleinen
Teilerstattungen und die Ablehnung fremder Nachrichten, Rechnungen und Dokumente.

Die Chatkonvertierung erfolgt erst nach einer tatsächlich verschlüsselten,
entschlüsselten und per Digest verifizierten Sicherung. Ein alter Klartext, der
zufällig mit dem Cipherpräfix beginnt, bleibt durch die separate Versionsspalte
korrekt lesbar. Nachrichtentext, Datum und Lesestatus bleiben erhalten; ein zweiter
Konvertierungslauf verändert nichts mehr.

Der SQL-Restore importiert tatsächlich in ein neues Schema und vergleicht alle
geordneten Daten sowie Tabellen-/Spalten-/Index-/FK-/Zeichensatzmetadaten und den
ungecachten AUTO_INCREMENT-Stand. Manipulierte Archive, falsche Schlüssel oder
fehlender authentifizierter Abschluss müssen ohne Klartextausgabe scheitern.
Manifesttests prüfen zusätzlich eine unabhängige Standard-HKDF/HMAC-Berechnung,
Manipulation, Schlüsselrechte, Größenlimit und Pfadversuche vor jedem DB-Zugriff.

## Browser und Barrierefreiheit

```sh
npm ci --ignore-scripts --prefix tests/browser-tools
tests/browser-tools/node_modules/.bin/playwright install --with-deps chromium
node tests/browser_smoke.js
node tests/international_smoke.js
node tests/accessibility_smoke.js
```

Diese Befehle nacheinander und nach dem Backendlauf ausführen, da die Suites die
selbe synthetische Datenbank verwenden. Ein vorhandenes `/usr/bin/chromium` oder
`CHROMIUM_PATH` wird verwendet; sonst die zu Playwright passende installierte Version.
Die Browsertest-Abhängigkeiten sind separat gelockt und keine öffentlichen Appressourcen.

- Elf echte Chromiumabläufe bestanden: Login, Inserat mit Bild, Merkliste, Chat,
  Gebot, responsive Ansicht, Theme-Persistenz, Admin-MFA, Markenänderung/Kategorien,
  manuelle Zahlungssperre und reduzierte Bewegung. Breiten 1440/768/390 Pixel,
  keine JavaScriptfehler, keine 5xx-Antworten und keine externen Assetrequests.
- Neun weitere Prüfungen bestanden: acht tatsächliche mobile Sprach-/Marktwechsel
  und die internationale Suchsteuerung. Alle Sprachen passen bei 390 Pixel Breite.
- Axe/WCAG-Regeln sowie Tastatur-Sprunglink bestanden auf Startseite, Suche, Login
  und Registrierung bei 1440 und 390 Pixeln: acht Kombinationen ohne gemeldeten
  Regelverstoß. Dies ist keine vollständige manuelle Barrierefreiheitsabnahme.

Anfangs gefundene Fehler wurden behoben: Der mobile Kontolink hatte keinen
zugänglichen Namen; kleine Authlinks brauchten ausreichend Trefferfläche und
sichtbare Unterstreichung. Ein wiederholbarer Händler-HTTP-Test benötigt eine
je Lauf eigene Kategorie, damit bezahlte synthetische Platzierungen aus alten
Läufen keine Kapazität des nächsten Laufs belegen. Die Produktionssperren blieben erhalten.

## Build und begrenzte Messung

Tatsächlich erfolgreich waren der Produktions-PHP-FPM-Build mit gelockter Dompdf-
Bibliothek, FPM als nicht privilegierter Benutzer auf read-only-Dateisystem,
nginx-Konfigurationsprüfung mit synthetischem TLS-Zertifikat, lokaler Postfix ohne
Netzwerk, Compose-Konfigurationsprüfung und ein Installer-Probelauf im offiziellen
Ubuntu-22.04-Container. Ein Rechtefehler bei den kopierten PHP-/FPM-Dateien wurde
gefunden und mit ausdrücklich lesbaren COPY-Dateimodi behoben.

`python3 tests/load_smoke.py --requests 40 --concurrency 4` lieferte lokal 40/40
HTTP 200, Median 27,54 ms, p95 43,11 ms und Maximum 48,52 ms. Das ist eine kleine
Stichprobe des Entwicklungsservers, keine Produktionskapazität, Lighthouse- oder
Core-Web-Vitals-Messung.

Composer-Plattformanforderungen bestanden. `npm audit` der gelockten Browsertools
meldete null Schwachstellen. `composer audit --locked` konnte wegen HTTP 403 für
`packagist.org/api/security-advisories/` im aktuellen Cloud-Netz nicht abgeschlossen
werden; die Domain ist für die wiederverwendbare Konfiguration vorgesehen. Der
CI-Schritt für diesen Audit ist eingerichtet, ein gehosteter CI-Lauf wurde hier
nicht beobachtet. Es wird kein erfolgreicher PHP-Schwachstellenaudit behauptet.

## Nicht nachgewiesen

Keine echte PayPal-Standardbutton-/Sandbox-/Livezahlung, kein automatischer
Bitcoin-Eingang, keine echte Mailzustellung bis zum Posteingang, kein produktiver
Altdatenimport, keine öffentliche Zertifikatsausstellung und kein Offsite-Transfer.
Der vollständige Produktions-Compose-Backup-/Restoreprozess, getrennte
Laufzeit-DB-Rechte, DNS-/SMTP-Betrieb, Screenreaderprüfung und unabhängige
Security-/ASVS-Abnahme müssen auf dem echten Zielsystem gesondert erfolgen.

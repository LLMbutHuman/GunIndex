# Geschützte Einrichtungsübersicht

`/admin/setup.php` benötigt `site.manage` und eine aktuelle MFA-Anmeldung.
Die Übersicht liest Einstellungen, aggregierte Datenbankzahlen und feste
Worker-Heartbeatdateien. Sie schreibt keine Konfiguration, aktiviert keine
Dienste und führt weder Shellbefehle noch Netzwerktests aus. Antwortdaten sind
`private, no-store` und `noindex, nofollow`.

Die 18 Schritte führen zu vorhandenen, weiterhin eigenständig berechtigungs-
und CSRF-geschützten Einstellungsseiten. Links zu anderen Verwaltungsrechten
erscheinen nur bei entsprechender aktueller Berechtigung. Prozessgeheimnisse,
Schlüssel, private Adressen und lokale Dateipfade werden nicht ausgegeben.

## Bedeutung der Zustände

- **Technisch eingerichtet:** die ausdrücklich beschriebene Konfigurationsprüfung
  ist erfolgreich. Dies ist kein Funktionstest oder externer Freigabenachweis.
- **Technisch geprüft:** derzeit nur frische Heartbeats aller fünf Worker,
  ohne jüngeren oder gleichzeitigen Fehlereintrag. Fachliche Workerfunktionen
  sind dadurch nicht vollständig nachgewiesen.
- **Konfiguration fehlt:** benötigte Angaben oder Daten fehlen.
- **Externe Prüfung erforderlich:** die Seite besitzt keinen tatsächlichen
  Nachweis über Provider, Recht, DNS/TLS, Postfacheingang, Restore oder Abnahme.
- **Für Produktion deaktiviert:** vorhandene Aktivierungssperre oder Testtransport.
- **Fehlerhaft:** inkonsistente Konfiguration, fehlgeschlagene Diagnose oder
  ausbleibende/fehlerhafte Workeraktivität.

Die PLZ-Prüfung erkennt aktive Länder ohne freigegebene Koordinaten, beweist
jedoch keine vollständige nationale PLZ-Abdeckung. Rechtstextversionen und
Steuerregeln werden gezählt, ohne rechtliche Vollständigkeit zu unterstellen.
Dateiprüfungen verwenden aufgelöste Pfade außerhalb des Webroots und restriktive
POSIX-Rechte; Windows-ACLs werden nicht als geprüft ausgegeben. Es werden keine
Probe-Dateien angelegt. Alarm- und Zertifikatsprüfungen laufen nicht automatisch.

## Tests und Grenzen

`php tests/setup_test.php` prüft die reine Statusableitung ohne Datenbank,
Geheimniswerte, Provider oder echte Nachrichten. HTTP-Zugriff, MFA, Datenbank-
Diagnosen und mobile Darstellung benötigen zusätzliche Integrationstests.
Die explizit eingebundenen Kataloge `locales/setup/{de,en,fr,nl,pl,cs,da,it}.php`
übersetzen sämtliche neuen Einrichtungstexte, Diagnosen und Statusangaben mit
je 77 Schlüsseln. Dynamische Zahlen und vorgegebene Parameter bleiben unverändert.
Es gibt keine automatische Übersetzung von Betreiber- oder Nutzertexten.
Die Tests prüfen Schlüssel- und Platzhaltergleichheit sowie die Ausgabe aller
18 Schritte in jeder Sprache, ohne den jeweiligen Funktionsstatus zu verändern.
Das ist keine Übersetzung oder rechtliche Freigabe von Vertragsdokumenten.

Am 2026-11-02 mit dem verifizierten portablen PHP 8.4.27 ausgeführt:
elf Setup-Prüfungen und sieben datenbankfreie Geo-/I18N-Prüfungen bestanden.
Alle zwölf betroffenen PHP-Dateien bestanden die Syntaxprüfung.
Dies ist kein Linux-/HTTP-/Datenbank-Testnachweis.

Dies ist eine funktionale geführte Bestandsübersicht über die bestehenden
Einrichtungsmodule. Noch offen bleiben ein Eingabeassistent für SMTP samt
geschützter Konfiguration, DNS-Abfragen, echte Testsendung und gesonderter
Posteingangsbestätigung; außerdem persistente Betriebsnachweise und Browser-
prüfungen der mobilen Darstellung aller Sprachen. Die älteren gemeinsamen
Admin-Navigations-/Layouttexte sind nicht Teil dieses neuen Übersetzungsmoduls.
Es werden keine fehlenden Module durch
scheinbar funktionierende Aktivierungsschalter ersetzt.

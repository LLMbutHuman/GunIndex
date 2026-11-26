# Geschütztes Setup und Freischaltung

`/admin/setup.php` benötigt die Berechtigung `site.manage` und eine aktuelle
MFA-Anmeldung. Lege das erste Administratorkonto im Installer oder an der
Serverkonsole nach der [Ubuntu-Anleitung](UBUNTU_22_04.md) an. Bei einer
Neuinstallation beantwortet der öffentliche Marktplatz Anfragen bis zur
Aktivierung mit HTTP 503. Anmeldung, MFA und die berechtigten Verwaltungsseiten
stehen währenddessen bereit. Bestehende Installationen behalten ihren
Betriebszustand.

## Einrichtung abschließen

1. Öffne das Setup über die konfigurierte HTTPS-Domain. Die 18 Schritte führen
   zu vorhandenen, eigenständig berechtigungs- und CSRF-geschützten Einstellungen.
2. Prüfe Betreiberangaben, Marke, Länder und Kategorien. Richte Mail über
   [den Mailassistenten](MAIL_SETUP.md) ein und prüfe den tatsächlichen Empfang.
   Lass die notwendigen Rechtsdokumentversionen prüfen und veröffentlichen.
3. Erstelle ein verschlüsseltes Backup und probe die Wiederherstellung in eine
   separate Datenbank. Die [Anleitung](UBUNTU_22_04.md) enthält die Befehle.
4. Wähle **Technische Prüfungen ausführen**. Das Panel zeigt den Zeitpunkt und
   das Ergebnis für Datenbank, HTTPS, Laufzeit, private Speicherung, Worker,
   Betreiberangaben, Katalog, Mail, Rechtstexte, Sicherung und Zahlungssperren.
5. Prüfe die Betreiberanforderungen und bestätige die Aktivierung. Der Server
   wiederholt die technischen Prüfungen beim Freischalten und protokolliert den
   Vorgang. Führe die Prüfung erneut aus, wenn das Ergebnis älter als 15 Minuten ist.

Die Datenspeicherprüfung schreibt, liest und entfernt kurzlebige Stichproben
in fest konfigurierten privaten Verzeichnissen. Die Diagnose überprüft den
aktuellen HTTPS-Aufruf und die Cookie-Einstellungen, frische Worker-Heartbeats
und datierte Betriebsnachweise für Sicherung und Wiederherstellung. Antwortdaten
sind `private, no-store` und `noindex, nofollow`. Prozessgeheimnisse, Schlüssel
und lokale Dateipfade bleiben privat. Das Panel führt weder Shellbefehle noch
beliebige Netzwerkanfragen aus.

## Bedeutung der Zustände

- **Technisch eingerichtet.** Die beschriebene Konfigurationsprüfung ist erfolgreich.
- **Technisch geprüft.** Ein messbarer lokaler Nachweis liegt vor.
- **Konfiguration fehlt.** Benötigte Angaben oder Daten fehlen.
- **Externe Prüfung erforderlich.** Betreiber oder Anbieter müssen die Angaben
  fachlich abnehmen. Das betrifft insbesondere Mailzustellung, Rechtstexte und Zahlungen.
- **Für Produktion deaktiviert.** Die betreffende Funktion ist gesperrt.
- **Fehlerhaft.** Die Diagnose ist fehlgeschlagen, die Konfiguration ist
  inkonsistent oder ein erforderlicher Worker meldet einen Fehler.

Die Freischaltung öffnet den Marktplatz. Zahlungsanbieter, Echtgeld, Treuhand
und Identitätsprüfung behalten ihre eigenen Freigabesperren. Ein erfolgreicher
SMTP-Transport zeigt eine Annahme durch den Mailserver. Der Eingang in einem
kontrollierten Postfach wird gesondert geprüft. Die PLZ-Prüfung erkennt aktive
Länder ohne freigegebene Koordinaten. Die einzelnen Quellen und ihre Abdeckung
stehen unter [PLZ-Quellen](POSTCODE_SOURCES.md).

`php tests/setup_test.php` prüft Statuslogik und Übersetzungsschlüssel aller
acht Sprachkataloge. Die Installer-CI prüft den geschützten HTTPS-Ablauf mit
synthetischen Daten. Die Ergebnisse und Grenzen stehen in [TESTING.md](TESTING.md).

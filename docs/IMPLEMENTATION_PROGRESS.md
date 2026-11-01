# Implementierungsfortschritt

## 2026-11-01: lokale Bestandsaufnahme und Windows-Einstieg

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

Geplanter Commit: `build: add Windows development preflight and runtime guards`.
Git hat den Commit wegen fehlender Autorenidentität abgewiesen; Name/E-Mail
wurden beim Nutzer angefragt und anschließend ausdrücklich vorgegeben.
Die lokale Repository-Identität ist jetzt gesetzt; die zehn PowerShell-Prüfungen
bestanden erneut. Commit und Push werden mit diesem Stand erneut ausgeführt.
Push-/Release-Nachweise werden in `RELEASE_OPERATIONS.md` festgehalten.

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

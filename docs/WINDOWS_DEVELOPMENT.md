# Lokale Entwicklung unter Windows

Die Testumgebung setzt Linux, einen Unix-Docker-Socket und Linux-Dateirechte
voraus. Native Windows-Python-Aufrufe des Runners werden vor jeder Aktion
abgewiesen. Produktivdaten, alte SQL-Exporte und private Uploads nicht importieren.

## 1. Voraussetzungen prüfen

Im vorhandenen Projektordner in PowerShell:

```powershell
powershell -NoProfile -File .\ops\check-windows.ps1
# Optional maschinenlesbar; Exitcode 1 bedeutet fehlende Voraussetzung:
powershell -NoProfile -File .\ops\check-windows.ps1 -Json
```

Die Prüfung verändert keine Konfiguration, installiert nichts und startet keine
Container. Sie kann eine vorhandene Ubuntu-Distribution zur Prüfung starten.
Ein Programmname im PATH reicht nicht als Erfolgsnachweis. WSL muss eine
Ubuntu-Distribution mit Version 2 melden; innerhalb dieser Distribution werden
Python, Node, CA-Zertifikate, Unix-Socket und ein erreichbarer Linux-Docker-Daemon
geprüft. Docker-/WSL-Diagnoseausgaben werden nicht ins Ergebnis übernommen.
Ein grünes Ergebnis ist noch kein erfolgreicher Build oder Anwendungstest.

## 2. Fehlende Systemvoraussetzungen

Falls Ubuntu/WSL2 fehlt, ist eine Windows-Administrationsaktion erforderlich:
`wsl --install -d Ubuntu` in einer administrativen PowerShell. Einen angeforderten
Neustart durchführen, Ubuntu öffnen und den lokalen Linux-Benutzer einrichten.
Mit `wsl --list --verbose` Version 2 kontrollieren. Bestehende Distributionen
nicht entfernen oder zurücksetzen.

Docker Desktop ausschließlich aus der offiziellen Docker-Quelle installieren,
Publisher/Signatur prüfen und die geltenden Nutzungsbedingungen berücksichtigen.
Linux-Container und WSL-Integration für Ubuntu einschalten. Python 3, Node.js und
CA-Zertifikate müssen auch innerhalb Ubuntu verfügbar sein. Windows-PHP und
Windows-MySQL werden nicht benötigt: die Testumgebung baut PHP und MySQL isoliert.
Es gibt keinen automatischen Download oder ungeprüfte Ausführung von Installern.

Offizielle Anleitungen: [Microsoft WSL-Installation](https://learn.microsoft.com/en-us/windows/wsl/install)
und [Docker WSL-Integration](https://docs.docker.com/desktop/features/wsl/use-wsl/).

## 3. Isolierte Tests in Ubuntu

In Ubuntu in den bestehenden Checkout wechseln, zum Beispiel:

```sh
cd /mnt/c/Users/user/Projects/GunIndex
python3 tests/environment.py up
python3 tests/environment.py test
```

DB-schreibende Suites ausschließlich nacheinander ausführen. Weitere Browser-
und Integrationstests stehen in [TESTING.md](TESTING.md). Keine parallelen Runner.
Der erste Start lädt Images/Composer-Abhängigkeiten und benötigt Netzwerkzugriff.
Der Runner erzeugt nur seine synthetische Entwicklungsdatenbank. Bestehende
Docker-Volumes und Zugangsdaten werden nicht durch diese Anleitung gelöscht.

Achtung: Windows-Laufwerke unter `/mnt/c` können Unix-Dateirechte anders abbilden.
Wenn private Dateirechte nicht durchgesetzt werden, den Testlauf abbrechen und
einen separaten Checkout im Linux-Homeverzeichnis verwenden. Vorhandene lokale
Änderungen dabei gezielt übertragen; keine alten privaten Exporte kopieren.
Die Windows-Vorprüfung prüft diese Mount-Eigenschaft nicht.

Nur den Entwicklungsstack anhalten, Volumes erhalten:

```sh
python3 tests/environment.py stop
```

Die isolierte Vorprüfung selbst lässt sich ohne Linux testen:

```powershell
powershell -NoProfile -File .\tests\windows_preflight_test.ps1
```

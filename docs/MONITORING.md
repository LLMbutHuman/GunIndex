# Lokaler Betrieb, Monitoring und Offsite-Backup

`ops/health.php` und `ops/monitor.php` sind ausschließlich CLI-Werkzeuge. Sie geben
technische Statuswerte, Queuezahlen und Worker-Alter aus, keine Zugangsdaten,
Empfänger, Token, Ausweisdokumente oder Chattexte. Es existiert kein öffentlicher
Diagnoseendpunkt. Rückgabecode 0 bedeutet technisch ok, 2 eingeschränkt und 1 nicht
verfügbar. Eine nicht konfigurierte Mailzustellung bleibt sichtbar.

Der Produktionsworker startet Auktion, Mail, Discovery, Business und Privacy
getrennt. Ein Fehler einer Aufgabe beendet nicht die Verarbeitung aller anderen.
Erfolgs-/Fehler-Heartbeats liegen privat unter `/var/lib/gunindex/heartbeats`.
Der Auktionsdienst muss unabhängig von Websitebesuchen laufen. Prozessstatus allein
ist kein Funktionsnachweis; regelmäßig eine synthetische Testauktion bis zum Ende prüfen.

Der Ubuntu-Installer aktiviert den minütlichen lokalen Check als systemd-Timer.
Der optionale Backup-Timer läuft täglich um 03:15 Uhr Europe/Berlin mit kleiner
Zufallsverzögerung. Die Einrichtung steht in [Ubuntu 22.04](UBUNTU_22_04.md).
Timer und letzte Ergebnisse kontrollieren:

```sh
sudo systemctl list-timers gunindex-monitor.timer gunindex-backup.timer
sudo journalctl -u gunindex-monitor.service -u gunindex-backup.service --since today --no-pager
```

Aufbewahrungsfrist im Journal und gegebenenfalls ein externes Alarmziel festlegen.
Lokale Überwachung kann Exitcodes mit systemd oder einem Monitoringdienst
auswerten. nginx protokolliert keine Querystrings mit Reset-/Verifikationstoken.
Postfixlogs enthalten gegebenenfalls notwendige Zustelladressen; Zugriff und Retention
beschränken. Es werden keine Nachrichtentexte oder Passwörter geloggt.

## Geprüfte Sicherung

```sh
sudo python3 /srv/gunindex/current/ops/production/backup.py --directory /srv/gunindex backup --maintenance
```

Es entstehen getrennte authentifiziert verschlüsselte DB-/Privatdateiarchive und
ein privates Manifest. Der Key wird nur in den temporären Backupcontainer gemountet,
nicht in den Webprozess. Klartext-SQL wird nicht als benannte Datei gespeichert.
Die authentifizierte Entschlüsselung benötigt einen privaten temporären Stagingbereich;
verschlüsselte Hostdatenträger bleiben eine zusätzliche Betriebsanforderung.

Die Datenbank wird mit Single-Transaction exportiert. Für gemeinsame Konsistenz mit
privaten Dateien müssen Web-/Worker-Schreiber gestoppt sein. `--maintenance` erledigt
das für die eigenen beiden Dienste und startet zuvor laufende Dienste im finally wieder.
Ohne diesen Schalter verweigert das Tool den Snapshot laufender Schreiber. Datenbank
und vorhandene Sicherungen werden niemals gelöscht. Produktive Aufbewahrung und
Kapazitätsgrenzen sind vom Betreiber festzulegen; es gibt keine automatische Datenlöschung.

Für Offsite-Übertragung einen eigenen beschränkten SSH-Benutzer und einen vorab über
einen zweiten vertrauenswürdigen Weg geprüften Hostschlüssel einsetzen. Erst lokal prüfen:

```sh
sudo bash /srv/gunindex/current/ops/offsite-backup.sh --manifest /srv/gunindex/backups/DEIN_SNAPSHOT.json --destination backup@dein-backup-host.tld --remote-directory /private/gunindex --identity /root/.ssh/backup_ed25519 --known-hosts /root/.ssh/backup_known_hosts --check-only
```

Danach denselben Befehl ohne `--check-only` ausführen. Das Zielverzeichnis muss
vorhanden und nur für den Sicherungsbenutzer zugänglich sein. Bei abweichendem
SSH-Port `--port` setzen, bei abweichendem Installationspfad `--directory`.
Vor der Übertragung prüft GunIndex lokal die Manifest-Signatur und die Prüfsummen
der beiden verschlüsselten Archive. OpenSSH verwendet den angegebenen Hostschlüssel
im Batchmodus mit `StrictHostKeyChecking=yes`.

SFTP überträgt Manifest und Archive in ein privates Zwischenverzeichnis. GunIndex
liest alle drei Dateien zurück und prüft Gleichheit und Signatur. Dafür muss unter
`/tmp` freier Platz in Größe des Snapshots verfügbar sein. Nach bestandener Prüfung
benennt das Skript das Zwischenverzeichnis in `DEIN_SNAPSHOT` um und gibt den Pfad
zum Manifest aus. Eine bereits veröffentlichte Sicherung gleichen Namens wird
abgewiesen. Nach einem Abbruch kann ein verborgenes Zwischenverzeichnis am Ziel
übrig bleiben. Alte Snapshots aus früheren Übertragungen bleiben an ihrem bisherigen
Pfad. Archivschlüssel und SSH-Schlüssel gehören in die getrennte Schlüsselverwahrung.
[OpenSSH-SFTP-Handbuch](https://man.openbsd.org/sftp)

Für die automatische Kopie nach dem täglichen Backup die Datei
`/srv/gunindex/config/offsite-backup.json` als `root` mit Dateimodus `0600`
anlegen. `identity` und `known_hosts` enthalten absolute Pfade. `port` ist
optional und steht standardmäßig auf 22.

```json
{
  "destination": "backup@backup.example.invalid",
  "remote_directory": "/private/gunindex",
  "identity": "/root/.ssh/backup_ed25519",
  "known_hosts": "/root/.ssh/backup_known_hosts"
}
```

Der bestehende Backup-Dienst erstellt und prüft zuerst die lokale Sicherung.
Nach dem Wiederanlauf der Schreiber sendet er das Ergebnis an das konfigurierte
Ziel. Ein SFTP-Fehler wird im Dienstprotokoll sichtbar, die geprüfte lokale
Sicherung bleibt verfügbar. Der geschützte Setup-Assistent zeigt den letzten
erfolgreichen automatischen Rücklesetest an. Die Zeitpunkte für lokales Backup
und Restore werden separat angezeigt. Für den lokalen Betrieb kann die Datei
entfallen. Die Sicherung vor einem Upgrade bleibt ein lokaler Vorgang.

Regelmäßig von der Offsite-Kopie in ein frisches Scratch-Schema/privates Verzeichnis
wiederherstellen und Daten-/Schemametadaten vergleichen. Eine erfolgreiche Übertragung
oder entschlüsselbare Datei ersetzt keine Restoreprobe. Vor tatsächlichem Wiederanlauf
alte Sessions unabhängig widerrufen; keine automatische Umschaltung auf restaurierte Daten.

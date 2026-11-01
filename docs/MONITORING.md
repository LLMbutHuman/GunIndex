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

Ein Beispiel für einen minütlichen lokalen Check über root-crontab:

```cron
* * * * * /srv/gunindex/compose.sh exec -T php php ops/monitor.php >> /var/log/gunindex-monitor.log 2>&1
```

Alternativ die unter `config/systemd/` erzeugten Timer mit den Befehlen aus
`UBUNTU_22_04.md` installieren: Monitor minütlich, Backup täglich um 03:15 Uhr
Europe/Berlin mit kleiner Zufallsverzögerung. Nur eine der beiden Varianten verwenden.
Timer und letzte Ergebnisse kontrollieren:

```sh
sudo systemctl list-timers gunindex-monitor.timer gunindex-backup.timer
sudo journalctl -u gunindex-monitor.service -u gunindex-backup.service --since today --no-pager
```

Logdatei mit 0600 anlegen, Rotation und Aufbewahrungsfrist festlegen. Ein Cronjob
allein versendet noch keinen Alarm. Lokale Überwachung kann Exitcodes mit systemd/
einem eigenen Monitoringdienst auswerten; externe Alarmziele nur ausdrücklich
konfigurieren. nginx protokolliert bewusst keine Querystrings mit Reset-/Verifikationstoken.
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

Danach denselben Befehl ohne `--check-only` ausführen. SFTP verwendet Batchmodus und
`StrictHostKeyChecking=yes`; unbekannte/geänderte Hostschlüssel werden abgewiesen.
Die Dateien werden zunächst unter temporärem Namen übertragen und dann umbenannt.
Der Archivschlüssel wird nicht übertragen. Diese Konfiguration wurde nicht mit einem
realen Offsite-Server oder Kundendaten ausgeführt.

Regelmäßig von der Offsite-Kopie in ein frisches Scratch-Schema/privates Verzeichnis
wiederherstellen und Daten-/Schemametadaten vergleichen. Eine erfolgreiche Übertragung
oder entschlüsselbare Datei ersetzt keine Restoreprobe. Vor tatsächlichem Wiederanlauf
alte Sessions unabhängig widerrufen; keine automatische Umschaltung auf restaurierte Daten.

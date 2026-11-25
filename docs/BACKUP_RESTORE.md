# Verschlüsselte Backups und Restore

`ops/backup.php encrypt /private/key` liest einen Stream von stdin und schreibt ein authentifiziert verschlüsseltes Archiv nach stdout. `decrypt` prüft Header, alle Frames, Abschluss und Dateiende, bevor irgendein Klartext ausgegeben wird. Es verwendet libsodiums etablierten XChaCha20-Poly1305-Secretstream, keine selbst entwickelte Verschlüsselung. Die Schlüsseldatei muss 32 zufällige Bytes enthalten, regulär und nur für ihren Eigentümer zugänglich sein. Schlüssel und Archiv getrennt verwahren.

Die Testumgebung sichert ihre synthetische Datenbank vor ausstehenden Migrationen. Der Schlüssel liegt privat im ignorierten Laufzeitverzeichnis; der Betreiber muss für Produktion sichere getrennte Verwahrung, Rotation, Retention und Offsite-Kopien einrichten.

`tests/backup_test.py` prüft leere/binäre/mehrteilige Roundtrips, manipulierte/abgeschnittene/verlängerte Archive, falsche Schlüssel und Dateirechte. Fehler müssen nichtnull beenden und null Bytes Klartext ausgeben.

Der Datenbanktest exportiert die tatsächliche synthetische MySQL-Datenbank, verschlüsselt/entschlüsselt sie und importiert in ein frisches zufälliges Scratch-Schema. Er vergleicht sämtliche geordneten Daten sowie Tabellen-, Spalten-, Zeichensatz-, Default-, Index- und Fremdschlüsselmetadaten. AUTO_INCREMENT wird ohne MySQL-Statistikcache gelesen. Der ursprüngliche vollständige Export muss unverändert bleiben. Keine bestehenden Datenbanken werden überschrieben oder gelöscht; Scratch-Schemata bleiben zur Kontrolle erhalten.

Vor erneutem Öffnen eines restaurierten Diensts müssen alte Sessions unabhängig widerrufen werden. Dafür müssen auch die gesicherten Sessiondateien berücksichtigt werden. Der Betreiber richtet die getrennte gesicherte Verwahrung von APP_KEY und Archivschlüssel ein und probt den Wiederanlauf mit allen benötigten Beständen auf der Zielinfrastruktur.

## Produktionswerkzeuge

`ops/production/backup.py` koordiniert SQL- und Privatdatei-Sicherung in
verschlüsselte Archive sowie ein authentifiziertes Manifest. Es stoppt Schreiber
für einen konsistenten Snapshot und stellt ausschließlich in ein neues Schema und
ein neues Dateiverzeichnis wieder her. Bestehende Daten und die laufende
Anwendung werden dabei nicht ersetzt. Der vollständige Produktions-Compose-Restore
muss auf dem Zielserver gesondert ausgeführt werden. Nach der Abnahme sind die
Dateirechte für die tatsächliche PHP-UID einzurichten und alte Sessions zu widerrufen.

Die [Ubuntu-Anleitung](UBUNTU_22_04.md) enthält die Befehle für Sicherung und
Restore. Der [Betriebsleitfaden](MONITORING.md) beschreibt die Offsite-Kopie
über SFTP mit geprüftem Hostschlüssel und Rückleseprüfung. Das
Produktions-CI-Skript verwendet dafür synthetische SQL- und Dateidaten und
einen auf Loopback beschränkten OpenSSH-Dienst. Die Restoreprobe am tatsächlichen
externen Betreiberziel gehört zur Betriebsabnahme.

`tests/chat_migration_test.py` prüft zusätzlich die verifizierte verschlüsselte
Sicherung vor der nachträglichen Nachrichtenverschlüsselung: Metadaten und
Nachrichtentext bleiben erhalten, die Versionsmarkierung verhindert eine
Verwechslung von alten Benutzereingaben mit Ciphertext, ein zweiter Lauf ändert
nichts mehr. Das CLI erfordert bei Änderungen `--backup-confirmed`.

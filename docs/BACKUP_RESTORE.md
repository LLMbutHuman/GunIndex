# Verschlüsselte Backups und Restore

`ops/backup.php encrypt /private/key` liest einen Stream von stdin und schreibt ein authentifiziert verschlüsseltes Archiv nach stdout. `decrypt` prüft Header, alle Frames, Abschluss und Dateiende, bevor irgendein Klartext ausgegeben wird. Es verwendet libsodiums etablierten XChaCha20-Poly1305-Secretstream, keine selbst entwickelte Verschlüsselung. Die Schlüsseldatei muss 32 zufällige Bytes enthalten, regulär und nur für ihren Eigentümer zugänglich sein. Schlüssel und Archiv getrennt verwahren.

Die Testumgebung sichert ihre synthetische Datenbank vor ausstehenden Migrationen. Der Schlüssel liegt privat im ignorierten Laufzeitverzeichnis; der Betreiber muss für Produktion sichere getrennte Verwahrung, Rotation, Retention und Offsite-Kopien einrichten.

`tests/backup_test.py` prüft leere/binäre/mehrteilige Roundtrips, manipulierte/abgeschnittene/verlängerte Archive, falsche Schlüssel und Dateirechte. Fehler müssen nichtnull beenden und null Bytes Klartext ausgeben.

Der Datenbanktest exportiert die tatsächliche synthetische MySQL-Datenbank, verschlüsselt/entschlüsselt sie und importiert in ein frisches zufälliges Scratch-Schema. Er vergleicht sämtliche geordneten Daten sowie Tabellen-, Spalten-, Zeichensatz-, Default-, Index- und Fremdschlüsselmetadaten. AUTO_INCREMENT wird ohne MySQL-Statistikcache gelesen. Der ursprüngliche vollständige Export muss unverändert bleiben. Keine bestehenden Datenbanken werden überschrieben oder gelöscht; Scratch-Schemata bleiben zur Kontrolle erhalten.

Eine Wiederherstellung produktiver Daten, private Produktbilder, Offsite-Backups und Disaster-Recovery wurden damit noch nicht geprobt. Vor erneutem Öffnen eines restaurierten Diensts müssen alte Sessions unabhängig widerrufen werden; ein SQL-Restore entfernt keine PHP-Sessiondateien. Auch APP_KEY und Bildbestände benötigen eine getrennte sichere Sicherung.

## Produktionswerkzeuge

`ops/production/backup.py` koordiniert SQL- und Privatdatei-Sicherung in
verschlüsselte Archive sowie ein authentifiziertes Manifest. Es stoppt Schreiber
für einen konsistenten Snapshot und stellt ausschließlich in ein neues Schema und
ein neues Dateiverzeichnis wieder her. Bestehende Daten und die laufende
Anwendung werden dabei nicht ersetzt. Der vollständige Produktions-Compose-Restore
muss auf dem Zielserver gesondert ausgeführt werden. Nach der Abnahme sind die
Dateirechte für die tatsächliche PHP-UID einzurichten und alte Sessions zu widerrufen.

Befehle: [Ubuntu-Anleitung](UBUNTU_22_04.md). Offsite-Übertragung erfolgt über
`ops/offsite-backup.sh` mit ausdrücklich konfiguriertem SSH-Ziel und unabhängig
geprüftem Hostschlüssel. Keine privaten Schlüssel oder Klartextarchive übertragen.
Der tatsächliche Offsite-Transfer wurde hier nicht ausgeführt.

`tests/chat_migration_test.py` prüft zusätzlich die verifizierte verschlüsselte
Sicherung vor der nachträglichen Nachrichtenverschlüsselung: Metadaten und
Nachrichtentext bleiben erhalten, die Versionsmarkierung verhindert eine
Verwechslung von alten Benutzereingaben mit Ciphertext, ein zweiter Lauf ändert
nichts mehr. Das CLI erfordert bei Änderungen `--backup-confirmed`.

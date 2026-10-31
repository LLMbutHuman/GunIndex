# Datenbank und Migration

`sql/schema.sql` ist ein sauberes Schema für **leere** Datenbanken ohne privilegierte Beispieldaten. Es ist kein Importskript für vorhandene Produktivtabellen. Die alte `sql/structure.sql` enthält keinen vordefinierten Admin mehr und verweist auf die kontrollierte Installation.

Der aktive Marktplatz verwendet eigene `market_*`-Tabellen für Inserate, Bilder, Proxygebote, öffentliche Gebotsereignisse, Merklisten, Nachrichten, Bewertungen, Gebührenforderungen, Zahlungsversuche und Zahlungsereignisse. Benutzer, Systemsettings und Auditprotokoll besitzen zentrale Tabellen. Geld wird in Integer-Cent gespeichert, Zeitwerte in UTC. Fremdschlüssel und eindeutige Kennungen schützen Zuordnungen und Deduplizierung.

`001_manual_billing.sql` ergänzt manuelle Zahlungsaufträge, Bump-Guthaben und Aktivierungsereignisse. Der CLI-Runner ergänzt `bumped_at` nur bei fehlender Spalte. Version/Prüfsumme werden unter einem MySQL-Migrationslock gespeichert; bereits ausgeführte Dateien dürfen nicht verändert werden.

```sh
php ops/migrate.php --backup-confirmed
```

Dieser Schalter bestätigt einen zuvor tatsächlich geprüften verschlüsselten Backup- und Wiederherstellungsplan. Er erzeugt auf einer Produktivdatenbank selbst keinen Backupnachweis. Die Testumgebung übernimmt diese Sicherung automatisch und ausschließlich für ihre synthetische Datenbank.

MySQL-DDL kann implizit committen. Ein Fehler erfordert Prüfung und Vorwärtskorrektur, keinen automatischen Reset. Bestehende Daten werden nicht gelöscht. Der Runner setzt bereits vorhandene Kernstrukturen voraus. Eine vollständige Migration aller widersprüchlichen historischen Benutzer-/Nachrichten-/Bewertungsschemata ist noch offen; das neue Bootstrap darf diese nicht überschreiben.

Historische Exporte und Uploads wurden aus der neuen Git-Indexversion genommen, die lokalen Dateien bleiben erhalten. Alte Git-History enthält diese weiter und wurde nicht umgeschrieben. Für Veröffentlichung/Retention/Rotation ist eine gesonderte Betreiberentscheidung notwendig.

002_seed_encoding.sql korrigiert nur die exakt bekannten ursprünglichen Umlaut-Fehlkodierungen anhand von Slug und altem Hexwert. Neue Installationen setzen UTF8MB4 ausdrücklich am SQL-Client; geänderte Kategorien werden nicht überschrieben.

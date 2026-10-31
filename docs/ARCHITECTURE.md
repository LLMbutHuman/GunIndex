# Architektur

Serverseitig gerendertes PHP, MySQL/InnoDB/UTF8MB4, lokale CSS-/JavaScript-Ressourcen. Kein SPA-Framework und keine externen Chat-, Such- oder Trackingdienste.

`public/index.php` besitzt eine explizite Routen- und Assetliste. Keine direkte Auslieferung von Konfiguration, Git, Tests, SQL oder Uploadverzeichnissen. `config/bootstrap.php` verbindet PDO und die wiederverwendbaren Dienste für Authentifizierung, Settings, Kategorien, Geld, Bilder, Marktplatz und Zahlungen.

Bestehende Alt-Inseratstabellen werden nicht in den aktiven Katalog eingebunden. Die neue Logik verwendet ausschließlich `market_*`. Ein Benutzerkonto wird anhand aktueller Datenbankrechte und Sessionversion geprüft. Administratoren melden sich zwingend mit TOTP an; dessen Secret liegt mit libsodium AEAD verschlüsselt vor.

Auktionsgebote, manuelle Zahlungsprüfungen und Bump-Aktivierungen verwenden Datenbanktransaktionen und Zeilensperren. Maximalgebote bleiben privat. Ein unabhängiger Abschlussjob verarbeitet fällige Auktionen. Konflikte werden ohne erfundene Gewinner in einen Prüfzustand verschoben und blockieren andere fällige Angebote nicht.

Das Designsystem verwendet Ink, Mint und Amber, einstellbare Markenfarben, Light/Dark Mode und progressive Animationen mit Besucherpause und Vorrang reduzierter Bewegung. Markenname, Texte, Kategorien und Preisregeln sind ohne Codeänderung administrierbar. Ausführbarer Code und Geheimnisse sind keine Website-Einstellungen.

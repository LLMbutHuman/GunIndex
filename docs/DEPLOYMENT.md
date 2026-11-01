# Produktionsvorbereitung

Dieser Stand wurde nicht produktiv bereitgestellt. Der eingebaute PHP-Server ist für lokale Entwicklung. Für einen eigenen Server:

- Nur `public/` als DocumentRoot, ausschließlich den festen Frontcontroller an PHP-FPM weitergeben. Keine Aliasroute auf den Checkout oder alte Uploadverzeichnisse.
- Unterstütztes PHP mit PDO/GD/mbstring/libsodium/cURL, MySQL8 bzw. geprüfte MariaDB, UTC und UTF8MB4.
- HTTPS, sichere TLS-Konfiguration, korrekte Proxy-/Hostgrenzen, `SESSION_COOKIE_SECURE=1`, minimale nicht privilegierte Prozess-/DB-Rechte.
- Private Volumes für Bilder, Limits, Schlüssel und Backups; Quellcode read-only, keine öffentlich erreichbare DB.
- Den idempotenten Auktionsworker unabhängig von Webaufrufen regelmäßig starten und Fehler/Prüfzustände überwachen.
- Nur leere Datenbanken bootstrappen; Bestandsmigration separat prüfen und verschlüsselt sichern.
- Sämtliche Releasepunkte, Betreiber-/Datenschutz-/Vertrags-/Steuerfragen und tatsächliche PayPal-Kontoeignung prüfen.

Die manuelle PayPal-Prüfung benötigt keine API-Geheimnisse, aber ein tatsächlich geeignetes Empfängerkonto und geprüfte Bestellprozesse. Die Website erteilt diese Freigaben nicht selbst. Automatische REST-Zahlungen benötigen zusätzlich die sichere API-Konfiguration; keine reale Sandbox- oder Livezahlung wurde hier nachgewiesen.

Die konkrete selbst hostbare Umsetzung liegt unter `ops/production/`: gepinnte
PHP-FPM-/nginx-/MySQL-Images, private Volumes, fünf Queueworker, lokaler Postfix,
TLS-Bootstrap, Health-/Monitor-CLI und verschlüsselte Sicherung. Der
[Ubuntu-22.04-Installer](../ops/install-ubuntu-22.04.sh) benötigt einen sauberen
Commit und erhält bestehende Schlüssel sowie Daten. Die
[Schritt-für-Schritt-Anleitung](UBUNTU_22_04.md) enthält die tatsächlichen Optionen.

Die lokale Build-/Probelaufprüfung ersetzt keinen vollständigen Betrieb auf dem
Zielserver. SMTP/DNS, ACME-Zertifikat, getrennte Datenbankberechtigungen, Firewall,
Schlüsselverwahrung, Offsite-Ziel und vollständiger Produktions-Backup-Restore
sind am tatsächlichen Server zu prüfen. Das Standard-MySQL-Konto kann Migrationen
ausführen; für Least Privilege sind getrennte Migrations- und Laufzeitrechte vor
öffentlichem Betrieb zu konfigurieren. Core Web Vitals und Belastbarkeit sind
unter repräsentativen Produktionsbedingungen zu messen.

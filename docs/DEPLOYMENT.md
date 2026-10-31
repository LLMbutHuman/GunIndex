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

# Entwicklungsinstallation

Python 3.12, Docker mit lokalem Socket und Internetzugriff für die gepinnten Images werden benötigt. Das Skript baut PHP 8.4.27 mit PDO-MySQL, GD und mbstring; libsodium/cURL gehören zur Laufzeit. TLS und Paketsignaturen bleiben aktiv. Die öffentliche System-CA wird nur als BuildKit-Secret und Laufzeit-Read-only-Datei eingebunden.

```sh
cd /workspace/GunIndex
python3 tests/environment.py up
python3 tests/environment.py test
node tests/browser_smoke.js
```

Der letzte Befehl benötigt Node, Playwright und Chromium. Diese Cloud-Umgebung besitzt sie; `CHROMIUM_PATH` kann ein anderes installiertes Binary wählen.

Die Container heißen `gunindex-rebuild-db`, `gunindex-rebuild-app` und `gunindex-rebuild-worker`. MySQL besitzt kein Netzwerkinterface; PHP benutzt einen privaten gemeinsamen Unix-Socket. Nur der Webserverport 8081 ist am Hostloopback gebunden. Der Quellcode ist im Container read-only. Die benannten Volumes für Datenbank, Socket und private Bilder/Limiter bleiben bei `stop` erhalten.

Zufällige Entwicklungszugänge und APP_KEY werden einmalig unter `tests/.runtime/` mit 0600 erzeugt. Dieses Verzeichnis ist ignoriert und kein öffentlicher Webroot. Werte niemals in Chat oder Git kopieren. Ein Verlust dieser Dateien darf nicht durch blindes Erzeugen neuer Zugangsdaten über bestehende Datenbanken kompensiert werden.

Nur eine leere Testdatenbank erhält `sql/schema.sql`. Keine historischen Benutzer- oder Dokumentexporte werden importiert. Neue additive Migrationen erhalten vorher einen tatsächlich verschlüsselten, wieder entschlüsselten und per Digest geprüften Snapshot. Die Datei bleibt privat erhalten; produktives Schlüssel-/Offsite-Management ist gesondert erforderlich.

Administratorinitialisierung: `docker exec -it gunindex-rebuild-app php ops/create-admin.php`. Der Assistent fragt ein neues sicheres Passwort verdeckt ab, zeigt das nur zur lokalen Einrichtung benötigte TOTP-Secret und verlangt einen gültigen Code. Keine Standardkonten. TOTP-Secret und APP_KEY nicht protokollieren. Siehe AUTH.md.

`.env.example` ist eine Vorlage, kein automatischer dotenv-Loader. Für einen eigenen Betrieb müssen die Werte sicher in den PHP-Prozess injiziert werden. Nach Änderungen an Prozessumfeld oder Proxy nur die betroffenen Entwicklungscontainer neu erstellen; Datenbank- und private Volumes erhalten. Produktion benötigt andere Webserver-/Rechte-/TLS-Einstellungen.

# Kontrollierte Übernahme bestehender Konten

`ops/import-legacy.php` importiert ausschließlich geprüfte Benutzerstammdaten aus
einer separat konfigurierten, nur lesend verwendeten Datenbank. Standard ist ein
aggregierter Dry-run ohne Zieländerungen. Namen, E-Mail-Adressen, Passwort-Hashes,
Dokumente und Quellzugänge erscheinen nicht im Bericht.

Vorbereitung: reale verschlüsselte Sicherung und Restoreprobe beider Datenbanken,
Quell-Rechte auf SELECT beschränken, Testkopie bereitstellen, Kollisionen und
Aufbewahrung fachlich prüfen. Historische produktive Exporte wurden hier nicht
importiert. Das Werkzeug ersetzt keine vollständige fachliche Bestandsmigration.

Eine private Datei mit 0600 enthält nur die vorhandenen Quellzugänge:

```text
LEGACY_DB_DATABASE=deine_quelldatenbank
LEGACY_DB_USERNAME=dein_lesekonto
LEGACY_DB_PASSWORD=HIER_SICHER_EINTRAGEN
LEGACY_DB_HOST=dein_interner_db_host
LEGACY_DB_PORT=3306
```

Alternativ `LEGACY_DB_SOCKET` setzen. Keine tatsächlichen Werte in Chat, Tickets,
Versionsverwaltung oder CLI-Argumente kopieren. Die Datei ist nicht shell-ausführbar.

Auf dem Ubuntu-Host ist kein separates PHP erforderlich. Die private Quellkonfiguration
zunächst außerhalb des Repositorys als `/root/legacy.env` mit 0600 anlegen. Nur der
kurzlebige Wartungscontainer erhält diese Datei; PHP-Webdienst und Worker erhalten sie nicht:

```sh
sudo /srv/gunindex/compose.sh run --rm --no-deps maintenance php ops/import-legacy.php --help
sudo /srv/gunindex/compose.sh run --rm --no-deps --user 0:0 -v /root/legacy.env:/run/legacy.env:ro maintenance php ops/import-legacy.php --source-env=/run/legacy.env --after-id=0 --limit=500
sudo /srv/gunindex/compose.sh run --rm --no-deps --user 0:0 -v /root/legacy.env:/run/legacy.env:ro maintenance php ops/import-legacy.php --source-env=/run/legacy.env --after-id=0 --limit=500 --apply --backup-confirmed
```

Der Quellhost muss vom privaten Wartungsnetz aus erreichbar sein. Für ein Unix-Socket
ist zusätzlich dessen ausdrücklich geprüfter Read-only-Mount nötig. Eine entfernte
Quelle niemals ungeschützt über das öffentliche Internet verbinden; lokale Kopie
oder einen vom Betreiber eingerichteten geschützten Tunnel verwenden.

Ein Batch umfasst höchstens 1000 Konten. Den gemeldeten `next_after_id` als Cursor
für den nächsten ausdrücklich geprüften Batch verwenden. Standardmäßig bleiben
Quell-IDs erhalten; Kollisionen, ungültige Daten und Benutzer-/E-Mail-Duplikate
stoppen die Übernahme. `--remap-ids` erzeugt neue Ziel-IDs und speichert ihre Zuordnung
in `market_legacy_user_map`. Bereits importierte Quell-IDs werden idempotent übersprungen.
Es gibt keine automatische Zusammenführung vermeintlich gleicher Konten.

Kein alter Admin-/Verifikationsstatus und kein TOTP-Secret wird übernommen. Alle
importierten Konten erhalten einen unbekannten zufälligen neuen Passwort-Hash und
müssen den sicheren Passwortreset durchführen. E-Mail-/Identitäts-/Altersfreigaben
werden nicht erfunden. Berechtigte Mitarbeiter sind anschließend gesondert mit MFA
und den vorgesehenen Rollen einzurichten. Alte Sessions nicht übernehmen.

Historische spezialisierte Inserate, Gebote, Zahlungs-/Rechnungsbehauptungen,
Ausweisdateien und Nachrichten werden nicht unkontrolliert als neue allgemeine
Geschäftsdaten aktiviert. Ihre Quelle bleibt unverändert erhalten. Eine zusätzliche
fachliche Mapping-/Freigabeentscheidung ist erforderlich, bevor solche Daten in
neue Prozesse einfließen. Ungültige oder widersprüchliche Geld-/Statusfelder dürfen
keine nachträglich behauptete Zahlung erzeugen.

# Ubuntu 22.04: Installation Schritt für Schritt

Ziel ist ein eigener Ubuntu 22.04-Server mit **amd64**, einer tatsächlich auf ihn
zeigenden Domain, SSH-Zugang und mindestens 4 GB RAM (8 GB empfohlen). PHP 8.1 aus
Ubuntu 22.04 genügt dem Projekt nicht. Deshalb verwendet die Installation geprüfte,
fest gepinnte PHP 8.4-FPM-, nginx- und MySQL 8-Container statt eines fremden PHP-PPA.
Der Server wurde hier **nicht** produktiv bereitgestellt; Installerprüfung und
lokale Tests sind von echten DNS-/TLS-/Mail-/Betriebsfreigaben zu unterscheiden.

Die Beispiele verwenden Variablen: Ersetze die drei Werte durch deine echten
Angaben. Das Skript akzeptiert keine Platzhalterdomain wie example.com.

1. Betriebssystem und Updates prüfen:

   ```sh
   cat /etc/os-release
   dpkg --print-architecture
   sudo apt-get update
   sudo apt-get upgrade
   sudo apt-get install -y git ca-certificates
   ```

2. SSH absichern und Firewall **unter Erhalt deines tatsächlichen SSH-Ports**
   konfigurieren. Nicht blind einen abweichenden SSH-Port aussperren:

   ```sh
   sudo ufw status verbose
   sudo ufw allow OpenSSH
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   ```

   Eine vorhandene Firewallregelung wird vom Installer nicht überschrieben.
   Docker veröffentlicht nur 80/443; MySQL besitzt keinen Hostport.

3. Entwicklungs-/Releasebranch holen und Einstellungen setzen:

   ```sh
   git clone --branch feature/general-marketplace-rebuild https://github.com/LLMbutHuman/GunIndex.git
   cd GunIndex
   git status --short
   git log -1 --oneline
   MARKET_DOMAIN='deine-echte-domain.tld'
   MARKET_EMAIL='betreiber@deine-echte-domain.tld'
   MARKET_DIRECTORY='/srv/gunindex'
   ```

4. Installer lesen und zunächst nur den Ablauf anzeigen:

   ```sh
   less ops/install-ubuntu-22.04.sh
   bash ops/install-ubuntu-22.04.sh --help
   bash ops/install-ubuntu-22.04.sh --domain "$MARKET_DOMAIN" --email "$MARKET_EMAIL" --directory "$MARKET_DIRECTORY" --dry-run
   ```

5. Nach Prüfung installieren. Domain-DNS und Port 80 müssen für ACME erreichbar
   sein; andernfalls mit `--prepare-only` nur vorbereiten:

   ```sh
   sudo bash ops/install-ubuntu-22.04.sh --domain "$MARKET_DOMAIN" --email "$MARKET_EMAIL" --directory "$MARKET_DIRECTORY"
   ```

   Docker kommt ausschließlich aus seinem offiziellen signierten Ubuntu-Repository;
   der GPG-Fingerprint wird geprüft. Es gibt kein `curl | bash`, keine abgeschaltete
   TLS-Prüfung und keinen Datenbankreset. Das Skript kopiert einen eigenen sauberen
   Release ohne alte Exporte/Uploads, `.git` oder Laufzeitgeheimnisse. Ein vorhandener
   Checkout und ältere Releases bleiben erhalten. Bestehende Keys werden nicht ersetzt.

6. Dienste und interne Gesundheitsprüfung kontrollieren:

   ```sh
   sudo /srv/gunindex/compose.sh ps
   sudo /srv/gunindex/compose.sh logs --tail=50 php worker web
   sudo /srv/gunindex/compose.sh exec -T php php ops/health.php
   ```

   `0=ok`, `2=degraded` und `1=unavailable`. Noch nicht eingerichtete E-Mail oder
   fehlende Worker-Heartbeats dürfen nicht als vollständige Betriebsbereitschaft gelten.

7. Einen neuen echten Administrator mit sicherem Passwort und geprüftem
   Authenticator-Code anlegen. Keine Standardzugänge verwenden:

   ```sh
   sudo /srv/gunindex/compose.sh exec php php ops/create-admin.php
   ```

   APP_KEY liegt nur in `/srv/gunindex/config/app.env` (0600). Er und weitere
   Verschlüsselungsschlüssel müssen getrennt gesichert werden; ein Verlust verhindert
   die Entschlüsselung bestehender Daten. Das TOTP-Einrichtungssecret nicht protokollieren.

8. Im Admin-Dashboard Marke, Kategorien, Länder, Preise, Betreiberangaben und
   rechtliche Dokumente prüfen. Zahlungsfreigaben bleiben standardmäßig aus.
   Die Installation setzt keine rechtlichen oder vertraglichen Freigaben.

9. E-Mail lokal einrichten. Der private Postfix-Container veröffentlicht keinen
   SMTP-Hostport. Nur PHP und Worker dürfen intern weiterleiten. Für echte
   Zustellung sind passende A/PTR-, SPF-, DKIM- und DMARC-Einstellungen, ein zulässiger
   Absender und eine nicht blockierte ausgehende SMTP-Verbindung nötig. Diese
   DNS-/Providerdaten kann der Installer nicht erfinden. Lokales Postfix ist ein
   eigener MTA, kein externer API-Dienst. Falls Port 25 nicht nutzbar ist, ist ein
   ausdrücklich konfigurierter TLS-SMTP-Relay eine mögliche externe Ausnahme.

   ```sh
   sudoedit /srv/gunindex/config/app.env
   ```

   Erst nach der Mail-/DNS-Prüfung `MAIL_TRANSPORT=none` auf `sendmail` ändern;
   `MAIL_FROM` und `APP_URL` müssen zur eigenen Domain passen. Anschließend:

   ```sh
   sudo /srv/gunindex/compose.sh up -d --force-recreate php worker
   sudo /srv/gunindex/compose.sh exec -T php php ops/monitor.php
   sudo /srv/gunindex/compose.sh logs --tail=50 mailer worker
   ```

   Ein Versandversuch beweist keinen Empfang. Verifikation/Reset mit einem eigenen
   kontrollierten Konto bis zum tatsächlichen Posteingang testen; keine Kundentokens
   oder Nachrichtentexte in Logs/Tickets kopieren. Die Versandqueue liefert mindestens
   einmal und dedupliziert Anwendungsereignisse; SMTP besitzt keine Exactly-once-Garantie.

10. PLZ-Daten nur mit geprüften Nutzungsrechten lokal importieren. Datenformat,
    Länderabdeckung und tatsächlichen Importbefehl beschreibt `INTERNATIONAL.md`.
    Ohne passende Koordinaten darf keine erfundene Entfernung angezeigt werden.

11. Verschlüsseltes Backup und Wiederherstellung in eine **neue** Datenbank proben:

    ```sh
    sudo python3 /srv/gunindex/current/ops/production/backup.py --directory /srv/gunindex backup --maintenance
    sudo ls -l /srv/gunindex/backups
    sudo python3 /srv/gunindex/current/ops/production/backup.py --directory /srv/gunindex restore --manifest /srv/gunindex/backups/DEIN_SNAPSHOT.json --database gunindex_restore_probe01 --files-directory /srv/gunindex/restored-private-probe01
    ```

    Der normale Backupweg stoppt Schreiber kurz kontrolliert und startet sie wieder.
    Er sichert SQL und private Dateien verschlüsselt, prüft Decryption-Digests und
    stellt nur in ein bisher nicht existierendes Schema/Verzeichnis wieder her.
    Die laufende Anwendung wird niemals automatisch umgeschaltet. APP_KEY und der
    rootgeschützte Archivschlüssel unter `/srv/gunindex/keys/` gehören getrennt verwahrt.

12. Offsite-Ziel mit unabhängig geprüftem SSH-Hostschlüssel konfigurieren. Beispiel
    und Grenzen: `MONITORING.md`. Es werden nur verschlüsselte Archive übertragen,
    nicht der Archivschlüssel. Keine automatische Hostschlüsselannahme.

    Nach einer erfolgreichen manuellen Backup-/Restoreprobe die vorbereiteten Timer
    installieren. Nicht parallel zusätzliche Cronjobs für dieselben Aufgaben anlegen:

    ```sh
    sudo install -m 0644 /srv/gunindex/config/systemd/gunindex-*.service /etc/systemd/system/
    sudo install -m 0644 /srv/gunindex/config/systemd/gunindex-*.timer /etc/systemd/system/
    sudo systemctl daemon-reload
    sudo systemctl enable --now gunindex-monitor.timer gunindex-backup.timer
    sudo systemctl list-timers gunindex-monitor.timer gunindex-backup.timer
    ```

    Ein fehlgeschlagener oder eingeschränkter Monitorcheck ist im Journal sichtbar.
    Alarmempfänger und Offsite-Ziel werden dadurch noch nicht automatisch eingerichtet.

13. Vor öffentlichem Betrieb `RELEASE_CHECKLIST.md` abarbeiten und tatsächlich
    testen: HTTPS, Mailzustellung, Passwortreset, Admin-MFA, Länder/Entfernungen,
    Zahlungstest, rechtliche Angaben, Backup/Restore, Monitoring und Barrierefreiheit.

## Update ohne Datenverlust

Das Quellrepository aktualisieren, Änderungen prüfen und einen getesteten Commit
verwenden. Der Installer verlangt einen sauberen Commit und `--update` für einen
vorhandenen Dienst. Er erstellt ein geprüftes Backup, hält Schreiber an und führt
additive Migrationen aus. Bei Fehlern werden weder produktive Daten gelöscht noch
SQL-Änderungen automatisch zurückgerollt; Dienst geschlossen lassen und vorwärts
reparieren. Administratorveränderte verwaltete Konfigdateien benötigen eine manuelle
Zusammenführung und werden nicht still überschrieben.

```sh
git fetch origin
git log --oneline HEAD..origin/feature/general-marketplace-rebuild
git pull --ff-only
sudo bash ops/install-ubuntu-22.04.sh --domain "$MARKET_DOMAIN" --email "$MARKET_EMAIL" --directory "$MARKET_DIRECTORY" --update
```

Images sind fest gepinnt. Sicherheitsupdates benötigen eine bewusst geprüfte
Aktualisierung der Digests und erneute Tests; keine unbeaufsichtigten Containerupdates.
Ubuntu-Updates, Zertifikatsablauf und Mail-/Queuezustand regelmäßig überwachen.

## Bestehende Klartext-Chats kontrolliert verschlüsseln

Die Schema-Migration führt die neue Cipher-Version ein; vorhandene Nachrichten werden
dadurch allein noch nicht verschlüsselt. Nach verifiziertem Backup beide Schreiber
anhalten und zunächst nur die verbleibenden Klartextzeilen zählen. Der Wartungscontainer
verwendet denselben dauerhaft gesicherten APP_KEY wie die Anwendung:

```sh
sudo python3 /srv/gunindex/current/ops/production/backup.py --directory /srv/gunindex backup --maintenance --leave-stopped
sudo /srv/gunindex/compose.sh run --rm --no-deps maintenance php ops/encrypt-chats.php
sudo /srv/gunindex/compose.sh run --rm --no-deps maintenance php ops/encrypt-chats.php --apply --backup-confirmed
sudo /srv/gunindex/compose.sh run --rm --no-deps maintenance php ops/encrypt-chats.php
```

Den Apply-Befehl bei begrenzten Batches wiederholen, bis der abschließende Dry-run
keine Klartextnachrichten mehr meldet. Die tatsächlichen Ergebniszähler prüfen;
ein erfolgreicher Schemaimport belegt keine abgeschlossene Datenkonvertierung.
Nach einem fehlerfreien abschließenden Dry-run die Dienste wieder starten:

```sh
sudo /srv/gunindex/compose.sh up -d php worker
sudo /srv/gunindex/compose.sh exec -T php php ops/health.php
```

Anschließend vor der fachlichen Produktivfreigabe einen eigenen Testchat von beiden
Konten lesen und die erfolgreiche Entschlüsselung prüfen.

Bei einem Fehler die Schreiber angehalten lassen und Ursache prüfen. Der Server kann
Chats für berechtigte Teilnehmer entschlüsseln; dies ist keine Ende-zu-Ende-Verschlüsselung.

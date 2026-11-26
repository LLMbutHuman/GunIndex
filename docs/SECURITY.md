# Sicherheitsstand

Die Maßnahmen sind implementiert und besitzen lokale/synthetische Prüfungen; der genaue ausgeführte Umfang steht in [TESTING.md](TESTING.md). Eine unabhängige vollständige OWASP-ASVS-Abnahme oder absolute Sicherheitsgarantie wird nicht behauptet.

## Zugriff und Eingaben

Datenbankzugänge kommen aus dem Prozessumfeld. PDO nutzt echte Prepared Statements. Strikte Cookie-Sessions besitzen Rotation, Widerruf und Fristen; Passwörter werden mit Argon2id verarbeitet. Persistente Loginlimits, generische Resetantworten und gebundene Einmaltokens erschweren Enumeration und Wiederholung. E-Mail-Bestätigung ist kein Identitätsnachweis.

Personalrechte werden aus der aktuellen Datenbank gelesen und verlangen MFA. Rollenänderungen widerrufen Sitzungen; ein frei übermittelter Rollenwert oder alter Sessionflag erzeugt keine Berechtigung. Konkrete Objektprüfungen schützen Nachrichten, Rechnungen, Verifikationsdokumente, Suchprofile, Meldungen und Exporte. Zustandsänderungen benötigen POST/CSRF.

HTML wird kontextgerecht escaped, Passwörter und Eingaben werden nicht global verändert. Uploads haben Typ-, Größen- und Auflösungsgrenzen und werden neu codiert. Der Dispatcher gibt nur freigegebene Routen/Assets aus. Konfiguration, SQL, Logs, Backups und private Verzeichnisse bleiben außerhalb des Webroots. Lokale Skripte und feste Zahlungsziele sind per CSP beschränkt; Inline-Styles bleiben für geprüfte Markenfarben erlaubt.

## Verschlüsselte Daten

`config/crypto.php` verwendet libsodium XChaCha20-Poly1305 mit zufälligen Nonces und Standard-HKDF für getrennte Zwecke. `APP_KEY` kommt aus privater Serverkonfiguration; `APP_KEY_PREVIOUS` ermöglicht eine dokumentierte Übergangsrotation. Ohne passende Schlüssel bleiben verschlüsselte Inhalte unlesbar. Schlüssel benötigen getrennte Sicherung und einen tatsächlich geprobten Wiederherstellungsprozess.

Verschlüsselt sind sensible Mailqueue-Payloads, Identitätsbilder, vertrauliche Fallzusatzangaben und unveränderliche Finanzsnapshots. MFA-Secrets werden ebenfalls geschützt abgelegt. Der alte Chat (`cipher_version` 0/1) bleibt serverlesbar und ist keine E2EE. Der neue, standardmäßig deaktivierte E2EE-Chat (`cipher_version` 2) verwendet Geräteschlüssel und signierte OpenPGP-Pakete; der Server speichert keine Klartextnachrichten oder unverschlüsselten Dateischlüssel. Architektur, Grenzen und ausstehende unabhängige Abnahme: [E2EE_PROTOCOL.md](E2EE_PROTOCOL.md). Alte allgemeine Klartextnachrichten benötigen weiterhin den gesonderten Konvertierungslauf; historische private Legacy-Uploads werden nicht automatisch migriert oder als E2EE ausgegeben.

Mailtokens werden nur gehasht in der Tokentabelle gespeichert. Verschlüsselte Queue-Payloads werden nach erfolgreichem MTA-Handoff redigiert; technische Deduplizierung bleibt erhalten. Erforderliche Sicherheits-/Kontomails und optionale Nutzerhinweise sind getrennt. Chatnachrichteninhalte gehören nicht in Hinweismails.

## Identitäts- und Datenschutzabläufe

Die Identitätsprüfung ist technisch vorhanden und standardmäßig gesperrt. Aktivierung benötigt tatsächliche Fachfreigabe, private Ablage, Schlüssel und festgelegte Fristen. JPEG-/PNG-Uploads werden im Arbeitsspeicher neu codiert und vor persistenter Ablage verschlüsselt. Dokumente sind nur über fallgebundene, einmalige, personengebundene, kurz gültige POST-Zugänge mit MFA/CSRF verfügbar; Einsichten werden protokolliert. Der Retentionworker entfernt abgelaufene Dateien.

Identität, Alter und E-Mail-Besitz sind getrennte Feststellungen. Keine davon aktiviert regulierte Waren. Export und Deaktivierung verlangen das aktuelle Passwort; Anonymisierung erfordert eine dokumentierte Prüfung offener Geschäfte und Aufbewahrung. Ein Löschantrag behauptet keine sofortige vollständige Entfernung aller Geschäftsunterlagen.

## Zahlungen und Konkurrenz

Manuelle Zahlungsbestätigungen vertrauen dem berechtigten Finanzpersonal und sind keine providerverifizierten API-Ereignisse. Kundenhinweise, Browserparameter, Screenshots oder ungeprüfte Webhooks erzeugen keine bezahlten Leistungen. Betrag, Referenz und tatsächliche Providerkennung müssen geprüft werden. Erstattungsanforderungen sind keine Auszahlung; eine erfasste Rückzahlung braucht eine tatsächlich extern ausgeführte Zahlung.

Transaktionen, Zeilensperren, eindeutige Referenzen und begrenzte Workerbatches schützen Gebote, knappe Plätze, Bumps und doppelte Buchungen. Maximalgebote bleiben privat. Werbe- und Suchabfragen prüfen zusätzlich aktuelle Laufzeiten und Länder-/Kontofreigaben.

## Betrieb und offene Abnahmen

[PHP 8.4.28](https://www.php.net/releases/8_4_28.php), [nginx 1.30.8](https://nginx.org/en/security_advisories.html),
[MySQL 8.4.13.1 LTS](https://dev.mysql.com/doc/relnotes/mysql/8.4/en/) und
[Composer 2.11.3](https://getcomposer.org/changelog/2.11.3) sind per Image-Digest
fixiert. MySQL 8.0 hat sein Wartungsende erreicht. Bestehende MySQL-8.0-Datenbanken
erfordern die gesonderte [Migrationsprüfung](UBUNTU_22_04.md#update-ohne-datenverlust).

PHP-Dateiuploads kommen ohne serverseitige Sitzungs-Fortschrittsdaten aus.
nginx begrenzt Anfragerate, gleichzeitige verarbeitete Anfragen und inaktive
Verbindungen je Client. TLS-Early-Data bleibt deaktiviert. MySQL veröffentlicht
keinen Hostport und deaktiviert den X-Protocol-Dienst sowie `LOCAL INFILE`.
Frische Datenbanken legen den MySQL-Root-Account nur für `localhost` an.

Neuinstallationen sperren den öffentlichen Marktplatz bis zur Aktivierung im
[geschützten Setup-Panel](ADMIN_SETUP.md). Administrationsrecht, aktuelle MFA,
CSRF, erneut geprüfte technische Voraussetzungen und ein Audit-Eintrag sichern
den Übergang. Zahlungs- und sonstige Fachfreigaben werden separat verwaltet.

[Ubuntu 22.04](UBUNTU_22_04.md) enthält private Datenbank-/Dateiverzeichnisse, TLS-/Webserverkonfiguration, lokale Mailzustellung, unabhängige Worker, verschlüsselte Sicherungen und strikte SSH-Offsitewerkzeuge. Die tatsächliche Installation auf dem Zielserver, Mail-DNS/Zustellung, Rechte-/Firewallprüfung, Offsite-Restore, Alarmierung und Incidentprozess müssen dort abgenommen werden. Ein lokaler Test ersetzt keine reale Betriebsabnahme.

Historische veröffentlichte Datenbankzugänge gelten als möglicherweise kompromittiert und müssen rotiert werden. Alte sensible Dateien sind aus der neuen Versionierung ausgeschlossen, bleiben aber in der Git-History. Es wurde keine destruktive Historyänderung vorgenommen. Retention, gegebenenfalls Meldepflichten und eine koordinierte Bereinigung sind weiterhin offen.

Weitere Grenzen sind eine unabhängige Sicherheitsprüfung, repräsentative Last-/Missbrauchstests, vollständige Screenreaderprüfung sowie ein geprüftes länderspezifisches Produktregelwerk. Eine ergänzende Wortprüfung ist kein vollständiger Rechtsklassifikator. Alle Produktivfreigaben für Zahlungen, Werbung, Finanzdokumente, Identität und Rechtsveröffentlichung müssen tatsächlich erfüllt sein.

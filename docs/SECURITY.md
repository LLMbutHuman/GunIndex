# Sicherheitsstand

Die Maßnahmen sind implementiert und besitzen lokale/synthetische Prüfungen; der genaue ausgeführte Umfang steht in [TESTING.md](TESTING.md). Eine unabhängige vollständige OWASP-ASVS-Abnahme oder absolute Sicherheitsgarantie wird nicht behauptet.

## Zugriff und Eingaben

Datenbankzugänge kommen aus dem Prozessumfeld. PDO nutzt echte Prepared Statements. Strikte Cookie-Sessions besitzen Rotation, Widerruf und Fristen; Passwörter werden mit Argon2id verarbeitet. Persistente Loginlimits, generische Resetantworten und gebundene Einmaltokens erschweren Enumeration und Wiederholung. E-Mail-Bestätigung ist kein Identitätsnachweis.

Personalrechte werden aus der aktuellen Datenbank gelesen und verlangen MFA. Rollenänderungen widerrufen Sitzungen; ein frei übermittelter Rollenwert oder alter Sessionflag erzeugt keine Berechtigung. Konkrete Objektprüfungen schützen Nachrichten, Rechnungen, Verifikationsdokumente, Suchprofile, Meldungen und Exporte. Zustandsänderungen benötigen POST/CSRF.

HTML wird kontextgerecht escaped, Passwörter und Eingaben werden nicht global verändert. Uploads haben Typ-, Größen- und Auflösungsgrenzen und werden neu codiert. Der Dispatcher gibt nur freigegebene Routen/Assets aus. Konfiguration, SQL, Logs, Backups und private Verzeichnisse bleiben außerhalb des Webroots. Lokale Skripte und feste Zahlungsziele sind per CSP beschränkt; Inline-Styles bleiben für geprüfte Markenfarben erlaubt.

## Verschlüsselte Daten

`config/crypto.php` verwendet libsodium XChaCha20-Poly1305 mit zufälligen Nonces und Standard-HKDF für getrennte Zwecke. `APP_KEY` kommt aus privater Serverkonfiguration; `APP_KEY_PREVIOUS` ermöglicht eine dokumentierte Übergangsrotation. Ohne passende Schlüssel bleiben verschlüsselte Inhalte unlesbar. Schlüssel benötigen getrennte Sicherung und einen tatsächlich geprobten Wiederherstellungsprozess.

Verschlüsselt sind neue Chattexte, sensible Mailqueue-Payloads, Identitätsbilder, vertrauliche Fallzusatzangaben und unveränderliche Finanzsnapshots. MFA-Secrets werden ebenfalls geschützt abgelegt. Der berechtigte Server kann entschlüsseln: **Chat ist nicht Ende-zu-Ende-verschlüsselt**. Alte allgemeine Klartextnachrichten benötigen den gesonderten Konvertierungslauf; historische private Legacy-Uploads werden nicht automatisch migriert.

Mailtokens werden nur gehasht in der Tokentabelle gespeichert. Verschlüsselte Queue-Payloads werden nach erfolgreichem MTA-Handoff redigiert; technische Deduplizierung bleibt erhalten. Erforderliche Sicherheits-/Kontomails und optionale Nutzerhinweise sind getrennt. Chatnachrichteninhalte gehören nicht in Hinweismails.

## Identitäts- und Datenschutzabläufe

Die Identitätsprüfung ist technisch vorhanden und standardmäßig gesperrt. Aktivierung benötigt tatsächliche Fachfreigabe, private Ablage, Schlüssel und festgelegte Fristen. JPEG-/PNG-Uploads werden im Arbeitsspeicher neu codiert und vor persistenter Ablage verschlüsselt. Dokumente sind nur über fallgebundene, einmalige, personengebundene, kurz gültige POST-Zugänge mit MFA/CSRF verfügbar; Einsichten werden protokolliert. Der Retentionworker entfernt abgelaufene Dateien.

Identität, Alter und E-Mail-Besitz sind getrennte Feststellungen. Keine davon aktiviert regulierte Waren. Export und Deaktivierung verlangen das aktuelle Passwort; Anonymisierung erfordert eine dokumentierte Prüfung offener Geschäfte und Aufbewahrung. Ein Löschantrag behauptet keine sofortige vollständige Entfernung aller Geschäftsunterlagen.

## Zahlungen und Konkurrenz

Manuelle Zahlungsbestätigungen vertrauen dem berechtigten Finanzpersonal und sind keine providerverifizierten API-Ereignisse. Kundenhinweise, Browserparameter, Screenshots oder ungeprüfte Webhooks erzeugen keine bezahlten Leistungen. Betrag, Referenz und tatsächliche Providerkennung müssen geprüft werden. Erstattungsanforderungen sind keine Auszahlung; eine erfasste Rückzahlung braucht eine tatsächlich extern ausgeführte Zahlung.

Transaktionen, Zeilensperren, eindeutige Referenzen und begrenzte Workerbatches schützen Gebote, knappe Plätze, Bumps und doppelte Buchungen. Maximalgebote bleiben privat. Werbe- und Suchabfragen prüfen zusätzlich aktuelle Laufzeiten und Länder-/Kontofreigaben.

## Betrieb und offene Abnahmen

[Ubuntu 22.04](UBUNTU_22_04.md) enthält private Datenbank-/Dateiverzeichnisse, TLS-/Webserverkonfiguration, lokale Mailzustellung, unabhängige Worker, verschlüsselte Sicherungen und strikte SSH-Offsitewerkzeuge. Die tatsächliche Installation auf dem Zielserver, Mail-DNS/Zustellung, Rechte-/Firewallprüfung, Offsite-Restore, Alarmierung und Incidentprozess müssen dort abgenommen werden. Ein lokaler Test ersetzt keine reale Betriebsabnahme.

Historische veröffentlichte Datenbankzugänge gelten als möglicherweise kompromittiert und müssen rotiert werden. Alte sensible Dateien sind aus der neuen Versionierung ausgeschlossen, bleiben aber in der Git-History. Es wurde keine destruktive Historyänderung vorgenommen. Retention, gegebenenfalls Meldepflichten und eine koordinierte Bereinigung sind weiterhin offen.

Weitere Grenzen sind eine unabhängige Sicherheitsprüfung, repräsentative Last-/Missbrauchstests, vollständige Screenreaderprüfung sowie ein geprüftes länderspezifisches Produktregelwerk. Eine ergänzende Wortprüfung ist kein vollständiger Rechtsklassifikator. Alle Produktivfreigaben für Zahlungen, Werbung, Finanzdokumente, Identität und Rechtsveröffentlichung müssen tatsächlich erfüllt sein.

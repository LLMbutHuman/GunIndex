# Benutzerkonten und administrativer Zugang

Neue Konten verwenden PHP-Argon2id, sofern verfügbar, andernfalls den aktuellen
PHP-Standardalgorithmus. Neue Passwörter benötigen mindestens zwölf Zeichen;
Passwörter werden weder getrimmt noch HTML-bereinigt. Alte gültige Hashes können
beim Login aktualisiert werden. Ein kompromittiertes Passwort wird dadurch nicht
sicher; betroffene historische Zugangsdaten müssen separat rotiert werden.

Registrierung nimmt keine Identitätsdateien an. Login-Fehler nennen kein
gefundenes Konto. Anmeldeversuche werden im privaten persistenten Verzeichnis
`AUTH_RATE_LIMIT_DIR` begrenzt: 120/IP und zehn/IP+Login je 15 Minuten. MFA besitzt
zusätzliche IP- und Kontolimits. Fehlender/beschädigter Schreibspeicher sperrt die
Anmeldung mit 503. Reverse-Proxy-Header werden nicht ungeprüft als Client-IP
verwendet. Für mehrere Hosts ist ein gemeinsam wirksamer Limiter nötig.

Sessions erhalten nach Login und sensiblen Änderungen eine neue ID. Jede
datenbankgebundene Benutzerprüfung vergleicht `auth_version`; geänderte
Zugangsdaten und „Andere Sitzungen beenden“ widerrufen andere Sessions bei deren
nächster Anfrage. Die aktuelle Sitzung bleibt erhalten. Sessions verfallen nach
30 Minuten ohne solche Benutzerprüfung oder nach zwölf Stunden absolut.
Ein vollständiges gerätebezogenes Sessionregister ist noch nicht vorhanden.

## Administrationskonto

Es existiert kein vordefinierter Administrator und kein Standardpasswort.
Ein Administrator benötigt immer Passwort **und** RFC-6238-TOTP. Ein Login mit
Passwort allein setzt lediglich einen zehn Minuten gültigen MFA-Zwischenzustand
ohne Benutzerberechtigungen. TOTP verwendet den verbreiteten Modus SHA-1, sechs
Stellen, 30 Sekunden und ein Zeitfenster von ± einem Schritt. Bereits verwendete
Codes werden unter Datenbanksperre über `mfa_last_counter` abgewiesen.

`APP_KEY` ist ein außerhalb des Repositories bereitgestellter Base64-Schlüssel
mit genau 32 zufälligen Bytes. TOTP-Geheimnisse werden mit libsodium
XChaCha20-Poly1305 und zweckgebundenen Zusatzdaten verschlüsselt. Verschlüsselung
ersetzt weder Schlüsselverwaltung noch Zugriffsrechte. Ein Verlust des Schlüssels
sperrt den Zugang; Sicherung und Rotation müssen betrieblich geplant werden.

Ersteinrichtung ausschließlich auf einem vertrauenswürdigen interaktiven Terminal:

```sh
php ops/create-admin.php
```

Das CLI verwendet die konfigurierten DB-Prozesswerte und `APP_KEY`, fragt
Passwörter verdeckt ab, zeigt den TOTP-Schlüssel einmal zur lokalen Übernahme und
legt das erste Konto erst nach bestätigtem App-Code an. Keine Terminalaufzeichnung
verwenden; keine Passwörter als Argumente oder im Chat übergeben. Der beim
Einrichten verwendete Code wird für den Login nicht erneut akzeptiert.
Parallele Ersteinrichtungen werden durch einen Datenbanklock begrenzt.

Existiert bereits ein Administrator, überschreibt das CLI ihn nicht. Ein
abgesicherter Wiederherstellungsprozess, Recoverycodes und optionale Benutzer-MFA
sind noch offen. Vorhandene alte Admins ohne eingerichtete MFA erhalten keinen
Verwaltungszugang. Die aktuellen DB-Rechte werden bei jeder Adminprüfung gelesen.

## Profiländerung und Grenzen

E-Mail- oder Passwortänderung verlangt das aktuelle Passwort. Hashprüfung,
Profiländerung und Versionswechsel erfolgen unter Zeilensperre in einer
Transaktion. Fehler und E-Mail-Kollisionen erzeugen keine Teiländerung. Der
öffentliche Profilname ist der Benutzername, nicht der optionale private Name.

E-Mail-Verifikation, Passwort-zurücksetzen, Kontodatenexport/-löschung und
E-Mail-Sicherheitsbenachrichtigungen sind noch nicht implementiert. Es gibt keine
scheinbar funktionierenden Reset-/Verifikationslinks. Diese Implementierung und
ihre Dokumentation sind keine vollständige Sicherheitsabnahme.

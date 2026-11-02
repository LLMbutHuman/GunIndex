# Zuletzt angesehen und Produktvergleich

`/user/personal.php` ist nur für angemeldete Benutzer erreichbar. Der Link steht
im Dashboard; aktive Angebotsseiten besitzen einen CSRF-geschützten Vergleichsbutton.
Der Vergleich zeigt bis zu vier Angebote mit Preisart, Zustand, Kategorie, Land
und Übergabe. Auktionspreise werden ausdrücklich als Startpreis/aktuelles Gebot
bezeichnet; ein Gebot wird nicht als garantierter Kaufpreis angeboten.

Der Verlauf ist zunächst ausgeschaltet. Erst der ausdrückliche POST zur Aktivierung
erlaubt das Merken später aufgerufener Angebote. Maximal 20 Einträge, neueste zuerst,
ohne Dubletten. Deaktivieren löscht den Verlauf, separate Funktionen leeren Verlauf
und Vergleich. Alle Änderungen brauchen POST/CSRF, außer der nach Einwilligung
erfolgenden Aufzeichnung eines normalen GET-Angebotsaufrufs.

Gespeichert werden nur Konto-ID, Angebots-IDs, Zeitpunkte und Einwilligungsflag in
der bestehenden PHP-Sitzung. Keine Kopien von Titeln, Nachrichten oder Kontaktdaten.
Es gibt keine dauerhafte Kontohistorie und keine Synchronisierung über Geräte.
Die Anwendung entfernt abgelaufene Einträge beim nächsten Zugriff nach sechs
Stunden; nach zwölf Stunden werden auch die Zustimmung und beide Listen verworfen.
Abmeldung, Kontowechsel und bestehender Sitzungswiderruf entfernen die Listen aus
dem aktiven Sitzungskontext. Physisches Aufräumen inaktiver Sitzungsdateien obliegt
dem bestehenden Session-GC/Betrieb; ein sekundengenaues Löschen dieser Dateien
wird nicht behauptet.

Vor Anzeige wird jedes Angebot erneut über den bestehenden öffentlichen
Katalogzugriff geprüft. Verborgene Inserate, inaktive Verkäufer/Kategorien/Länder
und abgelaufene Auktionen werden entfernt. Private Sessionansichten bleiben
`noindex`/`no-store`. Alle neu hinzugekommenen Texte besitzen acht Sprachfassungen.

Prüfungen: `tests/personal_test.php` (datenbankfreie Logik) und optional
`--database` ausschließlich im synthetischen Schema; Browserabläufe im bestehenden
Smoke-Test. Der tatsächliche Ausführungsstand steht in TESTING.md.

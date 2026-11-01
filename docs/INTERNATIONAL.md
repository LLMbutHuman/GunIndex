# Länder, Sprachen und lokale Entfernungssuche

Der allgemeine Marktplatz unterstützt Deutschland sowie Österreich, Schweiz,
Frankreich, Belgien, Niederlande, Luxemburg, Dänemark, Polen, Tschechien und
Liechtenstein. Das ausgewählte Land ist ein **Marktkontext**, nicht automatisch
der Wohnsitz eines Kontos. Angebote bleiben ihrem Ursprungsland zugeordnet;
„International“ entfernt bei der Suche den Länderfilter. Ein Konto kann Angebote
aus anderen aktiven Märkten aufrufen. Es gibt keine erfundenen Wechselkurse:
Preise und Plattformgebühren bleiben in EUR.

Händlerbanner und gesponserte Plätze verwenden den ausgewählten Ländermarkt.
Eine internationale Produktsuche schaltet keine fremden Länderbanner in diesen
Kontext. Länder lassen sich unter `admin/geography.php` aktivieren/deaktivieren.
Die Sperre gilt serverseitig für Sichtbarkeit und neue Transaktionen. Bereits
vorhandene Gebote werden nicht gelöscht oder mit einem erfundenen Gewinner
abgeschlossen; Konflikte gehen in die bestehende Prüfwarteschlange.

## Sprachen

Lokale Wörterbücher mit identischen Schlüssel- und Platzhaltersätzen liegen unter `locales/`: Deutsch, Englisch, Französisch,
Niederländisch, Polnisch, Tschechisch, Dänisch und Italienisch. Sprache und Markt
werden unabhängig über ein CSRF-geschütztes Formular geändert. Das Dokument
setzt das passende HTML-`lang`. Übersetzt werden explizite Oberflächenstrings;
Artikeltexte, Chats, Namen und andere Nutzerinhalte werden niemals automatisch
übersetzt oder an einen Übersetzungsdienst gesendet.

Kernnavigation, Suche, Länder/PLZ/Entfernung, Angebotsfelder und wesentliche
Detailaktionen besitzen lokale Fassungen. Unbekannte Texte verwenden den
deutschen Ausgangstext. Noch nicht vollständig lokalisierte Admin-, Geschäfts-
und Rechtstexte dürfen nicht als vollständig geprüft übersetzt bezeichnet
werden. Für eigene Markenclaims, Hero-Texte und Kategorien können Administratoren
pro Sprache geprüfte Fassungen eingeben. Ein eigener deutscher Text wird ohne
solche Fassung nicht automatisch durch eine andere Bedeutung ersetzt.

## Postleitzahldaten und Nutzungsrechte

Entfernungen sind ungefähre **Luftlinien zwischen PLZ-Mittelpunkten**, keine
Straßenentfernungen und keine genaue Entfernung zu einer Privatadresse. Die
Suche nutzt ausschließlich lokale Daten mit dokumentierter Quelle, SHA-256,
Lizenznotiz und ausdrücklicher Rechtebestätigung. Ohne freigegebenen Ursprung
ist Radius-/Entfernungssortierung nicht verfügbar; eine exakt passende PLZ kann
weiterhin gesucht werden. Fehlende Koordinaten werden nie als null Kilometer
ausgegeben. Landeskennungen verhindern Kollisionen gleicher PLZ in verschiedenen
Ländern. Die Kugeldistanz wird mit einer Bounding Box und genauerer Distanzformel
gefiltert; Datumsgrenzen und Polnähe werden nicht fälschlich ausgeschlossen.

`ZIP/PLZ.csv` enthält vier Semikolonspalten **ohne Header**:
PLZ;Ort;Längengrad;Breitengrad. Manche deutschen PLZ haben verlorene führende
Nullen, die ausschließlich im expliziten Legacy-DE-Import ergänzt werden. Die
Datei belegt ihre Nutzungsrechte nicht. Sie wird daher nicht automatisch
produktiv importiert. Der Betreiber muss Herkunft und zulässige Nutzung prüfen.

Standardformat für selbst bereitgestellte, rechtmäßig nutzbare UTF-8-Dateien:

```text
postal_code;city;latitude;longitude
```

Koordinaten verwenden Dezimalpunkte. Länder werden je Import ausdrücklich
angegeben. Maximal 32 MiB und 100.000 eindeutige PLZ pro Datei; größere Quellen
können in geprüfte Teile aufgeteilt werden. Vorhandene andere PLZ werden nicht
gelöscht. Widersprüchliche doppelte PLZ werden vor dem Schreiben abgewiesen.

```sh
# Nur Format/Koordinaten prüfen, ohne Datenbankänderung:
php ops/import-postcodes.php --country=DE --file=/private/postcodes.csv --dry-run

# Erst NACH tatsächlicher Rechteprüfung; keine erfundene Lizenznotiz verwenden:
php ops/import-postcodes.php --country=DE --file=/private/postcodes.csv \
  --source='Tatsächliche Quelle und Ausgabe' \
  --license-note='Tatsächlich geprüfte Lizenz und erforderliche Namensnennung' \
  --license-reviewed

# Vorhandenes altes Format ausschließlich nach geklärten Rechten:
php ops/import-postcodes.php --country=DE --file=ZIP/PLZ.csv --format=legacy-de \
  --source='Geprüfte tatsächliche Herkunft' \
  --license-note='Geprüfte Nutzungsrechte dieses Bestands' --license-reviewed
```

Diese Befehle sind keine Bestätigung der beispielhaft bezeichneten Rechte.
Quell- und Länderabdeckung sind im geschützten Administrationsbereich sichtbar.
Eine deaktivierte Datenquelle wird nicht weiter zur Entfernungssuche verwendet.
Erfolgreiche Importe ersetzen nur ihre konkret enthaltenen PLZ-Punkte in einer
Transaktion und schreiben einen technischen Audit-Eintrag ohne lokale Dateipfade.

## Prüfungen

`php tests/geo_test.php` prüft unterstützte Länder, Postleitzahlformen,
Normalisierung, Luftlinien, Pol-/Datumsgrenzen und Sprachfallbacks ohne Datenbank.
`php tests/geo_test.php --database` benötigt ausschließlich
`gunindex_rebuild_test` und erzeugt ausdrücklich synthetische Quellen/Punkte;
es prüft Länderisolation, internationale Radiusresultate, deaktivierte Quellen
und Märkte. Diese Punkte sind kein produktiver Datensatz. Keine externe
Geocoding-, Karten- oder Übersetzungs-API ist erforderlich.

Systemereignisse verwenden `i18n_user_text(PDO, userId, key, params)` und damit die gespeicherte Empfängersprache, ohne die Sprache des auslösenden Browsers zu verändern. `notification.*` enthält eigenständige Betreff-/Textschlüssel für Gebote, Überbietung, Auktionsabschluss, Sofortkauf, Zahlungsbestätigung, Rechnung, dokumentierte Rückerstattung und neue Chatnachrichten. Artikelbezeichnungen werden nur als Parameter eingefügt und nicht übersetzt.

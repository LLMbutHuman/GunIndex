# Postleitzahlen: Bestand, Quellen und Importnachweise

Ergänzung 2026-11-02: Auch der vorhandene PHP-Importer wurde mit `--country=DE
--file=ZIP/PLZ.csv --format=legacy-de --dry-run` tatsächlich ausgeführt. Ergebnis:
8.289 validierte Einträge, null Dubletten, identischer SHA-256 wie unten.
Keine Datenbankänderung; Länder-/Radius-DB-Tests weiterhin separat erforderlich.

Recherche und lokale Prüfung: 2026-11-01. Die maschinenlesbaren Angaben stehen
in [postcode-sources.json](postcode-sources.json). Die Datei enthält Quellmetadaten,
keine automatische Freigabe und keine externen Koordinatendatensätze.

## Bereitgestellte deutsche Datei

`ZIP/PLZ.csv` wurde vollständig und ohne Änderung gelesen:

| Prüfung | Tatsächliches Ergebnis |
| --- | --- |
| Dateigröße | 497.912 Bytes |
| SHA-256 | `3f8a041a81caceb948ba30b285ba80281d464cd22fc4a049eba4bebdd8aab59b` |
| Gültige Quellzeilen / eindeutige normalisierte PLZ | 8.289 / 8.289 |
| Identische oder widersprüchliche Dubletten | 0 / 0 |
| Ergänzung führender Nullen im expliziten Legacy-DE-Modus | 686 PLZ |
| Breitengrade | 47,3754137477491 bis 55,0248003984965 |
| Längengrade | 5,97270624609937 bis 14,9841912137216 |
| Format | UTF-8; vier Semikolonspalten ohne Header; PLZ, Ort, Länge, Breite |

Der Nutzer bestätigt die erforderlichen Rechte an genau dieser Datei. Dies ist
als **Nutzerangabe** dokumentiert. Ursprünglicher Herausgeber und Erhebungsstand
sind nicht aus der Datei nachgewiesen. Die Formatprüfung beweist weder vollständige
deutsche PLZ-Abdeckung noch die tatsächliche Lage oder Aktualität jedes Punktes.
Ein Datenbankimport wurde in dieser Etappe nicht ausgeführt.

## Wiederholbare Vorprüfung ohne PHP oder Datenbank

```sh
node ops/validate-postcodes.mjs --country=DE --file=ZIP/PLZ.csv --format=legacy-de
node tests/postcodes_validation_test.mjs
```

Der Validator benötigt nur Node-Bordmittel. Er liest maximal 32 MiB, verlangt
UTF-8, prüft CSV-Struktur, Ländercodes, PLZ-Formate, Koordinatengrenzen und
Dubletten. Es gibt keine Schreib-, Download- oder Lizenzfreigabeoption.
Er zeigt Hash, Umfang und Grenzen, keine vollständigen Ortslisten. Symlinks,
Verzeichnisse, veränderte Dateien und mehr als 100.000 eindeutige PLZ werden
abgewiesen. Temporäre Dateien der Tests enthalten ausschließlich synthetische Daten.

Der Node-Validator ist eine zusätzliche Vorprüfung. Er verlangt bei Dubletten
identische numerische Koordinaten; der PHP-Importer vergleicht auf sieben
Nachkommastellen gerundete Werte. CSV-Quote-Regeln sind ebenfalls bewusst streng.
Die endgültige Importvalidierung erfolgt weiterhin durch den vorhandenen
PHP-Importer. Eine kleine Differenz wird nicht stillschweigend gemittelt.

Tatsächlicher Testnachweis vom 2026-11-01: Unter Node 18.2.0/Windows bestanden
alle zehn synthetischen Testgruppen. Geprüft wurden UTF-8/BOM/CRLF/Quotes/Hash,
Legacy-Spalten und führende Nullen, alle elf Länderformate, Dubletten/Konflikte,
Koordinatenfehler, Kontrollzeichen/Textlängen, fehlerhaftes CSV, ungültige
Kodierung/Header/Länder, Größenlimit sowie lesender Datei-/CLI-Zugriff mit
Leerzeichen im Pfad. Der separate vollständige Lauf gegen `ZIP/PLZ.csv` ergab
die oben genannten Zahlen. Diese Prüfungen benötigen keine Datenbank und sind
kein Nachweis eines tatsächlichen Imports oder einer funktionierenden Radiusabfrage.

In der Linux-Testumgebung zuerst den bestehenden Importer lesend ausführen:

```sh
php ops/import-postcodes.php --country=DE --file=ZIP/PLZ.csv --format=legacy-de --dry-run
```

Nach geprüftem Backup, passendem Datenbankziel und bestätigtem Dateihash kann die
bereits vorliegende Nutzerangabe als Lizenznotiz verwendet werden:

```sh
php ops/import-postcodes.php --country=DE --file=ZIP/PLZ.csv --format=legacy-de \
  --source='Vom Betreiber bereitgestellte ZIP/PLZ.csv; SHA siehe Quellmanifest' \
  --license-note='Nutzerangabe im Masterauftrag vom 2026-11-01: erforderliche Rechte an genau ZIP/PLZ.csv vorhanden; keine Aussage zu fremden Quellen.' \
  --license-reviewed
```

Dieser Befehl ist dokumentiert, hier nicht ausgeführt. Er aktiviert keine fremden
Datensätze. Anschließend tatsächliche `geo_imports`-Kennung, SHA, Datensatzanzahl,
verwendete Lizenznotiz und Länder-/Radiusprüfungen im Betriebsprotokoll ergänzen.

## Recherchierte externe Quellen

### GeoNames: alle zehn Nachbarmärkte

Der geprüfte Downloadindex führt AT, BE, CH, CZ, DK, FR, LI, LU, NL und PL.
Die Landesarchive verwenden UTF-8-Tabellen mit WGS84-Punkten und Genauigkeitsfeld.
Die Quelle weist darauf hin, dass Koordinaten teilweise geschätzt sind. `NL.zip`
enthält nur den ersten PLZ-Teil und passt nicht zum bestehenden vollständigen
NL-Suchschlüssel; dafür ist `NL_full.csv.zip` separat zu prüfen. Der angezeigte
Archivzeitstempel ist kein Nachweis des Alters jeder Ortskoordinate.
[Downloadindex](https://download.geonames.org/export/zip/),
[Format und Bedingungen](https://download.geonames.org/export/zip/readme.txt).

Die Quelle nennt CC BY 4.0. Bei Verwendung sind GeoNames, Lizenzlink und vorgenommene
Änderungen anzugeben. Vorgeschlagener Quellenhinweis: „Koordinatendaten: GeoNames,
CC BY 4.0; für GunIndex normalisiert.“ Das muss auch an der verwendenden Oberfläche
oder einer erreichbaren Datenseite erscheinen.
[Lizenzbedingungen](https://creativecommons.org/licenses/by/4.0/).

Die Archive wurden nicht heruntergeladen oder importiert. Vor der Übernahme sind
Mehrfachorte je PLZ zu untersuchen; das aktuelle Schema erlaubt einen Punkt pro
Land/PLZ. Konflikte brauchen eine dokumentierte fachliche Zuordnung oder ein
erweitertes Datenmodell. Keine erfundene Ortsbezeichnung und kein ungeprüftes Mittel.

### swisstopo: Schweiz und Liechtenstein

Das amtliche Ortschaftenverzeichnis enthält Ortsnamen, Postleitzahlen und Gebiete
für CH/LI. WGS84 ist als CSV erhältlich; die Quelle veröffentlicht monatlich am
Monatsersten. Reine Verwaltungs-/Firmen-/Postfachcodes sind nicht vollständig
enthalten. Vierstellige PLZ können mehreren Ortschaften zugeordnet sein, daher
ist vor Übernahme eine bewusste Auflösung dieses Modellunterschieds nötig.
[Produktbeschreibung](https://www.swisstopo.admin.ch/en/official-directory-of-towns-and-cities).

Die veröffentlichten OGD-Bedingungen gestatten Nutzung, Verarbeitung und
Weitergabe einschließlich kommerzieller Nutzung und verlangen einen Quellenhinweis,
beispielsweise „©swisstopo“. Noch kein Archiv heruntergeladen oder freigegeben.
[OGD-Nutzungsbedingungen](https://www.swisstopo.admin.ch/en/terms-of-use-free-geodata-and-geoservices).

### GISCO/Eurostat: europäische Alternative

GISCO veröffentlicht PLZ-Punkte für EU-, EFTA- und Kandidatenländer. Die Seite
weist auf fehlende nichtgeografische Codes und mögliche Lücken/Fehlzuordnungen
hin. Exakte Ausgabe, Format, Koordinatensystem und Länderumfang müssen beim
Download festgehalten werden. Die aktuelle Webseite nennt den Quellenhinweis
„© European Union - GISCO, 2024, postal code point dataset, Licence CC-BY-SA 4.0.“
[Produktseite](https://ec.europa.eu/eurostat/web/gisco/geodata/administrative-units/postal-codes).

CC BY-SA 4.0 verlangt Attribution und bei weitergegebenen Bearbeitungen die
entsprechende Weitergabe unter derselben Lizenz. Vor Vermischung mit anderen
Datensätzen muss das konkrete Weitergabemodell geprüft werden. Keine pauschale
Rechtefreigabe für die kombinierte Datenbank und kein Download in dieser Etappe.
[Lizenzbedingungen](https://creativecommons.org/licenses/by-sa/4.0/).

### La Poste: zusätzliche französische Primärquelle

Die offizielle PLZ-Datenbank ordnet PLZ und INSEE-Gemeinden zu und bietet einen
CSV-Export mit Gemeindezentroiden. Gemeindezentroide sind keine exakten
PLZ-Mittelpunkte. Die Produktseite nennt „Licence Ouverte / Open Licence“,
halbjährliche Aktualisierung und zuletzt 2026-10-08. Die Auswahl muss über FR
hinaus enthaltene Gebiete/Monaco berücksichtigen; vor Import ist eine passende
Länderzuordnung erforderlich. Die verlinkten Lizenzbedingungen und der konkrete
CSV-Ausgabestand sind vor Weitergabe gesondert zu dokumentieren.
[La-Poste-Produktseite](https://data.laposte.fr/datasets/laposte-hexasmal).

## Kontrollierte Aktualisierung

1. Eindeutige Quelle und Ausgabe wählen, Lizenz und Attribution speichern.
2. Über HTTPS in private Stagingablage laden; Hash des Originalarchivs und der
   normalisierten CSV getrennt erfassen. Entpackte Pfade und Größen begrenzen.
3. Schema, Länderumfang, Koordinatensystem, fehlende Werte und Dubletten prüfen.
   WGS84-Achsen ausdrücklich zuordnen; keine unbekannten Punkte durch 0/0 ersetzen.
4. Anzahl, Ergänzungen und Änderungen gegen die bisherige Ausgabe auswerten.
   Grobe Sprünge und neue Ausreißer vor Import prüfen.
5. Node-Vorprüfung und PHP-Dry-run durchführen; dann transaktional importieren.
6. Tatsächliche Länder-/PLZ-/Radiussuche testen, Quellenhinweis veröffentlichen,
   Importkennung/Hashes/Datum im Betriebsprotokoll nachtragen.

Diese Aktualisierungsschritte sind ein Betriebsverfahren, kein bereits
implementierter Download-Scheduler. Aktuell wurde kein externer Datenbestand
bezogen und keine neue Landesabdeckung aktiviert.

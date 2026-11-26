# Postleitzahlen: Bestand, Quellen und Importnachweise

Am 21. November 2026 wurden die 8.289 Punkte aus `ZIP/PLZ.csv` in einer
frischen isolierten MySQL-Instanz importiert. Der Lauf prüfte die Ablehnung
einer falschen SHA-256-Summe, `04838` mit führender Null, exakte PLZ-Suche,
Länderauswahl und den tatsächlichen Suchradius Berlin/München. Die Ergebnisse
beziehen sich auf die bereitgestellte Datei und dieses Testsystem.
[CI 44839813273](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839813273)

Ergänzung 2026-11-02: Auch der vorhandene PHP-Importer wurde mit `--country=DE
--file=ZIP/PLZ.csv --format=legacy-de --dry-run` tatsächlich ausgeführt. Ergebnis:
8.289 validierte Einträge, null Dubletten, identischer SHA-256 wie unten.
Keine Datenbankänderung; Länder-/Radius-DB-Tests weiterhin separat erforderlich.

Die maschinenlesbaren Angaben stehen in [postcode-sources.json](postcode-sources.json).
Der Installer startet den Import für DE, FR, AT, CH, LI, LU, CZ, BE und PL nur bei fehlender
Importhistorie. Die recherchierten weiteren Quellen sind Kandidaten.

## Polen

`ZIP/PLZ-PL.csv` enthält 18.644 geprüfte PLZ-Mittelpunkte. GUGiK stellt das
nationale PRG-Archiv vom 25. November 2026 mit 16 GML-Dateien zur freien
Nutzung bereit. Die Aufbereitung verarbeitet 8.635.874 Adresspunkte aus dem
Archiv im Stream. Sie verwirft 777 unvollständige oder unplausible Adresspunkte,
mittelt die Koordinaten je PLZ und transformiert EPSG:2180 mit Proj4js nach
WGS 84. Ausgeliefert werden ausschließlich aggregierte PLZ-Zentren.
[Amtliche PRG-Quelle](https://www.geoportal.gov.pl/pl/dane/panstwowy-rejestr-granic-prg/) ·
[GML-Format und Schema](https://www.gov.pl/web/gugik/zmiany-struktury-publikowanych-danych-adresowych3)

Von 20.555 PLZ mit verwertbaren Adresskoordinaten haben 18.972 mindestens
drei unterschiedliche Punkte. Ein Vergleich mit dem GeoNames-Archiv
vom 25. November hält nur Mittelwerte, die höchstens 25 km von mindestens
einem GeoNames-Zentrum derselben PLZ entfernt sind. 305 PLZ ohne
Vergleichspunkt und 23 mit größerer Abweichung entfallen. Dazu zählt `00-010`.
Die exakte Suche bleibt für alle syntaktisch gültigen PLZ möglich. Der Radius
verwendet angenäherte PLZ-Zentren und ist kein Nachweis einer Zustelladresse.
[GeoNames Daten und Lizenz](https://download.geonames.org/export/zip/readme.txt) ·
[EPSG:2180](https://epsg.io/2180)

Die Prüfsummen und Versionen der Eingabedateien stehen im Quellenmanifest.
Die Aufbereitung braucht PHP 8.4 mit ZIP und XMLReader sowie den geprüften
Proj4js-Bundle wie im Abschnitt Tschechien. Für Updates den gemeinsamen
Datenstand, die Schema-Version, die verworfenen Zeilen und die Abdeckung prüfen.

```sh
php -d memory_limit=512M ops/prepare-pl-postcodes.php --file=PRG-punkty_adresowe.zip --output=pl-aggregates.json \
  --expected-source-sha256=5df6a95709c544007919529d7e916b29d72dcf933b4e3fd4cad268bc0d6fd002
node ops/project-postcodes.mjs PL pl-aggregates.json package/dist/proj4.js pl-wgs84.csv
php ops/crosscheck-pl-postcodes.php --file=pl-wgs84.csv --reference=PL.zip --output=PLZ-PL.csv \
  --file-sha256=29de30680ae13e310d568b7b8593582af48e15cab9c45b685ebaf653f6ca6c3a \
  --reference-sha256=a068b5ee61df4d699f8efb49f9a61fb2c84c5970842c61d95d1a65f3623432ef
node ops/validate-postcodes.mjs --country=PL --file=PLZ-PL.csv
```

## Belgien

`ZIP/PLZ-BE.csv` enthält 1.139 PLZ aus den BeST-Adressregistern von Flandern,
Brüssel-Hauptstadt und Wallonien. BOSA stellt die verwendeten drei CSV-Archive
vom 25. November 2026 unter CC BY 4.0 bereit. Jedes Archiv stellt WGS84 bei.
Die Aufbereitung verwendet aktuelle Adresspunkte mit vollständigen, gültigen
Feldern. Sie mittelt die Punkte je PLZ und übernimmt die am häufigsten
vertretene Gemeinde aus dem jeweiligen regionalen Namensfeld. Vier von
1.143 PLZ mit gültigen Koordinaten haben weniger als drei verschiedene Punkte
und behalten die exakte Suche. Einzelne Zustellorte können vom Mittelwert
abweichen. Adresszeilen und Hausnummern sind in der Ausgabedatei nicht enthalten.
[BOSA und regionale Herausgeber](https://opendata.bosa.be/index.nl.html) ·
[BOSA-Nutzungsbedingungen](https://opendata.bosa.be/download/best/bestinabox/bestinabox_conditionsdutilisation.pdf) ·
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)

Von 7.184.728 Zeilen scheiden 522.919 wegen Status oder fehlender Felder
aus. Weitere 133.755 Zeilen aus Wallonien enthalten ungültige Koordinaten oder
Punkte außerhalb des belgischen Koordinatenrahmens. Die Originalarchive werden direkt von BOSA anhand der
im Quellenmanifest dokumentierten SHA-256 geprüft. Für eine Aktualisierung
die regionalen Archive mit gleichem Stand wählen, die Prüfsummen ergänzen
und Datenfelder, Abdeckung, Nutzungsbedingungen und Radius erneut prüfen.

```sh
php ops/prepare-be-postcodes.php --source-dir=be-sources --output=PLZ-BE.csv \
  --bevlg-sha256=f2c56f7705101bfba60334c4715fe7940d1fa00ef8a8afad1528ad73a8b6aeec \
  --bebru-sha256=21ef211a86609db217d834b7d8e18153defe5694271afb8ce6b16d17c986ab3d \
  --bewal-sha256=52a7538a1cab80cbcc70def4dc3edd34ea9ea4c368c3cda4d62718612f8c84b9
node ops/validate-postcodes.mjs --country=BE --file=PLZ-BE.csv
```

## Tschechien

`ZIP/PLZ-CZ.csv` enthält 2.675 von 2.677 PLZ mit Koordinaten im geprüften
RÚIAN-Monatsstand vom 31. Oktober 2026. Von 3.023.585 Zeilen fehlen bei 904
die Postleitzahl oder Koordinaten. ČÚZK veröffentlicht die Daten unter CC BY 4.0.
GunIndex mittelt die Koordinaten je PLZ in S-JTSK und zeigt die häufigste
Gemeinde. Bei Gleichstand entscheidet die lexikografische Reihenfolge der
UTF-8-Werte. Für zwei PLZ mit weniger als drei verschiedenen Punkten bleibt
die exakte Suche verfügbar. Nur die aggregierten Punkte werden ausgeliefert.
[Amtlicher Datenkatalog](https://geoportal.gov.cz/php/micka/record/basic/CZ-00025712-CUZK_SERIES-MD_RUIAN-CSV-ADR-ST) ·
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)

Der Monatsdownload enthält Windows-1250-CSV je Gemeinde. Die Aufbereitung
wandelt die positiven West-/Südwerte in die Ost-/Nordachsen von EPSG:5514 um
und projiziert die Näherungen mit Proj4js und EPSG:1623 nach WGS 84. Die
Koordinaten dienen der Umkreissuche. Abweichungen von einzelnen Zustellorten
innerhalb derselben PLZ sind möglich.
[ČÚZK-Downloads](https://atom.cuzk.gov.cz/) · [Proj4js](https://proj4js.org/) ·
[EPSG-Umrechnung mit Geltungsbereich Tschechien](https://epsg.io/1623)

Die SHA-256 der amtlichen ZIP-Datei ist im Quellenmanifest festgehalten.
Für eine neue Ausgabe den Monatsstand über den amtlichen Feed wählen und die
Änderungen an Format, Lizenz und Abdeckung prüfen. Die Konvertierung braucht PHP
mit ZIP und iconv sowie Node.js mit der geprüften Datei `package/dist/proj4.js`
aus `proj4@2.23.0`. Node führt die Bibliothek erst nach Prüfung der gepinnten
Dateisumme aus. Der Installer braucht die Umrechnungsbibliothek beim Betrieb
des Marktplatzes nicht.

```sh
npm pack proj4@2.23.0 --ignore-scripts
tar -xf proj4-2.23.0.tgz package/dist/proj4.js
php ops/prepare-cz-postcodes.php --file=20261031_OB_ADR_csv.zip --output=cz-aggregates.json \
  --expected-source-sha256=56dc1392033cc6ab21e7f2bafc553c2837d49e62049a60a0f238a0ba83c27877
node ops/project-cz-postcodes.mjs cz-aggregates.json package/dist/proj4.js PLZ-CZ.csv
node ops/validate-postcodes.mjs --country=CZ --file=PLZ-CZ.csv
```

## Luxemburg

`ZIP/PLZ-LU.csv` enthält 4.068 von 4.201 in BD-Adresses vertretenen
Postleitzahlen. Die Verwaltung für Kataster und Topografie veröffentlichte
das verwendete WGS84-CSV am 23. November 2026 unter CC0. Bei der
Aufbereitung werden die vorhandenen Punkte je PLZ gemittelt und die
häufigste Ortsbezeichnung übernommen. 133 PLZ mit weniger als drei
verschiedenen Koordinaten bleiben für die exakte Suche verfügbar.
Die bereitgestellte Datei enthält ausschließlich PLZ, Ort und
zusammengefasste Koordinaten.
[BD-Adresses und Lizenz](https://data.public.lu/fr/datasets/adresses-georeferencees-bd-adresses/)
· [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/)

Der Download entsprach dem im Katalog veröffentlichten MD5. Die
Aufbereitung und der Import verwenden zusätzliche SHA-256-Werte.
Auf neue Veröffentlichungen den gleichen Ablauf mit getrenntem
Download, dokumentiertem Datum, Quellprüfung und erneuter Validierung
anwenden. Die Datei bleibt im Installer bis zum nächsten geprüften
Release fest versioniert.
[Prüfsummen](postcode-sources.json)

```sh
php ops/prepare-lu-postcodes.php --file=addresses.csv --output=PLZ-LU.csv \
  --expected-source-sha256=737a4848a4f78dd355a347119cf906934c2c0dd6d2d47030b38b580ee4e72728
```

## Niederlande

Der GeoPackage-Download des Katasters (BAG) ist in der öffentlichen
Veröffentlichung vom 1. November 2026 verfügbar. Der amtliche Datensatz
im Downloadkatalog ist mit Public Domain Mark 1.0 gekennzeichnet. Die
Datei umfasst 7.827.132.416 Bytes. Die Aufbereitung verarbeitet aus
`verblijfsobject`, `ligplaats` und `standplaats` gültige Nutzungspunkte
je vollständiger PLZ. Sie verwendet den Mittelwert der verschiedenen
Koordinaten und die häufigste Ortsbezeichnung. PLZ mit weniger als drei
verschiedenen Punkten oder mehr als fünf Kilometern Ausdehnung auf einer
RD-Achse erhalten aus dieser Verarbeitung keine Koordinaten.
[BAG-Datensatz](https://www.pdok.nl/introductie/-/article/basisregistratie-adressen-en-gebouwen-ba-1) ·
[BAG-Downloadkatalog](https://service.pdok.nl/kadaster/bag/atom/bag.xml) ·
[Lizenz im amtlichen Download-Datensatz](https://www.nationaalgeoregister.nl/geonetwork/srv/dut/csw?service=CSW&version=2.0.2&request=GetRecordById&outputschema=http%3A%2F%2Fwww.isotc211.org%2F2005%2Fgmd&elementsetname=full&id=aa3b5e6e-7baa-40c0-8972-3353e927ec2f)

Die Aufbereitung benötigt Node.js ab 22.13 mit `node:sqlite` und Platz
für temporäre SQLite-Dateien. Die Projektion nach WGS 84 verwendet
EPSG:28992, die Näherung EPSG:4833 und das bereits geprüfte `proj4`-Paket.
Die Kontrollpunkte Delft, Ameland und Vlissingen wurden mit den veröffentlichten
ETRS89-Referenzen verglichen. Die größte Abweichung dieser Stichprobe betrug
0,65 Meter. Die Ausgabe ist für die angenäherte Umkreissuche bestimmt. Für das Release
stehen der vollständige, hashgeprüfte Download, Umfang und Stichproben des
resultierenden Datensatzes, der zweite reproduzierbare Lauf sowie der
Installationstest noch aus. Es wird noch keine NL-Länderdatei ausgeliefert.
[RD-Koordinatensystem der NSGI](https://www.nsgi.nl/coordinatenstelsels-en-transformaties/overzicht-coordinatenstelsels) ·
[NSGI-Kontrollkoordinaten](https://www.nsgi.nl/documents/1888506/2842654/coordinaten_AGRS_NL.pdf/9a8420b9-822c-cba3-da09-8561c2d5a930?t=1589968713129) ·
[GeoPackage-Spezifikation](https://docs.ogc.org/is/12-128r19/12-128r19.html)

```sh
node ops/prepare-nl-postcodes.mjs bag-light.gpkg nl-rd.json EXPECTED_SOURCE_SHA256
node ops/project-postcodes.mjs NL nl-rd.json package/dist/proj4.js PLZ-NL.csv
node ops/validate-postcodes.mjs --country=NL --file=PLZ-NL.csv --sorted
php ops/import-postcodes.php --country=NL --file=PLZ-NL.csv --sorted --dry-run
```

## Dänemark

Der bislang öffentliche DAWA-Postnummern-Endpunkt antwortete am
25. November 2026 mit HTTP 410. Der Nachfolgedienst Datafordeler
bietet aktuelle DAGI-Postnummernbereiche per Fildownload mit
API-Schlüssel oder OAuth an. Für einen importierbaren Datensatz
werden ein autorisierter Download oder eine gleichwertige, prüfbare
öffentliche Quelle benötigt.
[DAGI-Fildownload](https://datafordeler.dk/dataoversigt/danmarks-administrative-geografiske-inddeling-dagi/dagi-fildownload/)

## Schweiz und Liechtenstein

Das amtliche Ortschaftenverzeichnis von swisstopo liefert
`ZIP/PLZ-CH.csv` mit 3.177 und `ZIP/PLZ-LI.csv` mit 13 gültigen
vierstelligen Postleitzahlpunkten aus dem WGS84-CSV zum 1. November 2026.
Postfach- und reine Verwaltungscodes liegen außerhalb des amtlichen
Datensatzes. Die veröffentlichten OGD-Bedingungen erlauben Bearbeitung
und Weitergabe mit Quellenangabe. Die Website nennt ©swisstopo.
[Amtliches Verzeichnis](https://www.swisstopo.admin.ch/en/official-directory-of-towns-and-cities)
· [OGD-Nutzungsbedingungen](https://www.swisstopo.admin.ch/en/terms-of-use-free-geodata-and-geoservices)

Die Aufbereitung übernimmt pro `ZIP_ID` den Punkt der Gemeinde mit
dem höchsten Adressenanteil. Gleichstände werden deterministisch
aufgelöst. Die ZIP6-Punkte werden nach vierstelliger Postleitzahl
gemittelt und die häufigste Ortsbezeichnung übernommen. Schweizer
Kantonskürzel und das leere Kantonsfeld für Liechtenstein trennen
beide Länder. Die Punkte eignen sich für ungefähre Luftlinien.
[Beschreibung der CSV-Felder](https://www.swisstopo.admin.ch/en/official-directory-of-towns-and-cities-update-20260101)

swisstopo veröffentlicht monatlich einen neuen Datensatz. Die
verwendete Ausgabedatei und das Quellarchiv haben eigene feste Hashes.
Mit PHP CLI und ZIP-Erweiterung wird aus dem gehashten WGS84-Archiv
erneut je ein CSV für `--country=CH` und `--country=LI` erzeugt.
[Datei, Stand und SHA-256](postcode-sources.json)

```sh
php ops/prepare-swisstopo-postcodes.php --country=CH --file=ortschaftenverzeichnis_plz_4326.csv.zip \
  --expected-source-sha256=5c744e0f0d6be63cef7b367fa0e9a6007f3b51d6db6460875dc4326b74fba0ee --output=PLZ-CH.csv
```

## Österreich

Die Datei `ZIP/PLZ-AT.csv` enthält 2.200 von 2.234 zum Stichtag
gültigen Adressierungs-PLZ aus dem RTR-Verzeichnis. Der Datensatz
verbindet dessen Ortsnamen und Gültigkeitsangaben (Version 61407 vom
4. November 2026) mit dem GeoNames-Archiv vom 25. November 2026.
Beide Quellen stehen unter CC BY 4.0. Die Anwendung nennt sie bei
aktivem Import auf Such- und Entfernungsseiten.
[RTR-Katalog und Lizenz](https://www.data.gv.at/datasets/f76ed887-00d6-450f-a158-9f8b1cbbeebf)
· [GeoNames-Quelle und Datenformat](https://download.geonames.org/export/zip/readme.txt)
· [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)

`ops/prepare-at-postcodes.php` filtert Geodaten der Qualitätsstufen 4
und 6 auf gültige Adressierungs-PLZ. Pro PLZ werden eindeutige Punkte
der höchsten verfügbaren Stufe gemittelt. Die Ortsbezeichnung stammt
aus dem RTR-Verzeichnis. Bei mehreren Namen gilt die alphabetisch
erste Bezeichnung nach Byte-Reihenfolge. Das Ergebnis enthält
PLZ, Ort und gerundete WGS84-Koordinaten. Die 34 PLZ ohne geeigneten
Punkt bleiben exakt suchbar. Dazu gehört Wien 1010.

Für die Aufbereitung sind PHP CLI mit ZIP-Erweiterung und genau die
beiden im [Quellmanifest](postcode-sources.json) gehashten Snapshots
erforderlich. Bei Updates die aktuellen Quellen gesondert speichern,
Publikationsstand, Lizenz und Prüfsummen erfassen, die Abdeckung vergleichen
und die neue Ableitung erneut prüfen. Der Installer lädt keine Live-Daten.

```sh
php ops/prepare-at-postcodes.php --geonames=AT.zip --rtr=plz.json \
  --geonames-sha256=61ae0da7221ff66ce1a78168042d813172558baefd43b3b24f83d9df4261964e \
  --rtr-sha256=0af881fa64d3056ba554532ba30085330b4c998d36ae3c924566ae4a9623d52b \
  --date=2026-11-25 --output=PLZ-AT.csv
```

PHP-Vorprüfung und Node-Validator haben 2.200 eindeutige Datensätze,
88.864 Bytes, keine Dubletten und SHA-256
`87109d717f9fdc9aba73d928ed7679b1a3efe563cd0229415cbfb1ab9b5ec29d`
bestätigt.

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
Der spätere isolierte Datenbankimport ist oben belegt.

## Wiederholbare Vorprüfung ohne PHP oder Datenbank

```sh
node ops/validate-postcodes.mjs --country=DE --file=ZIP/PLZ.csv --format=legacy-de
node tests/postcodes_validation_test.mjs
```

Der Validator benötigt nur Node-Bordmittel. Er liest maximal 32 MiB, verlangt
UTF-8, prüft CSV-Struktur, Ländercodes, PLZ-Formate, Koordinatengrenzen und
Dubletten. Es gibt keine Schreib-, Download- oder Lizenzfreigabeoption.
Er zeigt Hash, Umfang und Grenzen, keine vollständigen Ortslisten. Symlinks,
Verzeichnisse und veränderte Dateien werden abgewiesen. Eingaben in beliebiger
Reihenfolge sind auf 100.000 eindeutige PLZ begrenzt. Mit `--sorted` prüfen
Vorprüfung und Importer bis zu 650.000 PLZ in lexikografischer Reihenfolge ihrer
normalisierten Schlüssel. Die Dateigröße bleibt auf 32 MiB begrenzt. Der
Importer prüft die gesamte Eingabe vor der ersten Datenbankänderung, liest
das hashgeprüfte Abbild erneut und schreibt alle Stapel in einer Transaktion.
Temporäre Dateien der Tests enthalten ausschließlich synthetische Daten.

```sh
node ops/validate-postcodes.mjs --country=PL --file=ZIP/PLZ-PL.csv --sorted
php ops/import-postcodes.php --country=PL --file=ZIP/PLZ-PL.csv --sorted --dry-run
```

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

### Belgien: Lizenzunterschiede je Veröffentlichung

Der föderale Katalog nennt für `code-postaux-belge` CC BY 4.0. Die Metadaten
des veröffentlichenden ODWB-Portals zu genau diesem Export nennen CC BY-NC 4.0.
Der Export ist für GunIndex ausgeschlossen. BOSA stellt die drei eigenen
BeST-Adressregisterdateien unter CC BY 4.0 bereit. Die Lizenz des separaten
BeST-GeoPackage und des WMS gilt jeweils für diese Angebote.
[Föderaler Katalog](https://data.gov.be/en/datasets/httpswwwodwbbeexploredatasetcode-postaux-belge) ·
[ODWB-Metadaten](https://www.odwb.be/api/explore/v2.1/catalog/datasets/code-postaux-belge) ·
[BOSA-Datendownloads und Bedingungen](https://opendata.bosa.be/index.nl.html)

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

### La Poste: französisches Mutterland

Die verifizierte La-Poste-Ausgabe vom 8. November 2026 enthält
Gemeindemittelpunkte. GunIndex liefert daraus 6.051 PLZ für das französische
Mutterland einschließlich Korsika. Die Umwandlung mittelt je PLZ die
Koordinaten der eindeutig zugeordneten Gemeinden. Die Suchseite nennt
Herausgeber, Datenstand und Licence Ouverte 2.0. Der isolierte Installer
bestätigte Datenbankimport und Quellenanzeige.
[Reproduktion und Lizenz](INTERNATIONAL.md) · [Betriebsprobe](https://github.com/LLMbutHuman/GunIndex/actions/runs/44956675099)

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

Neue Stände werden über dieses Betriebsverfahren übernommen. Der Installer
verwendet die versionierten Bestände und prüft ihre Prüfsummen.

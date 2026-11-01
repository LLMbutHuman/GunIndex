# Kostenlose Gesuche, gespeicherte Suchen und Suchalarme

`user/wanted.php` erstellt und bearbeitet eigene Gesuche. Titel, Beschreibung,
Suchbegriffe, Kategorie, Hersteller/Modell, Preisbereich, Zustand, Land,
internationaler Bereich und optional Bezugs-PLZ/Radius werden serverseitig geprüft.
Eine deaktivierte oder ausgeschlossene Produktkategorie wird nicht durch ein
Gesuch wieder freigegeben. Der Radius benötigt freigegebene lokale Koordinaten;
fehlende Daten ergeben weder eine erfundene Entfernung noch einen Null-Kilometer-
Treffer. Ohne Radius wirkt eine angegebene PLZ als exakter landesgebundener Filter.

Gesuche können pausiert, wieder aktiviert und archiviert werden. Archivierung
löscht keine Geschäfts- oder Fremddaten. Eine Wiederaktivierung übernimmt während
der Pause veröffentlichte Angebote als bestehende Vorschläge, ohne einen Stapel
nachträglicher Sofortalarme auszulösen. Benutzer erhalten nur eigene Bearbeitungs-
und Trefferansichten. `marketplace/wanted.php` zeigt aktive öffentliche Gesuche,
keine Konto-IDs, E-Mail-Adressen oder privaten Benachrichtigungspräferenzen.

## Nachvollziehbares lokales Matching

`config/discovery.php` normalisiert Groß-/Kleinschreibung, Umlaute, ausgewählte
Akzente und Trennzeichen. Kleine lokale Synonymgruppen verbinden etwa Sofa/Couch,
Notebook/Laptop oder Rucksack/Backpack. Bis zu acht konkrete Suchbegriffe werden
mit begrenzter Tippfehlertoleranz verglichen. Hersteller und Modell müssen zur
normalisierten Bezeichnung passen und werden nicht durch unscharfe Modellnummern
ersetzt. Preis, Zustand, Kategorie, Land und Radius sind harte Filter. Mindestens
60 Prozent der konkreten Textbegriffe müssen passen; die Treffer erläutern ihren
Score. Kein externes KI-, Such- oder Karten-API wird aufgerufen.

Eine Entfernung ist ungefähre Luftlinie zwischen PLZ-Mittelpunkten, keine genaue
Adresse oder Fahrstrecke. Ein internationales Gesuch entfernt die Angebots-
Landesgrenze; ein Radius bleibt dabei an das explizite Bezugsland und dessen PLZ
gebunden. Gesuche gleichen nur aktive allgemeine Angebote aktiver Verkäufer ab;
abgelaufene Auktionen und eigene Angebote werden ausgeschlossen.

## Gespeicherte Suchen

`user/saved-searches.php` speichert geprüfte Suchfilter einschließlich Land,
internationaler Suche und Entfernung. Der Worker verwendet für die tatsächliche
Trefferentscheidung denselben `market_search()`-Dienst mit internem Listing-ID-
Filter; es existiert keine abweichende zweite SQL-Suchlogik. Gleiche Filter werden
pro Konto nicht mehrfach gespeichert. Namen und Suchalarme können geändert,
Suchaufträge pausiert oder archiviert werden.

## Verarbeitung und Benachrichtigungen

Die additive Migration `005_discovery.sql` erstellt Gesuche, gespeicherte Suchen,
Veröffentlichungsereignisse, Treffer und tägliche Digestnachweise. Der Code liest
keine historischen Spezialinserate oder alten Benutzerexporte.

`discovery_enqueue_listing($pdo, $listingId)` gehört in dieselbe Transaktion wie
die Veröffentlichung oder erneute aktive Bearbeitung eines allgemeinen Inserats.
Der Event-Cursor berücksichtigt dadurch auch später veröffentlichte alte Entwürfe.
Neue Suchaufträge erhalten zunächst einen begrenzten Backfill vorhandener Angebote.
Die ersten 50 Vorschläge werden nach dem POST-Speichern berechnet; weitere liefert
der unabhängige Worker. Vorhandene Vorschläge lösen keine E-Mail-Flut aus.

```sh
php ops/discovery-worker.php --once
```

Standardmäßig bearbeitet ein Lauf 20 Suchaufträge mit jeweils bis zu 50 Ereignissen
beziehungsweise Backfill-Angeboten. Ein datenbankspezifischer Lock verhindert
parallele Worker. Pro Suchauftrag sichern Transaktion und Zeilensperre Cursor,
Treffer und deduplizierte Notification. Fehlerhafte gespeicherte Kriterien werden
in einen Prüfzustand verschoben; sie blockieren weitere Aufträge nicht. Technische
Datenbankfehler werden nicht als Erfolg ausgegeben.

- **Sofort:** ein deduplizierter Hinweis pro Suchauftrag und neuem Angebot.
- **Täglich:** Zusammenfassung der noch offenen Treffer des Vortages, höchstens
  einmal je Benutzer und UTC-Tag. Standardmäßig ab 07:00 UTC; ein dokumentierter
  `DISCOVERY_DIGEST_HOUR_UTC`-Wert von 0–23 verschiebt die Ausführung.
- **Aus:** Treffer werden zur eigenen Ansicht ermittelt, aber nicht benachrichtigt.

Die globalen In-App-/Mailpräferenzen haben Vorrang. E-Mails gelangen in die lokale
verschlüsselte Mailqueue; die tatsächliche Zustellung besitzt ihren eigenen
Status und Wiederholungsablauf. Ein verarbeiteter Suchalarm behauptet keinen
erfolgreichen externen E-Mail-Empfang. Der Tagesdigest verarbeitet höchstens 100
Trefferzeilen pro Lauf/Tag und zählt dasselbe Inserat aus mehreren Suchaufträgen
nur einmal; weitere bleiben im eigenen Trefferbereich beziehungsweise
für spätere Verarbeitung erhalten. Ein Match wird durch die eindeutige Kombination
Suchart/Suchauftrag/Listing nicht mehrfach angelegt.

## Grenzen und Betriebsanforderungen

Pro Benutzer sind je Art bis zu 200 Suchaufträge, davon 50 gleichzeitig aktive,
vorgesehen. Die neuesten 500 abgeleiteten Treffer je Suchauftrag werden aufbewahrt;
die Oberfläche bezeichnet diese ausdrücklich als begrenzte Trefferhistorie.
Hintergrundjobs müssen tatsächlich regelmäßig laufen. Importrechte und lokale
Geodatenabdeckung sind gesonderte Voraussetzungen. Es wird keine vollständige
semantische Produkterkennung, Übersetzung nutzergenerierter Inhalte oder exakte
Routenentfernung versprochen. Die UI nutzt lokale Übersetzungen mit deutscher
Fallbackanzeige; noch fehlende Wörterbuchtexte bleiben sichtbar und bedürfen einer
sprachlichen Abnahme.

`tests/discovery_test.php` prüft die reine Matchinglogik ohne Datenbank. Mit
`--database` werden zusätzlich ausschließlich in `gunindex_rebuild_test`
synthetische Konten/Angebote angelegt und Backfill, Event-Deduplizierung, Eigentum,
Pausierung, Suchfilter, Tagesdigest und Archivierung geprüft. Diese DB-Prüfungen
dürfen nicht parallel zu einem Restore-/Quellvergleich laufen.

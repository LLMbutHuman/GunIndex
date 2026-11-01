# Händler, Länderwerbung und knappe Premiumplätze

Der allgemeine Marktplatz besitzt eigene Händlerprofile und physisch begrenzte
Werbekontingente. Die Länderzuordnung ist unabhängig von der Oberflächensprache.
Auch eine internationale Produktsuche mischt nicht automatisch die Werbemärkte:
Banner und Premiumangebote richten sich nach dem ausdrücklich gewählten
Marktkontext.

## Händler-Dashboard

`user/dealer.php` bietet:

- Explizite öffentliche Geschäftsangaben: Firma, Geschäftsadresse, Land,
  Kontakt-E-Mail, Website und rechtliche Unternehmensinformationen.
- Inhaltsprüfung durch berechtigte Mitarbeiter mit MFA. Private Kontodaten
  werden nicht automatisch als Unternehmensangaben veröffentlicht. Eine
  Inhaltsfreigabe ist keine Identitätsprüfung oder behördliche Zulassung.
- Tatsächliche Zahlen eigener aktiver Inserate, Merkliste-Einträge,
  Kontaktunterhaltungen, Gebotsereignisse und bestätigter Plattformzahlungen.
  Nicht erhobene Inseratsaufrufe werden nicht erfunden.
- Paginierte eigene Inserate und atomare Sammelaktionen für Pause/Archivierung.
  Fremde Inserate, abgelaufene Auktionen und Auktionen mit Geboten werden
  abgewiesen. Ein ungültiger Teil der Auswahl verhindert die gesamte Änderung.
- CSV-Import und CSV-Export mit Dublettenerkennung und zeilenweisen Fehlern.

Änderungen veröffentlichter Geschäftsangaben setzen das Profil erneut auf
Prüfung. Seine bestehenden Banner werden dann bis zur erneuten Freigabe nicht
öffentlich ausgespielt. Der Betreiber muss berechtigte Leistungs-/Erstattungs-
ansprüche bei solchen Unterbrechungen gesondert prüfen.

## Begrenzte Kontingente

Je aktivem Land bestehen:

| Leistung | Höchstens gleichzeitig belegte Plätze | Laufzeit nach ausdrücklich angefordertem Start |
| --- | ---: | --- |
| Kategorie-Top | 3 je Kategorie | 7 Tage |
| Startseiten-Top | 2 je Land | 7 Tage |
| Händlerbanner | 2 je Kategorie | 1 Kalendermonat |

Ein Händler kann nicht mehrere gleichzeitig aktive Bannerplätze im selben
Land/Kategoriebereich belegen. Ein Kalendermonat wird am Monatsende begrenzt,
beispielsweise 31. Januar bis 28./29. Februar, nicht bis Anfang März.

`market_business_inventory` besitzt einen eindeutigen Schlüssel aus Land,
Leistung, Kategorie und Slotnummer. Reservierung, Start, Ablauf und Stornierung
sperren diese Inventarzeile. Die Belegung wird unter aktueller Datenbanksperre
erneut gelesen; ein vorher gezeigter Verfügbarkeitswert ist keine Garantie.

`user/promotions.php` erzeugt nur bei freiem Kontingent einen Zahlungsauftrag.
Die Reservierungsfrist ist zentral konfigurierbar, standardmäßig 24 Stunden,
zulässig 15 Minuten bis 48 Stunden. Während einer offenen Reservierung kann
dasselbe Konto im gleichen Bereich nicht erneut reservieren. Kontingente sind
wirklich begrenzt und keine dekorativen Verkaufshinweise.

Die Preise kommen aus der gemeinsamen Gebühren-Engine. Der bestehende manuelle
PayPal-Prozess speichert Betrag, Steueraufteilung, Empfänger, Gebührenversion,
Leistungsbedingungen und eindeutige Zahlungsreferenz. Die Finanzprüfung benötigt
`finance.manage`, MFA, POST und CSRF. Das Ändern von Preisen oder Verfügbarkeit
schreibt bereits angelegte Aufträge nicht um.

Nach rechtzeitiger tatsächlicher Zahlungsbestätigung erhält eine Platzierung den
Zustand `awaiting_start`. Sie startet erst auf ausdrücklichen Wunsch ihres
Besitzers, spätestens innerhalb von sieben Tagen. Die Bestellung allein erklärt
keinen vorzeitigen Leistungsbeginn und keinen pauschalen Widerrufsverzicht.

Eine verspätet bestätigte Zahlung erhält `paid_unfulfilled`. Es wird kein bereits
anderweitig belegter Platz nachträglich vergeben. Der Betreiber muss Erfüllung
oder Erstattung klären. Dieses Geld wird weder als unbezahlt verschwiegen noch
automatisch in eine Überbuchung umgewandelt.

`ops/business-worker.php` beendet aktive Laufzeiten und räumt abgelaufene
Reservierungen unabhängig von Seitenbesuchen auf. Die öffentlichen Abfragen
prüfen zusätzlich Zeit, Zahlungsstatus, aktiven Verkäufer, Kategorie und Land.
Ein ausgefallener Worker verlängert keine sichtbare Werbelaufzeit.

Finanzintegration: `finance_prepare_order` und `finance_issue_invoice` werden,
wenn verfügbar, innerhalb derselben Auftrags-/Freigabetransaktion aufgerufen.
`business_cancel_invoice` und `business_refund_invoice` benötigen eine bestehende
Finanztransaktion und beenden zugeordnete Platzierungsrechte; bei Bumps wird
unverbrauchtes Guthaben gesperrt. Bereits erfolgte Bump-Ereignisse werden nicht
unsichtbar gelöscht. Rechnungen, Gutschriften und tatsächliche Rückzahlungen
gehören zum gesonderten Finanzmodul, nicht zu einem fingierten Ad-Statuswechsel.

## Banner und lokale Statistik

Ein Banner benötigt ein veröffentlichtes Händlerprofil, genau ein neu codiertes
JPEG-/PNG-/WebP-Bild und eine HTTP-/HTTPS-Zieladresse ohne Zugangsdaten,
Sonderport, IP-Literal oder lokale Hostnamen. Der Server ruft die Zieladresse
nicht ab. Diese syntaktische Kontrolle ist keine DNS- oder Inhaltsgarantie;
Mitarbeiter müssen das tatsächliche Angebot und Ziel inhaltlich prüfen.

Die Bildprüfung übernimmt die vorhandenen Größen-/Pixel-/MIME-Kontrollen und
WebP-Neucodierung. Dateien liegen außerhalb des Webroots. Nicht freigegebene
Banner sind nur dem Besitzer und berechtigten Prüfern zugänglich. Die
öffentlichen Banner-/Klickendpunkte liefern ausschließlich freigegebene,
bezahlte, aktive Kampagnen im passenden Land aus. Deaktivierung wirkt sofort.

Die Ausspielung ist als **Gesponsert** zu kennzeichnen. Es gibt keine Zusicherung
bestimmter Klick-, Reichweiten- oder Verkaufszahlen. Zählungen beruhen auf
erfolgreich bereitgestellten Bannerabrufen bzw. zulässigen Klickweiterleitungen.
HEAD-Anfragen und interne Vorschauen zählen nicht. Wiederholungen derselben
Kampagne/Art/Stunde werden anhand eines zweckgebundenen HMAC aus der
Client-IP dedupliziert. Roh-IP und User-Agent werden nicht gespeichert;
der Hash ist dennoch als pseudonymes Ereignisdatum zu behandeln. Dedupezeilen
werden nach zwei Tagen entfernt, die länder-/kampagnenbezogenen Tagesaggregate
bleiben für den Bericht erhalten. Dies ist keine Messung eindeutiger Personen.

## CSV

Import: UTF-8, Komma als Trennzeichen, höchstens 100 Datensätze und 1 MiB. Die
gesamte Anzahl wird vor dem ersten Schreibvorgang geprüft. Die genaue Kopfzeile:

```csv
title,description,category_slug,type,price,buyout_price,duration_days,country_code,zip,city,condition_label,pickup,shipping
```

Preise sind Euro-Dezimaltexte ohne Tausendertrennzeichen. Land und Postleitzahl
müssen zusammenpassen; Typ ist `fixed` oder `auction`, Zustand
`new`/`like_new`/`used`, Checkboxwerte `0`/`1`. Kategorien werden über ihre Kennung
zugeordnet. Jeder gültige Datensatz läuft durch dieselbe serverseitige
Inseratsprüfung wie das Webformular; Produktverbote, Besitzer und Auktionsregeln
bleiben wirksam. Ein gemeinsam gesperrter Benutzerimport verhindert parallele
Dubletten. Bereits vorhandene gleichartige Artikel werden übersprungen, nicht
überschrieben. Gültige Zeilen können trotz Fehlern anderer Zeilen angelegt werden;
Ergebnisse nennen angelegte Einträge, Dubletten und konkrete CSV-Zeilennummern.

Export: eigene Daten in 1000er-Blöcken mit Start-ID. Zusätzliche ID-/Statusspalten
dienen dem Bericht; vor erneutem Import muss die Datei zur Importvorlage
umgearbeitet werden. Potenziell ausführbare Tabellenformeln werden mit einem
führenden Apostroph entschärft. Der CSV-Export ist kein Ersatz für den vollständigen
Datenschutzexport des Kontos.

## Freigaben und Prüfstand

`business_promotions_enabled` ist standardmäßig aus. Die manuelle Zahlungs-
verfügbarkeit, Kontoeignung und Steuer-/Bestellprüfung bleiben zusätzliche
Voraussetzungen. Grenzüberschreitende Anbieter-/Steuer-/Verbraucherpflichten
werden nicht durch eine Länderkennung oder einen globalen Steuersatz erledigt.
Warenkaufpreise werden nicht vom Plattformkonto verwahrt.

Die gesonderten Tests `business_test.php` und `business_http_test.py` prüfen
Kapazität, echte parallele Reservierungen, Ländertrennung, verspätete Zahlungen,
Start/Ablauf, Moderation, tatsächlichen Bildupload, lokale Klickzählung und
CSV-/Besitzregeln. Testzahlen und tatsächlich ausgeführte Ergebnisse gehören
in den zentralen Testbericht; das Vorhandensein dieser Dateien allein ist kein
erfolgreicher Testnachweis oder eine Produktionsfreigabe.

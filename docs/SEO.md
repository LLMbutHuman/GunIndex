# Öffentliche Indexierungsfreigabe und SEO

`seo_indexing_enabled` ist standardmäßig aus. Erst nach der tatsächlichen
Freigabe der öffentlichen Inhalte aktiviert ein berechtigter MFA-Administrator
diese Einstellung. Ein gültiger, fest konfigurierter HTTPS-Ursprung in `APP_URL`
ist erforderlich; Request-Host, Weiterleitungsheader und Formularwerte bestimmen
keine Canonical-, Sitemap- oder strukturierten URLs. Wartung schließt die Freigabe.

## Verhalten

- Ohne Freigabe bleiben Seiten `noindex, nofollow`; robots.txt sperrt Crawling,
  die Sitemap liefert 404. Das ist kein Zugriffsschutz für vertrauliche Inhalte.
- Nach Freigabe werden ausschließlich Startseite und aktuell öffentlich
  verfügbare Angebote für Indexierung freigegeben. Konto-, Finanz-, Admin-, Such-
  und sonstige Seiten behalten `noindex, nofollow`.
- Entwürfe, pausierte/verkaufte/beendete Angebote und bereits zeitlich abgelaufene
  Auktionen erhalten keine Freigabe. Länder, Besitzer und der gesamte
  Kategoriepfad müssen aktiv sein. Die vorhandene zusätzliche Produktwortprüfung
  gilt auch für die Indexierung; sie ersetzt keine vollständige Moderation.
- Canonicals entfernen Herkunfts-PLZ und andere Such-/Standortparameter. Die
  drei bestehenden Startseitenpfade verweisen auf `/`. Fehlerantworten und
  andere HTTP-Methoden werden nicht zur Indexierung freigegeben.
- Product-JSON-LD enthält öffentliche Namen/Beschreibungen, tatsächliche Bilder,
  Zustand und bei Festpreisangeboten den centgenauen EUR-Angebotspreis. Es
  enthält keine privaten Gebotsgrenzen, Kontaktadressen oder erfundene Bewertungen.
  Auktionen erhalten keinen festen Offer-Preis aus dem aktuellen Gebot.

## Sitemap

`/sitemap.xml` liefert den Index, `/sitemap.xml?page=1` die erste Teil-Sitemap.
Je Teil werden höchstens 1.000 aktive Angebote gelesen; nur die erste enthält
zusätzlich die Startseite. Der Index ist auf 50.000 Teile begrenzt. Ein übergroßer
Bestand führt zu einem Fehler statt stiller Abschneidung. XML wird escaped,
Änderungszeiten kommen aus `updated_at`, keine täglichen Fantasieänderungen.
Es gibt keinen öffentlichen Cache, der deaktivierte Länder/Angebote weiter ausgibt.
Bestandsänderungen während eines Crawls können die Seitenaufteilung verschieben;
die nächste Abfrage verwendet den aktuellen Bestand.

Diese Grenzen liegen unter den [Sitemap-Protokollgrenzen](https://www.sitemaps.org/protocol.html).
Strukturierte Daten versprechen keine besondere Suchdarstellung; dafür gelten
die [Google-Anforderungen für Produktdaten](https://developers.google.com/search/docs/appearance/structured-data/product).

## Prüfungen und verbleibende Nachweise

`php tests/seo_test.php` enthält elf datenbankfreie Fälle zu Freigaben,
Canonicals, Host-/JSON-Injection, privatem Inhalt, Preisen, Zeitablauf und
Sitemapgrenzen. Ein vorhandener Test ist kein Ausführungsnachweis.

`php tests/seo_test.php --database` verlangt ausdrücklich
`gunindex_rebuild_test`. Eine zurückgerollte Transaktion erstellt 1.001
Festpreisangebote und Auktionen, prüft Seitenübergänge, Entwürfe, Zeitablauf,
Besitzersperre, Ländersperre und inaktive Kategorievorfahren. Diesen Lauf
nacheinander mit den anderen DB-Suites und pausierten Workern ausführen.

Vor Produktionsfreigabe tatsächliche HTTP-/MySQL-Tests ergänzen/ausführen:
aktive/inaktive Besitzer und Länder, deaktivierte Vorfahren, Moderation,
1.001 Angebote über mehrere Seiten, fehlerhafte Arrayparameter, GET/HEAD/POST,
abgelaufene Auktion vor Workerabschluss und unmittelbarer Entzug der Freigabe.
Danach XML-/Rich-Results-Prüfung auf dem echten HTTPS-Ziel durchführen.
Übersetzte URLs/hreflang sind noch nicht implementiert; die bisherigen Sprachen
werden durch Sitzungsauswahl und nicht durch eigene crawlbare URLs gewählt.

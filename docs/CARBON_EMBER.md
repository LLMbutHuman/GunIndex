# GunIndex 2.0 mit Carbon Ember

## Oberfläche

Der bestehende Hero mit animierter Grafik, Manifesto-Strip, Produktkarten und Hell/Dunkel-Umschaltung bleibt erhalten. Der Dunkelmodus verwendet `#101316` für den Hintergrund, `#22282E` für Karten, `#FF9955` für Hauptaktionen, `#8FAEBB` als Sekundärfarbe, `#F5F5F3` für Text, `#251406` für Text auf Orange und `#FFAD74` für Akzenttext. Die Farbvariablen liegen in `assets/css/app.css`. Gespeicherte historische Farbwerte bleiben in der Datenbank erhalten. Die Oberfläche verwendet die feste Palette.

Die Suche zeigt Kategorie, Preis, Land, PLZ und Anbieterart direkt an. Angebotstyp, Sortierung, Umkreis, internationales Suchgebiet und Kategorieattribute stehen unter „Weitere Filter“. Aktive Filter lassen sich gruppenweise oder gemeinsam zurücksetzen. Die Suche und ihre Vorschläge beziehen freigegebene Unterkategorien mit ein. Der Rechtsstatusfilter befindet sich im Fachbereich und bezieht sich auf das gewählte Land.

Auf Produktseiten stehen Titel, Bild, Preis, Zustand, Standort und Anbieterart vor den ergänzenden Angaben. Die Händlerkennzeichnung folgt dem veröffentlichten Händlerprofil. „Angebot melden“ nutzt die vorhandene Anmeldung und Moderation. Impressum, Datenschutzübersicht und Plattformregeln verwenden vorhandene Betreiberangaben und bleiben zur rechtlichen Prüfung gekennzeichnet. `locales/carbon_ember/` enthält die neue Oberfläche in den acht bestehenden Sprachen.

## Kategorien und Fachinformationen

`sql/migrations/043_carbon_ember.sql` ergänzt drei Hauptkategorien und 27 Unterkategorien für folgende Bereiche. Vorhandene Kategorien, IDs und Inserate bleiben erhalten.

- **Jagd und Revier** mit Bekleidung, Revierausstattung, Taschen, Rucksäcken, Wildbeobachtung und Literatur.
- **Schießsport** mit Schießstandausrüstung, Zielscheiben, Trainingszubehör, Gehörschutz, Schutzbrillen und Sportbekleidung.
- **Optik** mit Ferngläsern, Spektiven, Entfernungsmessgeräten, Beobachtungszubehör und Optikpflege.
- **Outdoor** mit Rucksäcken, Campingausrüstung, Bekleidung, Beleuchtung und Sicherheitsausrüstung.
- **Pflege und Aufbewahrung** mit Reinigungszubehör, Pflegeprodukten, Sicherheitsbehältnissen, Transporttaschen und Aufbewahrungszubehör.

Der redaktionelle Fachbereich unter `/de/fachwissen` behandelt Langwaffen, Kurzwaffen, Druckluft- und CO₂-Waffen, historische Waffen, Munition, wesentliche Waffenteile und waffenrechtliche Grundlagen. Die Fachthemen haben keine Marktplatz-Kategorie-IDs. Sie führen zu keiner Inserats-, Kauf-, Vermittlungs- oder Zahlungsfunktion.

Die Migration legt eine leere Tabelle für Einordnungen je Fachthema und Land an. Berechtigte Mitarbeiter mit aktueller MFA können unter „Rechtsstatus im Fachbereich“ die genaue Rechtsgrundlage sowie den konkreten Geltungsbereich erfassen, die Prüfung bestätigen und eine Einordnung zurückziehen. Die Änderungen werden protokolliert. Ungeprüfte, zurückgezogene und seit einem Jahr nicht aktualisierte Einordnungen erscheinen als „Rechtliche Prüfung erforderlich“. Die Links zu staatlichen Rechtsportalen werden erst beim Anklicken aufgerufen. Vor der Veröffentlichung einer Einordnung sind Rechtsgrundlage, Land, konkreter Fall und aktuelle Änderungsfassungen zu prüfen. [Gesetze im Internet](https://www.gesetze-im-internet.de/waffg_2002/) · [RIS](https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10006016) · [Fedlex](https://www.fedlex.admin.ch/eli/cc/1998/2535_2535_2535/de)

## Bestehende Funktionen und Freigaben

- Nachrichten, Ungelesen-Zähler, Profilaktionen und Adminzugang bleiben an die vorhandenen Anmelde- und Berechtigungsprüfungen gebunden.
- Händler- und Bannerverwaltung, responsive Platzierungen, Adminfreigaben, Rechte-Matrix, Einrichtung, Kategorieverwaltung, Bewertungen und lokale Speicherung stammen aus dem bestehenden Projekt.
- Fachseiten ergänzen die vorhandenen sprachspezifischen Canonicals, `hreflang`-Angaben und die Sitemap. Filtervarianten bleiben auf `noindex`. Die Indexierungsfreigabe der konfigurierten HTTPS-Produktionsdomain gilt weiterhin. [Google Search Central](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- Betreiberangaben vervollständigen und Rechtstexte fachlich prüfen. Die bearbeitete Version muss für manuelle Bedienungstests mit NVDA oder VoiceOver und mit technisch wenig erfahrenen Testpersonen bereitgestellt werden. Automatisierte Tastatur- und Kontrastprüfungen sind Teil der Entwicklung. [WCAG 2.2](https://www.w3.org/TR/WCAG22/)

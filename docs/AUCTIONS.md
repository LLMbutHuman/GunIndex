# Allgemeine Inserate und Auktionen

Die neue Handelslogik verwendet ausschließlich `market_listings`,
`market_proxy_bids`, `market_bids` und `market_images`. Historische Inserate und
Gebote werden nicht importiert, gelöscht oder umetikettiert. Die aktive Kategorie
und sämtliche Vorfahren werden serverseitig geprüft. Die einfache Textsperre
ersetzt keine fachliche Moderation oder rechtliche Produktklassifizierung.

## Inserate

Private Festpreise und Auktionen können als Entwurf gespeichert, veröffentlicht,
vor Geboten bearbeitet, pausiert und archiviert werden. Die Archivierung löscht
keine Datensätze. Allgemeine Festpreisangebote können als verkauft markiert
werden. Auktionen werden durch den Worker oder vor dem ersten Gebot durch einen
Sofortkauf abgeschlossen. Veröffentlichen einer pausierten Auktion erfolgt über
die Bearbeitung mit einer ausdrücklich gewählten neuen Dauer. Änderungen nach
Geboten oder nach Ablauf sind gesperrt. Entwürfe und pausierte/archivierte
Angebote erscheinen nicht öffentlich. Eine Bearbeitung laufender Auktionen
verlängert deren Endzeit nicht.

Preisberechnungen erfolgen in Integer-Cent. Auf diese Entwicklungsabläufe werden
noch keine Gebühren gebucht und es erfolgt kein Einzug des Warenkaufpreises.
Die ausdrückliche Produktregelbestätigung wird in derselben Transaktion als
strukturierter Audit-Eintrag mit Version `general-1.0` gespeichert. Das ist noch
kein vollständiges Rechtsdokumenten-Redaktions- und Freigabesystem.

## Gebote

Ein Gebot sperrt das Angebot mit `SELECT … FOR UPDATE`, prüft Verkäufer,
Kategorie, Zustand und UTC-Ende erneut und schreibt privaten Höchstbetrag,
öffentlichen Preis sowie Ereignis in derselben Transaktion. Die öffentliche
Historie enthält keine privaten Höchstbeträge. Maximalgebote können nur erhöht
werden. Bei gleichen Höchstbeträgen führt die frühere Proxy-Zeile anhand ihrer
unveränderlichen ID. Eine spätere Erhöhung ändert diese Priorität nicht.

Ohne Konkurrenz startet der öffentliche Preis beim Startpreis. Mit Konkurrenz
beträgt er höchstens das führende Maximum und ansonsten das zweitbeste Maximum
plus 1,00 €. Der veröffentlichte Preis sinkt nicht. Ein neues konkurrierendes
Gebot muss den aktuellen Preis um mindestens 1,00 € übersteigen. Eigene Angebote
und bereits abgelaufene Auktionen dürfen nicht beboten werden. Ein gültiges
Gebot verlängert das Ende auf mindestens 120 Sekunden nach seinem Eingang.

## Unabhängiger Abschluss und Konflikte

```sh
php ops/auction-worker.php --once
```

Der Befehl verarbeitet standardmäßig bis zu 100 Angebote und darf regelmäßig
parallel durch mehrere Worker laufen. Zeilensperren, Statusprüfung und eindeutige
Ereignisschlüssel verhindern doppelte Abschlüsse. Seitenaufrufe schließen keine
Auktionen ab. Eine erfolgreiche Ausführung liefert Exitcode 0, ein operativer
Fehler 1. Kategorienkonflikte oder widersprüchliche verbleibende Höchstgebote
verschieben nur das betroffene Angebot nach `review`; alle Gebote bleiben
erhalten. Andere fällige Angebote werden weiterverarbeitet. Exitcode 2 meldet
die Anzahl der zu prüfenden Auktionen, ohne private Maximalbeträge auszugeben.

Die manuelle Konfliktauflösung sowie vollständige Gebühren-/Benachrichtigungs-
und Streitfallprozesse sind noch separat zu implementieren. Ein abgeschlossener
Warenablauf ist keine bestätigte Plattform- oder Warenzahlung.

## Testnachweis

Die zentrale Testdokumentation führt nur tatsächlich ausgeführte Prüfungen auf.
Die Syntax oder die vorliegende Beschreibung allein beweist weder korrekte
Parallelverarbeitung noch Produktionsreife.

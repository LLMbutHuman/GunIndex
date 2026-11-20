# Händler-Abonnement

Inhaber eines veröffentlichten Händlerprofils können 30 Tage CSV-Export
für ihren Betrieb buchen. Mitglieder mit Bestandsleserecht können während
der Laufzeit exportieren. Die Verlängerung ist eine neue ausdrückliche
Bestellung. GunIndex führt keine automatische Abbuchung aus.

Für neue Bestellungen müssen `DEALER_SUBSCRIPTION_ENABLED` und
`DEALER_SUBSCRIPTION_APPROVED` auf `1` stehen. Der geprüfte Bruttopreis
in EUR-Cent steht in `DEALER_SUBSCRIPTION_CENTS`. Der Standard enthält
keinen Preis. Voraussetzung sind die aktive Rechnungsfunktion, freigegebene
Rechtsdokumente, die Steuerregel für die Rechnungsadresse und ein verfügbarer
für Live-Betrieb freigegebener Zahlungsweg. Die Betreiberfreigaben und
Providerkonten werden unabhängig geprüft. Testkanäle wie PayPal Sandbox
oder Bitcoin Regtest schalten diese Laufzeit nicht frei.

Ein Bestellauftrag bindet Preis, Laufzeit und akzeptierte Dokumentfassungen.
Der bestehende Checkout bietet die für den Auftrag freigegebenen Zahlungswege.
Nach bestätigtem Eingang stellt der gemeinsame Rechnungsprozess das Dokument
aus und beginnt die Laufzeit. Bereits bezahlte Restlaufzeit wird bei der
Verlängerung berücksichtigt. Gestoppte Neukäufe lassen bereits bezahlte
Laufzeiten gültig. Eine verifizierte vollständige Erstattung entzieht die
jeweilige Laufzeit. Teilweise Erstattungen kürzen die Dauer nicht. Ein
Bitcoin-Klärfall sperrt die betroffene Laufzeit bis zur Betreiberklärung.

Der private Export enthält Artikelkennung, SKU, Name, Menge und Änderungszeit.
Er liefert höchstens 1.000 Zeilen je Aufruf. Bei weiteren Zeilen enthält
`Link` den nächsten Downloadpfad. Der Export übernimmt den vorhandenen
Schutz für Textwerte, die Tabellenkalkulationen sonst als Formeln auffassen.

# Auktionsgebühren

## Umfang und Aktivierung

Migration `012_auction_billing.sql` ergänzt neue Tabellen und eine Spalte.
Bestehende Auktionen werden nicht rückwirkend berechnet. Neue Auktionen, die
bei ausgeschalteter Abrechnung veröffentlicht werden, bleiben für diesen
Durchlauf gebührenfrei. Ein neuer Entwurf kann beim ersten Veröffentlichen
einen Gebührenvertrag erhalten. Wiedereinstellung muss einen neuen Durchlauf
mit neuer Inserats-ID erzeugen. Der integrierte neue Ablauf samt einmaligem Rabatt
ist in [RELISTING.md](RELISTING.md) beschrieben; der aktuelle Prüfnachweis steht im
Fortschrittsprotokoll.

`auction_fees_enabled` und `auction_fees_approved` sind standardmäßig aus.
Zusätzlich erforderlich: freigegebene manuelle geschäftliche PayPal-Zahlung,
vollständige Betreiber- und Verkäufer-Rechnungsdaten, Rechnungsausstellung,
geprüfte Steuerregel für das Rechnungsland und konfigurierte, ausdrücklich
freigegebene Checkout-Rechtsversionen in der gewählten Sprache. Technische
Schalter ersetzen keine Anbieter-, Steuer- oder Rechtsprüfung.

## Vertrag und Forderungen

Der Verkäufer sieht Einstellpreis und gestaffelte Erfolgsprovision vor dem
Veröffentlichen. Er bestätigt Gebühren, sofortigen Leistungsbeginn und die
genauen Rechtsversionen ohne vorausgewählte Häkchen. Die kostenpflichtige
Veröffentlichung hat einen eigenen Button. Der Server verlangt dessen Aktion,
den unveränderten Angebots-Hash und alle Zustimmungen. Änderungen an Preisen,
Empfänger, Rechnungsdaten, Sprache oder Rechtsversionen verlangen erneute Prüfung.
Festpreis und Entwurf benötigen diesen Gebührenvertrag nicht.

Der verschlüsselte Vertrag enthält Tarif, Empfänger, Steuer, Anschriften,
Sprache und Rechtsversionsnachweise. Seine Integrität wird vor Verwendung geprüft.
Veröffentlichung, Vertrag, Zustimmung und Einstellforderung erfolgen gemeinsam
in einer Datenbanktransaktion. Zuschlag bzw. Sofortkauf und Erfolgsforderung
werden ebenfalls gemeinsam gespeichert. Kein Zuschlag bedeutet keine
Erfolgsforderung. Berechnung ausschließlich in Cent; Erfolgsstaffel 3/2/1/0,5 %,
Grenzen 100/500/2.000 EUR, maximal 29,90 EUR, Rundung erst nach Gewichtung.

Ein eindeutiger Schlüssel aus Durchlauf und Gebührenart verhindert doppelte
Forderungen. Nullgebühren erhalten einen Ereignisnachweis ohne Zahlungsauftrag.
Bearbeitung verändert einen akzeptierten Tarif nicht; Pause und Fortsetzung
setzen die gebuchte Laufzeit nicht zurück. Abgelaufene Durchläufe lassen sich
nicht als neue Auktionen weiterverwenden.

## Zahlung, Rechnung und Korrektur

Jede positive Forderung besitzt eine eindeutige Zahlungsreferenz, einen
unveränderlichen Rechnungssnapshot und einen Eintrag `fee_due`. Dadurch wird
kein Zahlungseingang behauptet. Zahlung erfolgt zunächst über den vorhandenen
manuellen PayPal-Weg: berechtigter MFA-Mitarbeiter prüft tatsächliche Transaktion,
Referenz und exakten Betrag. Wiederholte Bestätigung erzeugt keine zweite
Rechnung, kein zweites `cash_in` und kein Bump-/Premiumguthaben. Rechnungs-PDFs
entstehen im bestehenden Modell erst nach bestätigter Zahlung.

Positive neue Forderungen und vollständige Erlasse erzeugen transaktionale,
deduplizierte Hinweise in der gespeicherten Empfängersprache mit direktem Link
zum eigenen Zahlungsauftrag. Die vorhandenen In-App-/Mailpräferenzen gelten;
Mail wird nur für bestätigte Adressen und als verschlüsselte Queue-Nachricht
angelegt. Nullgebühren erzeugen keine Zahlungsaufforderung. Erzeugung eines
Hinweises bedeutet weder Zahlungseingang noch bewiesene E-Mail-Zustellung.

Verkäufer können entstandene Gebühren nicht selbst als unbenutzte Bestellung
stornieren. `finance.manage` kann eine geprüfte unbezahlte Forderung vollständig
erlassen; Begründung, Gegenbuchung und Audit bleiben erhalten. Behauptete, noch
nicht abschließend geprüfte Zahlungen sperren diesen Erlass. Bereits bezahlte
Gebühren verwenden die bestehende Verwaltung tatsächlich extern erfolgter
Teil-/Vollerstattungen mit neuen Korrekturbelegen. Diese Bedienung versendet kein Geld.

## Grenzen und Nachweise

Die vorhandene automatische PayPal-API nimmt diese manuellen Gebührenaufträge
absichtlich nicht an. Automatische Gebühreneinziehung, automatische
Providererstattung, Teilkorrektur einer unbezahlten Forderung und Verlängerungen
sind weitere Anforderungen. Wiedereinstellung erfolgloser Auktionen ist als
eigener Durchlauf integriert. Keine Kundengeldverwahrung
und kein Einzug des Warenkaufpreises. Finanz-/Bestandsseiten sind noch nicht
durchgängig übersetzt; das neue Gebührenmodul umfasst alle acht Sprachen.

Tests: `tests/auction_billing_test.php` prüft reine Tarif-/Zustimmungslogik;
`tests/auction_billing_integration_test.php` prüft echte MySQL-/HTTP-Abläufe nur
gegen `gunindex_rebuild_test`. Beide laufen geordnet in `tests/environment.py`.
Aktuelle tatsächliche Ergebnisse und der Wiederaufnahmepunkt stehen in
`IMPLEMENTATION_PROGRESS.md`; die Existenz eines Tests ist kein Erfolgsnachweis.

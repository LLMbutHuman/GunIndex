# Treuhandablauf im isolierten Testsystem

Der bestehende Entwicklungsstack aktiviert die Simulation ausschließlich im
Schema `gunindex_rebuild_test`, mit `APP_ENV=development` und
`ESCROW_SIMULATION=1`. Der Code prüft zusätzlich das tatsächlich verbundene
Schema. In der Standardkonfiguration steht die Funktion auf `0`. Ein
Produktivkonto kann über den Assistenten keinen Geldfluss aktivieren.

1. Ein Verkäufer eröffnet im bestehenden Chat zu einem aktiven Versandangebot
   ein befristetes Preisangebot. Die andere Partei kann annehmen, ablehnen oder
   einen Gegenpreis anbieten. Der Betrag und die Versandkosten bleiben bei der
   Annahme gebunden. GunIndex reserviert den Artikel in derselben Transaktion.
2. Käufer und Verkäufer erfassen je einen eigenen Testkontakt unter
   `example.invalid`. Kontakte liegen verschlüsselt vor und werden jeweils nur
   der betreffenden Partei angezeigt. Transaktions- und Zahlungsreferenz werden
   serverseitig erzeugt. Der Ablauf enthält keine Zahlungsanweisung.
3. Berechtigtes Finanzpersonal kann im Testsystem einen definierenden Beleg der
   Simulation erzeugen. Der Beleg bindet Referenz, EUR-Centbetrag, Aktion und
   Ereignis-ID. Erst der simulierte Zahlungseingang erlaubt den Versand. Die
   Oberfläche kennzeichnet sämtliche Anbieterereignisse als Simulation.
4. Der Verkäufer dokumentiert eine synthetische Sendung, der Käufer bestätigt
   den Empfang. Beide können einen Streitfall eröffnen. Finanzpersonal mit MFA,
   das selbst keine Partei ist, kann eine simulierte Freigabe oder Erstattung
   anstoßen. Erst ein zugehöriger simulierter Abschluss ändert den Endstatus.
   Fehlgeschlagene Auszahlungen oder Erstattungen gehen zurück in die Prüfung.
5. Der Worker verarbeitet Fristen. Ausgelaufene Angebote und offene Zahlungen
   enden ohne Zahlung, abgelaufene Versand- oder Empfangsfristen gehen zur
   Streitprüfung. Eine simulierte Rückbuchung nach Freigabe bleibt dokumentiert.

Zustände, Rollen, Versionen und eindeutige Anfragen schützen gegen doppelte oder
überholte Aktionen. Ein Artikel kann nur einmal reserviert werden. Bei einer
Erstattung oder einem Ablauf ohne Geldeingang endet seine Reservierung.
Die Ereignisse sind vom Finanzbuch für Betreiberleistungen getrennt. Der
Testablauf umfasst eine Sendung je Artikel und eine vollständige Freigabe oder
Erstattung. Teilsendungen und Teilbeträge gehören bislang zu keinem
freigegebenen Geschäftsmodell.

Für echten Betrieb fehlen ein geeigneter Treuhandprovider und sein eigener
geprüfter Adapter für sichere Eingangsbestätigung, Verwahrung, Auszahlungen,
Erstattungen und Rückbelastungen. Hinzu kommen fachlich freigegebene
Treuhandbedingungen, Kundenzustimmungen, Angaben zum Zahlungsempfänger,
Streit- und Fristenregeln sowie Abgleich und Betriebsabnahme. Gegenüber dem
Testablauf benötigen echte Fälle den Nachweis des Providers einschließlich
authentifizierter Ereignisse, idempotenter Anbieteraufrufe und Buchungsabgleich.

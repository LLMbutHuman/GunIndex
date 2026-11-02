# Rechnungen, Korrekturen und Erstattungen

## Automatisch verifizierte Zahlungen

Capture und erfolgreich verifizierter Webhook verwenden dieselbe Transaktion
für Zahlungsstatus, Providerereignis und `finance_issue_invoice()`. Bei aktivierter
Rechnungsfunktion werden Rechnung und `cash_in` genau einmal angelegt, auch wenn
beide Eingangskanäle mehrfach denselben Capture bestätigen. Sandbox-Versuche
erzeugen weiterhin weder eine echte Rechnung noch eine Cash-Buchung.

Vor einem neuen automatischen Live-Auftrag beziehungsweise Capture muss bei
aktiver Rechnungsfunktion ein unveränderlicher, integritätsgeprüfter Snapshot
vorliegen. Konto, Referenz, Leistung, Tarifversion, Währung und centgenaue Summen
müssen übereinstimmen. Es wird kein fehlender historischer Snapshot aus heutigen
Angaben erfunden. Bereits extern eingezogene, lokal wegen beschädigter Unterlagen
nicht buchbare Zahlungen benötigen Wiederherstellung/Abgleich; ein lokaler Fehler
macht eine externe Zahlung nicht rückgängig. Alte Aufträge ohne Snapshot bleiben
bis zu einer gesonderten, nachvollziehbaren Bestandsbehandlung gesperrt.

Die zusätzlichen Capture-/Webhook-Tests verwenden explizit injizierte synthetische
REST-Antworten im isolierten Testschema. Es wird keine Verbindung zu PayPal geöffnet.
Produktive HTTP-Endpunkte erlauben keine Auswahl eines Testadapters. Diese Tests
ersetzen weder reale Sandbox-/Providerabnahme noch Live-Freigaben. Die Änderung
aktiviert keine automatische Bestellung oder Erfüllung weiterer Leistungsarten;
manuelle Werbeaufträge behalten ihren getrennten, bestehenden Freigabeablauf.

Die Rechnungsfunktion wird erst mit `finance_enabled=1` aktiviert. Betreiberangaben
werden in der Administration gepflegt; ohne Steuer- oder Umsatzsteuerkennung wird
keine Rechnungsausstellung freigegeben. Für jedes bediente Rechnungsland muss eine
berechtigte Person einen fachlich geprüften Steuersatz und dessen Behandlung
dokumentieren. Die vom Kunden eingegebene Umsatzsteuer-ID bewirkt keine automatische
Steuerbefreiung. Steuer- und Rechnungspflichten sind vor dem Livebetrieb zu prüfen.

## Verbindliche Aufträge und Belege

- Bei der Buchung werden Betreiberanschrift, private Rechnungsanschrift,
  Leistungsbeschreibung, Gebührenversion, Steuerbehandlung und Geldbeträge in
  Integer-Cent eingefroren. Der Auftragssnapshot wird mit der AEAD-Bibliothek
  libsodium verschlüsselt; ein Hash prüft den entschlüsselten Inhalt.
- Spätere Preis-, Steuer- oder Adressänderungen ändern diesen Auftrag nicht.
  Die akzeptierten Leistungsbedingungen und ihr Hash liegen zusätzlich im
  unveränderlichen manuellen Zahlungsauftrag.
- Erst nach tatsächlich geprüftem Zahlungseingang entsteht einmalig eine Rechnung.
  Sandboxversuche und reine Browserrückleitungen gelten nicht als bezahlte Leistung.
  Die Nummern werden über eine gesperrte Jahressequenz eindeutig vergeben.
- PDFs erzeugt die lokal installierte, per Composer-Lock fixierte Dompdf-Bibliothek.
  Externe Ressourcen sowie PHP und JavaScript in PDFs sind deaktiviert. Persönliche
  Eingaben werden vor dem Einfügen in das PDF-HTML escaped.
- Der Kontoinhaber kann seine Dokumente herunterladen. Berechtigtes Finanzpersonal
  benötigt die aktuelle MFA-Anmeldung; administrative Einsicht wird protokolliert.
- Entwicklungsdatenbanken erzeugen deutlich markierte Testdokumente. Die Anwendung
  erfindet keine Betreiberidentität oder Steuerkennung.

## Storno und Erstattung

Unbezahlte Aufträge können vom Eigentümer storniert werden. Ein stornierter Auftrag
wird danach nicht durch eine verspätete manuelle Freigabe bezahlt geschaltet.
Eine bezahlte Rechnung bleibt erhalten; eine Korrektur erfolgt über verknüpfte
Gutschriften und Buchungen.

Eine Kundenanfrage löst keine Überweisung aus und markiert keine Erstattung als
abgeschlossen. Im aktuellen manuellen Verfahren führt der Betreiber die tatsächliche
Rückzahlung im Zahlungsanbieter-Konto aus. Finanzpersonal dokumentiert danach den
geprüften Betrag, die echte Erstattungskennung und die Begründung. Diese Aktion
versendet selbst kein Geld. Jede Kennung darf nur einmal zugeordnet werden;
ursprüngliche Zahlung und Erstattungsnachweis müssen verschieden sein.

Teilerstattungen und vollständige Erstattungen sind möglich. Die Rechnung wird
gesperrt, bevor die noch erstattbare Summe geprüft wird. Übererstattungen werden
verweigert. Steuerkorrekturen werden kumuliert berechnet, sodass auch mehrere kleine
Teilerstattungen bei einer vollständigen Rückzahlung exakt die ursprünglichen
Netto- und Steuerbeträge ergeben. Restliche Bumps und Platzierungen werden gesperrt;
bereits verwendete Leistungen und ihre Historie werden nicht rückwirkend entfernt.

## Grenzen

- Die Freigabe im Dashboard ersetzt keine fachliche steuerliche Prüfung.
- Der aktuelle vollständige Ablauf verwendet die manuell geprüften PayPal-Aufträge.
  Automatisierte Live-Erstattungen und die Zahlungsanbieter-Freigabe sind eigenständige
  Voraussetzungen und werden durch das Finanzdashboard nicht vorgetäuscht.
- Alte bezahlte Aufträge ohne eingefrorenen Rechnungssnapshot werden nicht automatisch
  mit heutigen Betreiber- und Steuerangaben zu angeblich historischen Rechnungen.
- Die Aufbewahrung unveränderlicher Finanzbelege muss mit der Datenschutz- und
  Löschorganisation abgestimmt werden. Eine Kontolöschanfrage löscht keine
  aufbewahrungspflichtigen Belege stillschweigend.

## Prüfungen

Die isolierte Suite `php tests/finance_test.php` setzt die migrierte synthetische
Datenbank `gunindex_rebuild_test`, die lokale HTTP-Anwendung und den gestoppten Worker
voraus. Sie prüft Snapshots, Preis-/Steueränderungen, PDF-Erzeugung, Eigentümerprüfung,
CSRF, einmalige Ausstellung, Storno, tatsächliche manuelle Erstattungsdokumentation
und kumulierte Steuerkorrekturen. Laufzeitkonfiguration und Länder-/Steuerregeln
werden anschließend wiederhergestellt. Testdaten bleiben als eindeutig synthetische
Regressionsdaten in der Testdatenbank; Produktivdaten werden nicht importiert.

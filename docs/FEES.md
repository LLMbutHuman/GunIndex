# Gebührenmodell

`config/fees.php` berechnet ausschließlich Integer-Cent. Feste Preise werden aus
validierten administrativen Einstellungen gelesen; die berechnete Version
enthält einen Hash des betroffenen Preises. Bereits ausgestellte Rechnungen
behalten ihren Betrag und ihre gespeicherte Gebührenversion.

| Vorgesehene Leistung | Bruttobetrag |
| --- | ---: |
| Privates Festpreisinserat / Gesuch / Suchalarm | 0,00 € |
| Auktion einstellen | 0,50 € |
| Einzelner Bump | 1,99 € |
| Drei Bumps | 4,99 € |
| Kategorie-Top, sieben Tage | 9,90 € |
| Startseiten-Top, sieben Tage | 19,90 € |
| Händlerbanner, ein Monat | 129,00 € |

Die Auktionsprovision wird progressiv mit 3 % auf die ersten 100 €, 2 % auf den
Anteil bis 500 €, 1 % auf den Anteil bis 2.000 € und 0,5 % darüber berechnet.
Erst die gewichtete Gesamtsumme wird kaufmännisch auf Cent gerundet, dann auf
29,90 € begrenzt. Bei 1.000 € Verkaufspreis ergeben sich 16,00 € Provision;
zusammen mit der Einstellgebühr sind es 16,50 € im Standardpreismodell.

Ein administrativ geänderter Festpreis gilt nur für neue Kalkulationen. Die
progressiven Sätze sind derzeit versionierter Programmstand, keine vollständige
administrative Staffelverwaltung. Der Rechner erzeugt keine automatische
Gebührenforderung und schaltet keine kostenpflichtige Funktion frei.

Die steuerliche Einordnung muss der Betreiber vor kostenpflichtigem Betrieb
festlegen. Die Steueraufteilung benötigt einen ausdrücklich geprüften Steuersatz
in Basispunkten; eine Kleinunternehmerregelung oder ein Steuersatz wird nicht
erfunden. Netto plus Steuer ergibt exakt den Bruttobetrag.

Treuhand ist eine ausschließlich deaktivierte Vorschau mit 2,9/1,9/1,2/0,6 % in
denselben Stufen, mindestens 2,49 € und höchstens 39,90 €. Es wird kein Kundengeld
verwahrt. Erstattungs-/Stornoregeln, kostenlose Wiedereinstellung und die sichere
Verknüpfung mit vollständigen Geschäftsprozessen sind vor Aktivierung zu ergänzen.

Einzel- und Dreier-Bumps besitzen inzwischen einen gesondert freizugebenden manuellen Zahlungs-/Guthabenprozess. Seine Aufträge speichern Preisversion und Steueraufteilung unveränderlich; andere kostenpflichtige Leistungen werden dadurch nicht freigeschaltet. Siehe MANUAL_PAYMENTS.md.

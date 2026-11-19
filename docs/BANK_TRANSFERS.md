# Banküberweisung und Kontenabgleich

Banküberweisungen sind in der Standardkonfiguration deaktiviert. Für die
Einrichtung braucht der Betreiber ein geprüftes EUR-Empfängerkonto, die
freigegebene Rechnungsfunktion, `BANK_TRANSFER_IBAN`,
`BANK_TRANSFER_BENEFICIARY`, `BANK_TRANSFER_ENABLED` und die gesonderte
Freigabe über `BANK_TRANSFER_APPROVED`. Die Werte gehören in die private
Serverkonfiguration. Das Team benötigt für den Abgleich `finance.manage`
und eine MFA-Sitzung, für die Kontoauszüge einen genehmigten Bankzugang.

Der Kunde wählt die Überweisung am offenen Auftrag. GunIndex speichert
Empfänger und Betrag zusammen mit der eindeutigen Referenz `GI-B-…`.
Die Zuordnung gilt für genau einen Zahlungsauftrag. Eine bereits begonnene
Zahlung über einen anderen Kanal blockiert den Wechsel. Eingänge für einen
stornierten oder abgelaufenen Auftrag bleiben zur Klärung sichtbar.

Die Finanzseite **Bankabgleich** nimmt direkt aus dem Empfängerkonto
exportierte Kontoauszüge als camt.053.001.08 XML bis 5 MiB entgegen. Der
Parser berücksichtigt gebuchte EUR-Einzelbuchungen mit stabiler
Bankbuchungsreferenz in einer Datei für ein bekanntes Empfängerkonto.
Sammelbuchungen und andere Formate bleiben zur externen Prüfung.
Der Parser erkennt einen eindeutigen Verwendungszweck in unstrukturiertem
Text, im strukturierten Referenzfeld oder in der End-to-End-Referenz.
[ISO 20022 camt.053.001.08](https://www.iso20022.org/sites/default/files/documents/messages/mdr_part_2/ISO20022_MDRPart2_BankToCustomerCashManagement_2018_2019_v1_0.pdf)

Ein Mitarbeiter bestätigt den tatsächlich gebuchten EUR-Eingang direkt
im Konto und gibt die passende Buchung einzeln frei. GunIndex prüft
Konto, Referenz, Centbetrag, Buchungsdatum, Auftragsstatus und erfasste
Rückbuchungen. Wiederholte Dateien und Buchungen werden dedupliziert.
Erst die bestätigte Zuordnung erzeugt Zahlungsstatus, Leistung und
Rechnungsnachweis. Ein späterer Bankeintrag mit Rückbuchungsmerkmal
erscheint in der Klärung. Erforderliche Folgeschritte prüft das Finanzteam.

Nur normalisierte Buchungsdaten und Prüfsummen werden gespeichert.
Der Rohkontoauszug wird nach dem Import nicht in der Plattform abgelegt.
Vor dem produktiven Start sind Format und Referenzstabilität mit einem
Originalexport des vorgesehenen Kontos zu prüfen. Rückzahlungen werden
im Bankkonto ausgeführt und nach der tatsächlichen Buchung in der
Finanzverwaltung dokumentiert. Eine automatische Bankverbindung für
Kontoabrufe oder Zahlungsaufträge benötigt eine gesonderte Anbindung.

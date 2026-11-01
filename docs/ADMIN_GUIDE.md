# Administration

Jede Personalansicht prüft die aktuellen Datenbankrechte und erfolgreiche MFA. Das initiale Superadministrationskonto entsteht durch das geschützte CLI-Verfahren; privilegierte Beispieldaten gibt es nicht. Die Navigation zeigt nur erlaubte Bereiche. Weiterführende Rollen- und Fallregeln stehen in [GOVERNANCE.md](GOVERNANCE.md).

| Bereich | Aufgabe |
| --- | --- |
| Übersicht | Tatsächliche Tabellen-/Betriebskennzahlen und offene Prüfungen |
| Marke & Website | Name, Texte, Farben, Animationen, Registrierung und zentrale Geschäftsparameter |
| Länder & Sprachen | Märkte, PLZ-Quellenstatus und ausgewählte Inhalts-/Kategorieübersetzungen |
| Kategorien | Hierarchie, Namen, Kennungen, Sortierung und Aktivierung |
| Benutzerkonten | Geschützte Kontenübersicht und Sitzungswiderruf |
| Rollen & Rechte | Begrenzte Personalrollen vergeben/entziehen |
| Inserate & Auktionen | Paginierte Angebote und öffentliche Gebotsereignisse |
| Bewertungen | Begründetes Ein-/Ausblenden |
| Meldungen & Beschwerden | Zuweisung, Entscheidung, Maßnahmen und Beschwerdebearbeitung |
| Händler & Werbung | Händlerprofile/Banner prüfen und konkrete Platzbuchungen verwalten |
| Zahlungseingänge | Manuelle PayPal-Aufträge anhand des tatsächlichen Eingangs prüfen |
| Rechnungen & Erstattungen | Steuerregeln, unveränderliche Belege, Korrekturen und reale Rückzahlungen dokumentieren |
| Datenschutzanfragen | Deaktivierungs-/Löschanträge und kontrollierte Anonymisierung |
| Identitätsprüfung | Fallübernahme, begründete Dokumenteneinsicht und getrennte Identitäts-/Altersentscheidung |
| Rechtstexte | Dokumentversionen, Prüfung, Gültigkeit und Veröffentlichung |
| Änderungsprotokoll | Paginierte technische Auditereignisse |

## Website und Kategorien

Unter **Marke & Website** sind Website-Name, Claim, Startseitentitel/-text, öffentliche Kontaktadresse, zwei Akzentfarben, Animationsintensität, Registrierungsverfügbarkeit und Wartungshinweis änderbar. Farben sind sechsstellige Hexwerte; ausführbarer Code, beliebiges CSS, Dateipfade und Providergeheimnisse sind keine Websitefelder. Die Systemeinstellung für reduzierte Bewegung hat Vorrang. Ein Wartungshinweis ist keine technische Schreibsperre für Auktionen.

Eine Versionsprüfsumme verhindert stilles Überschreiben paralleler Änderungen. Konflikte behalten die Formulareingaben; vor erneutem Speichern den aktuellen Stand laden. Änderungen und Audit liegen in einer Transaktion. Das Protokoll enthält Schlüsselnamen statt Geheimniswerten. Preise sind Integer-Cent; neue Preise aktivieren keinen Dienst und verändern keine verbindlichen Altbuchungen.

Kategorien erlauben Oberkategorie, Kennung, Reihenfolge und Status. Deaktivierte Vorfahren sperren Nachfahren; Zyklen und doppelte Kennungen werden abgewiesen. Das Baumlimit beträgt 1.000 Kategorien. Der aktive allgemeine Katalog enthält keine Waffenkategorien. Kategorien und Wortprüfung ersetzen keine rechtliche Produktfreigabe.

Unter **Länder & Sprachen** lassen sich elf Märkte sowie vorhandene PLZ-Quellen aktivieren/deaktivieren. Internationale Suche erweitert Angebote, mischt aber keine fremden Länderbanner ein. Quelle/Lizenz und Umfang lokaler PLZ-Daten sind sichtbar. Der eigentliche lizenzgeprüfte CSV-Import erfolgt kontrolliert per CLI; es wird kein unkontrollierter Web-Dateipfad ausgeführt. Markentexte und Kategorien können pro Sprache gepflegt werden. Unübersetzte Admin-/Geschäftstexte fallen auf Deutsch zurück.

## Personal, Inhalte und Beschwerden

Nur berechtigte Superadministration vergibt begrenzte Personalrollen an aktive Konten mit bereits eingerichteter TOTP. Neue Rollen oder Entzug erhöhen die Sessionversion. Superadministratorrechte sind nicht durch ein beliebiges Formular vergebbar. Personal-MFA wird nötigenfalls lokal mit `php ops/enrol-staff-mfa.php BENUTZERNAME` eingerichtet; Secrets nie in Logs übernehmen.

Fälle müssen vor einer Entscheidung zugewiesen werden. Entscheidungen brauchen Begründungen; zusätzlich erforderliche Benutzerrechte werden erneut geprüft. Eine ausgeblendete Auktion mit Geboten geht in Sachprüfung, statt einen Gewinner zu erfinden oder Gebote zu löschen. Meldende und Betroffene sehen nur berechtigte eigene Fälle und können begründet Beschwerde einlegen. Freiwillige vertrauliche Beweise erfordern begründeten, protokollierten Fallzugriff.

Bewertungen werden dokumentiert ein-/ausgeblendet; private Proxy-Maximalgebote werden nicht angezeigt. Das Auditlog ist nicht automatisch unveränderlich: minimale DB-Rechte, getrennte Aufbewahrung und Überwachung bleiben Betriebsaufgaben.

## Händler, Platzierungen und Zahlungseingänge

Händlerprofile und Banner brauchen ausdrückliche Inhaltsprüfung. Eine spätere Profiländerung löst erneute Prüfung aus. Freigabe ist kein behördlicher Gewerbe- oder Identitätsnachweis. Premiuminventar ist je Land begrenzt: drei Kategorieplätze, zwei Startseitenplätze und zwei Bannerkunden pro Kategorie. Reservierungen, tatsächliche Zahlung, gewünschter Start und Laufzeit sind getrennte Zustände.

Die Funktion bleibt standardmäßig mit `business_promotions_enabled=0` gesperrt. Zusätzlich gelten die manuellen Zahlungsfreigaben. Verspätet bestätigte Zahlung darf keinen belegten Platz verdrängen; der Fall bleibt bezahlt, aber unerfüllt, bis Erfüllung oder Erstattung geklärt ist. Keine Reichweiten-/Verkaufsgarantie geben.

Unter **Zahlungseingänge** stehen Betrag, feste Referenz, Empfänger und Status. Vor Freigabe tatsächlich im Providerkonto Betrag, Empfänger und Referenz prüfen und die reale Transaktionskennung erfassen. Ein Nutzerhinweis oder Screenshot genügt nicht. `manual_paypal_enabled`, geprüfte Kontoeignung und Steuer-/Bestellbedingungen sind eigenständige Voraussetzungen; ein Adminlogin schaltet sie nicht frei. Keine Friends-&-Family-Umgehung. Siehe [MANUAL_PAYMENTS.md](MANUAL_PAYMENTS.md).

## Finanzunterlagen

Vor Aktivierung von `finance_enabled` müssen tatsächlicher Betreiber und erforderliche Anschrift-/Steuerangaben vollständig sein. Für jedes bediente Kundenland ist die Steuerbehandlung fachlich zu prüfen und im Finanzbereich zu hinterlegen. Eine eingetragene Kunden-USt-ID erzeugt nicht automatisch eine Steuerbefreiung.

Neue Aufträge halten Betreiber, Käufer, Leistung, Gebührenversion und Beträge fest. Nach tatsächlicher Zahlungsfreigabe entstehen feste Rechnungsnummern/PDFs. Belege nicht rückwirkend umschreiben; Korrektur und Gutschrift bleiben verknüpft. Ein Erstattungsantrag führt keine Zahlung aus. Erst die tatsächlich beim Provider ausgeführte Rückzahlung mit eigener realer Referenz erfassen. Restbeträge, Teilrückzahlungen und nicht mehr nutzbare Leistungsrechte werden geprüft. Details: [FINANCE.md](FINANCE.md).

## Datenschutz, Identität und Rechtstexte

Löschanträge deaktivieren Konten und widerrufen Sitzungen. Vor Anonymisierung offene Verträge/Auktionen, andere Datensysteme und gesetzliche Aufbewahrung prüfen. Erhaltene Finanz-/Fallnachweise sind zweckgebunden zu behandeln; keine pauschale Sofortlöschung zusagen.

Dokumentensammlung ist standardmäßig aus. Aktivierung erfordert `verification_enabled`, tatsächliche fachliche Freigabe über `verification_review_approved`, private Verschlüsselung und festgelegte `verification_retention_days`. Der technische Rahmen von 1–90 Tagen ist keine gesetzliche Erlaubnis. Einsicht verlangt Fallübernahme, konkreten Grund und kurz gültigen personengebundenen POST-Zugang. Identität und Alter getrennt entscheiden; eigene Freigabe ist gesperrt. Der Privacyworker muss zuverlässig laufen.

Zwanzig deutsche Rechtsentwürfe können kontrolliert als unveröffentlichte Versionen importiert werden. Rechtsverwaltung benötigt Version, Hash, Pflichtangaben, verantwortliche Prüfung und Gültigkeitsbeginn. Neue Inhalte brauchen neue Versionen. `legal_publication_enabled` ist ein zusätzliches Gate; eine ausgefüllte Checkliste ersetzt keine tatsächliche anwaltliche Prüfung oder geprüfte Übersetzungen.

## Betriebsaufgaben außerhalb der Website

Geheimnisse, TLS/DNS, Mailzustellung, SSH/Firewall, lizenzgeprüfte PLZ-Importe, Migrationen und Sicherungs-/Restoreaufgaben bleiben kontrollierte Serveraufgaben. [UBUNTU_22_04.md](UBUNTU_22_04.md) enthält die Befehle und Installationsdatei. Vor Veröffentlichung die [Release-Checkliste](RELEASE_CHECKLIST.md) anhand des tatsächlichen Zielservers abarbeiten.

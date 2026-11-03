# Arbeitsstand für die fachliche Prüfung – 2026-11-03

Dies ist eine Übergabe des tatsächlich implementierten Entwicklungsstands,
**keine abschließende Rechtsfreigabe**. Die Plattform ist noch nicht fertig.
Technische A/B/C-Abnahme: [TECHNICAL_ACCEPTANCE.md](TECHNICAL_ACCEPTANCE.md).
Die fachlichen Entscheidungen sind anhand der realen Betreiberangaben,
Zielmärkte, Verträge und Providervereinbarungen zu treffen.

## Dokumente und Zustimmung

Zwanzig ungeprüfte deutsche Entwürfe stehen unter `docs/legal/`; Kennungen und
Titel in `docs/legal/manifest.php`. Pflichtfelder und ausstehende Prüfung sind
gekennzeichnet. Es liegen dadurch keine freigegebenen Rechtstextübersetzungen vor.
Die acht Oberflächensprachen sind kein Nachweis acht juristisch geprüfter Fassungen.

Die Admin-Verwaltung speichert Dokumentkennung, Version, Sprache, Inhalt und
SHA-256. Freigabe benötigt berechtigte MFA-Anmeldung, dokumentierte Prüfstelle,
Gültigkeitsdatum und die vorhandene Checkliste. Bereits freigegebene Fassungen
werden über die Oberfläche nicht umgeschrieben. Die globale Veröffentlichung
ist separat standardmäßig deaktiviert. Dies dokumentiert eine Betreiberangabe;
es beweist nicht die Qualität oder Berechtigung einer behaupteten Fachprüfung.

Für Registrierung und bestehende Leistungsbestellungen können Pflichtkennungen
konfiguriert werden. Abgesendet werden genau die angezeigten Versionen/Hashes,
Sprache und ausdrückliche, nicht vorausgewählte Bestätigung. Ein geänderter Satz
verlangt erneutes Laden. Konto/Bestellung und Belege entstehen in einer gemeinsamen
Datenbanktransaktion. Je Bestellung wird ein eigener Beleg geführt. Details und
Grenzen: [CONTRACT_BINDING.md](CONTRACT_BINDING.md).

Zu entscheiden: welche Dokumente je Vorgang erforderlich sind, geeignete Form der
Zustimmung/Kenntnisnahme, erforderliche erneute Zustimmung bestehender Konten,
Sprach- und Länderabdeckung sowie Aufbewahrung. Leere Konfigurationslisten bedeuten
„nicht eingerichtet“. Bestandskonten erhalten keine erfundenen Zustimmungen.

## Gebühren, Bestellungen und Rechnungen

Die aktuell konfigurierbaren Standardpreise sind Bump 1,99 EUR, drei Bumps
4,99 EUR, Kategorie-Top sieben Tage 9,90 EUR, Startseiten-Top sieben Tage
19,90 EUR und Händlerbanner ein Monat 129,00 EUR. Maßgeblich ist der tatsächliche
unveränderliche Auftragssnapshot, nicht ein später geänderter Standardpreis.

Auktions-Einstellpreis und progressive Erfolgsprovision sind jetzt mit neuer
Veröffentlichung, Zuschlag und Sofortkauf verbunden, standardmäßig deaktiviert.
Der verschlüsselte Durchlaufvertrag bindet Tarif, Steuern, Empfänger, Anschriften
und konkret bestätigte Rechtsfassungen. Gebühren-/Leistungsbeginnbestätigung und
kostenpflichtiger Veröffentlichen-Button sind ausdrücklich erforderlich.
Bestehende gebührenfreie Auktionen werden nicht rückwirkend belastet. Forderung
und tatsächlicher Zahlungseingang sind getrennt; Rechnungs-PDF nach verifizierter
Zahlung. Unbezahlter vollständiger Erlass mit Begründung und verifizierte bezahlte
Erstattungen sind vorhanden. Wiedereinstellung, unbezahlte Teilkorrekturen und
automatischer Provider-Einzug dieser Forderungen bleiben offen.
Details: [AUCTION_BILLING.md](AUCTION_BILLING.md).

Bestehende Leistungsaufträge speichern Referenz, Betrag, Tarif-/Bedingungsfassung
und bei aktivierter Rechnungsfunktion verschlüsselte Rechnungsdaten samt geprüfter
Steuerkonfiguration. Teil-/Vollerstattungen und Korrekturdokumente haben separate
Nachweise; ein Erstattungsantrag löst selbst keinen Geldtransfer aus. Bestehende
Aufträge ohne Rechnungssnapshot brauchen eine eigene Bestandsbehandlung.
Details: [FINANCE.md](FINANCE.md), [MANUAL_PAYMENTS.md](MANUAL_PAYMENTS.md).

Zu prüfen: Leistungsbeschreibung, Fälligkeit, Steuerbehandlung je Rechnungsland,
Pflichtangaben, Verbraucherrechte, vorzeitiger Leistungsbeginn, Storno/Erstattung
und Aufbewahrung. Technische Tests ersetzen diese Entscheidungen nicht.

## Zahlungsanbieter und Treuhand

Die zentrale manuelle PayPal-Empfängeradresse wird geschützt konfiguriert.
Bestellungen erhalten serverseitige Referenzen. Ein Nutzerhinweis oder Screenshot
bestätigt keinen Zahlungseingang. Ein berechtigter Betreiber muss tatsächlichen
Eingang, Betrag, Referenz und eindeutige Transaktionskennung prüfen. Diese manuelle
Dokumentation ist kein automatischer Zugriff auf ein Bank-/Providerkonto.

Die automatische PayPal-REST-Anbindung hat separate Geschäftsmodell-, Konto-,
Umgebungs- und Live-Freigaben. Providerabnahme ist ausstehend. Automatische
Bestätigung prüft Auftrag, Empfänger, Referenz, Währung und vollständigen Capture;
Webhook-Signaturprüfung erfolgt über die Provider-Schnittstelle. Tests verwenden
synthetische Adapter. Es wurden keine echten Gelder transferiert.

Treuhand ist noch kein implementierter verwahrender Zahlungsdienst. Es gibt
eine Gebührenvorschau, aber keinen vollständig freigegebenen Verwahrungs-/
Auszahlungsworkflow. Auch Bitcoin und Banküberweisung sind nicht aktiviert.
Ein persönliches PayPal-Konto wird nicht als Treuhandinfrastruktur behandelt.
Details: [PAYMENTS.md](PAYMENTS.md) und technische Restliste.

## Datenschutz, Identitätsprüfung und Kommunikation

Vorhandene private Speicherung, geschützte Exporte, Zugriffsrollen, Melde- und
Datenschutzabläufe müssen mit den tatsächlich vorgesehenen Betriebsprozessen
abgeglichen werden. Neue Vertragsbelege enthalten Konto/Vorgang, Dokumenthash
und Zeitpunkt; dafür werden keine zusätzlichen IP-/Browserprofile erhoben.

Der neue Verlauf ist freiwillig, sitzungsgebunden und abschaltbar; Vergleich
maximal vier Angebote. Logische Fristen und physisches Session-Aufräumen sind
in [PERSONAL_LISTS.md](PERSONAL_LISTS.md) getrennt beschrieben.

Chatinhalte werden serverseitig AEAD-verschlüsselt. **Keine E2EE vorhanden.**
Identitätsprüfung hat eigene deaktivierte Freigaben und Aufbewahrungseinstellungen.
E-Mail-Bestätigung beweist weder Identität noch Erwerbsberechtigung. Empfänger-
zustellung, externe Mail-/DNS-Infrastruktur und E2EE-Anhänge sind nicht abgenommen.

Zu prüfen: Zwecke, Rechtsgrundlagen, erforderliche Informationen, Dienstleister,
Ländertransfers, Rollen/Zugriffe, Aufbewahrung, Löschung und Auskunftsverfahren.

## Länder und externe Daten

Der bereitgestellte deutsche Datensatz `ZIP/PLZ.csv` wurde auf Integrität und
Inhalt geprüft; die Rechtebestätigung stammt vom Nutzer. Sie ist keine Freigabe
fremder Quellen. Herkunft, Lizenzgrenzen und mögliche Nachbarlandquellen stehen
in [POSTCODE_SOURCES.md](POSTCODE_SOURCES.md). Keine externen Koordinatenimporte
oder erfundenen Koordinaten wurden als abgeschlossen ausgegeben.

## Betriebliche Voraussetzungen

Reale Betreiber-/Domain-/HTTPS-/Mailkonfiguration, gültige Providerverträge,
private Serverablage, getrennte Schlüssel-/Backupsicherung und Wiederherstellung,
Worker-/Schedulerbetrieb, Monitoring und ein funktionierender Wartungsmodus
müssen anhand der späteren Installation abgenommen werden. Die geschützte Setup-
Übersicht zeigt technische Diagnosen; sie ersetzt weder vollständigen Installer
noch fachliche Freigaben. Releasekriterien: [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md).

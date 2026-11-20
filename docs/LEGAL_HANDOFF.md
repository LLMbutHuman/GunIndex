# Arbeitsstand für die fachliche Prüfung – 2026-11-21

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

Eine gesonderte, standardmäßig deaktivierte Einstellung verlangt bei
Bestandskonten die aktuelle Pflichtfassung vor neuen Marktplatzvorgängen.
Die ausdrückliche Zustimmung erhält einen eigenen Nachweis. Alte Nachweise
bleiben verfügbar. Rechnungen, Datenschutz und laufende Abwicklung sind auch
während einer ausstehenden Zustimmung zugänglich.
[CI 44794702907](https://github.com/LLMbutHuman/GunIndex/actions/runs/44794702907)

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
Zahlung. Teil- und Vollerlass unbezahlter Gebühren erfordern einen begründeten
Prüfeintrag. Der Teilerlass ersetzt den Zahlungsauftrag durch eine referenzierte
Restforderung mit den ursprünglichen Vertragsnachweisen. Verifizierte Eingänge
auf ersetzte Referenzen werden getrennt erfasst und bis zur dokumentierten
externen Rückzahlung geklärt. Der automatische Provider-Einzug dieser Forderungen
bleibt offen.
Details: [AUCTION_BILLING.md](AUCTION_BILLING.md).

Der einmalige Nachlass für eine erfolglose Auktion wird erst bei Veröffentlichung
eines zulässigen neuen Durchlaufs angewendet. Nach Verkauf durch Zuschlag oder
Sofortkauf erfordert ein neuer Entwurf die ausdrückliche Bestätigung, dass ein
weiterer Artikel verfügbar ist. Der neue Durchlauf verwendet den zum Zeitpunkt
der Veröffentlichung angebotenen Tarif. Eine begrenzte Laufzeitverlängerung
bleibt innerhalb des ursprünglichen Durchlaufs. [Wiedereinstellung](RELISTING.md)

Die Belegübersicht prüft die Kontozuordnung und den Auktionsbezug für Forderungen,
Korrekturen, Rechnungen und Erstattungsanfragen. Im isolierten Test blieben
Rechnungssnapshots bei Laufzeitverlängerung und Korrektur nachvollziehbar.
[CI 44839394997](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839394997)

Bestehende Leistungsaufträge speichern Referenz, Betrag, Tarif-/Bedingungsfassung
und bei aktivierter Rechnungsfunktion verschlüsselte Rechnungsdaten samt geprüfter
Steuerkonfiguration. Teil-/Vollerstattungen und Korrekturdokumente haben separate
Nachweise; ein Erstattungsantrag löst selbst keinen Geldtransfer aus. Bestehende
Aufträge ohne Rechnungssnapshot brauchen eine eigene Bestandsbehandlung.
Details: [FINANCE.md](FINANCE.md), [MANUAL_PAYMENTS.md](MANUAL_PAYMENTS.md).

Neue Rechnungen speichern die Beschriftungen der Bestellsprache zusammen mit
den übrigen Rechnungsdaten. Bei einer Korrektur oder späteren PDF-Erzeugung
bleibt der gespeicherte Sprachstand maßgeblich. Der fachlich verfasste Wortlaut
zu Steuerregeln und Bedingungen stammt aus dem jeweiligen Nachweis.
[CI 44834934106](https://github.com/LLMbutHuman/GunIndex/actions/runs/44834934106)

Händler können ein vorausbezahltes Abonnement für Plattformleistungen beziehen.
Eine weitere Laufzeit benötigt eine ausdrückliche Bestellung und verifizierte
Zahlung. Zur Freigabe stehen konkreter Preis, Leistungsumfang, Vertragsfassung,
Zahlungsweg sowie Verbraucher- und Erstattungsregeln an. Teamrollen, Bestands-API
und Zugriff auf Verkaufsdaten sind nach Rolle und Händlerkonto begrenzt.
[CI 44793558506](https://github.com/LLMbutHuman/GunIndex/actions/runs/44793558506)

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

Die Erstattungsfreigabe für REST-Zahlungen erfordert eine Finanzberechtigung,
MFA, einen Kundenantrag und den geprüften Betrag. Eine abgeschlossene
PayPal-Erstattung erzeugt die verknüpfte Finanzbuchung und den Korrekturbeleg.
Unklare Providerantworten halten die Forderung zur Klärung. Die separate
Live-Freigabe und die Abnahme mit tatsächlichem PayPal-Konto stehen aus.
Details: [FINANCE.md](FINANCE.md).

Für Banküberweisungen gibt es eine eindeutige Auftragsreferenz und die
geschützte Abstimmung gebuchter Kontoauszüge im Format camt.053.001.08.
Die Anwendung speichert normalisierte Buchungsnachweise und den Hash des
Imports. Finanzpersonal prüft den
Eingang direkt im Empfängerkonto vor der Freigabe. Die Bankverbindung
und ihr Exportformat müssen vor dem Live-Betrieb abgenommen werden.
Details: [BANK_TRANSFERS.md](BANK_TRANSFERS.md).

Bitcoin-Eingänge für Betreiberleistungen nutzen eine getrennte Empfangswallet,
eine begrenzte Kursgültigkeit und eine Prüfung gegen die aktive Bitcoin-Kette.
Regtest bleibt eine isolierte Simulation. Vor aktiviertem Mainnet-Betrieb sind
Node, Wallet, Kursverfahren und Umgang mit Reorganisationen und Erstattungen
fachlich und technisch abzunehmen. Details: [BITCOIN.md](BITCOIN.md).

Die Treuhand-Simulation umfasst Preisangebot, Gegenangebot, Eingangsereignis,
Versand, Empfang, Streitfall, Freigabe, Erstattung und Rückbuchung im isolierten
Testsystem. Der Live-Betrieb benötigt einen geeigneten Anbieter mit verifiziertem
Eingang und freigegebener Auszahlung, ein abgestimmtes Vertrags- und
Streitfallverfahren sowie Betriebsabnahme. Die Simulation verwendet weder eine
reale Verwahrung noch eine Anbindung an persönliche Zahlungskonten.
[Treuhandablauf](ESCROW.md), [Zahlungen](PAYMENTS.md)

## Datenschutz, Identitätsprüfung und Kommunikation

Vorhandene private Speicherung, geschützte Exporte, Zugriffsrollen, Melde- und
Datenschutzabläufe müssen mit den tatsächlich vorgesehenen Betriebsprozessen
abgeglichen werden. Neue Vertragsbelege enthalten Konto/Vorgang, Dokumenthash
und Zeitpunkt; dafür werden keine zusätzlichen IP-/Browserprofile erhoben.

Der neue Verlauf ist freiwillig, sitzungsgebunden und abschaltbar; Vergleich
maximal vier Angebote. Logische Fristen und physisches Session-Aufräumen sind
in [PERSONAL_LISTS.md](PERSONAL_LISTS.md) getrennt beschrieben.

Der alte Chatverlauf bleibt serverseitig lesbar (Klartext/AEAD, Version 0/1).
Der neue E2EE-Modus (Version 2) verschlüsselt Nachrichten und Dateien bereits
auf Endgeräten. Geräte- und Wiederherstellungsschlüssel liegen nicht im Klartext
auf dem Server. Standardmäßig deaktiviert; technische Nachweise und Grenzen:
[E2EE_PROTOCOL.md](E2EE_PROTOCOL.md). Keine rückwirkende E2EE des Altverlaufs.
Metadaten wie Teilnehmer, Inserat, Zeitpunkt, Gerätestand und Dateigröße bleiben
sichtbar. Moderation und Virenprüfung können verschlüsselte Inhalte nicht
serverseitig lesen. Der Datenschutzexport enthält für E2EE verschlüsselte Pakete
und öffentliche Prüfmanifeste; keine privaten Recoverygeheimnisse. Aufbewahrung,
Löschverfahren, Missbrauchsmeldungen, Support bei Schlüsselverlust und Nutzertexte
benötigen fachliche Prüfung. Ohne Geräte oder aktuellen Recoverycode ist keine
Betreiberentschlüsselung möglich. Ein kompromittierter Autoritätsschlüssel kann
derzeit nicht innerhalb desselben Kontos ersetzt werden.
Identitätsprüfung hat eigene deaktivierte Freigaben und Aufbewahrungseinstellungen.
E-Mail-Bestätigung beweist weder Identität noch Erwerbsberechtigung. Der geschützte
Mail-Assistent unterstützt lokalen Versand und TLS-Relay. Die tatsächliche
Empfängerzustellung und Betreiber-DNS müssen geprüft werden. [Mail-Einrichtung](MAIL_SETUP.md).
E2EE-Anhänge
besitzen einen erfolgreichen isolierten technischen Nachweis (CI 43648359381),
aber noch keine unabhängige Sicherheits- oder Produktivabnahme.

Zu prüfen: Zwecke, Rechtsgrundlagen, erforderliche Informationen, Dienstleister,
Ländertransfers, Rollen/Zugriffe, Aufbewahrung, Löschung und Auskunftsverfahren.

## Länder und externe Daten

Der bereitgestellte deutsche Datensatz `ZIP/PLZ.csv` enthält 8.289 eindeutige
PLZ-Ortspunkte. Die isolierte Installationsprobe bestätigte den Import mit
geprüfter SHA-256-Summe, Länderfilter, PLZ- und Umkreissuche. Die Rechtebestätigung
des Nutzers bezieht sich auf genau diese Datei. Für weitere Quellen bleiben
Herkunft, Datenstand, Lizenz und erforderliche Quellenangaben zu prüfen.
[Quellennachweis](POSTCODE_SOURCES.md) · [CI 44839813273](https://github.com/LLMbutHuman/GunIndex/actions/runs/44839813273)

## Betriebliche Voraussetzungen

Reale Betreiber-/Domain-/HTTPS-/Mailkonfiguration, gültige Providerverträge,
private Serverablage, getrennte Schlüssel-/Backupsicherung und Wiederherstellung,
Worker-/Schedulerbetrieb und Monitoring müssen anhand der vorgesehenen Installation
abgenommen werden. Die isolierte technische Probe bestätigt das Produktionsimage,
die Migrationsfortsetzung, verschlüsselte Sicherung und Wiederherstellung sowie
sieben Worker-Heartbeats. Der geschützte Admin-Assistent zeigt den Konfigurations-
und Prüfstatus. [CI 44838740876](https://github.com/LLMbutHuman/GunIndex/actions/runs/44838740876)

Während einer Wartung bleiben Auktionen und bezahlte Werbezeiten geschützt. Die
unbezahlte Reservierungsfrist läuft weiter. Verifizierte Eingänge bleiben
abstimmbar. Die Testaufzeichnungen dokumentieren die Fristverlängerung und
Wiedereröffnung für einen simulierten neuntägigen Ausfall.
[CI 44835645014](https://github.com/LLMbutHuman/GunIndex/actions/runs/44835645014)

Für die Freigabe benötigt das Team die endgültigen Betreiberangaben, Zielmärkte,
Steuerregeln, Dokumentfassungen und Providervereinbarungen. Auf dem Zielsystem
stehen Nachweise für HTTPS, Posteingang, Backups am getrennten Standort,
Wiederanlauf und die vorgesehenen Sicherheitsprüfungen aus.
[Releasekriterien](RELEASE_CHECKLIST.md)

# Rollen, Moderation, Datenschutz und Rechtstexte

## Rollen und Zugriff

Das initiale, über das gesicherte CLI-Verfahren angelegte Administrationskonto
behält die Superadministratorrechte. Es kann unter `admin/roles.php` begrenzte
Personalrollen vergeben und entziehen. Eine Rolle wird nur einem aktiven Konto
mit bereits eingerichteter TOTP zugeteilt; die Vergabe erhöht `auth_version` und
widerruft bestehende Sitzungen. Eine gewöhnliche Administration kann nicht allein
durch die Wahl einer Formular-ID neue Superadministratorrechte erzeugen.

| Rolle | Zweck |
| --- | --- |
| Administration | Website, Benutzer, Kategorien, Moderation, Datenschutz, Identität, Rechtstexte, Finanzen, Werbung |
| Moderation | Fallübersicht, begründete Entscheidungen und konkrete Inhaltsmaßnahmen |
| Support | Fallübersicht und Datenschutzanträge |
| Identitätsprüfung | Fallgebundene Dokumentenprüfung und getrennte Identitäts-/Altersfeststellungen |
| Finanzen | Finanzverwaltung |

Alle Personalrollen erhalten `dashboard.read`. Einzelne Seiten und Dienste
prüfen ihre spezifische Berechtigung aus der aktuellen Datenbank. Zusätzlich
benötigt jeder Webzugriff eine aktive Sitzung mit erfolgreicher MFA. Ein
Benutzername, alter Sessionflag oder frei übergebene Rolle genügt nicht.

Die lokale Ersteinrichtung der MFA eines Personalkandidaten erfolgt ohne
Rollenvergabe über:

```sh
php ops/enrol-staff-mfa.php BENUTZERNAME
```

Der Assistent verlangt ein privates interaktives Terminal und einen gültigen
Authenticatorcode. Er überschreibt kein vorhandenes Secret. Das lokale
Enrollmentsecret darf nicht protokolliert oder in Tickets übernommen werden.
Anschließend erteilt der initiale Administrator die benötigte Rolle über die
Website. Die Übergabe eines initialen Superadministrationskontos benötigt ein
gesondertes kontrolliertes Verfahren.

## Meldungen, Entscheidungen und Beschwerden

`marketplace/report.php?type=listing&id=123` meldet ein konkretes aktives
allgemeines Angebot. Unterstützt werden außerdem `user`, `message`, `rating`
und `advertisement`. Für eine Nachricht muss der Meldende tatsächlicher
Gesprächsteilnehmer sein. Es werden keine historischen Spezialangebote
übernommen. Meldungen des gleichen noch offenen Ziels werden dedupliziert;
ein Stundenlimit begrenzt Missbrauch.

Der Meldegrund ist für die Fallbearbeitung erforderlich. Freiwillige ergänzende
Angaben werden zweckgebunden verschlüsselt gespeichert. Die Meldung einer
Nachricht legt keinen vollständigen fremden Chat offen. Für die Einsicht in
freiwillige Zusatzangaben benötigt Personal einen begründeten, protokollierten
Fallzugriff. Ein möglicher Betroffener erhält dadurch keine pauschale Einsicht
in diese Zusatzangaben oder die Identität des Meldenden.

`admin/moderation.php` bietet Fallzuweisung, begründete Zurückweisung, Verwarnung,
gezielte Ausblendung und mit zusätzlichem Benutzerrecht eine Kontoeinschränkung.
Ein Fall muss vor einer Entscheidung der bearbeitenden Person zugewiesen sein.
Auktionen werden bei Ausblendung in den Prüfzustand versetzt; Gebote und
Transaktionsnachweise bleiben erhalten. Die Entscheidung und das Audit werden
transaktional gespeichert. Benachrichtigungen verweisen auf die persönliche
Fallansicht und enthalten keine vertrauliche Beweisabschrift.

Meldende und tatsächlich betroffene Nutzer sehen unter `user/reports.php` nur
ihre eigenen Fälle. Nach einer Entscheidung können sie eine begründete
Beschwerde einreichen. Sie wird als eigener Status bearbeitet; eine Antwort
schaltet Inhalte nicht ungeprüft wieder frei. Externe gesetzliche Rechte bleiben
bestehen. Gesetzliche Fristen, DSA-/P2B-Anwendbarkeit, Transparenzberichte und
außergerichtliche Wege müssen anhand des realen Betriebs fachlich festgelegt
werden. Eine technische Fallnummer ersetzt diese rechtliche Prüfung nicht.

## Datenexport und Kontolöschung

`user/privacy.php` verlangt für Export oder Deaktivierung das aktuelle Passwort,
POST und CSRF. Der Export wird vollständig in einem nur für den Prozess
zugänglichen temporären Stream vorbereitet, bevor der Download beginnt. Er
enthält ausschließlich eigene beziehungsweise für den Benutzer berechtigte
Datensätze: Profil, Angebote, eigene Gebote, Nachrichten, Bewertungen,
Merklisten/Blockierungen, Gesuche und Suchalarme, Benachrichtigungen,
Händler-/Werbe-/Buchungsdaten, Abrechnungs- und Korrekturbelege,
Rechtszustimmungen und verfügbare eigene Verifikationsunterlagen.

Verschlüsselte Nachrichten und eigene Finanznachweise werden für diesen
berechtigten Export entschlüsselt; Integritätshashes von Finanzdokumenten
werden geprüft. Passworthashes, MFA-Secrets, Zugriffstokens, Mailqueueinhalte
und fremde private Nachrichten werden nicht exportiert. Eine sichere
Einzeldateigrenze von 128 MiB verhindert unbeschränkten Ressourcenverbrauch;
größere Exporte benötigen einen betreuten vollständigen Export durch den
Datenschutzkontakt. Die temporäre Datei wird nach dem Download geschlossen
und entfernt. Der Download selbst ist vertraulich zu behandeln.

Eine Löschanforderung deaktiviert das Konto und widerruft sofort Sitzungen.
Aktive Angebote werden pausiert, Auktionen mit Geboten in die Sachprüfung
versetzt. Gesuche und Suchalarme werden pausiert; nicht abgeschlossene Mails
werden angehalten und ihre Nutzdaten redigiert. Bereits an ein Mailprogramm
übergebene Nachrichten können nicht zurückgerufen werden.

`admin/privacy.php` dokumentiert die weitere Aufbewahrungs- oder
Anonymisierungsentscheidung. Eine Anonymisierung verlangt eine ausdrückliche
Prüfung offener Verträge, weiterer Datensysteme und gesetzlicher Aufbewahrung.
Offene oder zu prüfende Auktionen verhindern eine unkontrollierte
Anonymisierung. Kontaktdaten, aktive Rollen, Suchprofile, private Präferenzen,
Tokens und das veränderliche Rechnungs-/Händlerprofil werden bereinigt.
Unveränderliche Finanz-/Vertragsnachweise und notwendige Fallunterlagen können
zweckgebunden erhalten bleiben. Das ist ausdrücklich keine Behauptung,
sämtliche Daten in allen Systemen sofort vollständig gelöscht zu haben.
Der Betreiber muss Fristen und die tatsächliche weitere Bereinigung festlegen.

## Identität und Alter

Die Dokumentensammlung ist standardmäßig deaktiviert. Eine Aktivierung setzt
die tatsächliche fachliche Freigabe, eingerichtete Verschlüsselung/private
Ablage und eine festgelegte Dokumentenfrist voraus. Technisch werden Fristen
von einem bis 90 Tagen unterstützt; dieser Rahmen ist keine pauschale
gesetzliche Erlaubnis zur Speicherung. `verification_enabled`,
`verification_review_approved` und `verification_retention_days` steuern diese
Voraussetzungen. Eine erforderliche längere oder anders strukturierte
Aufbewahrung benötigt ein gesondert geprüftes Verfahren.

`user/verification.php` nimmt ein bis drei tatsächliche JPEG-/PNG-HTTP-Uploads
mit maximal je 5 MiB, 12 Megapixeln und 6.000 Pixeln Seitenlänge an. PDFs und
andere Dateitypen werden abgewiesen. Bilder werden in Arbeitsspeicher neu als
WebP codiert, Metadaten entfernt und vor persistenter Speicherung mit der
zweckgetrennten AEAD-Funktion für `identity` verschlüsselt. Die zufälligen
Dateinamen liegen unter `GENERAL_PRIVATE_DIR/verification`, außerhalb des
Webroots. Dieses Verzeichnis benötigt minimale Rechte und eine getrennte
Sicherungs-/Schlüsselstrategie.

Ein berechtigter Prüfer mit MFA muss den Fall übernehmen und einen konkreten
Einsichtsgrund angeben. Dokumentenzugänge sind einmalig, an diese Person
gebunden und zwei Minuten gültig. Das Dokument wird ausschließlich über einen
POST mit CSRF und Token ausgeliefert; es gibt keine Token-URL für normale
Accesslogs und keinen direkten öffentlichen Dateipfad. Jede tatsächliche
Einsicht wird protokolliert. Eine Entscheidung benötigt eine aktuelle
protokollierte Einsicht; Selbstfreigaben sind ausgeschlossen.

Identität und Mindestalter werden getrennt bestätigt. Eine Identitätsfreigabe
bestätigt weder Alter noch Eigentum, Zahlungsfähigkeit oder eine rechtliche
Erwerbsberechtigung. Für regulierte Waren werden keine Transaktionen aktiviert.
Nachforderung, Ablehnung, Status und Begründung sind im Konto sichtbar.
Der folgende Job entfernt abgelaufene verschlüsselte Dokumentdateien, markiert
deren Metadaten und protokolliert die Entfernung:

```sh
php ops/privacy-worker.php --once
```

Der Job muss zuverlässig zeitgesteuert und überwacht laufen. Er verändert
keine Gebührenrechnungen oder Gebotshistorien. Gesetzliche Aufbewahrung der
minimalen Prüfentscheidung und Auditdaten ist separat zu bestimmen.

## Versionierte Rechtsdokumente

Unter `docs/legal/` stehen 20 inhaltliche deutsche Entwurfsfassungen samt
kontrolliertem Manifest. Sie enthalten klar markierte Pflichtfelder und
behaupten keine anwaltliche Freigabe. Nach der sicheren Einrichtung des
Betreibers können sie als unveröffentlichte Datenbankversionen angelegt werden:

```sh
php ops/privacy-worker.php --seed-legal ADMIN_ID
```

Der Import ist idempotent, solange der Hash einer bereits angelegten Version
unverändert bleibt. Eine abweichende Fassung benötigt eine neue Version;
bestehende Zustimmungen werden nicht umgeschrieben. `admin/legal.php` verwaltet
Kennung, Version, Sprache, Inhalt, SHA-256, verantwortliche Prüfstelle,
Checkliste und Gültigkeitsbeginn. Unausgefüllte Pflichtfelder oder ein
abweichender Dokumentenhash verhindern die Freigabe. Die Checkliste ersetzt
keine tatsächliche Fachprüfung und beweist diese nicht automatisch.

Eine Veröffentlichung erfolgt zusätzlich erst bei
`legal_publication_enabled=1`. Öffentlich werden nur freigegebene,
bereits gültige Versionen über `marketplace/legal.php` angezeigt und als Text
zum Speichern angeboten. Erforderliche Zustimmungen benötigen eine aktive,
nicht vorausgewählte Erklärung und speichern Benutzer, genaue Version, Hash,
Zeitpunkt und Zustimmungsart. Die rechtliche Entscheidung, wann eine erneute
Zustimmung notwendig ist und welche Dokumente je Vertrag gelten, bleibt Teil
der fachlichen Betreiberprüfung. Die neue Mehrsprachigkeit der Oberfläche
ersetzt keine fachlich geprüften landesspezifischen Vertragsübersetzungen.

## Prüfstand

`tests/governance_test.php` ist ausschließlich für die synthetische
`gunindex_rebuild_test`-Datenbank und CLI-Ausführung freigegeben. Die Suite
prüft begrenzte Rollen, MFA-Voraussetzung, Rechteentzug, private Objekte,
Meldungsdeduplizierung, Moderation und Beschwerde, Entwurfspflichtfelder,
Dokumentenhash und versionierte Zustimmung, private Dokumentenzugriffe,
getrennte Altersfeststellung, Retention, eigenen Export und Anonymisierung
bei erhaltenen Finanznachweisen. Vor dem tatsächlich ausgeführten Lauf ist
dies nur eine vorhandene Testsuite, kein Erfolgsnachweis. Die Gesamtintegration
und Browser-/Rollenmatrix werden vom zentralen Runner getrennt dokumentiert.

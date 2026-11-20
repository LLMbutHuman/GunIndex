# Versionierte Zustimmung in Registrierung und Checkout

`legal_registration_documents` und `legal_checkout_documents` enthalten je bis
zu zehn unterschiedliche Dokumentkennungen, mit Kommas getrennt. Bearbeitung
über die bestehende MFA-/CSRF-geschützte Websiteverwaltung. Die Dokumente werden
weiterhin separat unter Rechtsdokumente erstellt und nach tatsächlicher fachlicher
Prüfung freigegeben. Diese Konfiguration behauptet keine erfolgte Rechtsprüfung.

Die Listen sind zunächst leer, um vorhandene Entwicklungsinstallationen nicht
mit erfundenen Rechtsanforderungen zu konfigurieren. Leer bedeutet **nicht
eingerichtet**, nicht rechtlich freigegeben. Vor Produktion sind die vom Legal-Team
festgelegten Pflichtdokumente je Vorgang und alle vorgesehenen Sprachfassungen
einzurichten. Eine Datenschutzinformation ist nicht automatisch eine Einwilligung
zu optionaler Verarbeitung; das Legal-Team muss geeignete Vertragsdokumente und
die tatsächliche Zustimmungsform festlegen.

Für jede konfigurierte Kennung muss eine freigegebene, bereits wirksame Fassung
in der aktuellen Oberflächensprache existieren. Kein stiller Rückfall auf eine
andere Sprache. Geschlossene Veröffentlichungsfreigabe, fehlende Fassung oder
abweichender SHA-256 verhindern den Vorgang. Benutzer sehen Titel, Sprache,
Version und einen Link mit exakt dieser Dokument-ID. Zustimmung ist nie vorausgewählt.

Beim Absenden werden Konfiguration und Dokumente innerhalb der Konto-/Auftrags-
Transaktion erneut gelesen und gesperrt. Der Browser muss genau die angezeigten
IDs und Hashes sowie die ausdrückliche Bestätigung übertragen. Eine inzwischen
geänderte Fassung oder Pflichtdokumentliste verlangt ein neu geladenes Formular.
Das ist ein technischer Nachweis der bestätigten Fassung, kein Beweis tatsächlicher
Lektüre und keine automatische Rechtsfreigabe.

Migration `011_contract_receipts.sql` fügt ausschließlich eine neue Tabelle hinzu.
Ein Receipt bindet Konto, Vorgang, Zeitpunkt und Dokumenthash. Checkout-Receipts
verweisen mit Fremdschlüssel auf den tatsächlichen Zahlungsauftrag. Zwei neue
Bestellungen erzeugen getrennte Nachweise; die Wiederholung derselben bereits
verarbeiteten Bestellanfrage verwendet den vorhandenen Auftrag und seine Belege.
Fehler rollen Konto/Bestellung und Receipts gemeinsam zurück.

Die Registrierung, Bump-/Premium-/Bannerbestellungen, Auktionsgebühren,
Festpreis- und Verhandlungsverträge sowie Händler-Abos verwenden versionierte
Dokumente und Nachweise. Neue Live-Treuhandverträge benötigen die dafür
festgelegten Dokumente bei ihrer späteren Providerintegration.

Mit **Aktuelle Pflichtdokumente für bestehende Konten verlangen**
(`legal_existing_account_required`) greifen die aktuellen freigegebenen
Registrierungsdokumente auch vor neuen Marktplatzvorgängen bereits registrierter
Benutzer. Das umfasst insbesondere neue Angebote, Gebote, Sofortkäufe,
Bestellungen und Veröffentlichungen. Vor weiteren Vorgängen führt GunIndex
zur geschützten Bestätigungsseite. Benutzer bestätigen nur aktuelle Fassungen,
für die ihrem Konto ein exakter Nachweis fehlt. Die neuen Nachweise erscheinen
als `updated_terms` mit Zeitpunkt, Dokument-ID und Hash. Frühere Belege bleiben
erhalten. Rechnungen, Datenschutzfunktionen, Abmeldung und die Abwicklung
bereits bestehender Verträge bleiben erreichbar. Die Einstellung ist anfänglich
deaktiviert. Zur Aktivierung sind die veröffentlichten Pflichtfassungen in
allen unterstützten Sprachen erforderlich.

Die Zahlungsauftragsansicht verlinkt die zugehörigen angenommenen Fassungen.
Exakte Versionslinks und Downloads bleiben nach einer neuen Veröffentlichung
erhalten; auch die allgemeine Zustimmungsseite prüft die tatsächlich angezeigte
ID und den Hash. Manipulierte Dokumentinhalte werden nicht als gültig ausgegeben.
Der passwortgeschützte Datenauszug enthält die eigenen Vertragsbelege. Keine IP-
Adresse oder Browserkennung wird dafür neu gespeichert. Aufbewahrungsfristen und
Zugriffsorganisation bleiben durch das Legal-Team festzulegen; kein automatisches
Löschen vorhandener Nachweise.

Neue Bedien- und Fehlermeldungen: acht Sprachkataloge unter `locales/consent/`.
Rechtstexte selbst werden weder automatisch übersetzt noch freigegeben.
Ausführungsnachweise stehen in TESTING.md und IMPLEMENTATION_PROGRESS.md.

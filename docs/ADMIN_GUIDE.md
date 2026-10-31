# Administration und konfigurierbarer Auftritt

Bei jeder Anfrage werden der aktuelle Benutzer und seine Adminberechtigung
einschließlich verpflichtender Mehrfaktor-Authentifizierung geprüft. Ein alter
Sessionflag genügt nicht. Ein privilegierter Standardaccount wird nicht angelegt.

Unter **Marke & Website** sind Website-Name, Claim, Startseitenüberschrift und
-text, öffentliche Kontakt-E-Mail, zwei Akzentfarben, Animationsintensität,
Registrierungsverfügbarkeit und ein Wartungshinweis änderbar. Farben akzeptieren
nur sechsstellige Hexwerte. Es gibt keine Felder für ausführbaren Code, beliebige
CSS-Regeln, Dateipfade oder Zahlungsgeheimnisse. Die Systemeinstellung für
reduzierte Bewegung hat Vorrang. Ein Wartungshinweis sperrt keine laufenden
Auktionen und beendet keine Sitzungen.

Eine Versionsprüfsumme schützt vor dem stillen Überschreiben paralleler
Änderungen. Bei einem Konflikt bleiben die eigenen Formulareingaben sichtbar;
der aktuelle Stand muss vor erneutem Speichern neu geladen werden. Änderungen
und Protokolleintrag liegen in derselben Datenbanktransaktion. Das Protokoll
enthält die geänderten Schlüsselnamen und keine Geheimniswerte.

Die konfigurierbaren Preise sind Integer-Cent und beschreiben zunächst geplante
Plattformleistungen. Ihre Änderung aktiviert weder Checkout noch Premiumdienste.
Historische verbindliche Buchungen dürfen nicht rückwirkend verändert werden.

Haupt- und Unterkategorien sind ohne Codeänderung anlegbar. Name, eindeutige
Kennung, Oberkategorie, Sortierung und Aktivierung sind bearbeitbar. Ein
deaktivierter Vorfahr sperrt auch Nachfahren. Zyklische Zuordnungen, fehlende
Eltern und doppelte Kennungen werden abgewiesen. Kategorien erteilen keine
rechtliche Produktfreigabe. Der allgemeine Katalog enthält keine Waffenkategorien;
historische spezialisierte Angebote werden nicht umetikettiert. Ein Baumlimit
von 1000 Kategorien begrenzt Ressourcenverbrauch. Die zusätzliche Wortprüfung
ist eine Plattformregel, keine vollständige Rechtsklassifizierung oder Moderation.

Die paginierte Benutzerübersicht zeigt notwendige Kontaktdaten nur im geschützten
Bereich. Fremde Kontositzungen können mit POST und CSRF widerrufen werden; dabei
wird `auth_version` erhöht und ein Audit-Eintrag geschrieben. Es gibt keine freie
Rollenvergabe, pauschale Verifikation oder physische Kontolöschung. Das eigene
Profil besitzt seine separate Sitzungs- und Passwortverwaltung.

Das Änderungsprotokoll ist lesbar und paginiert. Es ist nicht automatisch
unveränderlich oder revisionssicher: Getrennte Aufbewahrung, minimale DB-Rechte
und Überwachung bleiben erforderlich. Die Kennzahlen stammen aus tatsächlichen
Tabellen. Abschlusskonflikte aus `market_listings.status='review'` erscheinen als
konkrete Zahl; bestehende Gebote werden nicht stillschweigend korrigiert.

Zahlungsbetrieb und Dokumentensammlung werden nicht durch einen Adminlogin
freigegeben. Vollständige Rollenverwaltung, DSA-Fallbearbeitung, Beschwerdeverfahren
und ein Rechtstextredaktionssystem sind weitere Arbeitsschritte.

Die allgemeine Inseratsübersicht bietet paginierte Statusfilter und eine
Detailansicht mit öffentlichen Auktionsereignissen. Private Maximalgebote werden
nicht angezeigt; Quarantänefälle lassen sich hier nicht unkontrolliert wieder
freischalten oder mit einem anderen Gewinner versehen. Bewertungen lassen sich
mit einer dokumentierten Begründung ein- oder ausblenden. Eine endgültige Löschung
ist nicht vorgesehen. Die Moderation benötigt POST, CSRF, Admin-MFA und aktuelle
Datenbankrechte; die Entscheidung und das Audit liegen in derselben Transaktion.

Unter **Zahlungseingänge** liegen die manuellen PayPal-Aufträge mit Betrag, Referenz und Status. Die Bestätigung benötigt eine tatsächliche Transaktionskennung und gleicht Betrag und Referenz ab. Empfänger, Verfügbarkeit und geprüfte Kontoeignung werden unter Marke & Website konfiguriert; API-Geheimnisse bleiben im Prozessumfeld. Siehe MANUAL_PAYMENTS.md.

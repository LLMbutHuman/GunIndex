# GunIndex-Übergabe vom 26. November 2026

## Übergabe und Testnachweise

- Für eine persönliche Abnahme auf einem Testserver steht der geprüfte Commit `43dbf6263dab8879e5ed12c26652bbcc120fdf7d` im Entwicklungsbranch `feature/general-marketplace-rebuild` bereit. [Quellstand](https://github.com/LLMbutHuman/GunIndex/commit/43dbf6263dab8879e5ed12c26652bbcc120fdf7d)
- Für diesen Commit sind MySQL, HTTP, Chromium sowie der verschlüsselte Chat browserübergreifend mit Firefox und WebKit auf Linux geprüft. [Marktplatzregression](https://github.com/LLMbutHuman/GunIndex/actions/runs/45751150875)
- Das Produktionsimage und die isolierte Ubuntu-22.04-Neuinstallation mit Update, elf PLZ-Quellen, Admin-Einrichtung, TLS-Probe, Timern und Datenerhalt sind grün. [Ubuntu- und Image-Test](https://github.com/LLMbutHuman/GunIndex/actions/runs/45751150681)
- Der Folgecommit `89939de` ergänzt die achtsprachige Darstellung der Eurobeträge im Checkout. Lokal bestanden 22 Zahlungs- und zehn Geoprüfungen. Der zugehörige Integrationslauf läuft separat. [GitHub Actions](https://github.com/LLMbutHuman/GunIndex/actions/runs/45824852437)
- Von den 26 nummerierten Anforderungen sind 17 technisch geprüft, sieben implementiert mit ausstehender externer Abnahme und zwei teilweise offen. Die Zahlen zählen Anforderungen. Der Aufwand der Einzelfunktionen unterscheidet sich. [Abnahmematrix](TECHNICAL_ACCEPTANCE.md)
- Der Termin für die Produktionsübergabe hängt von Zielserver, Betreiberangaben, den gewählten Zahlungswegen, den fachlichen Freigaben und den offenen technischen Nachweisen ab. Ein festes Datum ist dafür noch nicht belastbar.

## Implementiert und geprüft

### Marktplatz und Suche

- Öffentlicher Marktplatz für allgemeine Waren mit hierarchischen Kategorien, Ländersteuerung und Regeln für den zulässigen Katalog.
- Kategoriebezogene Pflicht- und Auswahlfelder, Texte, Zahlen und Ja/Nein-Merkmale. Die Suche und Produktseiten nutzen dieselben strukturierten Werte.
- Festpreis-, Verhandlungs- und Auktionsinserate mit privatem Autosave, Vorschau, Bearbeitung, Pause, Verkauf und Archiv. Die Veröffentlichung bindet aktuelle Regeln und gegebenenfalls den bestätigten Tarif.
- Bild-Uploads mit WebP-Aufbereitung, privater Entwurfsvorschau und veränderbarer Bildreihenfolge.
- Wiedereinstellen geschlossener Festpreis- und Verhandlungsinserate, neuer Auktionsdurchlauf nach abgeschlossenem Verkauf, einmal kostenloser Nachfolger nach erfolgloser Auktion und begrenzte einmalige Laufzeitverlängerung.
- Volltextsuche nach Relevanz, Titelsuche mit Vorschlägen sowie kombinierbare Filter für Kategorie, Produktmerkmale, Angebotsform, Preis, Land, PLZ und Umkreis.
- PLZ-Koordinaten und Quellenanzeige für Deutschland, Frankreich, Österreich, Schweiz, Liechtenstein, Luxemburg, Tschechien, Belgien, Polen, Dänemark und die Niederlande. Die exakte PLZ-Suche funktioniert auch bei gültigen Codes ohne Koordinate. [Quellen und Prüfsummen](POSTCODE_SOURCES.md)
- Merkliste, freiwilliger Verlauf zuletzt angesehener Angebote und Vergleich von bis zu vier Angeboten in der aktuellen Sitzung.
- Gesuche, gespeicherte Suchen, automatische Zuordnung passender Angebote, Soforthinweise und tägliche Zusammenfassungen.

### Auktionen, Handel und Finanzen

- Proxygebote mit privatem Maximalgebot, festem Gleichstandsverhalten, Verlängerung bei späten Geboten, Sofortkauf vor dem ersten Gebot und unabhängigem Auktionsabschluss.
- Centgenaue Einstellgebühr und gestaffelte Erfolgsprovision mit bestätigtem Gebührenstand, eindeutigen Forderungen, Teilkorrekturen, Erlass und Belegübersicht pro Auktion.
- Festpreis-Checkout nach ausdrücklicher Verkäuferfreigabe, Reservierung gegen Doppelverkauf sowie private Angebote, Gegenangebote, Annahme, Ablehnung, Rückzug und Ablauf.
- Versand oder Abholung, Empfang, Abschluss, Storno und moderierte Streitfälle. Der Abschluss kann den von einer Partei erklärten externen Zahlungsvorgang abbilden. [Handelsablauf](TRADE_IMPLEMENTATION_CONTRACT.md)
- Bewertungen nach abgeschlossener Auktion oder Transaktion, einmal je berechtigter Partei.
- Zentrale PayPal-Empfängeradresse, eindeutige Zahlungsreferenzen, serverseitig ermittelte Beträge, Personalprüfung des Zahlungseingangs und einmalige Freigabe der gebuchten Plattformleistung. Kauf- und Zahlungsnachweise sind getrennt.
- Bestellung von einzelnen Bumps oder Dreierpaketen mit 72-Stunden-Abstand je Inserat. Bezahlte Hervorhebung und Werbung starten nach eigener, versionierter Zustimmung.
- Checkout mit nachweisbarem Wortlaut, Sprache und Preis. Neue Bestellungen verlangen eine aktuelle Zustimmung, wenn sich Preis oder Fassung geändert haben. Die Bestellansicht zeigt die ursprünglich akzeptierte Fassung.
- Rechnungsanschrift, Steuerregeln je Rechnungsland, eindeutige Belegnummern, unveränderliche Snapshots, PDF-Download, unbezahlte Stornierungen und dokumentierte Teil- sowie Vollerstattungen mit Korrekturbelegen.
- Automatischer PayPal-Checkout, Ereignisprüfung und Erstattungsaufträge mit Testantworten. Banküberweisung mit CAMT-Kontoauszugsabgleich und Bitcoin mit einer isolierten Bitcoin-Core-Wallet technisch geprüft. Die echten Anbieterabnahmen stehen unten.
- Treuhand-Simulation für Angebot, Gegenangebot, vertrauliche Testkontakte, Zahlungsnachweis, Versand, Empfang, Streit, Fristablauf, Erstattung, Freigabe und Rückbelastung. [Technischer Treuhandablauf](ESCROW.md)

### Händler und Werbung

- Händlerprofil mit überprüften Geschäftsangaben und Logo. Änderungen der veröffentlichten Angaben lösen eine erneute Prüfung aus.
- Händlerübersicht mit eigenen Inseraten, Kennzahlen, Massenaktionen, dublettengeprüftem CSV-Import und CSV-Export.
- Mitarbeiterkonten und Rollen, Warenbestand und Bestandsbewegungen. Der integrierte Checkout bucht den Bestand transaktionsbezogen.
- Private Händler-API für Bestand, Bestellungen und Bestandsbewegungen mit befristeten, widerrufbaren Berechtigungen. [API-Umfang](DEALER_API.md)
- Vorausbezahltes Händlerabo für befristeten CSV-Bestandsexport mit ausdrücklicher Verlängerung. [Abo-Umfang](DEALER_SUBSCRIPTION.md)
- Begrenzte Kategorie- und Startseitenplätze, landesbezogene Händlerbanner, Anzeigenprüfung sowie deduplizierte Klick- und Sichtbarkeitskennzahlen. Reservierungen, Laufzeiten und verspäteter Geldeingang sind getestet.

### Konten, Kommunikation und Verwaltung

- Registrierung, E-Mail-Verifikation, Passwortreset, Passwortänderung, Profile, Profilbilder, Sitzungsübersicht und gezielter Sitzungswiderruf.
- Optionale TOTP-2FA für Nutzer, einmalige Wiederherstellungscodes, Passkeys und verpflichtende MFA für berechtigtes Personal.
- Inseratsbezogener Chat mit Teilnehmerprüfung, Blockieren, Melden und bestehendem serverseitig verschlüsseltem Verlauf.
- Ende-zu-Ende-verschlüsselter Chat mit eigenen Geräteschlüsseln, Fingerabdruckprüfung, mehreren Geräten, Widerruf, Wiederherstellung, Schlüsselwechsel und Ersatz einer kompromittierten Kontoautorität. [Protokoll und Abnahme](E2EE_PROTOCOL.md)
- Ende-zu-Ende-verschlüsselte Bilder, Dokumente und Dateien mit Teilnehmerberechtigungen, Größen- und Speichergrenzen, Integritätsprüfung und geschütztem Download.
- Hinweise für Gebote, Auktionsende, Änderungen, Ablauf, Suchtreffer, Nachrichten, Moderation, Zahlungen und Belege mit In-App- und optionalem E-Mail-Kanal. Entscheidungen zu Meldungen und Beschwerden verwenden die Kontosprache der Beteiligten.
- Meldungen, Fallzuweisung, private Belege, dokumentierte Moderation und Beschwerden. Personalrollen trennen Support, Moderation, Finanzen, Verifikation und Administration.
- Datenschutzexport, Deaktivierung, Löschantrag und kontrollierte Anonymisierung unter Erhalt vertraglich notwendiger Datensätze. Technisch geschützte Identitäts- und Altersprüfung mit privater Verschlüsselung, begründeter Einsicht und Aufbewahrungssteuerung.
- Rechtsverwaltung mit 20 Entwürfen in Deutsch, Englisch, Französisch, Niederländisch, Polnisch, Tschechisch, Dänisch und Italienisch. Sammelimport, JSON-Download, Versionsschutz, Sprachübersicht und explizite Bindung an Registrierung, Checkout und erneute Zustimmung.
- Acht Sprachkataloge für geprüfte öffentliche und private Abläufe, Admin-Einrichtung, Checkout und Systemmeldungen. Nutzereingaben behalten ihre Originalsprache.

### Installation und Betrieb

- Responsives Design mit Hell- und Dunkelmodus, reduzierter Bewegung und automatisiert geprüften Tastatur- und Mobilabläufen.
- Mehrsprachige öffentliche SEO-Adressen, Canonicals, `hreflang`, strukturierte Daten, Sitemap, Robots- und Indexierungsfreigabe.
- MFA-geschütztes Admin-Dashboard mit 18 Einrichtungsschritten. Verlinkte Einstellungsseiten verwalten die jeweiligen Werte und Berechtigungen.
- Mail-Assistent mit lokalem MTA und SMTP-Relay, geschützten Zugangsdaten, TLS, SPF-/DKIM-/DMARC-Diagnose, Testversand, Warteschlange und Wiederholung bei Fehlern.
- Tatsächlich sperrender Wartungsmodus mit Worker-Schutz, Auktions- und Werbefristen sowie getrennter Behandlung bereits begonnener Zahlungen.
- Ausführbarer Ubuntu-22.04-Installer mit Erstinstallation, sicheren Schlüsseln, interaktiver erster Admin-Einrichtung, Update, privaten Speicherpfaden, Scheduler und sieben überwachten Workern.
- Healthchecks, Timer, verschlüsselte Datenbank- und Dateisicherungen, Integritätsprüfung, Wiederherstellung in eine getrennte Umgebung und geprüfter SFTP-Offsite-Transfer. [Installation](UBUNTU_22_04.md) · [Sicherung und Wiederherstellung](BACKUP_RESTORE.md)
- Kontrollierter Altkontenimport mit Dry-Run und Kollisionsprüfung. Die tatsächlichen Altdaten sind ein separater Abnahmepunkt. [Altdaten](LEGACY_MIGRATION.md)

## Von dir auf dem Zielsystem zu testen

- Installation auf dem vorgesehenen Ubuntu-Host, erste Admin-Anmeldung mit deiner MFA-App, Rechtevergabe, Rechteentzug und Zugriff auf alle benötigten Einstellungen.
- Aufruf mit deiner Domain, gültigem HTTPS-Zertifikat, Weiterleitung, Zertifikatserneuerung und vorgesehenen Privatsphäre-Einstellungen.
- Zwei Testkonten erstellen und Verifikations-, Passwortreset- und Benachrichtigungsmails im tatsächlichen Posteingang prüfen. SMTP-Annahme und DNS-Prüfung sind eigene Nachweise.
- Je ein Festpreis-, Verhandlungs- und Auktionsangebot einstellen. Preis, Bildreihenfolge, Vorschau, Kauf, Versand oder Abholung, Storno, Bewertung und Belege mit zwei Konten nachvollziehen.
- Händlerprofil mit Testbetrieb freigeben, Teamrolle prüfen, exemplarisch Bestand ändern und eine CSV importieren oder exportieren. Die eigenen Produktkategorien und Texte prüfen.
- Auf deinen Handys und Rechnern mit den vorgesehenen Browsern testen. Besonders Safari auf macOS/iOS, Passkeys mit deinem Authenticator sowie E2EE mit zwei Geräten, Wiederherstellung, Widerruf und Anhängen prüfen.
- Eine PLZ und einen Umkreis aus den von dir vorgesehenen Ländern prüfen. Eigene Startseiten-, Checkout-, Hilfs- und Rechtstexte auf Mobilgeräten stichprobenartig lesen.
- Wartungsmodus, Timer, Monitoringalarm und Neustart auf deinem Host prüfen. Eine verschlüsselte Kopie am getrennten Ziel ablegen und in eine frische Testumgebung zurückspielen.
- Mit geeigneten Testzugängen PayPal-Zahlung, Rückerstattung, Rechnung, Bankauszug und gegebenenfalls Bitcoin technisch abnehmen. Die produktiven Schalter erst nach den jeweiligen tatsächlichen Freigaben setzen.

## Noch offen für die vollständige Übergabe

- Vollständiger Sprach- und Formatabgleich aller verbleibenden Ansichten, dynamischen Fehlertexte und Systemmails. Die aktuelle Nummer 5 bleibt teilweise offen.
- Betreiberfassungen der Rechtsdokumente mit tatsächlichen Betreiberangaben, Gebühren, Steuern, Aufbewahrung und freigegebenen Ländern. Freigabe und Gültigkeitsdatum vor Veröffentlichung durch das Legal-Team dokumentieren.
- Unabhängige E2EE- und Anwendungssicherheitsprüfung, native Apple-Browserabnahme und Wiederherstellung des privaten Chatbestands auf dem Zielhost. Der alte Verlauf verwendet serverseitige Verschlüsselung.
- Echte PayPal-Sandbox und getrennte Freigabe für Live-Zahlungen und Erstattungen, Kontoauszugsformat des gewählten Bankkontos und gegebenenfalls freigegebene Bitcoin-Mainnet-Infrastruktur.
- Geeigneter Treuhandanbieter und produktiver Adapter für bestätigten Eingang, Verwahrung, Auszahlung, Erstattung und Rückbelastung. Teillieferungen und Teilfreigaben benötigen ein konkretes Geschäftsmodell und Implementierung, falls sie vorgesehen sind.
- Zielserver mit Domain/DNS, TLS, SMTP-Relay oder lokalem Mailserver, getrenntem Backupziel, Schlüsselverwahrung, Alarmierung, Lastprüfung, Barrierefreiheit und finaler Betreiberabnahme.
- Falls du auch lokal unter Windows entwickeln willst, braucht dein Rechner eine einsatzfähige WSL-2-Ubuntu-Distribution und eine Linux-Containerlaufzeit. Die integrierten Linux-Tests laufen auf GitHub. [Windows-Vorbereitung](WINDOWS_DEVELOPMENT.md)
- Historische Schlüssel und Altdaten prüfen. Falls du Altbestände übernehmen willst, zuerst Bestandszuordnung und Wiederherstellung in einer getrennten Testumgebung abnehmen.

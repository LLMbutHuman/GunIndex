# Release-Checkliste

Aktueller Abgleich aller 26 Masteranforderungen mit A/B/C-Einstufung:
[Technischer Abnahmestand](TECHNICAL_ACCEPTANCE.md).

Technisch erweiterter Entwicklungsstand, **keine Produktionsfreigabe**. Vorhandene Funktionen und ausgeführte lokale Prüfungen stehen im [Implementierungsbericht](IMPLEMENTATION_REPORT.md) und unter [Tests](TESTING.md). Einzelne bestandene synthetische Abläufe ersetzen keine Abnahme des tatsächlichen Betriebs.

## Technisch vorhanden, fachlich und betrieblich abzunehmen

Kontoverifikation/Reset, lokale Benachrichtigungen, Gesuche/Matching/Suchalarme, Länder-/Radiusfilter, Händler-/CSV-Verwaltung, begrenzte Platzierungen/Banner, Rechnungsbelege/PDFs, dokumentierte Korrekturen, Personalrollen, Meldungen/Beschwerden, Datenschutzabläufe und versionierte Rechtsentwürfe sind implementiert. Identitätsprüfung, Zahlungen, Werbung, Finanzdokumente und Rechtsveröffentlichung haben standardmäßig geschlossene Freigaben. Chat speichert neue Texte serverseitig AEAD-verschlüsselt und bietet keine E2EE.

Die folgenden Punkte sind vor einem jeweiligen Produktivbetrieb tatsächlich nachzuweisen:

## Bestand und Datenbank

- [ ] Historische Geheimnisse rotiert; Datenherkunft, Retention, Zugriff und alte Git-History bewertet.
- [ ] Verschlüsseltes Backup von Datenbank und privaten Dateien vorhanden; Schlüssel getrennt gesichert und Wiederherstellung überprüft.
- [ ] Basisschema ausschließlich für leere Datenbank verwendet; unveränderte Migrationen bis 011 kontrolliert angewendet.
- [ ] Tatsächliches Altkontenschema per Dry-run und Staging geprüft; Kollisionen/ID-Zuordnung und Passwortresetprozess abgenommen.
- [ ] Historische Angebote, Gebote, Nachrichten, Dokumente und Finanzen getrennt zugeordnet; keine ungeprüfte Übernahme.
- [ ] Vorhandene allgemeine Klartextchats nach geprüftem Backup kontrolliert verschlüsselt; Schlüsselrotation/Recovery geprobt.

## Betreiber, Länder und Recht

- [ ] Tatsächliche Betreiberidentität, Anschrift, Impressum, Steuerangaben, Geschäftsmodell und Versicherungsbedarf geprüft.
- [ ] Zwanzig Rechtsentwürfe fachlich geprüft, Pflichtfelder ersetzt, passende Fassungen/Freigabeperson und Gültigkeit dokumentiert.
- [ ] Relevante Zustimmungen und Bestellbedingungen im realen Vertragsablauf geprüft; keine vorausgewählten notwendigen Erklärungen.
- [ ] Verbraucherrechte, Leistungsbeginn, Widerruf, Erstattung und Kaufvertrag zwischen Nutzern fachlich abgenommen.
- [ ] Aktive Zielländer hinsichtlich Verbraucherrecht, Steuern, Datenschutz, Zoll und Versand tatsächlich freigegeben.
- [ ] Erforderliche Rechtsübersetzungen geprüft; deutsche UI-Fallbacks und tatsächlicher Umfang der acht Kernsprachen transparent.
- [ ] Lizenz/Herkunft jedes produktiven PLZ-Datensatzes bestätigt und importiert; echte Entfernungssuche ohne fehlende Koordinaten geprüft.
- [ ] Produktregeln und fachliches Moderationsverfahren für allgemeine Waren geprüft; regulierte Waren bleiben ausgeschlossen.

## Zahlungen, Werbung und Finanzen

- [ ] Eignung des tatsächlichen PayPal-Kontos, Zahlungsbuttons und Geschäftszwecks bestätigt; keine Umgehung über private Zahlungen.
- [ ] Manueller Eingang mit echtem Empfänger/Betrag/Referenz abgeglichen und Berechtigungs-/Vieraugenbedarf organisatorisch festgelegt.
- [ ] Automatische PayPal-Integration bei geplantem Einsatz mit echten Sandbox-Zugangsdaten abgenommen; Live nur gesondert freigegeben.
- [ ] Finanzfunktion erst nach tatsächlichen Betreiberangaben und fachlich bestätigten Ländersteuerregeln aktiviert.
- [ ] Rechnungs-/PDFnummern, unveränderliche Snapshots, unbezahlter Storno, Teil-/Vollerstatten und reale Providerreferenzen in Staging geprüft.
- [ ] Automatische Einstell-/Abschlussgebühren an Auktionen angebunden und vollständig getestet, bevor solche Forderungen produktiv erhoben werden.
- [ ] Platzlimits, Reservierungsablauf, verspätete Zahlung, bewusster Leistungsbeginn und Werbeprüfung mit echten Betriebsregeln abgenommen.
- [ ] Gesetzlich erforderliche Aufbewahrung und Buchhaltungsexport mit Steuerberatung geklärt.
- [ ] Banküberweisung, Bitcoin und Treuhand bleiben deaktiviert, bis separate technische/fachliche Freigaben und echte Infrastruktur vorliegen.

## Personal und Datenschutz

- [ ] Erstadministration und begrenzte Personalrollen mit MFA eingerichtet; Rechteentzug und Account-Recovery geprobt.
- [ ] Meldungs-/Beschwerdezuständigkeiten, DSA-/P2B-Anwendbarkeit, Fristen und externe Streitwege fachlich festgelegt.
- [ ] Export, Kontodeaktivierung, betreute Anonymisierung und Fälle mit offenen Geschäften anhand realer Datenflüsse abgenommen.
- [ ] Identitätsprüfung nur bei tatsächlichem Bedarf/Fachfreigabe und geprüfter Frist aktiviert; Einsichtsrechte und Retentionjob überwacht.
- [ ] Aufbewahrung für Audit, Mail-/Werbemetriken, private Dokumente und Sicherungen festgelegt; keine unbelegte vollständige Löschzusage.

## Zielserver und Betriebsabnahme

- [ ] [Ubuntu-22.04-Installation](UBUNTU_22_04.md) auf dem tatsächlichen Server geprüft; sauberen Releasecommit und Konfigurationszustand dokumentiert.
- [ ] HTTPS, sichere Cookies, private Datenbank, Dateirechte, SSH, Firewall und Rate-Limits überprüft.
- [ ] Eigene Mail-DNS-/Absender-/Relaykonfiguration eingerichtet; echte Verifikations-/Resetmail im Empfängerpostfach geprüft.
- [ ] Auktions-, Mail-, Discovery-, Werbe- und Privacyworker mit Heartbeats und sinnvollen Wiederholungen überwacht.
- [ ] Healthchecks, konkrete Alarmzustellung, Logrotation, Incidentkontakt und Wartung/Updates eingerichtet.
- [ ] Offsiteziel mit verifiziertem SSH-Hostkey und getrennten Schlüsseln eingerichtet; tatsächlichen Restore aus Offsite geprobt.
- [ ] Produktivdaten und Schlüssel nach Restore konsistent; Zugriffstokens/Sitzungen bei erforderlicher Wiederherstellung widerrufen.

## Qualität und Freigabe

- [ ] Aktuelle automatisierte Tests auf dem exakten Releasecommit ausgeführt und Ergebnisse dokumentiert.
- [ ] Repräsentative Browserabläufe für Konten, Handel, Länder, Nachrichten, Personal, Abrechnung und Datenschutz abgeschlossen.
- [ ] Desktop/Tablet/Mobil, Light/Dark, reduzierte Bewegung, Tastatur und umfassende Screenreaderbedienung geprüft.
- [ ] SEO, private Noindex-Bereiche, Core Web Vitals, realistische Last und Missbrauchsgrenzen auf dem Zielsystem abgenommen.
- [ ] Unabhängige Sicherheitsprüfung und verbleibende Befunde priorisiert; keine offenen kritischen Befunde vor Launch.
- [ ] Verbleibende Einschränkungen, aktivierte Gates, tatsächlich freigegebene Länder/Dienste und Rückfallplan dokumentiert.
- [ ] Schlussabnahme durch verantwortlichen Betreiber erfolgt.

Eine technisch gespeicherte Freigabe oder ein aktiviertes Feature-Flag beweist keine tatsächlich erteilte externe Genehmigung.

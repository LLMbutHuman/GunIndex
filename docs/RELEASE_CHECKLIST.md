# Release-Checkliste

Entwicklungsstand, keine Produktionsfreigabe.

- [ ] Historische Geheimnisse rotiert, Datenherkunft/Retention/alte Git-History geprüft.
- [ ] Benutzerbestandsmigration für das tatsächlich vorhandene Schema getestet.
- [ ] Betreiberidentität, Steuern, Impressum, Verträge, Datenschutz und Widerrufsprozess fachlich geprüft.
- [ ] Tatsächliche Zulässigkeit des PayPal-Kontos und Zahlungsbuttons bestätigt; echter Testlauf mit Providerdaten dokumentiert.
- [ ] Vollständige Rechnungs-/Storno-/Erstattungsprozesse fertiggestellt.
- [ ] Produktiver Webserver, TLS, Least Privilege, Rate-Limits und Monitoring geprüft.
- [ ] Getrennte Schlüsselverwahrung, Offsite-Backup, private Bilder und produktiver Restore geprobt.
- [ ] Vollständige Moderatorrollen, Meldungs-/Beschwerdeverfahren und Datenexport/-löschung bereitgestellt.
- [ ] Gesuche, Matching, Benachrichtigungen und noch offene Geschäftsleistungen vollständig integriert.
- [ ] Browser-/Tastatur-/Screenreader-/Last-/Core-Web-Vitals-Prüfungen unter repräsentativen Bedingungen bestanden.
- [ ] Abschlussabnahme anhand tatsächlichen Commitstands und Testergebnissen erfolgt.

Lokale Tests und eine gespeicherte Cloud-Konfiguration ersetzen keinen dieser produktiven Freigaben.

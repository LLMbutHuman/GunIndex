# Technischer Abnahmestand – 2026-11-02

Gesamtplattform **nicht fertig und nicht produktiv abgenommen**.
Allgemeiner Warenmarktplatz; keine Aktivierung historischer Spezialkategorien.

**A:** benannte Funktion implementiert und aktuell getestet.
**B:** Integration implementiert; externer Nachweis fehlt.
**C:** Gesamtanforderung offen, teilweise implementiert oder nicht vollständig geprüft.

## Belegte Teilfunktionen

- **A:** Admin-Setup-Diagnosen (18 Bereiche), Bildsortierung mit Revisions-/
  Eigentümerschutz, SEO-Grundfunktionen und PLZ-Validator: `2d496a8`, CI 41418425597.
- **A:** Sitzungsspezifischer Opt-in-Verlauf und Produktvergleich: `126d400`,
  CI 41420258463; ebenfalls echte Bildsortierung und acht mobile Setup-Sprachen.
- **A:** Dokumentbindung bei Registrierung und bestehenden Leistungsbestellungen:
  `dc046ae`, CI 41478575081; Migration 011, 14 Unit-, elf DB-/HTTP-Fälle und
  mobiler Browserablauf. Benötigt konfigurierte, fachlich freigegebene Fassungen.
- **B:** PayPal-REST-Anbindung; synthetische Adaptertests vorhanden, echte
  Sandbox-/Providerabnahme und Live-Freigabe fehlen.
- **A:** Automatischer Finanzabschluss mit synthetischem Provideradapter:
  `699c35b`, CI 41480002310 vollständig erfolgreich. Auch der echte Paralleltest
  mit zwei getrennten Prozessen besteht: `0194d59`, CI 41481388425.
  Keine reale Providerabnahme.
- **C:** Lokale WSL-/Docker-Laufzeit: Speicherplatz/Neustart ausstehend.
  Die externe Linux-CI ist funktionsfähig.

## Die 26 Masteranforderungen

Die Einstufung bezieht sich auf den **gesamten** Punkt. C bedeutet nicht, dass
keine Grundlagen vorhanden sind. Korrekte bestehende Implementierungen bleiben erhalten.

| Nr. | Anforderung | Stand | Restumfang |
| --- | --- | --- | --- |
| 1 | Auktions-Einstellgebühr | C | Verbindlicher Auftrag je Durchlauf und automatische Forderung |
| 2 | Auktions-Erfolgsprovision | C | Zuschlag/Sofortkauf, Buchung und Wiederholungsschutz |
| 3 | Gebühren, Korrekturen, Rechnungen | C | Unbezahlte Forderungen, Gegenbuchungen, Bestandsaufträge |
| 4 | Rechtsdokumentbindung | C | Bestehende Registrierungs-/Leistungsabläufe geprüft; neue Transaktionsarten und Pflichtzustimmung für Bestandskonten offen |
| 5 | Vollständige Übersetzungen | C | Altseiten, Validierungen, Finanz-/Kontobereiche und ungeprüfte Rechtsfassungen |
| 6 | Kategorieattribute | C | Typisierte Verwaltung und Validierung |
| 7 | Produktklassifizierung | C | Durchgängige strukturierte Eingabe, Suche und Darstellung |
| 8 | Autocomplete/Relevanz/Filter | C | Erweiterung und kombinierte End-to-End-Prüfung |
| 9 | Entwurfs-Autosave/Vorschau | C | Automatische Speicherung und vollständige Vorschau |
| 10 | Bildreihenfolge | A | Implementiert und geprüft |
| 11 | Wiedereinstellen/Verlängern | C | Vollständiger Ablauf einschließlich einmaligem Gratisanspruch |
| 12 | Verhandlungsmodus | C | Eigenständige Verhandlung und Angebotszustände |
| 13 | Festpreis-Transaktion | C | Vollständiger Käufer-/Verkäuferablauf |
| 14 | Transaktionsbewertungen | C | Vorhandene Auktionsgrundlage auf weitere Transaktionen erweitern |
| 15 | Benachrichtigungen | C | Fehlende Preis-/Ablauf-/Vorablaufereignisse |
| 16 | Geräte/Einzelsitzungen | C | Übersicht und Einzelwiderruf |
| 17 | Benutzer-2FA/Passkeys | C | Optionale Benutzeranmeldung, Passkeys und Wiederherstellung |
| 18 | Profilbilder/Händlerlogos | C | Sicherer Upload, Quoten und Integration |
| 19 | Verlauf/Produktvergleich | A | Innerhalb dokumentierter Sitzungsgrenzen geprüft |
| 20 | Händlerfunktionen | C | Mitarbeiterrollen, Abos, Warenwirtschaft und API |
| 21 | Vollständiges SEO | C | Internationale URL-/hreflang-Strategie und vollständige Abnahme |
| 22 | Wartungsmodus | C | HTTP-/API-/Worker-Sperre und faire Auktionsfortsetzung |
| 23 | Automatische Rückerstattungen | C | Vollständiger Workflow, Statusabgleich und Providerabnahme |
| 24 | Bitcoin | C | Node-Anbindung, Bestätigungen, Reorganisationen und Infrastruktur |
| 25 | Banküberweisung | C | Kontenabgleich und verifizierte zuordenbare Eingänge |
| 26 | Treuhand | C | Vollständiger gesperrter Workflow und geeignete Providerinfrastruktur |

Zusätzlich offen: echte E2EE einschließlich Geräteschlüsseln und verschlüsselten
Anhängen; SMTP-/DNS-Assistent; geführter Ubuntu-Installer; vollständiger Admin-
Einrichtungsablauf; internationale Datenimporte; vollständige Installations-/
Restore-/Betriebsabnahme und Legal-Team-Übergabe. Bestehende serverseitige
Chatverschlüsselung ist **keine E2EE**. Neue Sprachmodule sind nicht mit einer
vollständigen Übersetzung sämtlicher Seiten gleichzusetzen.

Fachliche Rechts-/Steuer-/Länderprüfung, Providerzulassung, echte DNS-/Posteingangs-
zustellung und produktive Backup-/Restoreziele können synthetische Tests nicht
beweisen. Details: CONTRACT_BINDING.md, FINANCE.md, POSTCODE_SOURCES.md,
IMPLEMENTATION_PROGRESS.md und RELEASE_OPERATIONS.md.

Fachlicher Übergabestand: [LEGAL_HANDOFF.md](LEGAL_HANDOFF.md). Er beschreibt
vorhandene Implementierungen und Grenzen, nicht eine fertige Plattform.

# Umsetzungsbericht: allgemeiner Marktplatz

Der Entwicklungsbranch `feature/general-marketplace-rebuild` baut auf dem
wiederhergestellten Stand `7e66f55` und dem bereits hochgeladenen Basiscommit
`085e4bb` auf. Der aktive Marktplatz enthält allgemeine Waren; historische
spezialisierte Inserate und private Altdateien werden nicht öffentlich übernommen.
Die alte Git-History wurde nicht umgeschrieben. Dieser Bericht beschreibt
implementierte Abläufe und tatsächliche lokale Nachweise, keine Produktionsfreigabe.

## Implementiert und lokal getestet

- Sichere PHP-/PDO-Konfiguration, kontrollierter Webroot, Argon2id, Sessions,
  CSRF, Rate-Limits, verpflichtende Personal-MFA und aktuelle Rollenrechte.
- Konten, Profil und Passwortänderung; E-Mail-Verifikation und Passwortreset mit
  gehashten Einmaltokens, Ablauf, Widerruf und Schutz vor Kontoausspähung.
- Lokale verschlüsselte Mailqueue, Versand über eigenen MTA, Wiederholungen,
  Deduplizierung, In-App-Hinweise und Benutzerpräferenzen. Testtransport ist auf
  synthetische Entwicklungsdatenbanken begrenzt.
- Eigenständiges responsives Ink-/Mint-/Amber-Design, Light/Dark Mode,
  Animationen mit Pausetaste und Vorrang reduzierter Bewegung.
- Datenbankgestützte Kategorien, Inserate mit sicheren WebP-Bildern, Entwurf,
  Bearbeitung, Pause, Verkauf und Archiv; Suche, Merkliste und sparsame Profile.
- Proxyauktionen, private Maximalgebote, definierter Gleichstand, Anti-Sniping,
  Sofortkauf vor dem ersten Gebot und Zeilensperren. Fünf unabhängige Worker
  verarbeiten Auktionen, Mail, Suche, Premiumlaufzeiten und Datenschutz.
- Elf Ländermärkte, acht lokale Sprachkataloge, internationale Suche,
  länderspezifische Banner und lokale PLZ-/Radiussuche mit Luftlinienentfernung.
  Unbekannte oder nicht lizenzierte Koordinaten erzeugen keine erfundenen Kilometer.
- Gesuche, gespeicherte Suchen, lokale normalisierte Relevanzbewertung,
  Synonyme/Ähnlichkeit, Sofortalarme und tägliche deduplizierte Zusammenfassungen.
- Verschlüsselte Chatnachrichten mit Zwecktrennung und Schlüsselwechsel,
  paargebundener Zugriff, Blockieren, Cursorverlauf und lokales Polling.
  Dies ist serverseitige Verschlüsselung ruhender Daten, keine Ende-zu-Ende-Verschlüsselung.
- Bewertungen abgeschlossener Auktionen, Meldungen, Fallzuweisung, begründete
  Moderation, Einspruch und Rollen für Administration, Moderation, Support,
  Verifikation und Finanzen. Alle Personalzugänge benötigen MFA.
- Manuelle PayPal-Aufträge mit serverseitigem Gesamtbetrag, Referenz,
  Gebühren-/Bedingungssnapshot, tatsächlicher Betreiberprüfung und einmaliger
  Leistungsfreigabe. Kundenhinweise und Browserrückleitungen bestätigen keine Zahlung.
- Bump-Guthaben einzeln/im Dreierpaket mit 72-Stunden-Sperre; knappe Kategorie-,
  Startseiten- und Bannerplätze mit transaktionssicherer Reservierung, Ablauf,
  ausdrücklichem Laufzeitbeginn und Behandlung verspäteter Zahlung ohne Überbuchung.
- Öffentliche geprüfte Händlerprofile, private Bannerprüfung, lokale deduplizierte
  Klick-/Impressionszahlen, eigene Statistiken sowie berechtigungsgeprüfte
  Massenänderungen, CSV-Import mit Fehlerbericht/Dubletten und CSV-Export.
- Private Rechnungsanschriften, geprüfte länderspezifische Steuerregeln,
  unveränderliche verschlüsselte Auftrags-/Rechnungssnapshots, eindeutige
  Belegnummern und echte lokal erzeugte PDFs. Unbezahlter Storno, Erstattungsanfragen
  und dokumentierte tatsächliche Teil-/Vollerstattungen erzeugen Korrekturbelege.
  Der manuelle Erstattungsdialog versendet selbst kein Geld.
- Eigener Datenexport, Deaktivierungs-/Löschanfrage, überprüfte Anonymisierung
  unter Erhalt notwendiger Vertragsbelege und getrennte Identitäts-/Altersprüfung.
  Dokumente liegen verschlüsselt außerhalb des Webroots; Einsicht erfordert
  Zuweisung, MFA, Begründung und ein kurzlebiges Einmalrecht mit Audit.
- Zwanzig deutsche Rechtsentwürfe, unveränderliche Dokumentversionen/Hashes,
  dokumentierte fachliche Freigabe und ausdrückliche Zustimmungsnachweise.
- Zehn additive SQL-Migrationen, geprüfte verschlüsselte Sicherung vor Migration,
  idempotente nachträgliche Chatverschlüsselung und kontrollierter Legacy-Kontenimport.
- Ubuntu-22.04-Installer, gepinnte PHP-FPM-/nginx-/MySQL-Container, lokaler Postfix,
  TLS-Bootstrap, Health-/Monitoring-CLI, verschlüsselte DB-/Dateiarchive und
  vorbereiteter SSH-Offsite-Transfer. Anleitung: [UBUNTU_22_04.md](UBUNTU_22_04.md).

Die genauen Testzahlen und Messgrenzen stehen in [TESTING.md](TESTING.md).
Der vollständige Backendlauf, echte Browserabläufe und automatisierte öffentliche
Barrierefreiheitsprüfungen wurden ausgeführt. Es gab keine echten Zahlungen,
keinen Import produktiver Altbestände und kein Deployment auf dem Zielserver.

## Durch Konfiguration und externe Voraussetzungen begrenzt

Kostenpflichtige Dienste bleiben standardmäßig aus. Die manuelle PayPal-Methode
benötigt keine API-Schlüssel, aber ein für den tatsächlichen Geschäftszweck
geeignetes Empfängerkonto und geprüfte Steuer-/Bestellbedingungen. Ein Privatkonto
wird nicht technisch entgegen Providerregeln zum Händlerkonto erklärt.
Automatischer PayPal-Checkout und automatische Rückzahlungen sind getrennte,
noch nicht tatsächlich mit PayPal abgenommene Abläufe. Offline-Transporttests
beweisen keinen erfolgreichen Sandbox- oder Live-Zahlungseingang.

Vollständige Rechnungsausstellung verlangt echte Betreiber-/Steuerangaben,
Kundenanschrift und eine geprüfte Steuerregel für das Rechnungsland.
Identitätsdokumentensammlung verlangt Freigabe und Aufbewahrungsdauer.
Rechtsentwürfe bleiben bis zur dokumentierten Prüfung unveröffentlicht;
Betreiberidentität, Rechtsberatung oder behördliche Freigaben werden nicht erfunden.
Banküberweisung, Bitcoin und Treuhand bleiben nicht verfügbar.

Die vorhandene PLZ-Datei mit 8.289 Datensätzen wurde geprüft, aber wegen ungeklärter
Nutzungsrechte nicht importiert. Echter Mailversand braucht passende DNS-/MTA-
Konfiguration und einen nachgewiesenen Empfang. Historische bekannte Geheimnisse
und personenbezogene Daten in alter Git-History benötigen weiterhin Rotation,
Retention und gegebenenfalls weitere Betreibermaßnahmen.

## Noch offen

- Automatische Erhebung von Auktions-Einstellgebühr/Erfolgsprovision und ihre
  vollständige Integration mit Vertragszustand, Korrektur und Rechnung. Der
  progressive exakte Gebührenrechner ist vorhanden; neue Auktionen werden noch
  nicht automatisch gebührenpflichtig gestellt.
- Vollständige Checkout-Verknüpfung aller jeweils rechtlich erforderlichen
  Dokumentfassungen und vollständige Übersetzung aller Konto-/Finanz-/Admin-
  Oberflächen und Rechtsdokumente. Kernnavigation, öffentliche Marktplatzsuche,
  Länder-/Sprachauswahl und definierte Systemmeldungen sind lokalisiert;
  benutzergenerierte Texte werden nicht automatisch übersetzt.
- Kategorieattribute, vollständige strukturierte Produktklassifizierung,
  weitere optionale Passkey-/Benutzer-2FA- und Komfortfunktionen sowie umfassendes SEO.
- Fachliche Abnahme der zwanzig Rechtsentwürfe einschließlich Betreiberangaben,
  Verbraucherbestellungen, Datenschutz, Moderation und länderspezifischer Pflichten.
- Fachliche Übernahme tatsächlich vorhandener Geschäftsdaten. Der geprüfte Importer
  übernimmt Kontostammdaten aus Testschemata und niemals alte Adminrechte,
  Verifikationsbehauptungen oder bekannte Passwörter. Reale Bestände wurden nicht importiert.
- Reale PayPal-Abnahme, geeignete Bitcoin-Infrastruktur, Bankintegration und
  gegebenenfalls gesonderte Treuhandfreigabe. Diese werden nicht vorgetäuscht.
- Zielserverinstallation mit DNS/TLS/SMTP, getrennten Datenbank-Laufzeitrechten,
  echter Offsite-Übertragung, vollständigem Produktions-Compose-Restore,
  Incident-/Schlüsselmanagement und repräsentativem Last-/Core-Web-Vitals-Test.
- Vollständige manuelle Barrierefreiheits-/Screenreaderprüfung und unabhängige
  Security-/ASVS-Abnahme. Ein automatisierter Teiltest beweist keine Vollkonformität.

## Auslieferung

Der Stand wird im genannten Entwicklungsbranch gesichert; `main` wird nicht
ungeprüft zusammengeführt. Native GitHub-Git-Zugriffe funktionieren. Die
GitHub-API für die PR-Erstellung wurde vom aktuellen Umgebungsnetz mit 403
abgewiesen; eine gespeicherte Freigabeliste ist noch keine Laufzeitänderung.
Die Cloud-Startkonfiguration wird separat aktualisiert und ist weder
Git-Push noch Veröffentlichung eines Snapshots oder Produktivdeployment.

> Für den inzwischen ergänzten Zahlungsbutton mit manueller Prüfung siehe [MANUAL_PAYMENTS.md](MANUAL_PAYMENTS.md). Die folgenden API-Grenzen betreffen die automatische Integration. Manuelle Aufträge dürfen nicht in deren Capture-Fluss gelangen.

# Zahlungsarchitektur und PayPal-Kontotyp

## Privatkonto: ehrliche technische Grenze

Ein privater Käufer kann bei einem freigegebenen PayPal-Händler bezahlen. Der
automatisierte **Empfang** über PayPal Checkout/Orders ist eine Händlerintegration.
Diese Anwendung schaltet sie für ein als privat konfiguriertes Empfängerkonto
nicht frei. Ein privates Konto wird weder durch PayPal.Me noch durch einen
„Freunde und Familie“-Zahlungsweg in einen sicher automatisierten Checkout
umgewandelt. E-Mails, Screenshots, Browserparameter oder Nutzerbestätigungen sind
kein Zahlungsnachweis.

Der Betreiber kann ohne Geschäftskonto den kostenlosen Marktplatz entwickeln und
betreiben, soweit sämtliche anderen Betriebsvoraussetzungen erfüllt sind. Die
kostenpflichtigen Plattformleistungen bleiben dann deaktiviert. Ein späteres
geeignetes Händlerkonto kann über dieselbe Provider-Schnittstelle angebunden
werden; Käufer benötigen deshalb nicht pauschal ein Geschäftskonto.

Offizielle Referenzen zur Prüfung des tatsächlichen Kontos/Geschäftsmodells:

- <https://developer.paypal.com/docs/checkout/standard/integrate/>
- <https://developer.paypal.com/api/rest/>
- <https://developer.paypal.com/api/rest/webhooks/>
- <https://www.paypal.com/de/legalhub/acceptableuse-full>

Die aktuelle Cloud-Anfrage an die offizielle Integrationsdokumentation endete mit
HTTP 403. Ihre aktuellen Inhalte und eine konkrete Konto-/Anbieterfreigabe wurden
hier deshalb **nicht extern verifiziert**. Eine technische Flag-Einstellung ist
keine Anbieterzusage. Die Art der Plattformleistung muss korrekt offengelegt sein.

## Implementierter Baustein

`config/payments.php` stellt einen REST-Adapter für OAuth, Orders, Capture,
serverseitiges Nachlesen, offizielle Webhook-Verifizierung und einen vorbereiteten
Refund-Aufruf bereit. Es werden ausschließlich feste offizielle API-Ursprünge
verwendet. TLS-/Hostprüfung bleiben aktiv; API-Redirects werden nicht verfolgt.
Eine vom Webhook genannte Zertifikatadresse wird niemals selbst abgerufen.

Lokale Daten liegen in `market_invoices`, `market_payment_attempts` und
`market_payment_events`. Jeder Rechnungsdatensatz gehört einem Benutzer. Nur
ausgestellte EUR-Rechnungen mit fester Nummer und positivem serverseitigem Betrag
sind zahlbar. Ein Entwurf ist keine fertige gesetzliche Rechnung.

Vor einem Capture werden Rechnung und Versuch unter Zeilensperre gelesen und
der offizielle APPROVED-Auftrag auf Empfänger, Referenzen, Währung und Betrag
geprüft. Erst ein frisch nachgelesener COMPLETED-Auftrag mit genau einer passenden
vollständigen Capture wird lokal bezahlt. Stornierte/erstattete Forderungen und
abgeschlossene widersprüchliche Versuche führen nicht zu einer neuen Belastung.

Die Request-ID wird vor dem ersten externen Erstellungsaufruf gespeichert.
Wiederholungen verwenden dieselbe Referenz. Events und Capture-IDs sind eindeutig;
eine andere Capture kann eine bereits bezahlte Rechnung nicht überschreiben.
Provider-Idempotenz gilt nur innerhalb der jeweiligen Anbieter-Aufbewahrungsfrist;
alte unklare Versuche müssen abgeglichen und dürfen nicht beliebig neu erzeugt
werden. Zeit-/Queuegestützter automatischer Langzeitabgleich bleibt offen.

Die Rückkehrseite nimmt keine Browser-Zahlungsbehauptung an. Ein Capture-Abgleich
verlangt Login, Rechnungsbesitz, POST und CSRF. Ein verifizierter Webhook wird
zusätzlich mit der offiziellen Order verglichen. Gespeichert wird ein Hash der
Providerantwort, kein vollständiges Zahlungsgeheimnis.

## Sichere Standardkonfiguration

Alle Werte kommen aus dem Prozessumfeld; `.env.example` enthält keine Geheimnisse:

```text
PAYPAL_ENABLED=0
PAYPAL_MODE=sandbox
PAYPAL_ACCOUNT_TYPE=personal
PAYPAL_BUSINESS_SCOPE=general_only
PLATFORM_BILLING_APPROVED=0
PAYPAL_LIVE_APPROVED=0
```

Zusätzlich sind für einen tatsächlich freigegebenen Checkout `PAYPAL_CLIENT_ID`,
`PAYPAL_CLIENT_SECRET`, `PAYPAL_MERCHANT_ID`, `PAYPAL_WEBHOOK_ID` und eine eigene
`APP_URL` erforderlich. Für automatischen Checkout muss `PAYPAL_ACCOUNT_TYPE`
ausdrücklich `business` sein. Sandbox erlaubt lokales Loopback-HTTP für manuelle
Rückkehrtests; echte Webhooks brauchen eine vom Anbieter erreichbare sichere
Adresse. Live verlangt eine getrennte ausdrückliche Freigabe. Im Admin-Dashboard
dürfen weder Geheimnisse noch diese Sicherheitsfreigaben durch Branding-Eingaben
ersetzt werden.

Banküberweisung und Bitcoin sind zentral sichtbar, aber serverseitig grundsätzlich
deaktiviert. Es gibt keine IBAN, keine Blockchain-Bestätigung und keine frei
wählbare Mock-Zahlung im Web. Treuhand ist nur eine gesperrte Gebührenvorschau.

## Prüfungen und Grenzen

`php tests/payments_unit.php` prüft Rechenregeln und den Adapter mit synthetischem
injiziertem Transport. Dadurch wird keine echte Sandboxzahlung ausgeführt. Die
ausgeführte Testzahl muss im Abschlussbericht aus dem tatsächlichen Lauf stammen.

Noch offen: produktive Rechnungsnummern-/Steuersnapshots/PDFs, rechtlich geprüfter
Bestell-/Widerrufsprozess, automatische Gebührenausstellung, vollständiger
Refund-/Storno-/Chargeback-Workflow, Premium-Freischaltung und Entzug, betriebener
Zahlungsabgleich mit Wiederholungsqueue sowie echte Sandbox-/Liveabnahme. Der
Refund-Adapter allein stellt noch keine benutzbare Rückerstattungsverwaltung dar.
Providerfehler dürfen deshalb keine Premiumleistung oder Kaufpreiszahlung
vortäuschen. Es werden keine Warenkaufpreise oder fremden Treuhandgelder verwahrt.

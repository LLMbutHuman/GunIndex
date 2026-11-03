# Wartungsmodus

Standardmäßig ausgeschaltet. `maintenance_enabled` nur über das geschützte
Admin-Einstellungsformular ändern. Migration 013 ergänzt Zustand, Zeitfenster
und Frist-Snapshots; vorhandene Daten werden nicht gelöscht. Migrationen bei
angehaltener Anwendung und angehaltenen Workern nach geprüftem Backup ausführen.
Ein vorher gesetzter reiner Wartungshinweis wird ab dem Migrationszeitpunkt zur
Sperre; davorliegende Zeit wird nicht als tatsächlich erfolgte Pause ausgegeben.

## Zugriff und laufende Arbeit

Der Frontcontroller prüft jede erlaubte dynamische Route. Öffentliche Anfragen
halten auf einer separaten PDO-Verbindung eine gemeinsame Zeilensperre bis zum
Requestende. Die Aktivierung benötigt eine exklusive Sperre und wartet deshalb
auf bereits begonnene öffentliche Anfragen und Worker-Batches. Die zusätzliche
Verbindung zählt gegen das MySQL-Verbindungslimit; Kapazität im Zielsystem prüfen.
Ein Lock-Timeout schlägt fehl, ohne einen teilweise aktivierten Zustand zu speichern.

Bei aktiver Wartung: 503, `Retry-After: 300`, keine Zwischenspeicherung, `noindex`,
acht lokalisierte Antworten; HEAD ohne Body. Es gibt keinen URL-/Cookie-Bypass.
Login, MFA und Logout bleiben mit ihren bestehenden Schutzprüfungen erreichbar.
Administration ist nur mit aktueller Sitzung, aktiver MFA und aktuellem
`site.manage` erreichbar. Auch Administratoren können nicht über Marktplatzrouten
handeln. Der genaue PayPal-Webhook bleibt ausschließlich per POST mit unveränderter
Providerprüfung für bereits begonnene Zahlungen offen. Neue Checkout-Aufträge
sind gesperrt. Betreiber können bestätigte Zahlungseingänge weiterhin prüfen.

Scheduler-Einstiege für Auktionen, Mail, Discovery, Werbebuchungen und Datenschutz
halten ebenfalls eine Lesesperre über ihren Batch. Bei aktiver Wartung melden sie
ausdrücklich Zurückstellung und beenden sich erfolgreich, statt Daten zu ändern.
Nach Ausschalten arbeitet der nächste geplante Lauf weiter. Kern-Markttransaktionen,
manuelle Neubestellungen/Bumps, Werbestart und automatischer Checkout haben zusätzlich
eine Sperre innerhalb der Transaktion für direkte Aufrufe. Diese Sperre wird vor
anderen Geschäftszeilen genommen. Abweichungen zwischen Konfigurationsflag und
persistiertem Zustand sperren Zugriffe und verlangen eine kontrollierte Reparatur.

## Auktionsfristen

Aktivierung speichert die ursprünglichen Endzeiten noch nicht abgelaufener aktiver
oder pausierter Auktionen. Deaktivierung verlängert passende Fristen genau einmal
um die tatsächliche Wartungsdauer, auf volle Sekunden aufgerundet. Gebote, Gewinner,
Gebührentarife und Zuschläge werden dabei nicht umgeschrieben. Vorher bereits
fällige oder abgeschlossene Auktionen werden nicht wiederbelebt. Während Wartung
anders beendete oder ausdrücklich geänderte Inserate werden nicht blind überschrieben;
der Audit-Eintrag zählt nicht verlängerte Snapshots. Wiederholtes Ein-/Ausschalten
ist für den jeweiligen Zustand idempotent; jedes neue Fenster erhält eine eigene ID.

## Abgrenzung und noch offene Betriebsabnahme

Dies ist kein vollständiger Backup-Schreibstopp: Adminänderungen, Authentifizierung
und verifizierte Zahlungsereignisse bleiben möglich. Backups verwenden weiterhin
die eigenen Konsistenz-/Restoreverfahren. Für lange Wartung sind die Auswirkungen
auf Mailtoken-, Datenschutz-, Reservierungs- und Werbelaufzeiten zu prüfen;
automatischer Ausgleich bezahlter Werbezeiten ist noch offen. Externe Providerzeiten
werden nicht durch eine lokale Pause verändert. Rohes SQL, zusätzliche selbst
installierte Worker und direkte PHP-Datei-Exposition umgehen die vorgesehenen
Einstiege und sind keine unterstützte Betriebsart. Webroot bleibt `public/`.

Tests: zwölf reine Regel-/Darstellungsgruppen, acht zusätzliche Datenbank-/Worker-
gruppen und 14 HTTP-/RBAC-Gruppen. Verbindliche tatsächlich ausgeführte Resultate
stehen in `IMPLEMENTATION_PROGRESS.md`, nicht in dieser Testumfangbeschreibung.

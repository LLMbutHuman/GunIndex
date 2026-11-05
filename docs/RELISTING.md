# Wiedereinstellung erfolgloser Auktionen

Eine eigene Auktion kann nach einem tatsächlichen Abschluss ohne Gebot erneut
angeboten werden. Bloßer Zeitablauf oder ein fehlender Gewinner genügen nicht:
der ursprüngliche `close_no_bid`-Abschluss muss vorhanden sein, Gebote und
Sofortkaufereignisse dürfen dem nicht widersprechen. Verkaufte, gesperrte oder
fremde Angebote erhalten keinen Wiedereinstellungsanspruch.

## Kundenablauf

Im Dashboard führt **Auktion wieder einstellen** zu einer Bestätigungsseite.
Der GET-Aufruf verändert nichts. Erst die ausdrückliche, CSRF-geschützte Bestätigung
erstellt einen neuen Entwurf. Dieser besitzt eine neue Angebots-ID und unabhängig
gespeicherte, erneut aufbereitete Bilddateien. Ursprüngliche Inserate, Gebote,
Gebührenverträge und Belege bleiben unverändert.

Der Verkäufer prüft den Entwurf, legt die neue Dauer fest und veröffentlicht ihn
über den bestehenden Angebotseditor mit den erforderlichen Produkt-/Gebühren- und
Rechtszustimmungen. Ein Entwurf erzeugt keine Gebühr. Derselbe Ursprung erzeugt
auch bei Wiederholung oder konkurrierenden Anfragen höchstens einen Nachfolger.
Ein bereits angelegter Entwurf ist auf der Bestätigungsseite erneut erreichbar.

## Einmalige kostenlose Wiedereinstellung

Migration 014 ergänzt Ursprungsketten und eindeutige Eltern-/Kind-Verknüpfungen.
Je Kette darf genau eine normalerweise positive Einstellgebühr entfallen.
Der Anspruch wird erst bei tatsächlicher Veröffentlichung zusammen mit Vertrag,
Gebührenereignis und Angebotsstatus in derselben Transaktion verbraucht. Gebühren-
deaktivierung oder ein ohnehin kostenloser Grundtarif verbrauchen ihn nicht.

Die Quote zeigt den Rabatt bereits vor der Zustimmung. Identität der Kette,
Originalpreis und Rabatt sind an den Quote-Hash und unveränderlichen Vertrag
gebunden. Eine nachträgliche Änderung verlangt neue Zustimmung. Die normale
Erfolgsprovision bleibt bestehen. Nullgebühren erzeugen einen Ereignisnachweis,
keine Rechnung über einen erfundenen Zahlungseingang. Weitere Nachfolger derselben
Kette verwenden nach Anspruchsverbrauch den aktuell angebotenen Einstelltarif.

## Schutz und technische Grenzen

Wartungsbarriere, Kontoprüfung, Kettensperre und Inseratsperre schützen Schreibabläufe.
Eindeutige Datenbankschlüssel verhindern mehrere Nachfolger und Anspruchsverbraucher.
Fehler vor Commit rollen Daten und ausschließlich neu erzeugte Bilddateien zurück.
Bei unklarem Commit-Ausgang bleiben private Dateien für eine kontrollierte
Abstimmung erhalten. Es werden keine ursprünglichen Bilddateien gelöscht.

Ein neuer Durchlauf bleibt eine Auktion. Nach seiner Veröffentlichung kann er nicht
zum Entwurf zurückgesetzt werden; Pausieren/Fortsetzen erhält seine Endzeit.
Der ursprüngliche Durchlauf wird weder verlängert noch wiederbelebt.

Diese Etappe betrifft erfolglos beendete Auktionen. Weitere Inseratarten, explizite
Verlängerungen und die restlichen Handelsabläufe aus Masteranforderung 11 bleiben
offen. Live-Zahlungs-/Finanzfreigaben werden durch die Funktion nicht aktiviert.

## Prüfungen

`tests/relisting_test.php` prüft Anspruch, Ereignisnachweise, Kontext, Bildmanifest
und Quote ohne Datenbank. `tests/relisting_integration_test.php` ergänzt isolierte
MySQL-/HTTP-/Nebenläufigkeitsfälle; der Runner führt schreibende Suiten sequenziell
aus. Die vorhandene Gebühren-Browsersuite ergänzt mobile Wiedereinstellungsabläufe.
Tatsächlich ausgeführte Ergebnisse stehen in `IMPLEMENTATION_PROGRESS.md` und
`TESTING.md`; diese Beschreibung ist noch kein erfolgreicher Testnachweis.

# Mailversand einrichten

Der Assistent **Administration → Mail-Einrichtung** ist mit `site.manage`
und einer aktuellen Mehrfaktoranmeldung erreichbar. Die Konfiguration gilt
für E-Mail-Bestätigung, Passwortwiederherstellung und Benachrichtigungen
über die vorhandene Mailwarteschlange.

1. Absender auf der Betreiber-Domain eintragen und einen Transport wählen.
   **Lokaler Mailserver** nutzt das vom Betreiber bereitgestellte
   Sendmail-kompatible Programm (`MAIL_SENDMAIL_PATH`). Den Pfad setzt
   ausschließlich die Deployment-Konfiguration. **SMTP-Relay** nutzt die
   Relay-Anmeldedaten des Betreibers und Port 465 für direktes TLS oder
   587 für verpflichtendes STARTTLS. Die Relay-Domain muss auf öffentliche
   Adressen auflösen und zum Serverzertifikat passen.
2. Beim Mailanbieter die Werte für SPF, DKIM und DMARC ermitteln und in der
   DNS-Verwaltung der Absender-Domain veröffentlichen. Zur Prüfung den
   DKIM-Selektor im Assistenten speichern. Die Diagnose prüft veröffentlichte
   TXT-Einträge. Effektive DMARC-Vererbung, Signaturen einer konkreten Nachricht,
   Zustellbarkeit und SPF-Autorisierung für die tatsächliche Relay-IP erfordern
   zusätzlich die Prüfung beim Mailanbieter und am Nachrichtenkopf.
3. Testversand an das eigene Administratorkonto bestätigen und den Status
   aktualisieren. Die Mailwarteschlange nutzt denselben Worker wie reguläre
   Systemmails. „Übernommen“ bezeichnet die Bestätigung des lokalen Mailservers
   oder SMTP-Relays. Posteingang und Anbieterprotokoll separat prüfen. Nach einer
   Änderung an den Einstellungen einen neuen Test starten. Testversand ist auf
   drei Aufträge pro Administratorkonto und Stunde beschränkt.

Vor dem ersten Speichern gelten `MAIL_TRANSPORT=none` bzw. ein vorhandener
`sendmail`-Transport und `MAIL_FROM` aus dem Deployment. Danach gelten die
gespeicherten Einstellungen. Der isolierte Entwicklungsmodus `test` simuliert
die Annahme ausschließlich in einer Entwicklungsdatenbank mit Suffix `_test`.

Die Anwendung verschlüsselt die gespeicherten Einstellungen mit `APP_KEY`.
Dieselben Schlüssel müssen im Webprozess, Mailworker und gesicherten
Wiederherstellungsbestand verfügbar sein. Das Relay-Passwort wird weder
angezeigt noch in Diagnose- oder Auditmeldungen übernommen. Beim Ändern von
Host, Port oder Benutzernamen muss es erneut eingegeben werden. Beim Umschalten
auf lokalen Mailserver oder deaktivierten Versand wird das Relay-Passwort aus
der aktiven Konfiguration entfernt. Ältere verschlüsselte Backups unterliegen
dem Aufbewahrungsverfahren.

Bei einem vorübergehenden Fehler wiederholt der Worker die Übergabe mit
größeren Abständen. Eine feste Message-ID bleibt über Wiederholungen erhalten.
SMTP bietet nach einem Verbindungsabbruch während der Annahme keine sichere
Erkennung, ob der Provider die Mail bereits verarbeitet hat. Prüfe in diesem
Fall das Anbieterprotokoll. Die Anwendung speichert nur einen allgemeinen
Fehlerstatus. Aktiviere Diagnoseprotokolle des Providers für nähere Angaben.

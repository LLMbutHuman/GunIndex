# Sicherheitsstand

Implementiert: umgebungsbasierte Datenbankzugänge, echte PDO-Prepared-Statements, strikte Cookie-Sessions, Rotation/Widerruf/Fristen, Argon2id, aktuelle DB-Rollen, verpflichtende Admin-TOTP mit Replayprüfung und verschlüsseltem Secret, persistente gesperrte Login-Limits, POST/CSRF für Zustandsänderungen, objektbezogene Markt-/Chat-/Rechnungsprüfungen, sichere Bild-Neucodierung, kontrollierter Webroot und generische Fehlerausgaben.

HTML-Ausgabe wird kontextgerecht escaped; Eingaben einschließlich Passwörtern werden nicht global verändert. CSP erlaubt nur lokale Skripte, lokale Bilder plus Daten-/Blobvorschau und feste PayPal-Formularziele. Inline-Styles sind für geprüfte Markenfarben noch erlaubt. Keine vollständige unabhängige OWASP-ASVS-Abnahme.

Die historischen Datenbankzugänge gelten als möglicherweise kompromittiert und müssen vom Betreiber rotiert werden. Sensible Altdateien sind lokal erhalten und aus der neuen Git-Versionierung ausgeschlossen, aber weiter in alter History vorhanden. HTTP-Sperren ersetzen keine Datenschutzentscheidung, Retention oder koordinierte History-Bereinigung. Es wurde keine History destruktiv verändert.

Identitätsdokumente werden nicht angenommen oder ausgeliefert. Es gibt keinen fertigen verschlüsselten Dokumentendienst. Die alte allgemeine Webrootauslieferung und unsichere nicht freigegebene Legacy-Endpunkte sind im neuen Dispatcher nicht erreichbar.

Restrisiken: Produktives Schlüsselmanagement und Recovery, Rollenfeinheiten/Supportprozesse, E-Mail-Verifikation/Passwortreset, rechtssichere Lösch-/Exportprozesse, vollständige Moderation/DSA-Fälle, Offsite-/Incidentbetrieb, Last-/Missbrauchstests und produktive Webserverhärtung bleiben offen. Eine Wortprüfung klassifiziert Produkte nicht vollständig; unbekannte oder unzulässige regulierte Waren dürfen nicht durch freie Kategorien freigeschaltet werden.

Manuelle Zahlungsbestätigungen vertrauen dem autorisierten Administrator und sind ausdrücklich keine providerverifizierten API-Ereignisse. Ein Kundenhinweis, Linkparameter, Screenshot oder ungeprüfter Webhook schaltet nichts frei. Reale Anbieter-/Rechtsfreigaben, Geschäftsdaten und Steuerprozesse fehlen weiterhin; alle Zahlungswege sind ohne Konfiguration aus.

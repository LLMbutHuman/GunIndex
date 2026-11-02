# Release- und Betriebsprotokoll

## 2026-11-02 – Vertragsbindung erfolgreich geprüft

`dc046aed7cb3232386c186ced3ff15c95d7c0713` erfolgreich gepusht, Remote identisch.
CI `41478575081` vollständig erfolgreich (Windows, Linux/MySQL/Migration,
Vertragsfälle, Browser und Accessibility). Nächste Finanzkorrektur verbindet den
verifizierten automatischen Zahlungseingang mit einmaliger Rechnung/Cash-Buchung;
ihre zusätzlichen DB-Adaptertests stehen noch aus. Sämtliche Live-Gates bleiben
unverändert geschlossen.

## 2026-11-02 – dritte Etappe gesichert, Vertragsbindung in Arbeit

Persönliche Listen: `126d4008df06f95be2892a280c33cebd776c6588` gepusht,
Remote-SHA identisch. CI `41420258463` vollständig erfolgreich einschließlich
neuer MySQL-/Browserprüfungen. Folgeetappe implementiert dokumentgebundene
Registrierung und bestehende Leistungsbestellungen. Migration 011 ist additiv;
keine Bestandszustimmungen oder Gebühren werden nachträglich erfunden.
Pflichtdokumentkonfiguration und fachliche Freigaben bleiben Betreiberaufgabe.
Für diese neue Folgeetappe steht der vollständige CI-Nachweis noch aus.

## 2026-11-02 – zweite Etappe zur CI-Prüfung

Bestand bei `754adf1` erhalten. Neue Admin-Setup-, Bildreihenfolge-, SEO- und
PLZ-Prüfmodule lokal implementiert und integriert. 83 PHP-Unitprüfungen,
157 Syntaxprüfungen und zehn Node-PLZ-Testgruppen bestanden. Neue DB-/HTTP-Suites
sind im sequenziellen Linux-Runner eingebunden; deren Ergebnis steht noch aus.
GitHub Actions bestätigt die ältere Basis `754adf1` als erfolgreich.

WSL-/Ubuntu-Installation angestoßen; Windows meldete nötigen Neustart. Folgeprüfung
scheitert an fehlendem Speicher auf C: (benötigt 1.556.019 KB, frei ca. 465.096 KB).
Kein Neustart, keine Löschung privater Dateien und keine DB-/Volumenänderung.
Portable offizielle PHP-CLI samt Archiv liegt ausschließlich ignoriert unter
`tests/.runtime/tools/`; kein Schlüssel oder Werkzeug-Binary wird versioniert.
SEO-Indexierung standardmäßig gesperrt; Zahlungsfreigaben unverändert geschlossen.

Commit-/Push-SHA und aktueller CI-Nachweis werden nach bestätigter Sicherung
ergänzt. Die gesamte Plattform ist noch nicht technisch abgenommen.

Bestätigt: `2d496a880b6e8ebb431f258646359adf858395ba` erfolgreich gepusht,
Remote identisch. GitHub-Actions-Lauf `41418425597` erfolgreich: Windows- und
Linux-Jobs einschließlich neuer DB-/HTTP-Suites, Audit und vorhandenem Browserumfang.
Persönliche Listen und zusätzliche Browserfälle werden als eigene Etappe entwickelt.

## 2026-11-01 – lokale Entwicklung

- Baseline lokal/remote bestätigt: `1636914f33f0e4495b92eec1936dc54e110d7576`.
- Ziel ausschließlich `LLMbutHuman/GunIndex`, Branch
  `feature/general-marketplace-rebuild`.
- Etappe Windows-Vorprüfung: zehn lokale PowerShell-Prüfungen erfolgreich.
- Kein Deployment, keine DB-Migration, kein Containerstart, kein Geldfluss.
- Kein aktueller Linux-/PHP-/Browser-/Provider-Testnachweis vorhanden.
- Administrative Voraussetzung: Ubuntu WSL2 bereitstellen, Docker-Integration
  aktivieren; danach dokumentierten isolierten Basislauf ausführen.
- Erster Commitversuch wurde wegen fehlender Autorenidentität abgewiesen.
  Name und E-Mail wurden anschließend vom Nutzer vorgegeben und ausschließlich
  lokal für dieses Repository gesetzt. Zehn PowerShell-Prüfungen erneut bestanden.
- Änderungen selektiv gestaged, Auftragsdatei bleibt unversioniert.
- Etappe committed als `56d1158ab0aa9b79db5dcc0a12fc1d08e45af249`
  (`build: add Windows development preflight and runtime guards`).
- Push auf `feature/general-marketplace-rebuild` erfolgreich bestätigt:
  `1636914..56d1158`. Keine Änderung an `main`.
- CI-Status noch nicht geprüft; die erfolgreiche Übertragung ist kein Testnachweis.
- Rücknahme dieser Etappe benötigt nur einen gezielten späteren Git-Revert;
  es gibt keine Datenmigration oder Volumenänderung rückgängig zu machen.

# Release- und Betriebsprotokoll

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

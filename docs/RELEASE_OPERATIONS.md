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
  Noch kein neuer Commit und kein Push; SHA nach bestätigter Sicherung ergänzen.
- Rücknahme dieser Etappe benötigt nur einen gezielten späteren Git-Revert;
  es gibt keine Datenmigration oder Volumenänderung rückgängig zu machen.

# GunIndex.eu

Allgemeiner PHP-/MySQL-Marktplatz auf dem Entwicklungsbranch `feature/general-marketplace-rebuild`. Historische spezialisierte Inserate und private Uploads werden nicht öffentlich übernommen.

```sh
python3 tests/environment.py up
python3 tests/environment.py test
node tests/browser_smoke.js
```

Webroot ausschließlich `public/`. Die Entwicklungsumgebung verwendet synthetische Daten, einen privaten MySQL-Socket, PHP 8.4 mit GD/mbstring und einen unabhängigen Auktionsworker. Keine historischen Exporte importieren.

Implementiert sind sichere Konten mit verpflichtender Admin-MFA, konfigurierbare Marke/Farben/Animationen/Preise, hierarchische Kategorien, Inserate/Bilder, Suche, Proxyauktionen, Chat, Merklisten und Bewertungen. Der manuelle PayPal-Ablauf erzeugt feste Zahlungsaufträge, verlangt tatsächliche Adminprüfung und schaltet Bump-Guthaben erst danach frei. Er ist ohne explizite Konfiguration deaktiviert.

Die vollständige Plattform aus dem Master-Prompt ist **noch nicht fertig oder produktionsfreigegeben**. Insbesondere Gesuche/Matching, Benachrichtigungen, Premiumslots/Händlerwerbung, vollständige Rechnungsausstellung/Erstattungen und rechtliche Freigaben sind offen. Keine echten Zahlungen oder Providerfreigaben wurden durchgeführt.

- [Aktueller Bericht](docs/IMPLEMENTATION_REPORT.md)
- [Installation](docs/INSTALLATION.md), [Architektur](docs/ARCHITECTURE.md), [Datenbank](docs/DATABASE.md)
- [Administration](docs/ADMIN_GUIDE.md), [Authentifizierung](docs/AUTH.md)
- [Manuelle PayPal-Prüfung](docs/MANUAL_PAYMENTS.md), [API-Zahlungen](docs/PAYMENTS.md), [Gebühren](docs/FEES.md)
- [Tests](docs/TESTING.md), [Sicherheit](docs/SECURITY.md), [Backups](docs/BACKUP_RESTORE.md)
- [Deployment](docs/DEPLOYMENT.md), [Release-Checkliste](docs/RELEASE_CHECKLIST.md)

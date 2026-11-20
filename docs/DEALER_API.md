# Händler-API

`DEALER_API_ENABLED=0` ist die Standardkonfiguration. Bei Aktivierung
erstellt der Inhaber eines veröffentlichten Händlerprofils im Bestandsbereich
einen Schlüssel mit selbst ausgewählten Berechtigungen und seinem aktuellen
Kontopasswort. Schlüssel gelten höchstens 90 Tage. Das Geheimnis ist einmalig
sichtbar und muss beim Empfänger in einem privaten Geheimnisspeicher liegen.
GunIndex speichert den Hash; die Zugriffsverwaltung zeigt Label, Gültigkeit
und Berechtigungen. Inhaber können einen Schlüssel jederzeit widerrufen.

Der Endpunkt ist `/api/dealer/v1.php`. Aufrufe nutzen HTTPS und den Header
`Authorization: Bearer <Schlüssel>`. Die Anwendung leitet den Betrieb aus dem
Schlüssel ab. Sitzungen und Query-Parameter sind keine Zugangsdaten. Wartung
liefert HTTP 503 und `Retry-After`. [Bearer-Header](https://www.rfc-editor.org/rfc/rfc6750.html#section-2.1)

| Berechtigung | Methode und Abfrage | Daten |
| --- | --- | --- |
| `inventory.read` | `GET ?resource=inventory` | Bis zu 50 Bestandseinträge, `next` für `after` |
| `inventory.read` | `GET ?resource=inventory&id=42` | Artikel und die letzten 100 Bewegungen |
| `orders.read` | `GET ?resource=orders` | Bis zu 50 eigene Referenzen, Beträge in EUR-Cent, Zustände, Versandart und Zeiten |
| `inventory.write` | `POST ?resource=stock_movement` | Zu- oder Abgang eines bestehenden Artikels |

Der POST-Body nutzt `application/json` und maximal 4.096 Bytes. Beispiel mit
synthetischen Angaben:

```json
{"id":42,"version":7,"request_key":"0123456789abcdef0123456789abcdef","amount":-2,"reason":"Lagerabgang"}
```

Für jede neue Bewegung einen eigenen kryptografisch zufälligen 32-stelligen
Hexadezimalschlüssel verwenden. Bei unklarem Ergebnis denselben Schlüssel und
Body erneut senden. Eine unveränderte Wiederholung antwortet erfolgreich und
erzeugt keine Doppelbuchung. Eine geänderte Revision oder Schlüsselwiederverwendung
mit abweichenden Daten liefert 409. Betriebe und Artikelzugriffe bleiben getrennt.
Ein abgelaufener, widerrufener oder ungültiger Schlüssel liefert 401, fehlender
Scope 403. Eine Sperre des Inhabers oder die Rücknahme der Händlerfreigabe stoppt
Schlüsselzugriffe. Bestellübersichten liefern keine Käufer- oder Adressdaten.

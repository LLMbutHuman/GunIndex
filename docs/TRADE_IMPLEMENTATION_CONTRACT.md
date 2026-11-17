# Handelsmodul – Entwicklungsvertrag, 2026-11-15

Einzelartikel, EUR/Cent. Neue Inseratsart `negotiated` neben `fixed`/`auction`.
Keine Zahlung durch diese Funktion, keine Treuhand, keine automatischen Erstattungen.
Externe Abwicklung wird nur als Erklärung einer Partei protokolliert. Bestehende
Auktionsgebühren/Finanzbelege bleiben getrennt. Vorhandene Daten bleiben erhalten.
Verbindliche Kauf- und Preisangebote setzen die ausdrückliche Verkäuferfreigabe
pro Inserat voraus (`trade_checkout_enabled`, Migration 018). Bestehende
Inserate und neue Wiedereinstellungsentwürfe beginnen im Kontaktverkauf.

## Dienstschnittstellen (config/trade.php)

- `trade_quote(PDO,int $actor,int $listing,string $delivery,?int $offerId=null):array`
  liefert `listing`, `hash`, `documents`, `locale`, `delivery`, `price_cents`,
  `shipping_cents`, `total_cents`, `offer` (null oder Zeile). Bei Angebot darf
  nur die Gegenpartei des noch offenen Angebots annehmen.
- `trade_purchase(PDO,int $actor,int $listing,array $input,?int $offerId=null):int`
  Input `delivery` pickup/shipping, `quote_hash` hex64, `request_key` hex32,
  `confirm_purchase`="1", normale legal_documents/legal_language/legal_confirm.
  Quote wird unter Inseratssperre nachgerechnet. Genau eine Transaktion je
  Inseratsdurchlauf, eindeutig je Akteur/Request-Key. Ein storniertes Inserat
  wird als neue ID wieder eingestellt, nie still wiederbelebt.
- `trade_offer_create(PDO,int $actor,int $listing,array $input):int`
  Input `amount` Eurotext, `delivery`, `hours` 1..168, `request_key` hex32,
  `listing_hash` aus `trade_listing_hash($listing)`, `confirm_offer`="1",
  Rechtszustimmung wie oben, optional `parent_id` (int oder kanonische Ziffern).
  Erstangebot nur Käufer, Gegenangebot nur Empfänger eines offenen Angebots;
  Preis/Versand/Inhaltssnapshot unveränderlich. Höchstens ein offenes Angebot
  je Käufer/Inserat. Vorige Version wird bei Gegenangebot atomar `countered`.
- `trade_offer_action(PDO,int $actor,int $offer,string $action):void`
  `reject` nur Empfänger, `withdraw` nur Ersteller; identische Wiederholung erlaubt.
  Abgelaufene/stale Angebote sind nicht annehmbar.
- `trade_offer_context(PDO,int $actor,int $listing):array`: `listing`, `hash`,
  `documents`, `locale`, `offers` eigene/als Verkäufer erhaltene, max100;
  keine Angebote fremder Käufer für andere Käufer.
- `trade_get(PDO,int $actor,int $id):array`: nur echte Parteien; Zeilenfelder
  plus `snapshot` (entschlüsselt), `events` (id,actor_id,action,from_status,
  to_status,created_at,details), `ratings` und `actions` Liste zulässiger Aktionen.
- `trade_list(PDO,int $actor,int $page=1):array`: `trades`, `offers`, `page`.
- `trade_transition(PDO,int $actor,int $id,string $action,array $input):void`
  Input `version` int/kanonisch, `request_key` hex32, optional `details` max1000.
  Aktionen `dispatch` (Verkäufer), `receive` (Käufer), `settle` (Verkäufer),
  `cancel_request`, `cancel_accept`, `cancel_decline`, `dispute` (Parteien),
  `resolve_complete`/`resolve_cancel` (MFA-Personal mit reports.review).
- `trade_rate(PDO,int $actor,int $id,int $stars,string $comment):void`:
  nur Parteien abgeschlossener Transaktion, 1..5, max1000 Zeichen, einmalig.
  Neue Bewertungen verwenden vorhandene market_ratings mit trade_id.
- `trade_listing_available(PDO,int $id):bool`: kein nichtstornierter Trade.
  Für Mutationen erst Inserat sperren; keine implizite Bestandserhöhung.
- `trade_expire(PDO,int $limit=100):int`: begrenzte offene Angebote ablaufen lassen.

## Daten und Zustände

Migration016: listing `shipping_cents` default0; neue Tabellen market_trades,
market_trade_events, market_trade_offers, market_trade_consents; ratings.trade_id.
Handelszeile: id,reference,listing_id,seller_id,buyer_id,offer_id,delivery,
price_cents,shipping_cents,total_cents,status,version,seller_confirmed,
buyer_confirmed,requested_by,previous_status,snapshot_cipher,created_at,updated_at.
Status: agreed → dispatched → completed erst nach receive UND settle.
Cancel_request/decline/accept und disputed/resolution bewahren Ereignisgeschichte.
`settle` bestätigt externe Abwicklung durch den Verkäufer, keinen von der
Plattform geprüften Zahlungseingang. UI sagt das ausdrücklich.
Alle schreibenden Abläufe: Wartungsbarriere, Inseratssperre, aktuelle
Konten-/Kategorie-/Länderprüfung, Parameterprüfung, CSRF im Controller.

## Oberflächen

user/checkout.php?listing_id=N[&offer_id=N][&delivery=pickup|shipping],
user/offers.php?listing_id=N, user/trades.php, user/trade.php?id=N.
POST immer explizite Aktion + CSRF; GET mutiert nicht. Nach POST Redirect.
Streitfälle bekommen geschützte admin/trades.php (Root). Versanddetails und
Streitbegründungen sind private serververschlüsselte Geschäftsdaten, keine E2EE.

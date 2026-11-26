-- Existing snapshots retain their exact text and hash. Their language was not recorded.
ALTER TABLE market_manual_orders ADD COLUMN terms_language CHAR(2) NULL;

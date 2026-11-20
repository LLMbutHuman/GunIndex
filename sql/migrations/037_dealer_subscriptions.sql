CREATE TABLE market_dealer_subscriptions (
 invoice_id BIGINT UNSIGNED PRIMARY KEY,
 owner_id INT NOT NULL,
 request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 term_days SMALLINT UNSIGNED NOT NULL,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY dealer_subscription_request(owner_id,request_key),
 KEY dealer_subscription_coverage(owner_id,revoked_at,starts_at,ends_at),
 CHECK (term_days BETWEEN 1 AND 366),
 CHECK ((starts_at IS NULL AND ends_at IS NULL) OR (starts_at IS NOT NULL AND ends_at>starts_at)),
 FOREIGN KEY(invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

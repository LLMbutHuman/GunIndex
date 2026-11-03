-- Existing auctions remain ineligible: never infer a historical fee agreement.
ALTER TABLE market_listings ADD COLUMN auction_fee_eligible TINYINT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS market_auction_fee_contracts (
 listing_id BIGINT UNSIGNED PRIMARY KEY,seller_id INT NOT NULL,
 payload_ciphertext MEDIUMTEXT NOT NULL,payload_hash CHAR(64) NOT NULL,
 accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_auction_fee_acceptances (
 listing_id BIGINT UNSIGNED NOT NULL,document_id BIGINT UNSIGNED NOT NULL,
 content_hash CHAR(64) NOT NULL,accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(listing_id,document_id),
 FOREIGN KEY(listing_id) REFERENCES market_auction_fee_contracts(listing_id) ON DELETE RESTRICT,
 FOREIGN KEY(document_id) REFERENCES market_legal_documents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_auction_fee_charges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,listing_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(16) NOT NULL,invoice_id BIGINT UNSIGNED NULL UNIQUE,
 gross_cents BIGINT UNSIGNED NOT NULL,sale_cents BIGINT UNSIGNED NULL,
 waived_by INT NULL,waiver_reason VARCHAR(2000) NULL,waived_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY auction_fee_once(listing_id,kind),
 FOREIGN KEY(listing_id) REFERENCES market_auction_fee_contracts(listing_id) ON DELETE RESTRICT,
 FOREIGN KEY(invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY(waived_by) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT auction_fee_kind CHECK(kind IN ('listing','success')),
 CONSTRAINT auction_fee_invoice CHECK((gross_cents=0 AND invoice_id IS NULL) OR (gross_cents>0 AND invoice_id IS NOT NULL)),
 CONSTRAINT auction_fee_basis CHECK((kind='listing' AND sale_cents IS NULL) OR (kind='success' AND sale_cents IS NOT NULL AND sale_cents>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

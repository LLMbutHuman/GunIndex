CREATE TABLE market_bank_orders (
 invoice_id BIGINT UNSIGNED PRIMARY KEY,
 attempt_id BIGINT UNSIGNED NOT NULL UNIQUE,
 reference CHAR(29) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 iban VARCHAR(34) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 beneficiary VARCHAR(140) NOT NULL,
 amount_cents BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY bank_order_account(account_hash),
 FOREIGN KEY (invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY (attempt_id) REFERENCES market_payment_attempts(id) ON DELETE RESTRICT,
 CHECK (amount_cents BETWEEN 1 AND 9999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_bank_imports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 file_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 imported_by INT NOT NULL,
 normalized_count INT UNSIGNED NOT NULL,
 skipped_count INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_bank_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 import_id BIGINT UNSIGNED NOT NULL,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_reference VARCHAR(128) COLLATE utf8mb4_bin NOT NULL,
 direction ENUM('credit','reversal') NOT NULL,
 booking_date DATE NOT NULL,
 amount_cents BIGINT UNSIGNED NOT NULL,
 payment_reference CHAR(29) CHARACTER SET ascii COLLATE ascii_bin NULL,
 evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 credited_invoice_id BIGINT UNSIGNED NULL UNIQUE,
 reviewed_by INT NULL,
 reviewed_at DATETIME NULL,
 UNIQUE KEY bank_entry_once(account_hash,direction,source_reference),
 KEY bank_entry_reference(account_hash,payment_reference,direction),
 KEY bank_review_queue(credited_invoice_id,id),
 FOREIGN KEY (import_id) REFERENCES market_bank_imports(id) ON DELETE RESTRICT,
 FOREIGN KEY (credited_invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK (amount_cents BETWEEN 1 AND 9999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

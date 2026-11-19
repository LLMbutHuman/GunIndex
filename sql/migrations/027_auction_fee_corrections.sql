CREATE TABLE market_auction_fee_corrections (
 previous_invoice_id BIGINT UNSIGNED PRIMARY KEY,
 replacement_invoice_id BIGINT UNSIGNED NOT NULL UNIQUE,
 charge_id BIGINT UNSIGNED NOT NULL,
 previous_cents BIGINT UNSIGNED NOT NULL,
 remaining_cents BIGINT UNSIGNED NOT NULL,
 actor_id INT NOT NULL,
 reason VARCHAR(2000) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY auction_correction_charge(charge_id),
 FOREIGN KEY (previous_invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY (replacement_invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY (charge_id) REFERENCES market_auction_fee_charges(id) ON DELETE RESTRICT,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK (remaining_cents>0 AND remaining_cents<previous_cents),
 CHECK (replacement_invoice_id>previous_invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_auction_late_receipts (
 attempt_id BIGINT UNSIGNED PRIMARY KEY,
 previous_invoice_id BIGINT UNSIGNED NOT NULL,
 amount_cents BIGINT UNSIGNED NOT NULL,
 reviewed_by INT NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 refund_id BIGINT UNSIGNED NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY auction_late_resolution(previous_invoice_id,refund_id),
 FOREIGN KEY (attempt_id) REFERENCES market_payment_attempts(id) ON DELETE RESTRICT,
 FOREIGN KEY (previous_invoice_id) REFERENCES market_auction_fee_corrections(previous_invoice_id) ON DELETE RESTRICT,
 FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (refund_id) REFERENCES market_refunds(id) ON DELETE RESTRICT,
 CHECK (amount_cents BETWEEN 1 AND 9999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

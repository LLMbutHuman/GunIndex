CREATE TABLE IF NOT EXISTS market_contract_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,scope VARCHAR(20) NOT NULL,context_key VARCHAR(40) NOT NULL,
 invoice_id BIGINT UNSIGNED NULL,document_id BIGINT UNSIGNED NOT NULL,
 content_hash CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY contract_receipt(user_id,scope,context_key,document_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY(document_id) REFERENCES market_legal_documents(id) ON DELETE RESTRICT,
 CONSTRAINT contract_scope CHECK((scope='registration' AND invoice_id IS NULL AND context_key='account') OR (scope='checkout' AND invoice_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

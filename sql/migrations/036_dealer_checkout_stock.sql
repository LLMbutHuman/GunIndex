ALTER TABLE market_dealer_stock_movements
 ADD COLUMN movement_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual',
 ADD CONSTRAINT dealer_stock_movement_kind CHECK (movement_kind IN ('manual','order_sale','order_cancel'));

CREATE TABLE market_dealer_stock_bindings (
 listing_id BIGINT UNSIGNED PRIMARY KEY,
 owner_id INT NOT NULL,
 item_id BIGINT UNSIGNED NOT NULL,
 revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY dealer_binding_item(owner_id,item_id),
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE CASCADE,
 FOREIGN KEY(owner_id,item_id) REFERENCES market_dealer_inventory(owner_id,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_dealer_trade_stock (
 trade_id BIGINT UNSIGNED PRIMARY KEY,
 owner_id INT NOT NULL,
 item_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'reserved',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY dealer_trade_stock_review(owner_id,item_id,status,trade_id),
 CHECK (status IN ('reserved','restored','return_required','count_limit','review_closed')),
 FOREIGN KEY(trade_id) REFERENCES market_trades(id) ON DELETE RESTRICT,
 FOREIGN KEY(owner_id,item_id) REFERENCES market_dealer_inventory(owner_id,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_dealer_inventory (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id INT NOT NULL,
 sku VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label VARCHAR(160) NOT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 0,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY dealer_inventory_sku(owner_id,sku),
 UNIQUE KEY dealer_inventory_tenant(owner_id,id),
 CHECK (quantity<=1000000),
 FOREIGN KEY(owner_id) REFERENCES market_dealer_profiles(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_dealer_stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id INT NOT NULL,
 item_id BIGINT UNSIGNED NOT NULL,
 actor_id INT NOT NULL,
 request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 delta INT NOT NULL,
 quantity_after INT UNSIGNED NOT NULL,
 version_after INT UNSIGNED NOT NULL,
 reason VARCHAR(240) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY dealer_stock_request(owner_id,request_key),
 UNIQUE KEY dealer_stock_revision(item_id,version_after),
 CHECK (delta<>0 AND delta BETWEEN -1000000 AND 1000000 AND quantity_after<=1000000),
 FOREIGN KEY(owner_id,item_id) REFERENCES market_dealer_inventory(owner_id,id) ON DELETE CASCADE,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

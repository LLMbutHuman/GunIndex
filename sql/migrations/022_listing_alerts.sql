ALTER TABLE market_notification_preferences ADD COLUMN listing_alerts_enabled TINYINT NOT NULL DEFAULT 1,
 ADD CONSTRAINT notification_listing_alerts CHECK(listing_alerts_enabled IN (0,1));
ALTER TABLE market_listings ADD KEY listing_status_deadline(status,ends_at,id);

CREATE TABLE market_listing_notice_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 listing_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(16) CHARACTER SET ascii NOT NULL,
 deadline DATETIME NULL,
 old_price BIGINT UNSIGNED NULL,
 new_price BIGINT UNSIGNED NULL,
 last_user_id INT NOT NULL DEFAULT 0,
 seller_done TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 processed_at DATETIME(6) NULL,
 finished_at DATETIME(6) NULL,
 UNIQUE KEY listing_deadline_notice(listing_id,kind,deadline),
 KEY notice_newer(listing_id,kind,id),
 KEY notice_pending(finished_at,processed_at,id),
 FOREIGN KEY (listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 CHECK ((kind='price' AND deadline IS NULL AND old_price IS NOT NULL AND new_price IS NOT NULL AND old_price>0 AND new_price>0 AND old_price<>new_price)
   OR (kind IN ('ending','ended') AND deadline IS NOT NULL AND old_price IS NULL AND new_price IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

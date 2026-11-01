CREATE TABLE IF NOT EXISTS market_dealer_profiles (
 user_id INT PRIMARY KEY,company_name VARCHAR(150) NOT NULL,address_text VARCHAR(500) NOT NULL,
 country_code CHAR(2) NOT NULL,contact_email VARCHAR(190) NOT NULL,website VARCHAR(1000) NOT NULL DEFAULT '',
 legal_notice TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',moderation_reason VARCHAR(1000) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,country_code CHAR(2) NOT NULL,category_id INT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,target_url VARCHAR(1000) NOT NULL,storage_key VARCHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',moderation_reason VARCHAR(1000) NOT NULL DEFAULT '',moderated_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY campaigns_context(country_code,category_id,status),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(category_id) REFERENCES market_categories(id) ON DELETE RESTRICT,FOREIGN KEY(moderated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_business_inventory (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,country_code CHAR(2) NOT NULL,scope VARCHAR(24) NOT NULL,category_id INT UNSIGNED NOT NULL DEFAULT 0,
 slot_no TINYINT UNSIGNED NOT NULL,UNIQUE KEY context_slot(country_code,scope,category_id,slot_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_premium_bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id BIGINT UNSIGNED NOT NULL UNIQUE,user_id INT NOT NULL,
 inventory_id BIGINT UNSIGNED NOT NULL,listing_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NULL,
 country_code CHAR(2) NOT NULL,scope VARCHAR(24) NOT NULL,category_id INT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(24) NOT NULL DEFAULT 'reserved',reserved_until DATETIME NOT NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,
 immediate_start_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY inventory_occupancy(inventory_id,status,reserved_until,ends_at),KEY booking_expiry(status,reserved_until,ends_at),
 FOREIGN KEY(invoice_id) REFERENCES market_invoices(id) ON DELETE RESTRICT,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(inventory_id) REFERENCES market_business_inventory(id) ON DELETE RESTRICT,FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(campaign_id) REFERENCES market_campaigns(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_ad_daily_stats (
 campaign_id BIGINT UNSIGNED NOT NULL,day_date DATE NOT NULL,impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(campaign_id,day_date),FOREIGN KEY(campaign_id) REFERENCES market_campaigns(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_ad_metric_dedupe (
 campaign_id BIGINT UNSIGNED NOT NULL,kind VARCHAR(12) NOT NULL,hour_bucket BIGINT UNSIGNED NOT NULL,visitor_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(campaign_id,kind,hour_bucket,visitor_hash),KEY metric_retention(created_at),
 FOREIGN KEY(campaign_id) REFERENCES market_campaigns(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

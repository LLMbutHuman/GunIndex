-- Free discovery features. No historical data or external API is used.
CREATE TABLE IF NOT EXISTS market_wanted (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,category_id INT UNSIGNED NULL,
 title VARCHAR(150) NOT NULL,description TEXT NOT NULL,keywords VARCHAR(500) NOT NULL DEFAULT '',
 manufacturer VARCHAR(100) NOT NULL DEFAULT '',model VARCHAR(100) NOT NULL DEFAULT '',
 min_cents BIGINT UNSIGNED NULL,max_cents BIGINT UNSIGNED NULL,
 condition_label VARCHAR(20) NOT NULL DEFAULT 'any',country_code CHAR(2) NOT NULL DEFAULT 'DE',
 scope VARCHAR(16) NOT NULL DEFAULT 'country',zip VARCHAR(16) NOT NULL DEFAULT '',
 latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,radius_km INT UNSIGNED NULL,
 notify_mode VARCHAR(16) NOT NULL DEFAULT 'instant',status VARCHAR(16) NOT NULL DEFAULT 'active',
 backfill_id BIGINT UNSIGNED NOT NULL DEFAULT 0,backfill_target BIGINT UNSIGNED NOT NULL DEFAULT 0,
 backfill_complete TINYINT(1) NOT NULL DEFAULT 0,last_event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_processed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY wanted_owner(user_id,status,id),KEY wanted_worker(status,last_processed_at,id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(category_id) REFERENCES market_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_saved_searches (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,
 name VARCHAR(100) NOT NULL,criteria_json TEXT NOT NULL,criteria_hash CHAR(64) NOT NULL,
 notify_mode VARCHAR(16) NOT NULL DEFAULT 'instant',status VARCHAR(16) NOT NULL DEFAULT 'active',
 backfill_id BIGINT UNSIGNED NOT NULL DEFAULT 0,backfill_target BIGINT UNSIGNED NOT NULL DEFAULT 0,
 backfill_complete TINYINT(1) NOT NULL DEFAULT 0,last_event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_processed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY saved_search_owner_criteria(user_id,criteria_hash),KEY search_worker(status,last_processed_at,id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_discovery_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,listing_id BIGINT UNSIGNED NOT NULL,
 event_key CHAR(32) NOT NULL UNIQUE,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY discovery_event_listing(listing_id,id),
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_discovery_matches (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,
 query_kind VARCHAR(12) NOT NULL,query_id BIGINT UNSIGNED NOT NULL,listing_id BIGINT UNSIGNED NOT NULL,
 score TINYINT UNSIGNED NOT NULL,reasons_json TEXT NOT NULL,
 notification_state VARCHAR(16) NOT NULL DEFAULT 'existing',matched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY discovery_query_listing(query_kind,query_id,listing_id),
 KEY discovery_owner(user_id,matched_at,id),KEY discovery_notifications(notification_state,matched_at,user_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 CONSTRAINT discovery_kind CHECK(query_kind IN ('wanted','search')),
 CONSTRAINT discovery_score CHECK(score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_discovery_digests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,digest_date DATE NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY discovery_digest_day(user_id,digest_date),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

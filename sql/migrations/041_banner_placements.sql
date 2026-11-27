ALTER TABLE market_campaigns MODIFY COLUMN category_id INT UNSIGNED NULL;
ALTER TABLE market_manual_orders MODIFY COLUMN listing_id BIGINT UNSIGNED NULL;
ALTER TABLE market_premium_bookings MODIFY COLUMN listing_id BIGINT UNSIGNED NULL;
ALTER TABLE market_campaigns ADD COLUMN placement ENUM('legacy','home','search') NOT NULL DEFAULT 'legacy';
ALTER TABLE market_campaigns ADD COLUMN operator_owned TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE market_campaigns ADD COLUMN priority INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE market_campaigns ADD COLUMN creative_format ENUM('webp','gif','text') NOT NULL DEFAULT 'webp';
ALTER TABLE market_campaigns ADD COLUMN overlay_style ENUM('plain','dark','light') NOT NULL DEFAULT 'plain';
ALTER TABLE market_campaigns ADD COLUMN overlay_text VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE market_campaigns ADD COLUMN scheduled_from DATETIME NULL;
ALTER TABLE market_campaigns ADD COLUMN scheduled_until DATETIME NULL;
ALTER TABLE market_campaigns ADD COLUMN term_days TINYINT UNSIGNED NULL;
ALTER TABLE market_campaigns ADD COLUMN agreed_gross_cents INT UNSIGNED NULL;
ALTER TABLE market_campaigns ADD COLUMN submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
ALTER TABLE market_campaigns ADD INDEX campaign_placement(country_code,placement,status,category_id,priority);
ALTER TABLE market_campaigns ADD INDEX campaign_submission(user_id,submission_key);
CREATE TABLE IF NOT EXISTS market_banner_rates (
 placement ENUM('home','search') NOT NULL,
 term_days TINYINT UNSIGNED NOT NULL,
 gross_cents INT UNSIGNED NOT NULL,
 updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(placement,term_days),
 CHECK (term_days IN (7,14,30)),
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Local country markets, language preferences and licensed postcode coordinates.
ALTER TABLE users ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT 'DE';
ALTER TABLE users ADD COLUMN preferred_locale VARCHAR(8) NOT NULL DEFAULT 'de';
ALTER TABLE market_listings ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT 'DE';
ALTER TABLE market_listings ADD COLUMN latitude DECIMAL(10,7) NULL;
ALTER TABLE market_listings ADD COLUMN longitude DECIMAL(10,7) NULL;
CREATE TABLE IF NOT EXISTS market_countries (
 code CHAR(2) PRIMARY KEY,is_active TINYINT(1) NOT NULL DEFAULT 1,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO market_countries(code) VALUES ('DE'),('AT'),('CH'),('FR'),('BE'),('NL'),('LU'),('DK'),('PL'),('CZ'),('LI');
CREATE TABLE IF NOT EXISTS geo_imports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,country_code CHAR(2) NOT NULL,source_name VARCHAR(200) NOT NULL,
 source_sha256 CHAR(64) NOT NULL,license_note TEXT NOT NULL,license_reviewed TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,record_count INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(country_code) REFERENCES market_countries(code) ON DELETE RESTRICT,KEY country_import(country_code,is_active,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS geo_locations (
 country_code CHAR(2) NOT NULL,postal_code VARCHAR(16) NOT NULL,city VARCHAR(150) NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,longitude DECIMAL(10,7) NOT NULL,import_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(country_code,postal_code),KEY coordinates(latitude,longitude),KEY source_import(import_id),
 CONSTRAINT geo_latitude CHECK(latitude BETWEEN -90 AND 90),CONSTRAINT geo_longitude CHECK(longitude BETWEEN -180 AND 180),
 FOREIGN KEY(country_code) REFERENCES market_countries(code) ON DELETE RESTRICT,FOREIGN KEY(import_id) REFERENCES geo_imports(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_content_translations (
 setting_key VARCHAR(100) NOT NULL,locale VARCHAR(8) NOT NULL,content TEXT NOT NULL,updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(setting_key,locale),
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_category_translations (
 category_id INT UNSIGNED NOT NULL,locale VARCHAR(8) NOT NULL,name VARCHAR(100) NOT NULL,updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(category_id,locale),
 FOREIGN KEY(category_id) REFERENCES market_categories(id) ON DELETE RESTRICT,FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE market_listings ADD INDEX listing_country_postal(country_code,zip,status);

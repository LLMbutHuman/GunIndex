-- Existing listings begin without attribute values. No product facts are inferred.
ALTER TABLE market_listings ADD COLUMN attribute_digest CHAR(64) NULL,
 ADD UNIQUE KEY listing_category_reference(id,category_id);

CREATE TABLE market_category_attributes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label VARCHAR(80) NOT NULL,
 kind VARCHAR(16) CHARACTER SET ascii NOT NULL,
 unit VARCHAR(24) NOT NULL DEFAULT '',
 choices JSON NOT NULL,
 is_required TINYINT NOT NULL DEFAULT 0,
 is_active TINYINT NOT NULL DEFAULT 1,
 UNIQUE KEY attribute_code(category_id,code),
 UNIQUE KEY attribute_category_reference(id,category_id),
 KEY attribute_available(category_id,is_active,id),
 FOREIGN KEY(category_id) REFERENCES market_categories(id) ON DELETE RESTRICT,
 CHECK (kind IN ('text','number','choice','boolean')),
 CHECK (is_required IN (0,1) AND is_active IN (0,1)),
 CHECK (JSON_TYPE(choices)='ARRAY'),
 CHECK (kind='number' OR unit='')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_listing_attributes (
 listing_id BIGINT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 attribute_id INT UNSIGNED NOT NULL,
 text_value VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
 number_value DECIMAL(12,3) NULL,
 PRIMARY KEY(listing_id,attribute_id),
 KEY attribute_text_lookup(attribute_id,text_value,listing_id),
 KEY attribute_number_lookup(attribute_id,number_value,listing_id),
 FOREIGN KEY(listing_id,category_id) REFERENCES market_listings(id,category_id) ON DELETE RESTRICT,
 FOREIGN KEY(attribute_id,category_id) REFERENCES market_category_attributes(id,category_id) ON DELETE RESTRICT,
 CHECK ((text_value IS NULL) <> (number_value IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

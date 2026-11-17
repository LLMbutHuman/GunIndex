-- General-goods successors never participate in the auction free-relisting lineage.
-- A seller may create one independent draft per closed source, then continue from
-- that successor after it closes. Existing listings, orders and images stay intact.
CREATE TABLE IF NOT EXISTS market_listing_copies (
 parent_listing_id BIGINT UNSIGNED PRIMARY KEY,
 child_listing_id BIGINT UNSIGNED NOT NULL UNIQUE,
 seller_id INT NOT NULL,
 source_type ENUM('fixed','negotiated') NOT NULL,
 source_status ENUM('archived','ended','sold') NOT NULL,
 inventory_confirmed TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(parent_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(child_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT listing_copy_forward CHECK(parent_listing_id<child_listing_id),
 CONSTRAINT listing_copy_inventory CHECK(inventory_confirmed IN (0,1) AND (source_status<>'sold' OR inventory_confirmed=1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only application events. Exactly one optional explicit extension per
-- listing. Original deadline and tariff are not rewritten in this record.
CREATE TABLE IF NOT EXISTS market_listing_extensions (
 listing_id BIGINT UNSIGNED PRIMARY KEY,
 seller_id INT NOT NULL,
 listing_type ENUM('fixed','negotiated','auction') NOT NULL,
 original_ends_at DATETIME NOT NULL,
 extended_ends_at DATETIME NOT NULL,
 days SMALLINT UNSIGNED NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT listing_extension_bounds CHECK(
  (listing_type='auction' AND days IN (1,3,7)) OR
  (listing_type IN ('fixed','negotiated') AND days IN (7,14,30))
 ),
 CONSTRAINT listing_extension_period CHECK(extended_ends_at>original_ends_at AND TIMESTAMPDIFF(SECOND,original_ends_at,extended_ends_at)=days*86400),
 CONSTRAINT listing_extension_free CHECK(fee_cents=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

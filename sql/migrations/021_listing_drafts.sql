-- One private workspace per owner and editor. Editor 0 denotes a new listing.
CREATE TABLE market_listing_drafts (
 seller_id INT NOT NULL,
 editor_id BIGINT UNSIGNED NOT NULL,
 revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 base_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(seller_id,editor_id),
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_listing_draft_images (
 seller_id INT NOT NULL,
 editor_id BIGINT UNSIGNED NOT NULL,
 position TINYINT UNSIGNED NOT NULL,
 width SMALLINT UNSIGNED NOT NULL,
 height SMALLINT UNSIGNED NOT NULL,
 large_webp MEDIUMBLOB NOT NULL,
 thumb_webp MEDIUMBLOB NOT NULL,
 PRIMARY KEY(seller_id,editor_id,position),
 FOREIGN KEY(seller_id,editor_id) REFERENCES market_listing_drafts(seller_id,editor_id) ON DELETE CASCADE,
 CHECK (position<5 AND width BETWEEN 1 AND 1600 AND height BETWEEN 1 AND 1600)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

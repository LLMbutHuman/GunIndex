-- A continuous auction lineage has one optional listing-fee discount.
-- Historical listings/bids/contracts are never rewritten or imported.
CREATE TABLE IF NOT EXISTS market_relisting_chains (
 root_listing_id BIGINT UNSIGNED PRIMARY KEY,
 seller_id INT NOT NULL,
 free_listing_id BIGINT UNSIGNED NULL UNIQUE,
 free_used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY relisting_chain_owner(root_listing_id,seller_id),
 FOREIGN KEY(root_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(free_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 CONSTRAINT relisting_free_state CHECK(
  (free_listing_id IS NULL AND free_used_at IS NULL) OR
  (free_listing_id IS NOT NULL AND free_used_at IS NOT NULL AND free_listing_id>root_listing_id)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- At most one successor per parent, including after publication/abandonment.
-- child>parent and root<=parent rule out cycles; a successor is always a new row.
CREATE TABLE IF NOT EXISTS market_relistings (
 parent_listing_id BIGINT UNSIGNED PRIMARY KEY,
 child_listing_id BIGINT UNSIGNED NOT NULL UNIQUE,
 root_listing_id BIGINT UNSIGNED NOT NULL,
 seller_id INT NOT NULL,
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(parent_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(child_listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(root_listing_id,seller_id) REFERENCES market_relisting_chains(root_listing_id,seller_id) ON DELETE RESTRICT,
 FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 UNIQUE KEY relisting_lineage(root_listing_id,child_listing_id),
 CONSTRAINT relisting_forward CHECK(root_listing_id<=parent_listing_id AND parent_listing_id<child_listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A consumed entitlement must name a real successor from this exact lineage.
-- NULL still permits creating the chain before its first draft exists.
ALTER TABLE market_relisting_chains
 ADD CONSTRAINT relisting_free_in_chain FOREIGN KEY(root_listing_id,free_listing_id)
 REFERENCES market_relistings(root_listing_id,child_listing_id) ON DELETE RESTRICT;

-- Existing links were created only after a verified auction close without bids.
ALTER TABLE market_relistings
 ADD COLUMN source_outcome ENUM('no_bid','sold') NOT NULL DEFAULT 'no_bid' AFTER seller_id;

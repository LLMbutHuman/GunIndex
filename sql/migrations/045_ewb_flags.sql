ALTER TABLE market_categories
ADD COLUMN ewb_required TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE market_listings
ADD COLUMN ewb_required TINYINT(1) NOT NULL DEFAULT 0;

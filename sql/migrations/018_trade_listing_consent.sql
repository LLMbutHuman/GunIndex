-- Existing offers keep contact-based sale until the seller opts into contract checkout.
ALTER TABLE market_listings ADD COLUMN trade_checkout_enabled TINYINT(1) NOT NULL DEFAULT 0,
 ADD CONSTRAINT market_listing_trade_checkout CHECK (trade_checkout_enabled IN (0,1));

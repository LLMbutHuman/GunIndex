-- General-goods single-item contracts. No provider settlement or custody is implied.
ALTER TABLE market_listings ADD COLUMN shipping_cents BIGINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE market_trade_offers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 listing_id BIGINT UNSIGNED NOT NULL,
 seller_id INT NOT NULL, buyer_id INT NOT NULL, proposer_id INT NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 delivery VARCHAR(16) NOT NULL,
 price_cents BIGINT UNSIGNED NOT NULL, shipping_cents BIGINT UNSIGNED NOT NULL,
 total_cents BIGINT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'open',
 listing_hash CHAR(64) NOT NULL, snapshot_cipher MEDIUMTEXT NOT NULL,
 request_key CHAR(32) NOT NULL, request_hash CHAR(64) NOT NULL,
 consent_locale VARCHAR(8) NOT NULL, consent_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY trade_offer_request(proposer_id,request_key),
 UNIQUE KEY trade_offer_parent(parent_id),
 KEY trade_offer_expiry(status,expires_at),
 KEY trade_offer_parties(listing_id,buyer_id,status),
 FOREIGN KEY (listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (parent_id) REFERENCES market_trade_offers(id) ON DELETE RESTRICT,
 CHECK (seller_id<>buyer_id), CHECK (proposer_id IN (seller_id,buyer_id)),
 CHECK (price_cents>0 AND total_cents=price_cents+shipping_cents),
 CHECK (delivery IN ('pickup','shipping'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_trades (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(32) NOT NULL UNIQUE,
 listing_id BIGINT UNSIGNED NOT NULL UNIQUE,
 seller_id INT NOT NULL, buyer_id INT NOT NULL, offer_id BIGINT UNSIGNED NULL UNIQUE,
 delivery VARCHAR(16) NOT NULL,
 price_cents BIGINT UNSIGNED NOT NULL, shipping_cents BIGINT UNSIGNED NOT NULL,
 total_cents BIGINT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'agreed', version INT UNSIGNED NOT NULL DEFAULT 1,
 seller_confirmed TINYINT NOT NULL DEFAULT 0, buyer_confirmed TINYINT NOT NULL DEFAULT 0,
 requested_by INT NULL, previous_status VARCHAR(24) NULL,
 snapshot_cipher MEDIUMTEXT NOT NULL,
 request_actor INT NOT NULL, request_key CHAR(32) NOT NULL, request_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY trade_purchase_request(request_actor,request_key),
 KEY trade_buyer(buyer_id,id), KEY trade_seller(seller_id,id), KEY trade_status(status,id),
 FOREIGN KEY (listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (offer_id) REFERENCES market_trade_offers(id) ON DELETE RESTRICT,
 FOREIGN KEY (request_actor) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK (seller_id<>buyer_id), CHECK (request_actor IN (seller_id,buyer_id)),
 CHECK (price_cents>0 AND total_cents=price_cents+shipping_cents),
 CHECK (delivery IN ('pickup','shipping')),
 CHECK (status IN ('agreed','dispatched','completed','cancel_requested','cancelled','disputed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_trade_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 trade_id BIGINT UNSIGNED NOT NULL, actor_id INT NOT NULL,
 action VARCHAR(32) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NOT NULL,
 version_after INT UNSIGNED NOT NULL,
 request_key CHAR(32) NOT NULL, request_hash CHAR(64) NOT NULL,
 details_cipher TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY trade_event_request(actor_id,request_key),
 KEY trade_event_history(trade_id,id),
 FOREIGN KEY (trade_id) REFERENCES market_trades(id) ON DELETE RESTRICT,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_trade_consents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 trade_id BIGINT UNSIGNED NULL, offer_id BIGINT UNSIGNED NULL,
 user_id INT NOT NULL, document_id BIGINT UNSIGNED NOT NULL,
 content_hash CHAR(64) NOT NULL, locale VARCHAR(8) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY trade_consent(trade_id,user_id,document_id),
 UNIQUE KEY offer_consent(offer_id,user_id,document_id),
 FOREIGN KEY (trade_id) REFERENCES market_trades(id) ON DELETE RESTRICT,
 FOREIGN KEY (offer_id) REFERENCES market_trade_offers(id) ON DELETE RESTRICT,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (document_id) REFERENCES market_legal_documents(id) ON DELETE RESTRICT,
 CHECK ((trade_id IS NULL) <> (offer_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE market_ratings ADD COLUMN trade_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT rating_trade FOREIGN KEY (trade_id) REFERENCES market_trades(id) ON DELETE RESTRICT;

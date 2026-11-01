ALTER TABLE market_messages ADD COLUMN cipher_version TINYINT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS market_user_blocks(
 user_id INT NOT NULL,blocked_id INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,blocked_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(blocked_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT different_block_parties CHECK(user_id<>blocked_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_conversations(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,listing_id BIGINT UNSIGNED NOT NULL,seller_id INT NOT NULL,buyer_id INT NOT NULL,last_message_id BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY conversation_parties(listing_id,seller_id,buyer_id),KEY seller_inbox(seller_id,last_message_id),KEY buyer_inbox(buyer_id,last_message_id),
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(last_message_id) REFERENCES market_messages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO market_conversations(listing_id,seller_id,buyer_id,last_message_id)
 SELECT m.listing_id,l.seller_id,CASE WHEN m.sender_id=l.seller_id THEN m.receiver_id ELSE m.sender_id END,MAX(m.id)
 FROM market_messages m JOIN market_listings l ON l.id=m.listing_id
 WHERE (m.sender_id=l.seller_id OR m.receiver_id=l.seller_id) AND m.sender_id<>m.receiver_id
 GROUP BY m.listing_id,l.seller_id,CASE WHEN m.sender_id=l.seller_id THEN m.receiver_id ELSE m.sender_id END
 ON DUPLICATE KEY UPDATE last_message_id=GREATEST(last_message_id,VALUES(last_message_id));

-- E2EE metadata and opaque packets are separate from the preserved legacy history.
-- An account identity is deliberately permanent. No private device keys are stored.
CREATE TABLE IF NOT EXISTS market_e2ee_accounts (
 user_id INT PRIMARY KEY,
 identity_key VARCHAR(44) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 current_version INT UNSIGNED NOT NULL,
 current_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT e2ee_account_version CHECK(current_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_e2ee_manifests (
 user_id INT NOT NULL,
 version INT UNSIGNED NOT NULL,
 manifest MEDIUMTEXT NOT NULL,
 signature VARCHAR(88) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 recovery_cipher MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,version),
 UNIQUE KEY e2ee_manifest_hash(user_id,manifest_hash),
 FOREIGN KEY(user_id) REFERENCES market_e2ee_accounts(user_id) ON DELETE RESTRICT,
 CONSTRAINT e2ee_manifest_version CHECK(version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_e2ee_messages (
 message_id BIGINT UNSIGNED PRIMARY KEY,
 sender_id INT NOT NULL,
 receiver_id INT NOT NULL,
 device_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 client_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sender_version INT UNSIGNED NOT NULL,
 receiver_version INT UNSIGNED NOT NULL,
 ciphertext MEDIUMTEXT NOT NULL,
 payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 UNIQUE KEY e2ee_sender_nonce(sender_id,client_id),
 FOREIGN KEY(message_id) REFERENCES market_messages(id) ON DELETE RESTRICT,
 FOREIGN KEY(sender_id,sender_version) REFERENCES market_e2ee_manifests(user_id,version) ON DELETE RESTRICT,
 FOREIGN KEY(receiver_id,receiver_version) REFERENCES market_e2ee_manifests(user_id,version) ON DELETE RESTRICT,
 CONSTRAINT e2ee_distinct_parties CHECK(sender_id<>receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_e2ee_attachments (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 owner_id INT NOT NULL,
 listing_id BIGINT UNSIGNED NOT NULL,
 peer_id INT NOT NULL,
 device_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 client_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 storage_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 cipher_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cipher_bytes INT UNSIGNED NOT NULL,
 message_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY e2ee_attachment_quota(owner_id,message_id),
 KEY e2ee_message_files(message_id,id),
 FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(peer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT,
 FOREIGN KEY(message_id) REFERENCES market_e2ee_messages(message_id) ON DELETE RESTRICT,
 CONSTRAINT e2ee_attachment_size CHECK(cipher_bytes>0 AND cipher_bytes<=5242880),
 CONSTRAINT e2ee_attachment_parties CHECK(owner_id<>peer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

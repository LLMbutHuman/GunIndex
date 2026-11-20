CREATE TABLE market_dealer_api_keys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id INT NOT NULL,
 label VARCHAR(80) NOT NULL,
 selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 scopes TINYINT UNSIGNED NOT NULL,
 request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 UNIQUE KEY dealer_api_request(owner_id,request_key),
 KEY dealer_api_active(owner_id,revoked_at,expires_at),
 CHECK (scopes BETWEEN 1 AND 7),
 FOREIGN KEY(owner_id) REFERENCES market_dealer_profiles(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

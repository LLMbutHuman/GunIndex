-- PHP session identifiers and raw device fingerprints never enter this table.
CREATE TABLE market_auth_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 auth_version INT NOT NULL,
 browser VARCHAR(16) CHARACTER SET ascii NOT NULL,
 platform VARCHAR(16) CHARACTER SET ascii NOT NULL,
 started_at DATETIME NOT NULL,
 last_seen_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 KEY auth_sessions_owner(user_id,revoked_at,expires_at,id),
 KEY auth_sessions_expiry(expires_at,id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

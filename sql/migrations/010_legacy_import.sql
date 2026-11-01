CREATE TABLE IF NOT EXISTS market_legacy_user_map (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_fingerprint CHAR(64) NOT NULL,
 source_user_id BIGINT UNSIGNED NOT NULL,
 target_user_id INT NOT NULL,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY legacy_user_once(source_fingerprint,source_user_id),
 FOREIGN KEY(target_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

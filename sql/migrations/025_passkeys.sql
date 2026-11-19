CREATE TABLE market_passkey_accounts (
 user_id INT PRIMARY KEY,
 user_handle CHAR(43) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_passkeys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 credential_id VARCHAR(1366) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 rp_id VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 public_key TEXT NOT NULL,
 sign_count INT UNSIGNED NOT NULL DEFAULT 0,
 backup_eligible TINYINT NOT NULL,
 label VARCHAR(80) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_used_at DATETIME NULL,
 KEY passkey_owner(user_id,id),
 FOREIGN KEY (user_id) REFERENCES market_passkey_accounts(user_id) ON DELETE CASCADE,
 CHECK (backup_eligible IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

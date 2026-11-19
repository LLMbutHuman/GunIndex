CREATE TABLE market_user_totp (
 user_id INT PRIMARY KEY,
 secret_cipher VARCHAR(600) NOT NULL,
 last_counter BIGINT UNSIGNED NOT NULL,
 enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE market_totp_recovery_codes (
 user_id INT NOT NULL,
 code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 used_at DATETIME NULL,
 PRIMARY KEY (user_id,code_hash),
 FOREIGN KEY (user_id) REFERENCES market_user_totp(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

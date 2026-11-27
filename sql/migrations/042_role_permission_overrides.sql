CREATE TABLE IF NOT EXISTS market_role_policy_state (
 id TINYINT UNSIGNED PRIMARY KEY,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_admin_setup_checkpoints (
 user_id INT NOT NULL PRIMARY KEY,
 step_code VARCHAR(32) NOT NULL,
 CONSTRAINT market_admin_setup_checkpoint_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO market_role_policy_state (id) VALUES (1);

CREATE TABLE IF NOT EXISTS market_role_permission_overrides (
 role_code VARCHAR(40) NOT NULL,
 permission_code VARCHAR(80) NOT NULL,
 granted TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY (role_code,permission_code),
 CONSTRAINT market_role_permission_role_fk FOREIGN KEY (role_code) REFERENCES market_roles(code) ON DELETE RESTRICT,
 CONSTRAINT market_role_permission_override_value CHECK (granted IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

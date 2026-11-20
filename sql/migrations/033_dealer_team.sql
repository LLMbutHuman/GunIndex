CREATE TABLE market_dealer_team (
 owner_id INT NOT NULL,
 member_id INT NOT NULL,
 role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'invited',
 version INT UNSIGNED NOT NULL DEFAULT 1,
 expires_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(owner_id,member_id),
 KEY dealer_team_member(member_id,owner_id),
 KEY dealer_team_status(owner_id,status,expires_at),
 CHECK (owner_id<>member_id AND role IN ('viewer','inventory','operations')),
 CHECK (status IN ('invited','active','declined','revoked') AND (expires_at IS NOT NULL)=(status='invited')),
 FOREIGN KEY(owner_id) REFERENCES market_dealer_profiles(user_id) ON DELETE CASCADE,
 FOREIGN KEY(member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

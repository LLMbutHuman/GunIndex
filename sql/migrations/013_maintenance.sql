-- Run with application and workers stopped, as for other production migrations.
CREATE TABLE IF NOT EXISTS market_maintenance_windows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activation_key VARCHAR(64) NOT NULL UNIQUE,
 started_at DATETIME(6) NOT NULL,ended_at DATETIME(6) NULL,
 duration_seconds BIGINT UNSIGNED NULL,activated_by INT NULL,deactivated_by INT NULL,
 FOREIGN KEY(activated_by) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(deactivated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_maintenance_state (
 id TINYINT UNSIGNED PRIMARY KEY,active TINYINT NOT NULL DEFAULT 0,
 window_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(window_id) REFERENCES market_maintenance_windows(id) ON DELETE RESTRICT,
 CONSTRAINT maintenance_singleton CHECK(id=1),
 CONSTRAINT maintenance_active CHECK(active IN (0,1)),
 CONSTRAINT maintenance_window CHECK(active=0 OR window_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_maintenance_auctions (
 window_id BIGINT UNSIGNED NOT NULL,listing_id BIGINT UNSIGNED NOT NULL,
 original_ends_at DATETIME NOT NULL,
 PRIMARY KEY(window_id,listing_id),
 FOREIGN KEY(window_id) REFERENCES market_maintenance_windows(id) ON DELETE RESTRICT,
 FOREIGN KEY(listing_id) REFERENCES market_listings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO market_maintenance_state(id,active) VALUES (1,0);
-- A pre-existing hint becomes a real pause only from this migration's timestamp.
INSERT IGNORE INTO market_maintenance_windows(activation_key,started_at)
 SELECT 'migration-013',UTC_TIMESTAMP(6) FROM market_maintenance_state
 WHERE id=1 AND active=0 AND window_id IS NULL
 AND EXISTS(SELECT 1 FROM system_settings WHERE setting_key='maintenance_enabled' AND setting_value='1');
UPDATE market_maintenance_state s JOIN market_maintenance_windows w ON w.activation_key='migration-013'
 SET s.active=1,s.window_id=w.id WHERE s.id=1 AND s.active=0 AND s.window_id IS NULL;
INSERT IGNORE INTO market_maintenance_auctions(window_id,listing_id,original_ends_at)
 SELECT w.id,l.id,l.ends_at FROM market_maintenance_state s
 JOIN market_maintenance_windows w ON w.id=s.window_id AND w.activation_key='migration-013'
 JOIN market_listings l ON l.type='auction' AND l.status IN ('active','paused') AND l.ends_at>w.started_at
 WHERE s.id=1 AND s.active=1;

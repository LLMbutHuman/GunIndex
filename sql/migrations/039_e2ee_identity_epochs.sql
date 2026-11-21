CREATE TABLE IF NOT EXISTS market_e2ee_identity_epochs (
 user_id INT NOT NULL,
 start_version INT UNSIGNED NOT NULL,
 identity_key VARCHAR(44) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(user_id,start_version),
 UNIQUE KEY e2ee_identity_used_once(user_id,identity_key),
 FOREIGN KEY(user_id,start_version) REFERENCES market_e2ee_manifests(user_id,version) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO market_e2ee_identity_epochs(user_id,start_version,identity_key)
 SELECT user_id,1,JSON_UNQUOTE(JSON_EXTRACT(manifest,'$.identity_key')) FROM market_e2ee_manifests WHERE version=1
 ON DUPLICATE KEY UPDATE start_version=market_e2ee_identity_epochs.start_version;

ALTER TABLE market_e2ee_attachments
 ADD COLUMN sender_version INT UNSIGNED NULL,
 ADD COLUMN receiver_version INT UNSIGNED NULL,
 ADD CONSTRAINT e2ee_upload_version_pair CHECK (
  (sender_version IS NULL AND receiver_version IS NULL) OR
  (sender_version IS NOT NULL AND receiver_version IS NOT NULL AND sender_version>=1 AND receiver_version>=1)
 );

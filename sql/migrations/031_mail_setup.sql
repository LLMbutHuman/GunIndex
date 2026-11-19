CREATE TABLE market_mail_setup (
 id TINYINT UNSIGNED PRIMARY KEY,
 revision CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_ciphertext MEDIUMTEXT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT mail_setup_singleton CHECK (id=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO market_mail_setup(id,revision) VALUES (1,REPEAT('0',64));

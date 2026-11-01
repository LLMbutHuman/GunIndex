ALTER TABLE users ADD COLUMN identity_verified_at DATETIME NULL;
ALTER TABLE users ADD COLUMN age_verified_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS market_roles (
 code VARCHAR(40) PRIMARY KEY,label VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_role_grants (
 user_id INT NOT NULL,role_code VARCHAR(40) NOT NULL,granted_by INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,role_code),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(role_code) REFERENCES market_roles(code) ON DELETE RESTRICT,FOREIGN KEY(granted_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO market_roles(code,label) VALUES ('administrator','Administration'),('moderator','Moderation'),('support','Support'),('verification','Identitätsprüfung'),('finance','Finanzen');
CREATE TABLE IF NOT EXISTS market_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,case_code VARCHAR(40) NOT NULL UNIQUE,reporter_id INT NOT NULL,subject_user_id INT NOT NULL,
 target_type VARCHAR(20) NOT NULL,target_id BIGINT UNSIGNED NOT NULL,reason TEXT NOT NULL,evidence_cipher MEDIUMTEXT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'open',assigned_to INT NULL,decision_action VARCHAR(40) NULL,decision_reason TEXT NULL,
 appeal_text TEXT NULL,appeal_by INT NULL,appeal_resolution TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(subject_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(appeal_by) REFERENCES users(id) ON DELETE RESTRICT,
 KEY report_owner(reporter_id,id),KEY subject_case(subject_user_id,id),KEY moderation_queue(status,assigned_to,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_deletion_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,status VARCHAR(24) NOT NULL DEFAULT 'pending_review',
 request_reason VARCHAR(2000) NOT NULL,review_reason TEXT NULL,reviewed_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_verification_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,status VARCHAR(24) NOT NULL DEFAULT 'pending',assigned_to INT NULL,
 identity_confirmed TINYINT(1) NOT NULL DEFAULT 0,age_confirmed TINYINT(1) NOT NULL DEFAULT 0,decision_reason VARCHAR(2000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,expires_at DATETIME NOT NULL,purged_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
 KEY verification_queue(status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_verification_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,storage_key CHAR(48) NOT NULL UNIQUE,
 mime_type VARCHAR(40) NOT NULL DEFAULT 'image/webp',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,deleted_at DATETIME NULL,
 FOREIGN KEY(request_id) REFERENCES market_verification_requests(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_document_access (
 token_hash CHAR(64) PRIMARY KEY,document_id BIGINT UNSIGNED NOT NULL,actor_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,
 expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(document_id) REFERENCES market_verification_documents(id) ON DELETE RESTRICT,FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_legal_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,document_code VARCHAR(80) NOT NULL,version VARCHAR(30) NOT NULL,language VARCHAR(8) NOT NULL DEFAULT 'de',
 title VARCHAR(200) NOT NULL,body_text MEDIUMTEXT NOT NULL,content_hash CHAR(64) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',
 reviewer_name VARCHAR(200) NULL,review_checklist TEXT NULL,approved_by INT NULL,approved_at DATETIME NULL,effective_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY legal_version(document_code,version,language),
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE RESTRICT,KEY legal_public(document_code,language,status,effective_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS market_legal_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,document_id BIGINT UNSIGNED NOT NULL,content_hash CHAR(64) NOT NULL,
 acceptance_type VARCHAR(40) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY legal_acceptance(user_id,document_id,acceptance_type),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(document_id) REFERENCES market_legal_documents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

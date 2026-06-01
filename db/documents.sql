-- =====================================================================
-- 자료실 (document) + 댓글(doc_comment) + 변경이력(doc_history) + 전 회원 권한 시드
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/documents.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;

CREATE TABLE IF NOT EXISTS document (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(300)  NOT NULL,
  link        VARCHAR(1000) NOT NULL DEFAULT '',
  content     MEDIUMTEXT    NULL,
  view_count  INT UNSIGNED  NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  created_by  VARCHAR(50)   NULL,
  updated_by  VARCHAR(50)   NULL,
  KEY idx_doc_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doc_comment (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_id      INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  author_id   INT NULL,
  author_name VARCHAR(50) NULL,
  body        TEXT NOT NULL,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dc_doc (doc_id),
  KEY idx_dc_parent (parent_id),
  CONSTRAINT fk_dc_doc    FOREIGN KEY (doc_id)    REFERENCES document(id)    ON DELETE CASCADE,
  CONSTRAINT fk_dc_parent FOREIGN KEY (parent_id) REFERENCES doc_comment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doc_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_id      INT UNSIGNED NOT NULL,
  field_key   VARCHAR(30)  NOT NULL,
  field_label VARCHAR(50)  NOT NULL,
  before_val  TEXT         NULL,
  after_val   TEXT         NULL,
  changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by  VARCHAR(50)  NULL,
  KEY idx_dh_doc (doc_id, id),
  CONSTRAINT fk_dh_doc FOREIGN KEY (doc_id) REFERENCES document(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 전 회원에게 documents 리소스 CRUD 시드 (없으면 can()=false로 접근 차단되므로 필수)
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
  SELECT id, 'documents', 1, 1, 1, 1, 1 FROM member;

-- =====================================================================
-- 공지사항 댓글/대댓글 (notif_comment) + 변경이력 (notif_history)
--  - req_*/todo_* 와 동일 구조, FK만 notification(id)
--  - notification.id 가 INT UNSIGNED 이므로 FK 컬럼도 INT UNSIGNED (안 맞추면 errno 3780)
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/notification_comment_history.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;

CREATE TABLE IF NOT EXISTS notif_comment (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notif_id    INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  author_id   INT NULL,
  author_name VARCHAR(50) NULL,
  body        TEXT NOT NULL,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_nc_notif (notif_id),
  KEY idx_nc_parent (parent_id),
  CONSTRAINT fk_nc_notif  FOREIGN KEY (notif_id)  REFERENCES notification(id)  ON DELETE CASCADE,
  CONSTRAINT fk_nc_parent FOREIGN KEY (parent_id) REFERENCES notif_comment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notif_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notif_id    INT UNSIGNED NOT NULL,
  field_key   VARCHAR(30)  NOT NULL,
  field_label VARCHAR(50)  NOT NULL,
  before_val  TEXT         NULL,
  after_val   TEXT         NULL,
  changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by  VARCHAR(50)  NULL,
  KEY idx_nh_notif (notif_id, id),
  CONSTRAINT fk_nh_notif FOREIGN KEY (notif_id) REFERENCES notification(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

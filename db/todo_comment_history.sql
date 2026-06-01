-- =====================================================================
-- 서비스기획 TO-DO 댓글/대댓글(todo_comment) + 변경이력(todo_history)
--  - requirements의 req_comment / req_history와 동일 구조, FK만 todo(id)
--  - todo.id 가 INT UNSIGNED 이므로 FK 컬럼도 INT UNSIGNED (안 맞추면 errno 3780)
--  - todo_comment : 작업별 댓글 트리(parent_id), is_deleted 소프트삭제
--  - todo_history : 작업 PUT(수정 저장) 시 바뀐 필드마다 1행(필드 단위)
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/todo_comment_history.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;

CREATE TABLE IF NOT EXISTS todo_comment (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  todo_id     INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  author_id   INT NULL,
  author_name VARCHAR(50) NULL,
  body        TEXT NOT NULL,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tc_todo (todo_id),
  KEY idx_tc_parent (parent_id),
  CONSTRAINT fk_tc_todo   FOREIGN KEY (todo_id)   REFERENCES todo(id)         ON DELETE CASCADE,
  CONSTRAINT fk_tc_parent FOREIGN KEY (parent_id) REFERENCES todo_comment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS todo_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  todo_id     INT UNSIGNED NOT NULL,
  field_key   VARCHAR(30)  NOT NULL,
  field_label VARCHAR(50)  NOT NULL,
  before_val  TEXT         NULL,
  after_val   TEXT         NULL,
  changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by  VARCHAR(50)  NULL,
  KEY idx_th_todo (todo_id, id),
  CONSTRAINT fk_th_todo FOREIGN KEY (todo_id) REFERENCES todo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

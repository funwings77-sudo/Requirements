-- =====================================================================
-- 요구사항 댓글/대댓글 (req_comment)
--  - requirement_id : 어느 요구사항에 달린 댓글인가 (요구사항 삭제 시 함께 삭제)
--  - parent_id      : NULL=최상위 댓글, 값 있으면 해당 댓글의 답글(대댓글, 트리)
--  - author_id/name : 작성 시점의 로그인 회원(이름 비정규화 저장 → 회원 변경/삭제와 무관하게 표시)
--  - is_deleted     : 소프트 삭제(답글 구조 보존, 화면엔 '삭제된 댓글입니다' 표시)
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/comment.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
CREATE TABLE IF NOT EXISTS req_comment (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requirement_id INT UNSIGNED NOT NULL,
  parent_id      INT UNSIGNED NULL,
  author_id      INT NULL,
  author_name    VARCHAR(50) NULL,
  body           TEXT NOT NULL,
  is_deleted     TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rc_req (requirement_id),
  KEY idx_rc_parent (parent_id),
  CONSTRAINT fk_rc_req    FOREIGN KEY (requirement_id) REFERENCES requirement(id) ON DELETE CASCADE,
  CONSTRAINT fk_rc_parent FOREIGN KEY (parent_id)      REFERENCES req_comment(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

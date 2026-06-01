-- =====================================================================
-- 공지사항 (notification)
--  - title:        제목 (필수)
--  - category:     카테고리 (일반/공지/긴급/시스템)
--  - is_important: 중요 공지 여부 (별표 표시용)
--  - publish_date: 게시일 (YYYY-MM-DD)
--  - status:       상태 (게시/임시저장/숨김)
--  - content:      본문(텍스트, 줄바꿈 유지)
--  - view_count:   조회수 (상세 조회 시 +1)
--  - created/updated_at/by: 메타. updated_at은 ON UPDATE 자동 미사용 → PUT에서 명시 갱신
-- 적용: mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/notification.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
CREATE TABLE IF NOT EXISTS notification (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(300)  NOT NULL,
  category     VARCHAR(30)   NOT NULL DEFAULT '일반',
  is_important TINYINT(1)    NOT NULL DEFAULT 0,
  publish_date VARCHAR(10)   NOT NULL DEFAULT '',
  status       VARCHAR(20)   NOT NULL DEFAULT '게시',
  content      MEDIUMTEXT    NULL,
  view_count   INT UNSIGNED  NOT NULL DEFAULT 0,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  created_by   VARCHAR(50)   NULL,
  updated_by   VARCHAR(50)   NULL,
  KEY idx_notif_status (status),
  KEY idx_notif_date (publish_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

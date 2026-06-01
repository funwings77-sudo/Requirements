-- =====================================================================
-- 요구사항 변경이력 (req_history)
--  - requirement_id : 어느 요구사항의 변경인가 (요구사항 삭제 시 함께 삭제)
--  - field_key      : 변경된 필드 키 (FIELDS의 key, 예: status / priority)
--  - field_label    : 표시용 라벨 (작성 시점 비정규화 저장, 예: 상태 / 우선순위)
--  - before_val     : 변경 전 표시값(라벨/텍스트), after_val : 변경 후 표시값
--  - changed_by     : 변경한 로그인 회원 이름(비정규화)
--  요구사항 PUT(수정 저장) 시 바뀐 필드마다 1행씩 lib.php가 INSERT.
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_req_history.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
CREATE TABLE IF NOT EXISTS req_history (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requirement_id INT UNSIGNED NOT NULL,
  field_key      VARCHAR(30)  NOT NULL,
  field_label    VARCHAR(50)  NOT NULL,
  before_val     TEXT         NULL,
  after_val      TEXT         NULL,
  changed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by     VARCHAR(50)  NULL,
  KEY idx_rh_req (requirement_id, id),
  CONSTRAINT fk_rh_req FOREIGN KEY (requirement_id) REFERENCES requirement(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

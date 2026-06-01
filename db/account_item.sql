-- 계정관리 대장 — 분류별 항목(이종 컬럼을 data JSON으로 저장)
CREATE TABLE IF NOT EXISTS account_item (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category    VARCHAR(20) NOT NULL COMMENT 'site/vendor/mobile/test',
  sort_order  INT NULL,
  data        JSON NULL COMMENT '{컬럼라벨: 값} — NO 컬럼 제외(번호는 표시 순번)',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by  VARCHAR(60) NULL,
  updated_by  VARCHAR(60) NULL,
  KEY idx_cat (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='계정관리 대장 항목';

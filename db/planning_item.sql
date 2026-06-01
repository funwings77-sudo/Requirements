-- 기획서 목록(planning) CRUD 테이블 + devops/planning 권한 분리 시드
CREATE TABLE IF NOT EXISTS planning_item (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sort_order  INT NULL,
  gubun       VARCHAR(255) NULL COMMENT '구분',
  title       VARCHAR(255) NULL COMMENT '제목',
  done        VARCHAR(20)  NULL COMMENT '작성완료(완료/진행 중)',
  `system`    VARCHAR(255) NULL COMMENT '시스템',
  wireframe   VARCHAR(1000) NULL COMMENT '와이어프레임 URL',
  author      VARCHAR(60)  NULL COMMENT '작성자',
  doc_date    DATE NULL COMMENT '최종 작성일자',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by  VARCHAR(60) NULL,
  updated_by  VARCHAR(60) NULL,
  KEY idx_sort (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='기획서 목록';

-- 기존 회원에게 devops·planning 접속/읽기 권한 시드(관리자는 권한설정에서 조정)
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
SELECT id, 'devops', 1, 1, 0, 0, 0 FROM member;
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
SELECT id, 'planning', 1, 1, 0, 0, 0 FROM member;

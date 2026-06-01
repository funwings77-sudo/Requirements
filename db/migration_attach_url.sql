-- 마이그레이션: 첨부 확인 컬럼을 URL 저장용으로 확장 (VARCHAR(20) → VARCHAR(500))
ALTER TABLE project_item
  MODIFY COLUMN attach_plan VARCHAR(500) NULL COMMENT '기획서 첨부(URL)',
  MODIFY COLUMN attach_des  VARCHAR(500) NULL COMMENT '디자인 첨부(URL)',
  MODIFY COLUMN attach_pub  VARCHAR(500) NULL COMMENT '퍼블리싱 첨부(URL)';

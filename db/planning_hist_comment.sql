-- 기획서 변경이력 + 댓글
CREATE TABLE IF NOT EXISTS planning_comment (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT NOT NULL,
  parent_id   INT UNSIGNED NULL,
  author_id   INT NULL,
  author_name VARCHAR(50) NULL,
  body        TEXT NOT NULL,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pc_plan (plan_id),
  KEY idx_pc_parent (parent_id),
  CONSTRAINT fk_pc_plan   FOREIGN KEY (plan_id)   REFERENCES planning_item(id)   ON DELETE CASCADE,
  CONSTRAINT fk_pc_parent FOREIGN KEY (parent_id) REFERENCES planning_comment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planning_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT NOT NULL,
  field_key   VARCHAR(30)  NOT NULL,
  field_label VARCHAR(50)  NOT NULL,
  before_val  TEXT         NULL,
  after_val   TEXT         NULL,
  changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by  VARCHAR(50)  NULL,
  KEY idx_ph_plan (plan_id, id),
  CONSTRAINT fk_ph_plan FOREIGN KEY (plan_id) REFERENCES planning_item(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

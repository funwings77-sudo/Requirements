-- =====================================================================
--  메디힘 요구사항 수집 - MySQL 스키마 (정규화 / 코드 테이블 + FK)
--  charset: utf8mb4 (한글/이모지 안전)
--  실행:  mysql -u root -p < db/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS medihim
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE medihim;

-- 재실행 가능하도록 기존 객체 제거 (FK 의존 순서 고려)
DROP VIEW  IF EXISTS v_dashboard_version;
DROP VIEW  IF EXISTS v_dashboard_status;
DROP VIEW  IF EXISTS v_dashboard_priority;
DROP VIEW  IF EXISTS v_dashboard_category;
DROP VIEW  IF EXISTS v_dashboard_summary;
DROP VIEW  IF EXISTS v_requirement;
DROP TABLE IF EXISTS requirement;
DROP TABLE IF EXISTS cat_rank;
DROP TABLE IF EXISTS cat_status;
DROP TABLE IF EXISTS cat_version;
DROP TABLE IF EXISTS cat_level;
DROP TABLE IF EXISTS cat_priority;
DROP TABLE IF EXISTS cat_part;
DROP TABLE IF EXISTS cat_category;

-- =====================================================================
--  코드(공통) 테이블
--  각 드롭다운 도메인을 별도 테이블로 정규화. label 은 UNIQUE.
-- =====================================================================

-- 구분
CREATE TABLE cat_category (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,           -- 안정적 영문 코드 (API용)
  label      VARCHAR(30)  NOT NULL,           -- 화면 표시값 (한글)
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_category_code  (code),
  UNIQUE KEY uk_cat_category_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='구분(기능/비기능/...)';

-- 파트 (요청파트 / 수행파트 공용)
CREATE TABLE cat_part (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_part_code  (code),
  UNIQUE KEY uk_cat_part_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='파트(사업부/마케팅/...)';

-- 우선순위 (필수/권장/선택)
CREATE TABLE cat_priority (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_priority_code  (code),
  UNIQUE KEY uk_cat_priority_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='우선순위(필수/권장/선택)';

-- 레벨 (중요도 / 난이도 공용: 상/중/하)
CREATE TABLE cat_level (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_level_code  (code),
  UNIQUE KEY uk_cat_level_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='레벨(상/중/하)';

-- 목표 버전
CREATE TABLE cat_version (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_version_code  (code),
  UNIQUE KEY uk_cat_version_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='목표버전(v1.0/.../미정)';

-- 상태
CREATE TABLE cat_status (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_status_code  (code),
  UNIQUE KEY uk_cat_status_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상태(신규/검토중/...)';

-- 우선순위 순번 (선택 / 1~10)
CREATE TABLE cat_rank (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  label      VARCHAR(30)  NOT NULL,
  sort_order TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_rank_code  (code),
  UNIQUE KEY uk_cat_rank_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='우선순위 순번(선택/1~10)';

-- =====================================================================
--  메인 테이블: requirement
--  코드 컬럼은 *_id (FK). 미선택 항목은 NULL.
-- =====================================================================
CREATE TABLE requirement (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  rank_id       TINYINT UNSIGNED  NULL,                       -- 우선순위(순번)
  category_id   TINYINT UNSIGNED  NOT NULL,                   -- 구분 (필수)
  major         VARCHAR(150)      NULL,                       -- 대분류(1st Depth)
  middle        VARCHAR(150)      NULL,                       -- 중분류(2nd Depth)
  name          VARCHAR(300)      NOT NULL,                   -- 요구사항명
  purpose       VARCHAR(1000)     NULL,                       -- 목적 & 필요성
  detail        TEXT              NULL,                       -- 상세 설명
  link          VARCHAR(500)      NULL,                       -- 참고링크
  doc           VARCHAR(500)      NULL,                       -- 참고문서
  requester     VARCHAR(100)      NULL,                       -- 요청자
  req_part_id   TINYINT UNSIGNED  NULL,                       -- 요청파트
  req_date      DATE              NULL,                       -- 요청일자
  do_part_id    TINYINT UNSIGNED  NULL,                       -- 수행파트
  do_person     VARCHAR(100)      NULL,                       -- 수행담당자
  priority_id   TINYINT UNSIGNED  NULL,                       -- 우선순위(중요)
  importance_id TINYINT UNSIGNED  NULL,                       -- 중요도
  difficulty_id TINYINT UNSIGNED  NULL,                       -- 난이도
  effort        DECIMAL(6,1)      NULL,                       -- 예상공수(MD)
  version_id    TINYINT UNSIGNED  NULL,                       -- 목표 버전
  status_id     TINYINT UNSIGNED  NULL,                       -- 상태
  reviewer      VARCHAR(100)      NULL,                       -- 검토자
  approve_date  DATE              NULL,                       -- 승인일
  start_date    DATE              NULL,                       -- 시작일자
  end_date      DATE              NULL,                       -- 종료일자(개발서버반영)
  prod_date     DATE              NULL,                       -- 운영서버반영일자
  remark        TEXT              NULL,                       -- 비고
  sort_order    INT UNSIGNED      NOT NULL DEFAULT 0,         -- 목록 표시 순서(드래그 정렬, 작을수록 위)
  created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- 수정 시 lib.php가 명시 갱신(ON UPDATE 미사용 → reorder엔 안 바뀜)
  updated_by    VARCHAR(50)       NULL,                                 -- 최종수정자(POST 작성자/PUT 수정자)
  created_by    VARCHAR(50)       NULL,                                 -- 최초작성자(POST 시 기록, PUT에서 불변) — 행 단위 수정/삭제 권한 판별용
  PRIMARY KEY (id),
  KEY idx_req_category (category_id),
  KEY idx_req_priority (priority_id),
  KEY idx_req_status   (status_id),
  KEY idx_req_version  (version_id),
  KEY idx_req_sort     (sort_order),
  CONSTRAINT fk_req_rank       FOREIGN KEY (rank_id)       REFERENCES cat_rank(id)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_category   FOREIGN KEY (category_id)   REFERENCES cat_category(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_req_req_part   FOREIGN KEY (req_part_id)   REFERENCES cat_part(id)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_do_part    FOREIGN KEY (do_part_id)    REFERENCES cat_part(id)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_priority   FOREIGN KEY (priority_id)   REFERENCES cat_priority(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_importance FOREIGN KEY (importance_id) REFERENCES cat_level(id)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_difficulty FOREIGN KEY (difficulty_id) REFERENCES cat_level(id)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_version    FOREIGN KEY (version_id)    REFERENCES cat_version(id)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_req_status     FOREIGN KEY (status_id)     REFERENCES cat_status(id)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='요구사항 목록';

-- =====================================================================
--  뷰: v_requirement
--  코드 id 를 한글 label 로 JOIN 하여 원본 엑셀 시트와 동일한 평면 형태로 반환.
--  컬럼명은 프런트엔드 필드명(camelCase)에 맞춰 alias.
-- =====================================================================
CREATE VIEW v_requirement AS
SELECT
  r.id                                              AS id,
  COALESCE(rk.label, '')                            AS `rank`,
  c.label                                           AS category,
  COALESCE(r.major, '')                             AS major,
  COALESCE(r.middle, '')                            AS middle,
  r.name                                            AS name,
  COALESCE(r.purpose, '')                           AS purpose,
  COALESCE(r.detail, '')                            AS detail,
  COALESCE(r.link, '')                              AS link,
  COALESCE(r.doc, '')                               AS doc,
  COALESCE(r.requester, '')                         AS requester,
  COALESCE(rp.label, '')                            AS reqPart,
  COALESCE(DATE_FORMAT(r.req_date, '%Y-%m-%d'), '') AS reqDate,
  COALESCE(dp.label, '')                            AS doPart,
  COALESCE(r.do_person, '')                         AS doPerson,
  COALESCE(pr.label, '')                            AS priority,
  COALESCE(im.label, '')                            AS importance,
  COALESCE(di.label, '')                            AS difficulty,
  COALESCE(CAST(r.effort AS CHAR), '')              AS effort,
  COALESCE(ve.label, '')                            AS version,
  COALESCE(st.label, '')                            AS status,
  COALESCE(r.reviewer, '')                          AS reviewer,
  COALESCE(DATE_FORMAT(r.approve_date,'%Y-%m-%d'),'') AS approveDate,
  COALESCE(DATE_FORMAT(r.start_date,  '%Y-%m-%d'),'') AS startDate,
  COALESCE(DATE_FORMAT(r.end_date,    '%Y-%m-%d'),'') AS endDate,
  COALESCE(DATE_FORMAT(r.prod_date,   '%Y-%m-%d'),'') AS prodDate,
  COALESCE(r.remark, '')                            AS remark,
  r.sort_order                                      AS sortOrder,
  r.created_at                                      AS createdAt,
  r.updated_at                                      AS updatedAt,
  COALESCE(r.updated_by,'')                         AS updatedBy,
  COALESCE(r.created_by,'')                         AS createdBy
FROM requirement r
JOIN      cat_category c  ON c.id  = r.category_id
LEFT JOIN cat_rank     rk ON rk.id = r.rank_id
LEFT JOIN cat_part     rp ON rp.id = r.req_part_id
LEFT JOIN cat_part     dp ON dp.id = r.do_part_id
LEFT JOIN cat_priority pr ON pr.id = r.priority_id
LEFT JOIN cat_level    im ON im.id = r.importance_id
LEFT JOIN cat_level    di ON di.id = r.difficulty_id
LEFT JOIN cat_version  ve ON ve.id = r.version_id
LEFT JOIN cat_status   st ON st.id = r.status_id;

-- =====================================================================
--  대시보드 뷰 (요약 + 차원별 집계)
-- =====================================================================
CREATE VIEW v_dashboard_summary AS
SELECT
  (SELECT COUNT(*) FROM requirement)                                              AS total,
  (SELECT COUNT(*) FROM requirement r JOIN cat_priority p ON p.id=r.priority_id
     WHERE p.label='필수')                                                        AS required_cnt,
  (SELECT COUNT(*) FROM requirement r JOIN cat_status s ON s.id=r.status_id
     WHERE s.label IN ('승인','완료'))                                            AS done_cnt,
  (SELECT COALESCE(SUM(effort),0) FROM requirement)                               AS total_effort;

CREATE VIEW v_dashboard_category AS
SELECT c.label AS label, c.sort_order, COUNT(r.id) AS cnt
FROM cat_category c LEFT JOIN requirement r ON r.category_id = c.id
GROUP BY c.id, c.label, c.sort_order ORDER BY c.sort_order;

CREATE VIEW v_dashboard_priority AS
SELECT p.label AS label, p.sort_order, COUNT(r.id) AS cnt
FROM cat_priority p LEFT JOIN requirement r ON r.priority_id = p.id
GROUP BY p.id, p.label, p.sort_order ORDER BY p.sort_order;

CREATE VIEW v_dashboard_status AS
SELECT s.label AS label, s.sort_order, COUNT(r.id) AS cnt
FROM cat_status s LEFT JOIN requirement r ON r.status_id = s.id
GROUP BY s.id, s.label, s.sort_order ORDER BY s.sort_order;

CREATE VIEW v_dashboard_version AS
SELECT v.label AS label, v.sort_order, COUNT(r.id) AS cnt
FROM cat_version v LEFT JOIN requirement r ON r.version_id = v.id
GROUP BY v.id, v.label, v.sort_order ORDER BY v.sort_order;

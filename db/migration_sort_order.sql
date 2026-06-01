-- =====================================================================
--  마이그레이션: requirement.sort_order 추가 (목록 드래그 정렬용)
--  기존 데이터 보존. schema/seed 재적재 없이 이 파일만 적용하면 됨.
--  실행:  mysql -u root -h 127.0.0.1 -P 3306 --protocol=TCP < db/migration_sort_order.sql
--  ※ 한 번만 실행. 이미 적용된 경우 ALTER 가 "Duplicate column" 으로 실패하니
--    그때는 UPDATE/뷰 재생성 구문만 따로 실행하세요.
-- =====================================================================
USE medihim;

-- 1) 컬럼 + 인덱스 추가
ALTER TABLE requirement
  ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER remark,
  ADD KEY idx_req_sort (sort_order);

-- 2) 기존 행 초기 순서 = id 순
UPDATE requirement SET sort_order = id;

-- 3) 뷰 재생성 (sortOrder 노출)
DROP VIEW IF EXISTS v_requirement;
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
  r.updated_at                                      AS updatedAt
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

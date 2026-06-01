-- =====================================================================
-- 요구사항 최초작성자(created_by) 컬럼 추가 — 행 단위 수정/삭제 권한용
--  - created_by: POST(등록) 시 로그인 사용자 이름 기록, PUT(수정)에서는 변경하지 않음
--    → 비관리자는 본인이 작성한(=created_by==본인 이름) 글만 수정/삭제 가능, 관리자는 전체 가능
--  - 기존 행 보정: 아직 내용 수정 이력이 없어 updated_by==작성자이므로 created_by=updated_by 로 채움
--  - v_requirement 에 createdBy 노출
-- 적용:  mysql.exe ... medihim < db/migration_req_createdby.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;

ALTER TABLE requirement
  ADD COLUMN created_by VARCHAR(50) NULL AFTER updated_by;

UPDATE requirement SET created_by = updated_by WHERE created_by IS NULL;

CREATE OR REPLACE VIEW v_requirement AS
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

-- =====================================================================
-- TO-DO 최종수정자 컬럼 추가: todo.updated_by + v_todo에 updatedBy 노출
--  - updated_at(최종수정일시, ON UPDATE 자동)은 이미 존재
--  - updated_by: 최종 수정한 로그인 사용자 이름(비정규화). POST 시 작성자와 동일, PUT 시 갱신
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_todo_updatedby.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
ALTER TABLE todo ADD COLUMN updated_by VARCHAR(50) NULL AFTER created_by;
CREATE OR REPLACE VIEW v_todo AS
SELECT t.id AS id, COALESCE(t.code,'') AS code,
  COALESCE(cl.label,'') AS class, COALESCE(t.phase_round,'') AS phaseRound,
  t.title AS title, COALESCE(ty.label,'') AS type, COALESCE(ph.label,'') AS workPhase,
  COALESCE(t.worker,'') AS worker, COALESCE(t.collaborator,'') AS collaborator,
  COALESCE(t.participants,'') AS participants, COALESCE(pr.label,'') AS priority,
  COALESCE(st.label,'') AS status,
  COALESCE(DATE_FORMAT(t.start_date,'%Y-%m-%d'),'') AS startDate,
  COALESCE(t.due,'') AS due,
  COALESCE(CAST(t.work_days AS CHAR),'') AS workDays,
  COALESCE(CAST(t.progress AS CHAR),'') AS progress,
  COALESCE(t.tags,'') AS tags, COALESCE(t.remark,'') AS remark,
  COALESCE(t.link1,'') AS link1, COALESCE(t.link2,'') AS link2,
  t.created_at AS createdAt, t.updated_at AS updatedAt,
  COALESCE(t.created_by,'') AS createdBy, COALESCE(t.updated_by,'') AS updatedBy
FROM todo t
LEFT JOIN td_class    cl ON cl.id=t.class_id
LEFT JOIN td_type     ty ON ty.id=t.type_id
LEFT JOIN td_phase    ph ON ph.id=t.work_phase_id
LEFT JOIN td_priority pr ON pr.id=t.priority_id
LEFT JOIN td_status   st ON st.id=t.status_id;

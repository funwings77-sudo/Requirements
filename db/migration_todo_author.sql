-- =====================================================================
-- TO-DO 작성자 컬럼 추가: todo.created_by + v_todo에 createdBy 노출
--  - created_at(최초추가일시)·updated_at(최종수정일시, ON UPDATE 자동)은 이미 존재
--  - created_by: 작업을 추가한 로그인 사용자 이름(비정규화). 기존 시드는 NULL
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_todo_author.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
ALTER TABLE todo ADD COLUMN created_by VARCHAR(50) NULL AFTER updated_at;
CREATE OR REPLACE VIEW v_todo AS
 select `t`.`id` AS `id`,coalesce(`t`.`code`,'') AS `code`,coalesce(`cl`.`label`,'') AS `class`,
  coalesce(`t`.`phase_round`,'') AS `phaseRound`,`t`.`title` AS `title`,coalesce(`ty`.`label`,'') AS `type`,
  coalesce(`ph`.`label`,'') AS `workPhase`,coalesce(`t`.`worker`,'') AS `worker`,coalesce(`t`.`collaborator`,'') AS `collaborator`,
  coalesce(`t`.`participants`,'') AS `participants`,coalesce(`pr`.`label`,'') AS `priority`,coalesce(`st`.`label`,'') AS `status`,
  coalesce(convert(date_format(`t`.`start_date`,'%Y-%m-%d') using utf8mb4),'') AS `startDate`,coalesce(`t`.`due`,'') AS `due`,
  coalesce(cast(`t`.`work_days` as char charset utf8mb4),'') AS `workDays`,coalesce(cast(`t`.`progress` as char charset utf8mb4),'') AS `progress`,
  coalesce(`t`.`tags`,'') AS `tags`,coalesce(`t`.`remark`,'') AS `remark`,coalesce(`t`.`link1`,'') AS `link1`,coalesce(`t`.`link2`,'') AS `link2`,
  `t`.`created_at` AS `createdAt`,`t`.`updated_at` AS `updatedAt`,coalesce(`t`.`created_by`,'') AS `createdBy`
 from (((((`todo` `t`
  left join `td_class` `cl` on((`cl`.`id` = `t`.`class_id`)))
  left join `td_type` `ty` on((`ty`.`id` = `t`.`type_id`)))
  left join `td_phase` `ph` on((`ph`.`id` = `t`.`work_phase_id`)))
  left join `td_priority` `pr` on((`pr`.`id` = `t`.`priority_id`)))
  left join `td_status` `st` on((`st`.`id` = `t`.`status_id`)));

-- =====================================================================
-- 요구사항 최종수정자 컬럼 추가 + updated_at을 수정 시에만 갱신하도록 변경
--  - updated_at: ON UPDATE 자동 제거 → 요구사항 PUT(내용 수정)에서만 lib.php가 CURRENT_TIMESTAMP 명시 세팅
--    (드래그 정렬 reorder의 sort_order UPDATE에는 안 바뀜)
--  - updated_by(최종수정자): POST(작성자)·PUT(수정자) 시 로그인 사용자 이름 기록. 기존 13건(import)은 NULL
--  - v_requirement에 updatedBy 노출(createdAt·updatedAt은 이미 노출)
-- 적용:  mysql.exe ... medihim < db/migration_req_updatedby.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
ALTER TABLE requirement
  MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by VARCHAR(50) NULL AFTER updated_at;
CREATE OR REPLACE VIEW v_requirement AS
 select `r`.`id` AS `id`,coalesce(`rk`.`label`,'') AS `rank`,`c`.`label` AS `category`,
  coalesce(`r`.`major`,'') AS `major`,coalesce(`r`.`middle`,'') AS `middle`,`r`.`name` AS `name`,
  coalesce(`r`.`purpose`,'') AS `purpose`,coalesce(`r`.`detail`,'') AS `detail`,coalesce(`r`.`link`,'') AS `link`,
  coalesce(`r`.`doc`,'') AS `doc`,coalesce(`r`.`requester`,'') AS `requester`,coalesce(`rp`.`label`,'') AS `reqPart`,
  coalesce(convert(date_format(`r`.`req_date`,'%Y-%m-%d') using utf8mb4),'') AS `reqDate`,
  coalesce(`dp`.`label`,'') AS `doPart`,coalesce(`r`.`do_person`,'') AS `doPerson`,
  coalesce(`pr`.`label`,'') AS `priority`,coalesce(`im`.`label`,'') AS `importance`,coalesce(`di`.`label`,'') AS `difficulty`,
  coalesce(cast(`r`.`effort` as char charset utf8mb4),'') AS `effort`,coalesce(`ve`.`label`,'') AS `version`,
  coalesce(`st`.`label`,'') AS `status`,coalesce(`r`.`reviewer`,'') AS `reviewer`,
  coalesce(convert(date_format(`r`.`approve_date`,'%Y-%m-%d') using utf8mb4),'') AS `approveDate`,
  coalesce(convert(date_format(`r`.`start_date`,'%Y-%m-%d') using utf8mb4),'') AS `startDate`,
  coalesce(convert(date_format(`r`.`end_date`,'%Y-%m-%d') using utf8mb4),'') AS `endDate`,
  coalesce(convert(date_format(`r`.`prod_date`,'%Y-%m-%d') using utf8mb4),'') AS `prodDate`,
  coalesce(`r`.`remark`,'') AS `remark`,`r`.`sort_order` AS `sortOrder`,
  `r`.`created_at` AS `createdAt`,`r`.`updated_at` AS `updatedAt`,coalesce(`r`.`updated_by`,'') AS `updatedBy`
 from (((((((((`requirement` `r`
  join `cat_category` `c` on((`c`.`id` = `r`.`category_id`)))
  left join `cat_rank` `rk` on((`rk`.`id` = `r`.`rank_id`)))
  left join `cat_part` `rp` on((`rp`.`id` = `r`.`req_part_id`)))
  left join `cat_part` `dp` on((`dp`.`id` = `r`.`do_part_id`)))
  left join `cat_priority` `pr` on((`pr`.`id` = `r`.`priority_id`)))
  left join `cat_level` `im` on((`im`.`id` = `r`.`importance_id`)))
  left join `cat_level` `di` on((`di`.`id` = `r`.`difficulty_id`)))
  left join `cat_version` `ve` on((`ve`.`id` = `r`.`version_id`)))
  left join `cat_status` `st` on((`st`.`id` = `r`.`status_id`)));

-- =====================================================================
-- 회원 최종수정자 컬럼 추가: member.updated_by
--  - created_by(최초작성자)·updated_at(최종수정일시)는 이미 존재
--  - updated_by(최종수정자): POST(작성자)·PUT(수정자) 시 로그인 사용자 이름 기록. 권한변경(?api=perms)은 갱신 안 함
-- 적용:  mysql.exe ... medihim < db/migration_member_updatedby.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
ALTER TABLE member ADD COLUMN updated_by VARCHAR(50) NULL AFTER created_by;

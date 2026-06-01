-- =====================================================================
-- 회원 메타 컬럼 추가: updated_at(최종수정일시) + created_by(작성자)
--  - created_at(최초추가일시)은 이미 존재(TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
--  - updated_at: 회원 정보(이름/부서/연락처/이메일) 수정 시 member.php가 명시적으로 CURRENT_TIMESTAMP 세팅
--    (권한/관리자 변경은 갱신하지 않음 → ON UPDATE 자동 갱신 미사용)
--  - created_by: 회원을 추가한 로그인 사용자 이름(비정규화 저장). 기존 15명(시드)은 NULL
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_member_meta.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
ALTER TABLE member
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by VARCHAR(50) NULL AFTER updated_at;

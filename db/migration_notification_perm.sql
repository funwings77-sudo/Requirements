-- =====================================================================
-- 공지사항(notification) 리소스 추가: 전 회원에게 기본 CRUD 권한 부여
--  - 신규 메뉴 추가 시 member_perm에 행이 없으면 can()이 false 반환해 접근 차단됨
--  - 기존 4개 리소스와 동일하게 전 회원에게 access/read/write/update/delete 모두 1로 시드
-- 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_notification_perm.sql
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
  SELECT id, 'notification', 1, 1, 1, 1, 1 FROM member;

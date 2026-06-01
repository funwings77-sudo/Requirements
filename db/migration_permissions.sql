-- =====================================================================
-- 회원 권한(RBAC) 추가: member.is_admin + member_perm (회원×메뉴×CRUD)
--  - 메뉴(리소스): requirements / dashboard / todolist / member
--  - 액션: access(메뉴접속) / read(읽기) / write(쓰기) / update(수정) / delete(삭제)
--  - 관리자(is_admin)는 앱에서 항상 전권 허용 → member_perm 무관
--  - 기존 회원 15명: 모든 메뉴 전체 CRUD 허용(잠김 방지), 최준혁을 첫 관리자로 지정
-- 라이브 적용:  mysql.exe -u root -h 127.0.0.1 -P 3306 --protocol=TCP medihim < db/migration_permissions.sql
-- (PowerShell의 Get-Content|mysql 파이프는 중간 문장이 누락될 수 있어 < 리다이렉트 권장)
-- =====================================================================
SET NAMES utf8mb4;
USE medihim;

-- 1) 관리자 플래그
ALTER TABLE member ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;

-- 2) 권한 테이블 (회원 × 메뉴 × CRUD)
CREATE TABLE IF NOT EXISTS member_perm (
  member_id  INT NOT NULL,
  resource   VARCHAR(32) NOT NULL,
  can_access TINYINT(1) NOT NULL DEFAULT 0,
  can_read   TINYINT(1) NOT NULL DEFAULT 0,
  can_write  TINYINT(1) NOT NULL DEFAULT 0,
  can_update TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (member_id, resource),
  CONSTRAINT fk_member_perm_member FOREIGN KEY (member_id) REFERENCES member(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) 기존 회원 전체 허용 (4개 메뉴 × 전체 CRUD). INSERT IGNORE → 재실행 시 기존 행 보존.
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
SELECT m.id, r.resource, 1, 1, 1, 1, 1
FROM member m
CROSS JOIN (
  SELECT 'requirements' AS resource
  UNION ALL SELECT 'dashboard'
  UNION ALL SELECT 'todolist'
  UNION ALL SELECT 'member'
) r;

-- 4) 첫 관리자 지정 (대표이사 최준혁)
UPDATE member SET is_admin = 1 WHERE name = '최준혁';

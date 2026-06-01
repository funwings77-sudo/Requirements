-- 마이그레이션: 계정관리(account_management)를 독립 권한 리소스로 분리
-- 기존 회원에게 접속/읽기 권한 시드(관리자는 권한설정 화면에서 개별 조정)
INSERT IGNORE INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
SELECT id, 'account_management', 1, 1, 0, 0, 0 FROM member;

-- 회원 관리 테이블 + 시드 (gd.xlsx 기반: 이름/소속부서/휴대폰번호/이메일)
SET NAMES utf8mb4;
USE medihim;
DROP TABLE IF EXISTS member_perm;
DROP TABLE IF EXISTS member;
CREATE TABLE member (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  dept VARCHAR(80) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT '이용중',              -- 구분: 이용중 / 이용중지(로그인 불가)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,            -- 최초추가일시
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,       -- 최종수정일시 (회원 정보 수정 시 member.php가 명시적으로 갱신)
  created_by VARCHAR(50) NULL,                               -- 최초작성자(추가한 로그인 사용자 이름)
  updated_by VARCHAR(50) NULL                                -- 최종수정자(마지막 수정한 로그인 사용자 이름)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO member (name,dept,phone,email) VALUES
 ('강보성','개발 R&D · R&D파트','010-4341-3919','bskang@castingn.com'),
 ('권수빈','MSO 해외환자유치','010-2803-5014','sbkwon@castingn.com'),
 ('김기문','개발 운영/유지보수','010-7657-5746','kkm@castingn.com'),
 ('김대진','MSO사업부','010-3341-4160','jaykim@castingn.com'),
 ('박진국','브랜드마케팅파트','010-2101-6790','jkpark@castingn.com'),
 ('신유경','MSO사업부','010-3089-9889','ug_shin@castingn.com'),
 ('심순영','해외사업팀','010-4180-0813','ssy@castingn.com'),
 ('양태겸','해외마케팅파트','010-7679-4085','tkyang@castingn.com'),
 ('용성남','MSO사업부','010-9245-1391','snyong@castingn.com'),
 ('유정은','해외마케팅파트','010-9369-3231','jeryu@castingn.com'),
 ('이홍근','브랜드마케팅파트','010-7291-1072','hklee@castingn.com'),
 ('채성훈','MSO사업부','010-8666-0200','shchae@castingn.com'),
 ('최준혁','대표이사','010-8950-9694','jhchoi@castingn.com'),
 ('한영은','컨설팅&대행파트','010-4499-2625','yehan@castingn.com'),
 ('김한나','브랜드마케팅파트','010-2245-3005','hnkim@castingn.com');

-- 권한(RBAC): member_perm(회원×메뉴×CRUD) + 첫 관리자(최준혁)
CREATE TABLE member_perm (
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
INSERT INTO member_perm (member_id, resource, can_access, can_read, can_write, can_update, can_delete)
SELECT m.id, r.resource, 1, 1, 1, 1, 1
FROM member m
CROSS JOIN (
  SELECT 'requirements' AS resource
  UNION ALL SELECT 'dashboard'
  UNION ALL SELECT 'todolist'
  UNION ALL SELECT 'member'
) r;
UPDATE member SET is_admin = 1 WHERE name = '최준혁';
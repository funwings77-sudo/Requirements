-- 마이그레이션: 회원 구분(이용중/이용중지) 추가 — 이용중지 회원은 로그인 불가
ALTER TABLE member ADD COLUMN status VARCHAR(10) NOT NULL DEFAULT '이용중' AFTER is_admin;

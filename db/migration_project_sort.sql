-- 마이그레이션: project_item 드래그 정렬용 sort_order 컬럼 추가
-- NULL이면 번호(no) 역순 기본 정렬, 드래그 시 0..N-1로 채워 사용자 순서 영속화
ALTER TABLE project_item ADD COLUMN sort_order INT NULL AFTER no;

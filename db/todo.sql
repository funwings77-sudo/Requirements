-- =====================================================================
--  서비스팀 TO-DO LIST (서비스 기획_작업 목록 엑셀 기반) - 스키마/뷰/시드
--  실행:  mysql -u root -p < db/todo.sql   (schema.sql 이후)
-- =====================================================================
USE medihim;
SET NAMES utf8mb4;

DROP VIEW  IF EXISTS v_todo;
DROP TABLE IF EXISTS todo;
DROP TABLE IF EXISTS cat_todo_status;
DROP TABLE IF EXISTS td_class;
DROP TABLE IF EXISTS td_type;
DROP TABLE IF EXISTS td_phase;
DROP TABLE IF EXISTS td_priority;
DROP TABLE IF EXISTS td_status;

CREATE TABLE td_class (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, label VARCHAR(40) NOT NULL, sort_order TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY uk_td_class_label(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE td_type (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, label VARCHAR(40) NOT NULL, sort_order TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY uk_td_type_label(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE td_phase (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, label VARCHAR(40) NOT NULL, sort_order TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY uk_td_phase_label(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE td_priority (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, label VARCHAR(40) NOT NULL, sort_order TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY uk_td_priority_label(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE td_status (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, label VARCHAR(40) NOT NULL, sort_order TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY uk_td_status_label(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE todo (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(20)  NULL,            -- 엑셀 ID (T001..)
  class_id      TINYINT UNSIGNED NULL,        -- 분류
  phase_round   VARCHAR(60)  NULL,            -- 차수
  title         VARCHAR(400) NOT NULL,        -- 프로젝트명
  type_id       TINYINT UNSIGNED NULL,        -- 구분
  work_phase_id TINYINT UNSIGNED NULL,        -- 현재 작업구간
  worker        VARCHAR(150) NULL,            -- 현재 작업자
  collaborator  VARCHAR(300) NULL,            -- 협업자/파트/외부업체
  participants  VARCHAR(400) NULL,            -- 프로젝트 참여자
  priority_id   TINYINT UNSIGNED NULL,        -- 우선순위
  status_id     TINYINT UNSIGNED NULL,        -- 상태
  start_date    DATE         NULL,            -- 시작일
  due           VARCHAR(40)  NULL,            -- 마감일(상시/수시/날짜)
  work_days     DECIMAL(6,1) NULL,            -- 작업일수
  progress      DECIMAL(5,2) NULL,            -- 총 진행률(0~1)
  tags          VARCHAR(200) NULL,            -- 태그
  remark        TEXT         NULL,            -- 비고
  link1         VARCHAR(500) NULL,
  link2         VARCHAR(500) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by    VARCHAR(50) NULL,            -- 최초추가자(작업을 등록한 로그인 사용자)
  updated_by    VARCHAR(50) NULL,            -- 최종수정자(마지막으로 수정한 로그인 사용자)
  PRIMARY KEY(id),
  KEY idx_todo_status(status_id), KEY idx_todo_type(type_id),
  CONSTRAINT fk_todo_class    FOREIGN KEY(class_id)      REFERENCES td_class(id)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_todo_type     FOREIGN KEY(type_id)       REFERENCES td_type(id)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_todo_phase    FOREIGN KEY(work_phase_id) REFERENCES td_phase(id)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_todo_priority FOREIGN KEY(priority_id)   REFERENCES td_priority(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_todo_status   FOREIGN KEY(status_id)     REFERENCES td_status(id)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='서비스팀 TO-DO';

CREATE VIEW v_todo AS
SELECT t.id AS id, COALESCE(t.code,'') AS code,
  COALESCE(cl.label,'') AS class, COALESCE(t.phase_round,'') AS phaseRound,
  t.title AS title, COALESCE(ty.label,'') AS type, COALESCE(ph.label,'') AS workPhase,
  COALESCE(t.worker,'') AS worker, COALESCE(t.collaborator,'') AS collaborator,
  COALESCE(t.participants,'') AS participants, COALESCE(pr.label,'') AS priority,
  COALESCE(st.label,'') AS status,
  COALESCE(DATE_FORMAT(t.start_date,'%Y-%m-%d'),'') AS startDate,
  COALESCE(t.due,'') AS due,
  COALESCE(CAST(t.work_days AS CHAR),'') AS workDays,
  COALESCE(CAST(t.progress AS CHAR),'') AS progress,
  COALESCE(t.tags,'') AS tags, COALESCE(t.remark,'') AS remark,
  COALESCE(t.link1,'') AS link1, COALESCE(t.link2,'') AS link2,
  t.created_at AS createdAt, t.updated_at AS updatedAt,
  COALESCE(t.created_by,'') AS createdBy, COALESCE(t.updated_by,'') AS updatedBy
FROM todo t
LEFT JOIN td_class    cl ON cl.id=t.class_id
LEFT JOIN td_type     ty ON ty.id=t.type_id
LEFT JOIN td_phase    ph ON ph.id=t.work_phase_id
LEFT JOIN td_priority pr ON pr.id=t.priority_id
LEFT JOIN td_status   st ON st.id=t.status_id;

INSERT INTO td_class (id,code,label,sort_order) VALUES
  (1,'OPS','운영',10),
  (2,'BUILD','구축',20),
  (3,'COLLAB','협업',30);

INSERT INTO td_type (id,code,label,sort_order) VALUES
  (1,'NEWSYS','신규 시스템구축',10),
  (2,'REVAMP','사이트개편',20),
  (3,'BACKEND','백엔드',30),
  (4,'OPS','운영',40),
  (5,'RESEARCH','리서치',50),
  (6,'ETC','기타',60);

INSERT INTO td_phase (id,code,label,sort_order) VALUES
  (1,'PLAN','기획',10),
  (2,'DESIGN','디자인',20),
  (3,'PUB','퍼블리싱',30),
  (4,'DEV','개발',40),
  (5,'QA','QA',50),
  (6,'MEETING','회의',60),
  (7,'ETC','기타',70);

INSERT INTO td_priority (id,code,label,sort_order) VALUES
  (1,'HIGH','높음',10),
  (2,'MID','중간',20),
  (3,'LOW','낮음',30);

INSERT INTO td_status (id,code,label,sort_order) VALUES
  (1,'TODO','진행전',10),
  (2,'DOING','진행중',20),
  (3,'DONE','완료',30),
  (4,'HOLD','보류',40),
  (5,'CANCEL','취소',50);

INSERT INTO todo
(code, class_id, phase_round, title, type_id, work_phase_id, worker, collaborator, participants, priority_id, status_id, start_date, due, work_days, progress, tags, remark, link1, link2)
VALUES
  ('T001', NULL, '유지보수', '운영, 개 시스템 Sync 작업', 6, 4, '김기문', NULL, '강보성, 박진국, 한영은', 1, 3, '2026-05-21', '2026-05-21', 1.0, 1.0, '#개발 #QA', NULL, NULL, NULL),
  ('T002', 1, '유지보수_상시', '메디힘 요구사항 수집 관리', 4, 7, '박진국', NULL, '박진국, 김기문, 강보성, 한영은', 1, 2, '2026-05-20', '상시', NULL, NULL, '#기획 #디자인 #퍼블리싱 #개발', NULL, '요구사항수집 리스트', NULL),
  ('T003', NULL, '유지보수_상시', '운영개발 (QA)', 4, 7, '박진국', NULL, '박진국, 김기문, 강보성, 한영은', 1, 2, '2026-05-19', '상시', NULL, NULL, '#기획 #디자인 #퍼블리싱 #개발', NULL, '레드마인', NULL),
  ('T004', 2, 'UI/UX 2.0 개편', 'UI/UX 2.0 개편 (홈 컨셉)', 2, 6, '한영은, 박진국', NULL, '박진국, 김기문, 강보성, 한영은', 1, 1, '2026-05-22', NULL, NULL, NULL, '#기획 #디자인 #퍼블리싱 #개발', NULL, NULL, NULL),
  ('T005', NULL, '유지보수_2차', '상담프로세스 개선', 2, 2, '한영은', NULL, '박진국, 김기문, 강보성', 1, 2, '2026-05-18', '2026-06-30', 32.0, 0.3, '#기획 #디자인 #퍼블리싱 #개발', '- 기획서작성 완료\n- FlowChart작성중/ 기획서 리뷰예정', NULL, NULL),
  ('T006', NULL, '유지보수_2차', '정산관리', 3, 1, '박진국', NULL, '박진국, 김기문', 1, 2, '2026-04-20', '2026-07-24', 2.0, 0.3, '#기획 #개발', '기획완료', NULL, NULL),
  ('T007', NULL, '유지보수_1_2차', '수수료 기능 2차 고도화', 3, 4, '김기문', NULL, '박진국, 김기문', 2, 2, '2026-04-20', NULL, NULL, 0.9, '#기획 #개발', '(결제 연동 미적용)\n개발팀 확인중', NULL, NULL),
  ('T008', NULL, '유지보수_2차', '결제관리(결제내역)', 3, 4, '김기문', NULL, '박진국, 김기문', 2, 2, '2026-04-20', NULL, NULL, 1.0, '#기획 #개발', '개발팀 확인중', NULL, NULL),
  ('T009', NULL, '유지보수_2차', '시술/수술 예약현황', 3, 4, '김기문', NULL, '박진국, 김기문', 2, 2, '2026-04-20', NULL, NULL, 0.99, '#기획 #개발', '(관리자 링크 연동 미적용)\n개발팀 확인중', NULL, NULL),
  ('T010', NULL, '유지보수_2차', '예약관리', 3, 4, '김기문', NULL, '박진국, 김기문', 2, 2, '2026-04-20', NULL, NULL, 0.3, '#기획 #개발', '개발팀 확인중', NULL, NULL),
  ('T011', NULL, '1차', '글로벌 병원 구축', 1, 1, '이홍근', NULL, '이홍근, 한영은', 1, 2, '2026-05-21', '수시', NULL, 0.8, '#기획', '핵심 기능 / 메뉴 구조 / 콘텐츠 정의', NULL, NULL),
  ('T012', NULL, '1차', '병원 관리자', 1, 1, '이홍근', NULL, '이홍근, 한영은', 1, 2, '2026-05-21', '2026-05-22', 2.0, 0.8, '#기획', '1차 프로토 타입', NULL, NULL),
  ('T013', NULL, '1차', '사용자 페이지 템플릿 디자인', 1, 2, '한영은', NULL, '이홍근, 한영은', 1, 2, '2026-05-21', '2026-05-22', 2.0, 0.5, '#디자인', '시안 디자인', NULL, NULL),
  ('T014', NULL, '1차', '플랫폼(메디힘) 관리자', 1, 1, '이홍근', NULL, '이홍근, 한영은', 1, 2, '2026-05-21', '2026-05-27', 3.0, 0.3, '#기획', '1차 프로토 타입', NULL, NULL),
  ('T015', NULL, '1차', '사용자 페이지 템플릿', 1, 1, '이홍근', NULL, '이홍근, 한영은', 1, 1, NULL, NULL, NULL, NULL, '#기획', NULL, NULL, NULL),
  ('T016', 3, '유지보수_1_2차', 'GA 셋팅', 3, 1, '박진국', '유정은(마케팅), 신유경(운영)', '박진국, 김기문,  APP개발자', 1, 2, '2026-05-21', '2026-06-12', 17.0, 0.3, '#기획 #개발', '- 기본셋팅완료(개발자 Support 필요)\n- firebase 설정완료(APP)\n- 관리자페이지 기본 설정\n- 기안준비', NULL, NULL),
  ('T017', NULL, '유지보수_1_2차', 'Amplitude 셋팅', 3, 1, '박진국', '유정은(마케팅), 신유경(운영)', '박진국, 김기문,  APP개발자', 1, 2, '2026-05-21', '2026-06-12', 17.0, 0.1, '#기획 #개발', '- 기본셋팅완료(개발자 Support 필요)\n- 계정등록\n- 기안준비', NULL, NULL),
  ('T018', NULL, '유지보수_1_2차', '채널톡 셋팅', 3, 1, '박진국', '유정은(마케팅), 신유경(운영)', '박진국, 김기문, 강보성, 한영은', 1, 1, NULL, NULL, NULL, NULL, '#기획 #디자인 #개발', '- 기안준비\n- 아이콘 디자인 완료', NULL, NULL),
  ('T019', NULL, '유지보수_1_2차', '알림(PUSH, SMS, 카카오알림, LINE) 셋팅', 6, 1, '박진국', '유정은(마케팅), 신유경(운영), 강리내(번역)', '박진국, 김기문, 강보성, 한영은', 1, 2, '2026-05-22', '2026-06-30', NULL, NULL, '#기획 #디자인 #개발', '요구사항수집중(2026.5.22)', '알림 문구리스트', NULL);

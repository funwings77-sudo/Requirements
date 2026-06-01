-- =====================================================================
--  메디힘 요구사항 수집 - 시드 데이터 (코드 + 30건)
--  실행:  mysql -u root -p < db/seed.sql   (schema.sql 먼저 실행)
-- =====================================================================
USE medihim;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE requirement;
TRUNCATE TABLE cat_category;
TRUNCATE TABLE cat_part;
TRUNCATE TABLE cat_priority;
TRUNCATE TABLE cat_level;
TRUNCATE TABLE cat_version;
TRUNCATE TABLE cat_status;
TRUNCATE TABLE cat_rank;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO cat_category (id, code, label, sort_order) VALUES
  (1,'FUNC','기능',10),
  (2,'NONFUNC','비기능',20),
  (3,'CONSTRAINT','제약',30),
  (4,'INTERFACE','인터페이스',40),
  (5,'DATA','데이터',50);

INSERT INTO cat_part (id, code, label, sort_order) VALUES
  (1,'NONE','선택',10),
  (2,'BIZ','사업부',20),
  (3,'MKT','마케팅',30),
  (4,'OPS','운영',40),
  (5,'DESIGN','디자인',50),
  (6,'DEV','개발',60),
  (7,'PLAN','기획',70);

INSERT INTO cat_priority (id, code, label, sort_order) VALUES
  (1,'MUST','필수',10),
  (2,'SHOULD','권장',20),
  (3,'OPTIONAL','선택',30);

INSERT INTO cat_level (id, code, label, sort_order) VALUES
  (1,'HIGH','상',10),
  (2,'MID','중',20),
  (3,'LOW','하',30);

INSERT INTO cat_version (id, code, label, sort_order) VALUES
  (1,'V10','v1.0',10),
  (2,'V11','v1.1',20),
  (3,'V12','v1.2',30),
  (4,'V20','v2.0',40),
  (5,'TBD','미정',50);

INSERT INTO cat_status (id, code, label, sort_order) VALUES
  (1,'NEW','신규',10),
  (2,'REVIEW','검토중',20),
  (3,'APPROVED','승인',30),
  (4,'INPROGRESS','진행중',40),
  (5,'DONE','완료',50),
  (6,'HOLD','보류',60),
  (7,'REJECTED','반려',70),
  (8,'DEPLOYED','운영서버반영',80);

INSERT INTO cat_rank (id, code, label, sort_order) VALUES
  (1,'NONE','선택',10),
  (2,'R1','1',20),
  (3,'R2','2',30),
  (4,'R3','3',40),
  (5,'R4','4',50),
  (6,'R5','5',60),
  (7,'R6','6',70),
  (8,'R7','7',80),
  (9,'R8','8',90),
  (10,'R9','9',100),
  (11,'R10','10',110);

INSERT INTO requirement
(rank_id, category_id, major, middle, name, purpose, detail, link, doc, requester, req_part_id, req_date, do_part_id, do_person, priority_id, importance_id, difficulty_id, effort, version_id, status_id, reviewer, approve_date, start_date, end_date, prod_date, remark)
VALUES
  (1, 1, '유입 채널 다변화', '성과 추적', 'GA', '채널별 유입·상담·결제 성과 추적 필요', '상세설명이 길 경우 별도 페이지에 작성 후 링크 걸어주세요.\n(별도협의 진행하겠습니다)\nGA4/GTM/UTM 세팅을 통해 광고, LINE, SNS, 인플루언서, SEO 등 유입 경로별 성과를 확인할 수 있도록 구성', '확인', '확인', '유정은', 3, '2026-05-19', 1, '박진국', 1, 1, 2, NULL, 5, 1, NULL, '2026-05-20', '2026-05-25', NULL, NULL, NULL),
  (1, 1, '서비스 고도화', '데이터 분석', 'Amplitude 이벤트 설계', '유입→상담→결제까지 고객 행동 데이터 수집 필요', '랜딩 조회, CTA 클릭, 상담 신청, 상담 완료, 결제 클릭, 예약금 결제 완료 등 핵심 이벤트 정의 및 추적', NULL, NULL, '유정은', 3, '2026-05-19', 1, '박진국', 1, 1, 2, NULL, 5, 1, NULL, '2026-05-20', '2026-05-25', NULL, NULL, NULL),
  (1, 1, '유입 채널 다변화', '상담 채널', '채널톡 설치', '일본 고객 상담 진입 채널 확보 필요', '채널톡 설치 후 앱/랜딩/LINE 상담 버튼과 연결. 고객 문의가 상담 관리 화면으로 연결되도록 구성', NULL, NULL, '신유경', 4, '2026-05-19', 1, '박진국', 1, 1, 2, NULL, 5, 1, NULL, '2026-05-20', '2026-05-25', NULL, NULL, NULL),
  (1, 1, NULL, NULL, '병원 툴 - 홈페이지, 컨텐츠, CS', NULL, NULL, NULL, NULL, '최준혁', 2, '2026-05-20', 1, '이홍근', 1, 1, 1, NULL, 5, 1, NULL, '2026-05-20', '2026-05-19', NULL, NULL, NULL),
  (1, 1, '서비스 고도화', '고객 경험 개선', 'UX 라이팅 개선', '고객이 상담 신청·결제 등 다음 행동으로 이동하도록 유도 필요', '상담 신청, 결제 안내, 예약금 안내, 미결제 리마인드, 방문 안내 등 주요 화면과 메시지의 CTA 문구 개선', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '유입 채널 다변화', '유입 정보', '유입 경로 필드 추가', '리드별 유입 채널 확인 필요', '고객 문의/상담 신청 시 광고, LINE, SNS, 인플루언서, SEO, 제휴 등 유입 경로가 자동 또는 수동으로 기록되도록 필드 추가', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '퍼널 고도화', '퍼널 상태값', '퍼널 단계 상태값 관리', '상담→예약금 결제 전환율 분석 필요', '문의 접수, 상담 예약, 상담 완료, 결제 링크 발송, 예약금 결제, 방문 완료 등 단계별 상태값 관리 기능 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '퍼널 고도화', '예약금 UX', '상담 후 예약금 결제 UX', '상담 완료 후 결제 전환 구조 구축 필요', '상담 완료 후 결제 링크/버튼 제공, 결제 완료 안내, 미결제 상태 처리, 결제 후 다음 단계 안내 UX 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '퍼널 고도화', 'CTA 개선', '상담 후 CTA 및 UX 라이팅 개선', '고객이 다음 행동으로 자연스럽게 이동하도록 유도 필요', '상담 완료 화면, 메시지, 랜딩 내 CTA 문구 개선. 예약금 결제, 상담 신청, LINE 문의 등 목적별 CTA 문구 적용', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '퍼널 고도화', '리마인드', '상담 후 리마인드 자동화', '상담 후 미결제 고객의 전환 유도 필요', '상담 완료 후 미결제 고객 대상으로 D+0, D+1, D+3 기준 LINE/문자/이메일 리마인드 발송 기능 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '퍼널 고도화', '가격·패키지', '가격 및 패키지 제안 영역', '가격 불안 해소 및 결제 설득 필요', '고객에게 제안할 가격, 포함 항목, 예약금 정책, 혜택, 패키지 구성을 명확히 안내할 수 있는 영역 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, 'CS·운영 표준화', '리드 관리', '리드 우선순위 및 CRM 관리', '상담 리드의 후속관리 기준 필요', '고관심 고객, 일정 확정 고객, 가격 문의 고객, 이탈 위험 고객 등으로 리드를 분류하고 상담 이력과 다음 액션을 관리할 수 있는 구조 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, 'CS·운영 표준화', 'FAQ/스크립트', '일본 고객 FAQ 및 상담 스크립트 관리', 'CS 응대 품질 표준화 필요', '가격, 시술 과정, 통역, 예약금, 환불, 병원 방문, 사후관리 등 일본 고객용 FAQ와 상담 스크립트를 등록·관리할 수 있는 구조 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, 'CS·운영 표준화', '제작 프로세스', '콘텐츠 제작·번역·검수 프로세스', '콘텐츠 발행 병목 해소 필요', '콘텐츠 요청 → 초안 작성 → 번역 → 의료/일본어 검수 → 발행까지 진행 상태를 관리할 수 있는 프로세스 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, 'CS·운영 표준화', '검수 기준', '콘텐츠 검수 체크리스트', '콘텐츠 품질 및 리스크 관리 필요', '의료 표현, 가격 정보, 병원 정보, 일본어 번역, CTA, 링크 오류 등을 발행 전 점검할 수 있는 체크리스트 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '인프라 구축', '결제 상태값', '결제·상담 상태값 정의', '결제 진행 상황과 매출 추적 필요', '결제 대기, 결제 완료, 결제 실패, 환불, 취소, 변경, 방문 완료 등 결제·상담 상태값 정의 및 관리 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '인프라 구축', '예약금 수납', '예약금 수납 플로우', '상담 후 예약금 결제 전환 필요', '예약금 금액, 결제 방식, 결제 기한, 환불/변경 기준, 결제 완료 후 안내 플로우 정의 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '인프라 구축', '매출 추적', '채널별 매출 및 예약금 추적', '캠페인별 수익성 판단 필요', '유입 채널, 캠페인, 병원, 시술, 예약금, 본결제, 정산 상태를 연결해 추적할 수 있는 데이터 구조 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '인프라 구축', '정산 기준', '병원별 정산 관리 항목', '병원별 정산 확인 필요', '병원별 예약금, 본결제, 수수료, 환불, 정산 상태를 확인할 수 있는 관리 항목 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '서비스 고도화', '대시보드', '퍼널 대시보드 항목', '주간 성과 및 병목 구간 확인 필요', '유입 수, 상담 신청 수, 상담 완료 수, 예약금 결제 수, 결제 전환율, CPA, 이탈 사유를 확인할 수 있는 대시보드 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '서비스 고도화', '이탈 분석', '이탈 사유 수집 항목', '고객 이탈 원인 분석 필요', '상담 미완료, 상담 후 미결제, 결제 포기, 가격 부담, 일정 불확정, 신뢰 부족, 결제 UX 불편 등 이탈 사유 선택/기록 항목 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '서비스 고도화', 'A/B 테스트', '앱 내 콘텐츠·CTA A/B 테스트', 'UX 개선 효과 검증 필요', '콘텐츠 제목, 후기 노출 위치, 가격 안내 방식, CTA 문구, 상담 신청 버튼 등을 테스트할 수 있는 항목 정의 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '서비스 고도화', '개선 관리', 'VOC 및 UX 개선 백로그', '고객 피드백 기반 서비스 개선 필요', 'CS, 상담, 데이터 분석에서 확인된 VOC와 UX 이슈를 개선 백로그로 등록하고 우선순위별 관리 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '유입 채널 다변화', '병원 툴', '병원 툴 - 홈페이지·콘텐츠·CS 관리', '병원별 정보와 콘텐츠 운영 관리 필요', '병원 소개, 시술 정보, 가격, 후기, FAQ, CS 안내 문구 등을 병원별로 관리할 수 있는 구조 필요', NULL, NULL, '유정은', 3, NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 4, 'CS·운영 표준화', '병원 웹', '병원 웹  UI UX 고도화', '병원에서 사용시 편리하고 괜찮은 사용성을 경험 필요.', '조금더 세련된 디자인으로 변경되었으면 좋겠습니다.', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, 'CS·운영 표준화', '병원 웹', '상담 대시보드 고도화', '한눈에 고객 사항을 파악할 수 있는 대시보드 생성필요', '한눈에 볼 수 있는 대시보드가 있었으면 좋겠습니다.', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '유입 채널 다변화', '서비스 다각화', 'URL 을 통해 중국 고객과 병원의 화상상담 가능화', '중국 고객 유입을 위해 사용성 개선이 필요합니다.', '링크만 전달하여 고객이 화상상담을 하되, 이뻐 병원웹에는 내용이 남을 수 있었으면 좋겠습니다.', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '유입 채널 다변화', '서비스 다각화', '중국어 번역 추가', '중국 고객 유입을 위해 사용성 개선이 필요합니다.', '중국어 실시간 번역 필요', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '앱기능', '앱 내 화상상담 예약', '예약 편의성 증대', '운영자의 일정 수기 조율 제거, 더블부킹 방지', '고객이 앱에서 가능 슬롯 선택, JST/KST 동시 표기, 통역 필요 여부 선택, 예약 즉시 운영 화면 반영', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (1, 1, '앱기능', '체류 일정 입력', '체류 일정 입력', '방한 일정 내 시술 가능 여부를 고객 스스로 입력 → 운영 문의 제거', '입국·출국일 필드, 입력값 기준 "체류 내 가능한 상담/시술" 자동 안내', NULL, NULL, '신유경', 4, '2026-05-22', 1, NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- 목록 표시 순서 초기화: 최초엔 id 순 (이후 드래그 정렬로 갱신됨)
UPDATE requirement SET sort_order = id;

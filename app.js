/* =====================================================================
   메디힘 요구사항 - 공유 프런트엔드 (requirements.php / dashboard.php 공통)
   원본 medihim_req.php 의 공통 로직을 그대로 추출함.
   - 데이터 계층: OPT / SEED / DB(?api=) 연동 / localStorage 폴백
   - 공용 헬퍼: esc / tally / dl / toast / LNB 드로어
   ===================================================================== */
/* ====== 드롭다운 옵션 (엑셀 데이터 검증 기반) ====== */
const OPT = {
  rank:      ['선택','1','2','3','4','5','6','7','8','9','10'],
  category:  ['기능','비기능','제약','인터페이스','데이터'],
  part:      ['선택','사업부','마케팅','운영','디자인','개발','기획'],
  priority:  ['필수','권장','선택'],
  level:     ['상','중','하'],
  version:   ['v1.0','v1.1','v1.2','v2.0','미정'],
  status:    ['신규','검토중','승인','진행중','완료','보류','반려','운영서버반영'],
};
const FIELDS = ['rank','category','major','middle','name','purpose','detail','link','doc',
  'requester','reqPart','reqDate','doPart','doPerson','priority','importance','difficulty',
  'effort','version','status','reviewer','approveDate','startDate','endDate','prodDate','remark'];
const LABELS = {rank:'우선순위(순번)',category:'구분',major:'대분류',middle:'중분류',name:'요구사항명',
  purpose:'목적&필요성',detail:'상세설명',link:'참고링크',doc:'참고링크',requester:'요청자',
  reqPart:'요청파트',reqDate:'요청일자',doPart:'수행파트',doPerson:'수행담당자',priority:'우선순위',
  importance:'중요도',difficulty:'난이도',effort:'예상공수(MD)',version:'목표버전',status:'상태',
  reviewer:'검토자',approveDate:'승인일',startDate:'시작일자',endDate:'종료일자(개발반영)',
  prodDate:'운영서버반영일자',remark:'비고'};

const SEED = [{"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "성과 추적", "name": "GA", "purpose": "채널별 유입·상담·결제 성과 추적 필요", "detail": "상세설명이 길 경우 별도 페이지에 작성 후 링크 걸어주세요.\n(별도협의 진행하겠습니다)\nGA4/GTM/UTM 세팅을 통해 광고, LINE, SNS, 인플루언서, SEO 등 유입 경로별 성과를 확인할 수 있도록 구성", "link": "확인", "doc": "확인", "requester": "유정은", "reqPart": "마케팅", "reqDate": "2026-05-19", "doPart": "선택", "doPerson": "박진국", "priority": "필수", "importance": "상", "difficulty": "중", "effort": "", "version": "미정", "status": "신규", "reviewer": "", "approveDate": "2026-05-20", "startDate": "2026-05-25", "endDate": "", "prodDate": "", "remark": "", "id": 1}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "데이터 분석", "name": "Amplitude 이벤트 설계", "purpose": "유입→상담→결제까지 고객 행동 데이터 수집 필요", "detail": "랜딩 조회, CTA 클릭, 상담 신청, 상담 완료, 결제 클릭, 예약금 결제 완료 등 핵심 이벤트 정의 및 추적", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "2026-05-19", "doPart": "선택", "doPerson": "박진국", "priority": "필수", "importance": "상", "difficulty": "중", "effort": "", "version": "미정", "status": "신규", "reviewer": "", "approveDate": "2026-05-20", "startDate": "2026-05-25", "endDate": "", "prodDate": "", "remark": "", "id": 2}, {"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "상담 채널", "name": "채널톡 설치", "purpose": "일본 고객 상담 진입 채널 확보 필요", "detail": "채널톡 설치 후 앱/랜딩/LINE 상담 버튼과 연결. 고객 문의가 상담 관리 화면으로 연결되도록 구성", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-19", "doPart": "선택", "doPerson": "박진국", "priority": "필수", "importance": "상", "difficulty": "중", "effort": "", "version": "미정", "status": "신규", "reviewer": "", "approveDate": "2026-05-20", "startDate": "2026-05-25", "endDate": "", "prodDate": "", "remark": "", "id": 3}, {"rank": "선택", "category": "기능", "major": "", "middle": "", "name": "병원 툴 - 홈페이지, 컨텐츠, CS", "purpose": "", "detail": "", "link": "", "doc": "", "requester": "최준혁", "reqPart": "사업부", "reqDate": "2026-05-20", "doPart": "선택", "doPerson": "이홍근", "priority": "필수", "importance": "상", "difficulty": "상", "effort": "", "version": "미정", "status": "신규", "reviewer": "", "approveDate": "2026-05-20", "startDate": "2026-05-19", "endDate": "", "prodDate": "", "remark": "", "id": 4}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "고객 경험 개선", "name": "UX 라이팅 개선", "purpose": "고객이 상담 신청·결제 등 다음 행동으로 이동하도록 유도 필요", "detail": "상담 신청, 결제 안내, 예약금 안내, 미결제 리마인드, 방문 안내 등 주요 화면과 메시지의 CTA 문구 개선", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 5}, {"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "유입 정보", "name": "유입 경로 필드 추가", "purpose": "리드별 유입 채널 확인 필요", "detail": "고객 문의/상담 신청 시 광고, LINE, SNS, 인플루언서, SEO, 제휴 등 유입 경로가 자동 또는 수동으로 기록되도록 필드 추가", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 6}, {"rank": "선택", "category": "기능", "major": "퍼널 고도화", "middle": "퍼널 상태값", "name": "퍼널 단계 상태값 관리", "purpose": "상담→예약금 결제 전환율 분석 필요", "detail": "문의 접수, 상담 예약, 상담 완료, 결제 링크 발송, 예약금 결제, 방문 완료 등 단계별 상태값 관리 기능 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 7}, {"rank": "선택", "category": "기능", "major": "퍼널 고도화", "middle": "예약금 UX", "name": "상담 후 예약금 결제 UX", "purpose": "상담 완료 후 결제 전환 구조 구축 필요", "detail": "상담 완료 후 결제 링크/버튼 제공, 결제 완료 안내, 미결제 상태 처리, 결제 후 다음 단계 안내 UX 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 8}, {"rank": "선택", "category": "기능", "major": "퍼널 고도화", "middle": "CTA 개선", "name": "상담 후 CTA 및 UX 라이팅 개선", "purpose": "고객이 다음 행동으로 자연스럽게 이동하도록 유도 필요", "detail": "상담 완료 화면, 메시지, 랜딩 내 CTA 문구 개선. 예약금 결제, 상담 신청, LINE 문의 등 목적별 CTA 문구 적용", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 9}, {"rank": "선택", "category": "기능", "major": "퍼널 고도화", "middle": "리마인드", "name": "상담 후 리마인드 자동화", "purpose": "상담 후 미결제 고객의 전환 유도 필요", "detail": "상담 완료 후 미결제 고객 대상으로 D+0, D+1, D+3 기준 LINE/문자/이메일 리마인드 발송 기능 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 10}, {"rank": "선택", "category": "기능", "major": "퍼널 고도화", "middle": "가격·패키지", "name": "가격 및 패키지 제안 영역", "purpose": "가격 불안 해소 및 결제 설득 필요", "detail": "고객에게 제안할 가격, 포함 항목, 예약금 정책, 혜택, 패키지 구성을 명확히 안내할 수 있는 영역 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 11}, {"rank": "선택", "category": "기능", "major": "CS·운영 표준화", "middle": "리드 관리", "name": "리드 우선순위 및 CRM 관리", "purpose": "상담 리드의 후속관리 기준 필요", "detail": "고관심 고객, 일정 확정 고객, 가격 문의 고객, 이탈 위험 고객 등으로 리드를 분류하고 상담 이력과 다음 액션을 관리할 수 있는 구조 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 12}, {"rank": "선택", "category": "기능", "major": "CS·운영 표준화", "middle": "FAQ/스크립트", "name": "일본 고객 FAQ 및 상담 스크립트 관리", "purpose": "CS 응대 품질 표준화 필요", "detail": "가격, 시술 과정, 통역, 예약금, 환불, 병원 방문, 사후관리 등 일본 고객용 FAQ와 상담 스크립트를 등록·관리할 수 있는 구조 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 13}, {"rank": "선택", "category": "기능", "major": "CS·운영 표준화", "middle": "제작 프로세스", "name": "콘텐츠 제작·번역·검수 프로세스", "purpose": "콘텐츠 발행 병목 해소 필요", "detail": "콘텐츠 요청 → 초안 작성 → 번역 → 의료/일본어 검수 → 발행까지 진행 상태를 관리할 수 있는 프로세스 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 14}, {"rank": "선택", "category": "기능", "major": "CS·운영 표준화", "middle": "검수 기준", "name": "콘텐츠 검수 체크리스트", "purpose": "콘텐츠 품질 및 리스크 관리 필요", "detail": "의료 표현, 가격 정보, 병원 정보, 일본어 번역, CTA, 링크 오류 등을 발행 전 점검할 수 있는 체크리스트 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 15}, {"rank": "선택", "category": "기능", "major": "인프라 구축", "middle": "결제 상태값", "name": "결제·상담 상태값 정의", "purpose": "결제 진행 상황과 매출 추적 필요", "detail": "결제 대기, 결제 완료, 결제 실패, 환불, 취소, 변경, 방문 완료 등 결제·상담 상태값 정의 및 관리 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 16}, {"rank": "선택", "category": "기능", "major": "인프라 구축", "middle": "예약금 수납", "name": "예약금 수납 플로우", "purpose": "상담 후 예약금 결제 전환 필요", "detail": "예약금 금액, 결제 방식, 결제 기한, 환불/변경 기준, 결제 완료 후 안내 플로우 정의 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 17}, {"rank": "선택", "category": "기능", "major": "인프라 구축", "middle": "매출 추적", "name": "채널별 매출 및 예약금 추적", "purpose": "캠페인별 수익성 판단 필요", "detail": "유입 채널, 캠페인, 병원, 시술, 예약금, 본결제, 정산 상태를 연결해 추적할 수 있는 데이터 구조 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 18}, {"rank": "선택", "category": "기능", "major": "인프라 구축", "middle": "정산 기준", "name": "병원별 정산 관리 항목", "purpose": "병원별 정산 확인 필요", "detail": "병원별 예약금, 본결제, 수수료, 환불, 정산 상태를 확인할 수 있는 관리 항목 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 19}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "대시보드", "name": "퍼널 대시보드 항목", "purpose": "주간 성과 및 병목 구간 확인 필요", "detail": "유입 수, 상담 신청 수, 상담 완료 수, 예약금 결제 수, 결제 전환율, CPA, 이탈 사유를 확인할 수 있는 대시보드 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 20}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "이탈 분석", "name": "이탈 사유 수집 항목", "purpose": "고객 이탈 원인 분석 필요", "detail": "상담 미완료, 상담 후 미결제, 결제 포기, 가격 부담, 일정 불확정, 신뢰 부족, 결제 UX 불편 등 이탈 사유 선택/기록 항목 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 21}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "A/B 테스트", "name": "앱 내 콘텐츠·CTA A/B 테스트", "purpose": "UX 개선 효과 검증 필요", "detail": "콘텐츠 제목, 후기 노출 위치, 가격 안내 방식, CTA 문구, 상담 신청 버튼 등을 테스트할 수 있는 항목 정의 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 22}, {"rank": "선택", "category": "기능", "major": "서비스 고도화", "middle": "개선 관리", "name": "VOC 및 UX 개선 백로그", "purpose": "고객 피드백 기반 서비스 개선 필요", "detail": "CS, 상담, 데이터 분석에서 확인된 VOC와 UX 이슈를 개선 백로그로 등록하고 우선순위별 관리 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 23}, {"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "병원 툴", "name": "병원 툴 - 홈페이지·콘텐츠·CS 관리", "purpose": "병원별 정보와 콘텐츠 운영 관리 필요", "detail": "병원 소개, 시술 정보, 가격, 후기, FAQ, CS 안내 문구 등을 병원별로 관리할 수 있는 구조 필요", "link": "", "doc": "", "requester": "유정은", "reqPart": "마케팅", "reqDate": "", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 24}, {"rank": "선택", "category": "인터페이스", "major": "CS·운영 표준화", "middle": "병원 웹", "name": "병원 웹  UI UX 고도화", "purpose": "병원에서 사용시 편리하고 괜찮은 사용성을 경험 필요.", "detail": "조금더 세련된 디자인으로 변경되었으면 좋겠습니다.", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 25}, {"rank": "선택", "category": "기능", "major": "CS·운영 표준화", "middle": "병원 웹", "name": "상담 대시보드 고도화", "purpose": "한눈에 고객 사항을 파악할 수 있는 대시보드 생성필요", "detail": "한눈에 볼 수 있는 대시보드가 있었으면 좋겠습니다.", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 26}, {"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "서비스 다각화", "name": "URL 을 통해 중국 고객과 병원의 화상상담 가능화", "purpose": "중국 고객 유입을 위해 사용성 개선이 필요합니다.", "detail": "링크만 전달하여 고객이 화상상담을 하되, 이뻐 병원웹에는 내용이 남을 수 있었으면 좋겠습니다.", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 27}, {"rank": "선택", "category": "기능", "major": "유입 채널 다변화", "middle": "서비스 다각화", "name": "중국어 번역 추가", "purpose": "중국 고객 유입을 위해 사용성 개선이 필요합니다.", "detail": "중국어 실시간 번역 필요", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 28}, {"rank": "선택", "category": "기능", "major": "앱기능", "middle": "앱 내 화상상담 예약", "name": "예약 편의성 증대", "purpose": "운영자의 일정 수기 조율 제거, 더블부킹 방지", "detail": "고객이 앱에서 가능 슬롯 선택, JST/KST 동시 표기, 통역 필요 여부 선택, 예약 즉시 운영 화면 반영", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 29}, {"rank": "선택", "category": "기능", "major": "앱기능", "middle": "체류 일정 입력", "name": "체류 일정 입력", "purpose": "방한 일정 내 시술 가능 여부를 고객 스스로 입력 → 운영 문의 제거", "detail": "입국·출국일 필드, 입력값 기준 \"체류 내 가능한 상담/시술\" 자동 안내", "link": "", "doc": "", "requester": "신유경", "reqPart": "운영", "reqDate": "2026-05-22", "doPart": "선택", "doPerson": "", "priority": "", "importance": "", "difficulty": "", "effort": "", "version": "미정", "status": "", "reviewer": "", "approveDate": "", "startDate": "", "endDate": "", "prodDate": "", "remark": "", "id": 30}];

const STORE_KEY = 'medihim_requirements_v1';
let data = [];

/* ---- 백엔드(MySQL) 연동: 서버가 있으면 API 사용, 없으면 localStorage 폴백 ---- */
const API = '?api=';   // PHP 단일 파일 엔드포인트 (medihim_req.php?api=...)
const onHttp = location.protocol === 'http:' || location.protocol === 'https:';
let useApi = false;
async function tryConnectApi(){
  if(!onHttp) return false;            // file:// 로 열면 서버 없음 → 로컬 모드
  try{
    const r = await fetch(API + 'options', {cache:'no-store'});
    if(!r.ok) throw new Error('options ' + r.status);
    const o = await r.json();
    const lab = g => (o[g]||[]).map(x=>x.label);
    if(o.category) OPT.category = lab('category');
    if(o.part)     OPT.part     = lab('part');
    if(o.priority) OPT.priority = lab('priority');
    if(o.level)    OPT.level    = lab('level');
    if(o.version)  OPT.version  = lab('version');
    if(o.status)   OPT.status   = lab('status');
    if(o.rank)     OPT.rank     = lab('rank');
    useApi = true;
  }catch(e){ useApi = false; }
  return useApi;
}
async function loadData(){
  if(useApi){
    const r = await fetch(API + 'requirements', {cache:'no-store'});
    if(!r.ok) throw new Error('목록 조회 실패 (' + r.status + ')');
    data = await r.json();
  } else { load(); }
}
function renderStatus(){
  const el = document.getElementById('dbStatus'); if(!el) return;
  if(useApi){ el.textContent='🟢 DB 연결됨'; el.style.background='#dcfce7'; el.style.color='#166534'; el.title='MySQL 백엔드와 연동 중'; }
  else { el.textContent='💾 로컬 모드'; el.style.background='#fef3c7'; el.style.color='#92400e'; el.title='서버 미연결 — 브라우저 localStorage 사용 중'; }
}

function load(){
  const saved = localStorage.getItem(STORE_KEY);
  if(saved){ try{ data = JSON.parse(saved); }catch(e){ data = SEED.slice(); } }
  else { data = SEED.slice(); }
  let mx = 0; data.forEach(d=>{ if(d.id>mx) mx=d.id; });
  data.forEach(d=>{ if(!d.id) d.id = ++mx; });
}
function save(){ localStorage.setItem(STORE_KEY, JSON.stringify(data)); }
function nextId(){ return data.reduce((m,d)=>Math.max(m,d.id||0),0)+1; }

/* ---- selects ---- */
function fillSelect(el, arr, withBlank){
  el.innerHTML = '';
  if(withBlank) el.appendChild(new Option('— 선택 —',''));
  arr.forEach(v=>el.appendChild(new Option(v,v)));
}

/* ---- LNB drawer (mobile) ---- */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
function openLnb(){ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); }
function closeLnb(){ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); }
document.getElementById('hambBtn').onclick=openLnb;
document.getElementById('lnbClose').onclick=closeLnb;
lnbOverlay.onclick=closeLnb;
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLnb(); });

/* ---- helpers (공유) ---- */
/* ---- table ---- */
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function tally(key, order){
  const m={}; data.forEach(d=>{ const v=d[key]||'(미입력)'; m[v]=(m[v]||0)+1; });
  let keys=order?order.filter(k=>m[k]):Object.keys(m);
  order&&Object.keys(m).forEach(k=>{ if(!keys.includes(k)) keys.push(k); });
  return keys.map(k=>({k,n:m[k]}));
}
function dl(blob,name){ const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=name; a.click(); URL.revokeObjectURL(a.href); }
/* ---- toast ---- */
let toT;
function toast(msg){ const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* ---- 차트 색상 매핑 (목록 태그 색과 일관, 대시보드/요약 공용) ---- */
const CMAP={
  category:{'기능':'#4f46e5','비기능':'#0ea5e9','제약':'#f97316','인터페이스':'#14b8a6','데이터':'#a855f7'},
  priority:{'필수':'#ef4444','권장':'#f59e0b','선택':'#94a3b8'},
  status:{'신규':'#3b82f6','검토중':'#eab308','승인':'#22c55e','진행중':'#06b6d4','완료':'#10b981','보류':'#94a3b8','반려':'#ef4444','운영서버반영':'#8b5cf6'},
  version:{'v1.0':'#2f6bff','v1.1':'#5b8df9','v1.2':'#22c55e','v2.0':'#f59e0b','미정':'#cbd5e1'},
};
const PALETTE=['#2f6bff','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#14b8a6','#94a3b8'];
const colorFor=(g,k,i)=>(k==='(미입력)') ? '#cbd5e1' : ((CMAP[g]&&CMAP[g][k])||PALETTE[i%PALETTE.length]);

<?php
/* =====================================================================
   메디힘 요구사항 수집 - PHP 단일 파일 (웹페이지 + MySQL API)
   - 일반 요청        : 아래 HTML 페이지 출력
   - ?api=... 요청    : JSON API (PDO 로 MySQL 연동)
   실행: php -S localhost:8000   →  http://localhost:8000/medihim_req.php
   ===================================================================== */

$DB = [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '3306',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
  'name' => getenv('DB_NAME') ?: 'medihim',
];

if (isset($_GET['api'])) { medihim_api($DB); exit; }   // API 요청이면 JSON 반환 후 종료

function medihim_json($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function medihim_err($msg, $code = 400) { medihim_json(['error' => $msg], $code); }

function medihim_api($DB) {
  // 프런트엔드 키 ↔ DB 컬럼 ↔ 코드 테이블
  $FIELDS = [
    ['key'=>'rank',       'col'=>'rank_id',       'type'=>'fk',   'group'=>'rank'],
    ['key'=>'category',   'col'=>'category_id',   'type'=>'fk',   'group'=>'category', 'required'=>true],
    ['key'=>'major',      'col'=>'major',         'type'=>'text'],
    ['key'=>'middle',     'col'=>'middle',        'type'=>'text'],
    ['key'=>'name',       'col'=>'name',          'type'=>'text', 'required'=>true],
    ['key'=>'purpose',    'col'=>'purpose',       'type'=>'text'],
    ['key'=>'detail',     'col'=>'detail',        'type'=>'text'],
    ['key'=>'link',       'col'=>'link',          'type'=>'text'],
    ['key'=>'doc',        'col'=>'doc',           'type'=>'text'],
    ['key'=>'requester',  'col'=>'requester',     'type'=>'text'],
    ['key'=>'reqPart',    'col'=>'req_part_id',   'type'=>'fk',   'group'=>'part'],
    ['key'=>'reqDate',    'col'=>'req_date',      'type'=>'date'],
    ['key'=>'doPart',     'col'=>'do_part_id',    'type'=>'fk',   'group'=>'part'],
    ['key'=>'doPerson',   'col'=>'do_person',     'type'=>'text'],
    ['key'=>'priority',   'col'=>'priority_id',   'type'=>'fk',   'group'=>'priority'],
    ['key'=>'importance', 'col'=>'importance_id', 'type'=>'fk',   'group'=>'level'],
    ['key'=>'difficulty', 'col'=>'difficulty_id', 'type'=>'fk',   'group'=>'level'],
    ['key'=>'effort',     'col'=>'effort',        'type'=>'num'],
    ['key'=>'version',    'col'=>'version_id',    'type'=>'fk',   'group'=>'version'],
    ['key'=>'status',     'col'=>'status_id',     'type'=>'fk',   'group'=>'status'],
    ['key'=>'reviewer',   'col'=>'reviewer',      'type'=>'text'],
    ['key'=>'approveDate','col'=>'approve_date',  'type'=>'date'],
    ['key'=>'startDate',  'col'=>'start_date',    'type'=>'date'],
    ['key'=>'endDate',    'col'=>'end_date',      'type'=>'date'],
    ['key'=>'prodDate',   'col'=>'prod_date',     'type'=>'date'],
    ['key'=>'remark',     'col'=>'remark',        'type'=>'text'],
  ];
  $GROUP_TABLE = [
    'category'=>'cat_category', 'part'=>'cat_part', 'priority'=>'cat_priority',
    'level'=>'cat_level', 'version'=>'cat_version', 'status'=>'cat_status', 'rank'=>'cat_rank',
  ];

  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    medihim_err('DB 연결 실패: ' . $e->getMessage(), 500);
  }

  $api = $_GET['api'];
  $method = $_SERVER['REQUEST_METHOD'];

  if ($api === 'health') medihim_json(['ok' => true]);

  // 코드(드롭다운) 로드
  $lookups = []; $labelToId = [];
  foreach ($GROUP_TABLE as $g => $t) {
    $rows = $pdo->query("SELECT id, code, label FROM `$t` ORDER BY sort_order, id")->fetchAll();
    $lookups[$g] = $rows;
    $map = [];
    foreach ($rows as $r) $map[$r['label']] = (int)$r['id'];
    $labelToId[$g] = $map;
  }
  if ($api === 'options') medihim_json($lookups);

  if ($api === 'dashboard') {
    medihim_json([
      'summary'  => $pdo->query("SELECT * FROM v_dashboard_summary")->fetch(),
      'category' => $pdo->query("SELECT label, cnt FROM v_dashboard_category")->fetchAll(),
      'priority' => $pdo->query("SELECT label, cnt FROM v_dashboard_priority")->fetchAll(),
      'status'   => $pdo->query("SELECT label, cnt FROM v_dashboard_status")->fetchAll(),
      'version'  => $pdo->query("SELECT label, cnt FROM v_dashboard_version")->fetchAll(),
    ]);
  }

  if ($api === 'requirements') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($method === 'GET') {
      if ($id !== null) {
        $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) medihim_err('없는 요구사항', 404);
        medihim_json($row);
      }
      $where = []; $params = [];
      foreach (['category','priority','status'] as $f) {
        if (!empty($_GET[$f])) { $where[] = "$f = ?"; $params[] = $_GET[$f]; }
      }
      if (!empty($_GET['q'])) {
        $where[] = "(name LIKE ? OR detail LIKE ? OR purpose LIKE ? OR major LIKE ? OR middle LIKE ?)";
        $like = '%' . $_GET['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like);
      }
      $sql = "SELECT * FROM v_requirement" . ($where ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY id";
      $st = $pdo->prepare($sql); $st->execute($params);
      medihim_json($st->fetchAll());
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    if ($method === 'POST') {
      list($cols, $vals) = medihim_to_row($body, $FIELDS, $labelToId);
      $sql = "INSERT INTO requirement (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
      $pdo->prepare($sql)->execute($vals);
      $newId = $pdo->lastInsertId();
      $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?"); $st->execute([$newId]);
      medihim_json($st->fetch(), 201);
    }

    if ($method === 'PUT') {
      if ($id === null) medihim_err('id 필요');
      list($cols, $vals) = medihim_to_row($body, $FIELDS, $labelToId);
      $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, $cols));
      $vals[] = $id;
      $pdo->prepare("UPDATE requirement SET $set WHERE id = ?")->execute($vals);
      $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?"); $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) medihim_err('없는 요구사항', 404);
      medihim_json($row);
    }

    if ($method === 'DELETE') {
      if ($id === null) medihim_err('id 필요');
      $st = $pdo->prepare("DELETE FROM requirement WHERE id = ?"); $st->execute([$id]);
      if ($st->rowCount() === 0) medihim_err('없는 요구사항', 404);
      medihim_json(['ok' => true]);
    }
  }

  medihim_err('unknown api: ' . $api, 404);
}

function medihim_to_row($body, $FIELDS, $labelToId) {
  $cols = []; $vals = [];
  foreach ($FIELDS as $d) {
    $v = isset($body[$d['key']]) ? trim((string)$body[$d['key']]) : '';
    $type = $d['type']; $val = null;
    if ($type === 'text') {
      $val = ($v === '') ? null : $v;
      if (!empty($d['required']) && $val === null) medihim_err('필수 항목 누락: ' . $d['key']);
    } elseif ($type === 'date') {
      if ($v === '') $val = null;
      elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $val = $v;
      else medihim_err('날짜 형식 오류(' . $d['key'] . '): YYYY-MM-DD');
    } elseif ($type === 'num') {
      if ($v === '') $val = null;
      elseif (is_numeric($v)) $val = 0 + $v;
      else medihim_err('숫자 형식 오류(' . $d['key'] . ')');
    } elseif ($type === 'fk') {
      if ($v === '') {
        if (!empty($d['required'])) medihim_err('필수 항목 누락: ' . $d['key']);
        $val = null;
      } else {
        $g = $d['group'];
        if (!isset($labelToId[$g][$v])) medihim_err('허용되지 않은 값(' . $d['key'] . '): "' . $v . '"');
        $val = $labelToId[$g][$v];
      }
    }
    $cols[] = $d['col']; $vals[] = $val;
  }
  return [$cols, $vals];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>메디힘 요구사항 수집</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<!-- ===== mobile top bar ===== -->
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📋 메디힘 요구사항</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <!-- ================= LNB ================= -->
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <div class="lnb-profile">
      <span class="avatar">관</span>
      <div class="who"><div class="nm">메디힘 관리자</div><div class="role">Admin</div></div>
      <span class="chev">›</span>
    </div>
    <nav class="lnb-menu">
      <div class="lnb-group">메뉴</div>
      <button class="lnb-item active" data-view="list">
        <span class="ico">🗂️</span><span class="lbl">요구사항 목록</span><span class="badge" id="navCount">0</span>
      </button>
      <button class="lnb-item" data-view="dash">
        <span class="ico">📊</span><span class="lbl">요약 대시보드</span>
      </button>
    </nav>
    <div class="lnb-foot">
      <button class="btn primary" id="addBtn">＋ 요구사항 추가</button>
    </div>
  </aside>

  <!-- ================= MAIN ================= -->
  <main class="main">

    <!-- ===== LIST VIEW ===== -->
    <section id="view-list">
      <div class="topbar">
        <div>
          <h1>요구사항 목록</h1>
          <div class="pg-sub">엑셀 기반 수집 리스트 · 선택/입력 항목으로 관리</div>
        </div>
        <span class="spacer"></span>
        <button class="icon-btn" type="button" title="알림" onclick="location.href='/notification/notification.php'">🔔</button>
        <button class="icon-btn" type="button" title="도움말">❓</button>
        <button class="btn primary" id="addBtn2">＋ 요구사항 추가</button>
      </div>
      <div class="banner">
        <div class="banner-icon">✦</div>
        <div class="banner-text">
          <div class="banner-title">엑셀 요구사항을 한 곳에서 관리하세요</div>
          <div class="banner-sub">선택·입력 항목으로 수집하고 MySQL에 실시간 저장됩니다.</div>
        </div>
        <button class="btn dark" id="addBtn3">＋ 요구사항 추가</button>
      </div>
      <div class="panel">
        <div class="toolbar">
          <input id="search" placeholder="🔍 요구사항명 / 설명 검색" style="width:240px">
          <select id="fCat"><option value="">구분 전체</option></select>
          <select id="fPri"><option value="">우선순위 전체</option></select>
          <select id="fStatus"><option value="">상태 전체</option></select>
          <span class="spacer"></span>
          <span class="count-chip" id="dbStatus" title="">…</span>
          <span class="count-chip" id="listCount">0건</span>
          <button class="btn primary sm" id="exportXlsx">⬇ Excel (.xlsx)</button>
          <button class="btn ghost sm" id="exportCsv">⬇ CSV</button>
          <button class="btn ghost sm" id="exportJson">⬇ JSON 백업</button>
          <button class="btn ghost sm" id="importBtn">⬆ JSON 복원</button>
          <input type="file" id="importFile" accept=".json" class="hidden">
        </div>
        <div class="table-scroll">
          <table id="reqTable">
            <thead><tr>
              <th>#</th><th>구분</th><th>대분류 / 중분류</th><th>요구사항명</th><th>상세 설명</th>
              <th>요청</th><th>우선순위</th><th>중요/난이</th><th>버전</th><th>상태</th><th>관리</th>
            </tr></thead>
            <tbody id="reqBody"></tbody>
          </table>
        </div>
        <div class="empty hidden" id="emptyMsg">등록된 요구사항이 없습니다. 우측 상단 <b>＋ 요구사항 추가</b>로 등록하세요.</div>
      </div>
    </section>

    <!-- ===== DASHBOARD VIEW ===== -->
    <section id="view-dash" class="hidden">
      <div class="topbar">
        <div>
          <h1>요약 대시보드</h1>
          <div class="pg-sub">요구사항 현황 실시간 집계</div>
        </div>
        <span class="spacer"></span>
        <button class="icon-btn" type="button" title="알림" onclick="location.href='/notification/notification.php'">🔔</button>
        <button class="icon-btn" type="button" title="도움말">❓</button>
      </div>
      <div class="stat-row">
        <div class="stat b1"><div class="stat-top"><span class="stat-ico i1">📋</span><span class="lab">전체 요구사항</span></div><div class="num" id="sTotal">0</div></div>
        <div class="stat b2"><div class="stat-top"><span class="stat-ico i2">⭐</span><span class="lab">필수 요구사항</span></div><div class="num" id="sReq">0</div></div>
        <div class="stat b3"><div class="stat-top"><span class="stat-ico i3">✅</span><span class="lab">승인/완료</span></div><div class="num" id="sDone">0</div></div>
        <div class="stat b4"><div class="stat-top"><span class="stat-ico i4">⏱️</span><span class="lab">총 예상공수(MD)</span></div><div class="num" id="sEffort">0</div></div>
      </div>
      <div class="dash-grid">
        <div class="panel"><h2>구분별 현황</h2><div id="dashCat"></div></div>
        <div class="panel"><h2>우선순위별 현황</h2><div id="dashPri"></div></div>
        <div class="panel"><h2>상태별 현황</h2><div id="dashStatus"></div></div>
        <div class="panel"><h2>목표 버전별 현황</h2><div id="dashVer"></div></div>
      </div>
    </section>

  </main>
</div>

<!-- ================= FORM MODAL ================= -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">요구사항 추가</h2>
      <button class="x" id="modalClose" type="button">×</button>
    </div>
    <form id="reqForm">
      <div class="modal-body">
        <input type="hidden" id="f_id">
        <div class="grid">
          <div class="field c2"><label>우선순위(순번)</label><select id="f_rank"></select></div>
          <div class="field c2"><label>구분 <span class="req">*</span></label><select id="f_category" required></select></div>
          <div class="field c4"><label>대분류 (1st Depth)</label><input id="f_major" placeholder="예: 유입 채널 다변화"></div>
          <div class="field c4"><label>중분류 (2nd Depth)</label><input id="f_middle" placeholder="예: 성과 추적"></div>

          <div class="field c6"><label>요구사항명 <span class="req">*</span></label><input id="f_name" required placeholder="요구사항 제목"></div>
          <div class="field c6"><label>목적 &amp; 필요성</label><input id="f_purpose" placeholder="왜 필요한지 한 줄 요약"></div>

          <div class="field c12"><label>상세 설명</label><textarea id="f_detail" placeholder="구체적인 요구 내용을 입력하세요"></textarea></div>

          <div class="field c6"><label>참고링크</label><input id="f_link" placeholder="https://"></div>
          <div class="field c6"><label>참고문서</label><input id="f_doc" placeholder="문서명 / 링크"></div>

          <div class="field c3"><label>요청자</label><input id="f_requester"></div>
          <div class="field c3"><label>요청파트</label><select id="f_reqPart"></select></div>
          <div class="field c3"><label>요청일자</label><input id="f_reqDate" type="date"></div>
          <div class="field c3"><label>수행파트</label><select id="f_doPart"></select></div>

          <div class="field c3"><label>수행담당자</label><input id="f_doPerson"></div>
          <div class="field c3"><label>우선순위(중요)</label><select id="f_priority"></select></div>
          <div class="field c3"><label>중요도</label><select id="f_importance"></select></div>
          <div class="field c3"><label>난이도</label><select id="f_difficulty"></select></div>

          <div class="field c3"><label>예상공수(MD)</label><input id="f_effort" type="number" min="0" step="0.5"></div>
          <div class="field c3"><label>목표 버전</label><select id="f_version"></select></div>
          <div class="field c3"><label>상태</label><select id="f_status"></select></div>
          <div class="field c3"><label>검토자</label><input id="f_reviewer"></div>

          <div class="field c3"><label>승인일</label><input id="f_approveDate" type="date"></div>
          <div class="field c3"><label>시작일자</label><input id="f_startDate" type="date"></div>
          <div class="field c3"><label>종료일자(개발반영)</label><input id="f_endDate" type="date"></div>
          <div class="field c3"><label>운영서버반영일자</label><input id="f_prodDate" type="date"></div>

          <div class="field c12"><label>비고</label><textarea id="f_remark" placeholder="기타 참고사항"></textarea></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn primary">💾 저장</button>
        <button type="button" class="btn ghost" id="modalCancel">취소</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
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
  purpose:'목적&필요성',detail:'상세설명',link:'참고링크',doc:'참고문서',requester:'요청자',
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
function initSelects(){
  fillSelect(f_rank, OPT.rank);
  fillSelect(f_category, OPT.category, true);
  fillSelect(f_reqPart, OPT.part);
  fillSelect(f_doPart, OPT.part);
  fillSelect(f_priority, OPT.priority, true);
  fillSelect(f_importance, OPT.level, true);
  fillSelect(f_difficulty, OPT.level, true);
  fillSelect(f_version, OPT.version, true);
  fillSelect(f_status, OPT.status, true);
  OPT.category.forEach(v=>fCat.appendChild(new Option(v,v)));
  OPT.priority.forEach(v=>fPri.appendChild(new Option(v,v)));
  OPT.status.forEach(v=>fStatus.appendChild(new Option(v,v)));
}

/* ---- LNB drawer (mobile) ---- */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
function openLnb(){ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); }
function closeLnb(){ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); }
document.getElementById('hambBtn').onclick=openLnb;
document.getElementById('lnbClose').onclick=closeLnb;
lnbOverlay.onclick=closeLnb;
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLnb(); });

/* ---- LNB navigation ---- */
document.querySelectorAll('.lnb-item').forEach(b=>{
  b.onclick=()=>{
    document.querySelectorAll('.lnb-item').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    const v=b.dataset.view;
    document.getElementById('view-list').classList.toggle('hidden', v!=='list');
    document.getElementById('view-dash').classList.toggle('hidden', v!=='dash');
    if(v==='list') renderTable();
    if(v==='dash') renderDash();
    closeLnb();
    window.scrollTo({top:0});
  };
});
function gotoView(v){ document.querySelector('.lnb-item[data-view="'+v+'"]').click(); }

/* ---- modal ---- */
const modalBg=document.getElementById('modalBg'), form=document.getElementById('reqForm');
function openModal(){ modalBg.classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal(){ modalBg.classList.remove('show'); document.body.style.overflow=''; }
function openAdd(){
  closeLnb();
  form.reset(); f_id.value='';
  document.getElementById('modalTitle').textContent='요구사항 추가';
  openModal(); setTimeout(()=>f_category.focus(),50);
}
function editItem(id){
  const d = data.find(x=>x.id==id); if(!d) return;
  FIELDS.forEach(k=>{ document.getElementById('f_'+k).value = d[k]||''; });
  f_id.value = d.id;
  document.getElementById('modalTitle').textContent='요구사항 수정 (#'+id+')';
  openModal();
}
document.getElementById('addBtn').onclick=openAdd;
document.getElementById('addBtn2').onclick=openAdd;
document.getElementById('addBtn3').onclick=openAdd;
document.getElementById('modalClose').onclick=closeModal;
document.getElementById('modalCancel').onclick=closeModal;
modalBg.addEventListener('click',e=>{ if(e.target===modalBg) closeModal(); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&modalBg.classList.contains('show')) closeModal(); });

/* ---- save ---- */
form.addEventListener('submit', async e=>{
  e.preventDefault();
  const obj={}; FIELDS.forEach(k=>{ obj[k]=(document.getElementById('f_'+k).value||'').trim(); });
  const idVal=f_id.value;
  if(useApi){
    try{
      const url = API + 'requirements' + (idVal ? '&id='+idVal : '');
      const r = await fetch(url, {method: idVal?'PUT':'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(obj)});
      if(!r.ok){ const er=await r.json().catch(()=>({})); throw new Error(er.error || ('HTTP '+r.status)); }
      await loadData();
      toast(idVal ? '요구사항이 수정되었습니다' : '요구사항이 추가되었습니다');
    }catch(err){ alert('저장 실패: '+err.message); return; }
  } else {
    if(idVal){ const i=data.findIndex(d=>d.id==idVal); obj.id=Number(idVal); data[i]=obj; toast('요구사항이 수정되었습니다'); }
    else { obj.id=nextId(); data.push(obj); toast('요구사항이 추가되었습니다'); }
    save();
  }
  closeModal(); renderTable(); updateCounts();
});
async function delItem(id){
  if(!confirm('이 요구사항을 삭제하시겠습니까?')) return;
  if(useApi){
    try{
      const r = await fetch(API + 'requirements&id=' + id, {method:'DELETE'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      await loadData();
    }catch(err){ alert('삭제 실패: '+err.message); return; }
  } else {
    data=data.filter(d=>d.id!=id); save();
  }
  renderTable(); updateCounts(); toast('삭제되었습니다');
}

/* ---- table ---- */
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function filtered(){
  const q=search.value.trim().toLowerCase(), c=fCat.value, p=fPri.value, s=fStatus.value;
  return data.filter(d=>{
    if(c&&d.category!==c) return false;
    if(p&&d.priority!==p) return false;
    if(s&&d.status!==s) return false;
    if(q){ const hay=((d.name||'')+' '+(d.detail||'')+' '+(d.purpose||'')+' '+(d.major||'')+' '+(d.middle||'')).toLowerCase();
      if(!hay.includes(q)) return false; }
    return true;
  });
}
function renderTable(){
  const rows=filtered();
  listCount.textContent=rows.length+'건';
  const body=document.getElementById('reqBody');
  if(rows.length===0){ body.innerHTML=''; emptyMsg.classList.remove('hidden'); return; }
  emptyMsg.classList.add('hidden');
  body.innerHTML=rows.map((d,i)=>{
    const cat=d.category?`<span class="tag cat">${esc(d.category)}</span>`:'';
    const pri=d.priority?`<span class="tag pri-${esc(d.priority)}">${esc(d.priority)}</span>`:'-';
    const st =d.status?`<span class="tag st-${esc(d.status)}">${esc(d.status)}</span>`:'-';
    const imp=d.importance?`<span class="lvl lvl-${esc(d.importance)}">${esc(d.importance)}</span>`:'-';
    const dif=d.difficulty?`<span class="lvl lvl-${esc(d.difficulty)}">${esc(d.difficulty)}</span>`:'-';
    const cat2=[d.major,d.middle].filter(Boolean).map(esc).join('<br><span style="color:#9aa3b2">└ ')+(d.middle?'</span>':'');
    return `<tr>
      <td data-label="#">${i+1}</td><td data-label="구분">${cat}</td><td data-label="분류">${cat2||'-'}</td>
      <td class="name" data-label="요구사항명">${esc(d.name)||'-'}</td>
      <td class="detail" data-label="상세">${esc((d.detail||d.purpose||'').slice(0,90))}${(d.detail||'').length>90?'…':''}</td>
      <td data-label="요청">${esc(d.requester)||'-'}<br><span style="color:#9aa3b2">${esc(d.reqPart||'')}</span></td>
      <td data-label="우선순위">${pri}</td><td data-label="중요/난이">${imp} / ${dif}</td><td data-label="버전">${esc(d.version)||'-'}</td><td data-label="상태">${st}</td>
      <td data-label="관리"><div class="row-actions">
        <button class="btn ghost sm" onclick="editItem(${d.id})">수정</button>
        <button class="btn danger sm" onclick="delItem(${d.id})">삭제</button>
      </div></td></tr>`;
  }).join('');
}
[search,fCat,fPri,fStatus].forEach(el=>el.addEventListener('input',renderTable));

/* ---- counts / dashboard ---- */
function updateCounts(){
  navCount.textContent=data.length;
  if(!document.getElementById('view-list').classList.contains('hidden')) renderTable();
  if(!document.getElementById('view-dash').classList.contains('hidden')) renderDash();
}
function tally(key, order){
  const m={}; data.forEach(d=>{ const v=d[key]||'(미입력)'; m[v]=(m[v]||0)+1; });
  let keys=order?order.filter(k=>m[k]):Object.keys(m);
  order&&Object.keys(m).forEach(k=>{ if(!keys.includes(k)) keys.push(k); });
  return keys.map(k=>({k,n:m[k]}));
}
function bars(el, rows){
  const max=Math.max(1,...rows.map(r=>r.n)), tot=data.length||1;
  el.innerHTML=rows.map(r=>`<div class="bar-row">
    <div class="bl">${esc(r.k)}</div>
    <div class="bar-track"><div class="bar-fill" style="width:${r.n/max*100}%"></div></div>
    <div class="bv">${r.n}건 (${Math.round(r.n/tot*100)}%)</div></div>`).join('')||'<p class="desc">데이터 없음</p>';
}
function renderDash(){
  sTotal.textContent=data.length;
  sReq.textContent=data.filter(d=>d.priority==='필수').length;
  sDone.textContent=data.filter(d=>d.status==='승인'||d.status==='완료').length;
  sEffort.textContent=data.reduce((s,d)=>s+(parseFloat(d.effort)||0),0);
  bars(dashCat, tally('category', OPT.category));
  bars(dashPri, tally('priority', OPT.priority));
  bars(dashStatus, tally('status', OPT.status));
  bars(dashVer, tally('version', OPT.version));
}

/* ---- Excel(.xlsx) 내보내기 (라이브러리 없이 순수 JS로 생성) ---- */
function crc32(bytes){
  let t=crc32.t;
  if(!t){ t=crc32.t=[]; for(let n=0;n<256;n++){ let c=n; for(let k=0;k<8;k++) c=c&1?0xEDB88320^(c>>>1):c>>>1; t[n]=c>>>0; } }
  let crc=0^(-1);
  for(let i=0;i<bytes.length;i++) crc=(crc>>>8)^t[(crc^bytes[i])&0xFF];
  return (crc^(-1))>>>0;
}
function zipStored(files){
  const enc=new TextEncoder(), chunks=[], central=[]; let offset=0;
  const u16=v=>[v&0xFF,(v>>>8)&0xFF];
  const u32=v=>[v&0xFF,(v>>>8)&0xFF,(v>>>16)&0xFF,(v>>>24)&0xFF];
  files.forEach(f=>{
    const name=enc.encode(f.name), data=f.bytes, crc=crc32(data);
    chunks.push(new Uint8Array([].concat(
      u32(0x04034b50),u16(20),u16(0),u16(0),u16(0),u16(0),
      u32(crc),u32(data.length),u32(data.length),u16(name.length),u16(0))));
    chunks.push(name); chunks.push(data);
    central.push(new Uint8Array([].concat(
      u32(0x02014b50),u16(20),u16(20),u16(0),u16(0),u16(0),u16(0),
      u32(crc),u32(data.length),u32(data.length),u16(name.length),
      u16(0),u16(0),u16(0),u16(0),u32(0),u32(offset))));
    central.push(name);
    offset += 30+name.length+data.length;
  });
  const cdStart=offset; let cdSize=0;
  central.forEach(c=>{ chunks.push(c); cdSize+=c.length; });
  chunks.push(new Uint8Array([].concat(
    u32(0x06054b50),u16(0),u16(0),
    u16(central.length/2),u16(central.length/2),
    u32(cdSize),u32(cdStart),u16(0))));
  let total=chunks.reduce((s,c)=>s+c.length,0), out=new Uint8Array(total), p=0;
  chunks.forEach(c=>{ out.set(c,p); p+=c.length; });
  return new Blob([out],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
}
function buildXlsxBlob(){
  const enc=new TextEncoder();
  const xesc=s=>String(s==null?'':s).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
  const colL=n=>{ let s=''; n++; while(n>0){ const m=(n-1)%26; s=String.fromCharCode(65+m)+s; n=(n-m-1)/26; } return s; };
  const H=v=>({v,header:true}), T=v=>({v:v==null?'':v}), N=v=>({v,num:true});
  function sheetXml(rows, colsXml){
    let body='';
    rows.forEach((cells,ri)=>{
      let r='<row r="'+(ri+1)+'">';
      cells.forEach((cell,ci)=>{
        const ref=colL(ci)+(ri+1);
        if(!cell){ return; }
        const s=cell.header?' s="1"':'';
        if(cell.num && cell.v!=='' && cell.v!=null && !isNaN(cell.v)){ r+='<c r="'+ref+'"'+s+'><v>'+cell.v+'</v></c>'; }
        else if(cell.v===''||cell.v==null){ if(cell.header) r+='<c r="'+ref+'"'+s+' t="inlineStr"><is><t></t></is></c>'; }
        else { r+='<c r="'+ref+'"'+s+' t="inlineStr"><is><t xml:space="preserve">'+xesc(cell.v)+'</t></is></c>'; }
      });
      body+=r+'</row>';
    });
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'+
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'+
      (colsXml||'')+'<sheetData>'+body+'</sheetData></worksheet>';
  }
  /* sheet1: 요구사항 목록 */
  const rows1=[ FIELDS.map(k=>H(LABELS[k])) ];
  data.forEach(d=>{
    rows1.push(FIELDS.map(k=>{
      if(k==='effort' && d[k]!=='' && d[k]!=null && !isNaN(d[k])) return N(d[k]);
      return T(d[k]);
    }));
  });
  const cols1='<cols><col min="1" max="2" width="11"/><col min="3" max="4" width="16"/>'+
    '<col min="5" max="5" width="22"/><col min="6" max="7" width="34"/>'+
    '<col min="8" max="26" width="14"/></cols>';
  /* sheet2: 요약 대시보드 */
  const tot=data.length, reqN=data.filter(d=>d.priority==='필수').length;
  const doneN=data.filter(d=>d.status==='승인'||d.status==='완료').length;
  const effN=data.reduce((s,d)=>s+(parseFloat(d.effort)||0),0);
  const rows2=[
    [H('📊 요구사항 현황 대시보드')], [],
    [H('전체 요구사항'), N(tot)],
    [H('필수 요구사항'), N(reqN)],
    [H('승인/완료'), N(doneN)],
    [H('총 예상공수(MD)'), N(effN)], []
  ];
  const section=(title, key, order)=>{
    rows2.push([H(title)]);
    rows2.push([H('항목'),H('건수'),H('비율(%)')]);
    tally(key, order).forEach(r=>rows2.push([T(r.k), N(r.n), N(tot?Math.round(r.n/tot*100):0)]));
    rows2.push([]);
  };
  section('구분별 현황','category',OPT.category);
  section('우선순위별 현황','priority',OPT.priority);
  section('상태별 현황','status',OPT.status);
  section('목표 버전별 현황','version',OPT.version);
  const cols2='<cols><col min="1" max="1" width="20"/><col min="2" max="3" width="12"/></cols>';

  const files=[
    {name:'[Content_Types].xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'+
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'+
      '<Default Extension="xml" ContentType="application/xml"/>'+
      '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'+
      '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'+
      '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'+
      '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>')},
    {name:'_rels/.rels', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')},
    {name:'xl/workbook.xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'+
      '<sheets><sheet name="요구사항 목록" sheetId="1" r:id="rId1"/><sheet name="요약 대시보드" sheetId="2" r:id="rId2"/></sheets></workbook>')},
    {name:'xl/_rels/workbook.xml.rels', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'+
      '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'+
      '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>')},
    {name:'xl/styles.xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'+
      '<fonts count="2"><font><sz val="11"/><name val="맑은 고딕"/></font>'+
      '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="맑은 고딕"/></font></fonts>'+
      '<fills count="3"><fill><patternFill patternType="none"/></fill>'+
      '<fill><patternFill patternType="gray125"/></fill>'+
      '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'+
      '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'+
      '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'+
      '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'+
      '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs>'+
      '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>')},
    {name:'xl/worksheets/sheet1.xml', bytes:enc.encode(sheetXml(rows1, cols1))},
    {name:'xl/worksheets/sheet2.xml', bytes:enc.encode(sheetXml(rows2, cols2))},
  ];
  return zipStored(files);
}
document.getElementById('exportXlsx').onclick=()=>{
  try{ dl(buildXlsxBlob(),'메디힘_요구사항.xlsx'); toast('Excel 파일을 내보냈습니다'); }
  catch(e){ alert('Excel 생성 실패: '+e.message); }
};

/* ---- export / import ---- */
document.getElementById('exportCsv').onclick=()=>{
  const head=FIELDS.map(k=>LABELS[k]);
  const lines=[head.join(',')].concat(data.map(d=>FIELDS.map(k=>{
    let v=(d[k]==null?'':String(d[k])); if(/[",\n]/.test(v)) v='"'+v.replace(/"/g,'""')+'"'; return v;
  }).join(',')));
  dl(new Blob(['﻿'+lines.join('\n')],{type:'text/csv;charset=utf-8'}),'메디힘_요구사항.csv');
};
document.getElementById('exportJson').onclick=()=>dl(new Blob([JSON.stringify(data,null,2)],{type:'application/json'}),'메디힘_요구사항_백업.json');
document.getElementById('importBtn').onclick=()=>{
  if(useApi){ alert('DB 연결 모드에서는 JSON 복원이 비활성화됩니다.\n로컬(file://) 모드에서만 사용하세요. (DB는 db/seed.sql 로 적재)'); return; }
  importFile.click();
};
importFile.onchange=e=>{
  const f=e.target.files[0]; if(!f) return;
  const r=new FileReader();
  r.onload=()=>{ try{ const arr=JSON.parse(r.result); if(Array.isArray(arr)){ data=arr; let mx=0;data.forEach(d=>{if(d.id>mx)mx=d.id});data.forEach(d=>{if(!d.id)d.id=++mx}); save(); updateCounts(); toast('복원 완료 ('+arr.length+'건)'); } }catch(err){ alert('JSON 파싱 실패'); } };
  r.readAsText(f); importFile.value='';
};
function dl(blob,name){ const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=name; a.click(); URL.revokeObjectURL(a.href); }

/* ---- toast ---- */
let toT;
function toast(msg){ const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* ---- init ---- */
(async function boot(){
  await tryConnectApi();      // 서버 있으면 DB 옵션으로 OPT 갱신 + useApi=true
  initSelects();              // (갱신된) OPT 기준으로 select 구성
  try{ await loadData(); }    // DB 또는 localStorage 에서 데이터 로드
  catch(e){ useApi=false; load(); }
  renderStatus();
  renderTable();
  updateCounts();
})();
</script>
</body>
</html>

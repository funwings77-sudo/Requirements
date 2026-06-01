<?php
/* =====================================================================
   회원 관리 - 독립 PHP 단일 파일 (페이지 + ?api= JSON API, PDO/MySQL `member`)
   구성: gd.xlsx(이름/소속부서/휴대폰번호/이메일) 기반, 15명 시드
   - 일반 요청     : 회원 목록 페이지
   - ?api=... 요청 : JSON API (members CRUD / health)
   동작: MySQL 연결되면 DB CRUD(실시간 저장), 미연결이면 $SEED 읽기전용 표시
   실행: 프로젝트 루트에서  php -c php.ini -S localhost:8000
        → http://localhost:8000/member/member.php
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
$DB = [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '3306',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
  'name' => getenv('DB_NAME') ?: 'medihim',
];

if (isset($_GET['api'])) { member_api($DB); exit; }   // API는 member_api 내부에서 액션별 권한 검사
require_perm('member', 'access');                     // 페이지(HTML) 진입은 회원관리 '접속' 권한 필요

function member_json($d, $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function member_err($m, $c = 400) { member_json(['error' => $m], $c); }

function member_api($DB) {
  $FIELDS = ['name','dept','phone','email'];   // status(구분)는 별도 처리(관리자만 변경, 미전송 시 기존값 보존)
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { member_err('DB 연결 실패: ' . $e->getMessage(), 500); }

  $api = $_GET['api']; $method = $_SERVER['REQUEST_METHOD'];
  if ($api === 'health') member_json(['ok' => true]);

  $SEL = "SELECT id,name,dept,phone,email,is_admin,status,created_at AS createdAt,updated_at AS updatedAt,created_by AS createdBy,updated_by AS updatedBy FROM member";
  if ($api === 'members') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($method === 'GET') {
      if (!can('member', 'read')) member_err('읽기 권한이 없습니다', 403);
      if ($id !== null) { $g = $pdo->prepare("$SEL WHERE id=?"); $g->execute([$id]); $row = $g->fetch(); if (!$row) member_err('없는 회원', 404); member_json($row); }
      member_json($pdo->query("$SEL ORDER BY name")->fetchAll());
    }

    $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
    $vals = [];
    foreach ($FIELDS as $f) { $v = isset($body[$f]) ? trim((string)$body[$f]) : ''; $vals[$f] = ($v === '') ? null : $v; }
    if (($method === 'POST' || $method === 'PUT') && $vals['name'] === null) member_err('이름은 필수입니다');  // 이름 필수는 등록/수정에만 (DELETE는 본문 없음)
    // 구분(status): 관리자만 변경 가능. 값은 이용중/이용중지로 정규화
    $statusGiven = array_key_exists('status', $body) && auth_is_admin();
    $statusVal   = ((isset($body['status']) ? trim((string)$body['status']) : '') === '이용중지') ? '이용중지' : '이용중';

    if ($method === 'POST') {
      if (!auth_is_admin()) member_err('관리자만 회원을 추가할 수 있습니다', 403);   // 회원 추가는 관리자 전용
      $u = auth_user(); $author = $u ? $u['name'] : null;             // 최초작성자=최종수정자=로그인 사용자(생성 시점)
      $cols = array_merge($FIELDS, ['status', 'created_by', 'updated_by']);
      $sql = "INSERT INTO member (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
      $params = array_map(function ($f) use ($vals) { return $vals[$f]; }, $FIELDS);
      $params[] = $statusVal; $params[] = $author; $params[] = $author;
      $pdo->prepare($sql)->execute($params);
      $g = $pdo->prepare("$SEL WHERE id=?"); $g->execute([$pdo->lastInsertId()]); member_json($g->fetch(), 201);
    }
    if ($method === 'PUT') {
      if ($id === null) member_err('id 필요');
      $u = auth_user(); $editor = $u ? $u['name'] : null;
      // 회원 수정: 관리자 또는 본인(self-edit, LNB ⚙️ 모달에서 프로필 수정)만 허용
      $isSelf = $u && (int)$u['id'] === (int)$id;
      if (!auth_is_admin() && !$isSelf) member_err('관리자 또는 본인만 회원 정보를 수정할 수 있습니다', 403);
      $set = implode(', ', array_map(function ($f) { return "$f=?"; }, $FIELDS));
      $params = array_map(function ($f) use ($vals) { return $vals[$f]; }, $FIELDS);
      if ($statusGiven) { $set .= ', status=?'; $params[] = $statusVal; }   // 관리자가 구분을 보낸 경우에만 변경(아니면 기존값 보존)
      $params[] = $editor; $params[] = $id;
      $pdo->prepare("UPDATE member SET $set, updated_at=CURRENT_TIMESTAMP, updated_by=? WHERE id=?")->execute($params);  // 최종수정일시·최종수정자 갱신
      $g = $pdo->prepare("$SEL WHERE id=?"); $g->execute([$id]); $row = $g->fetch(); if (!$row) member_err('없는 회원', 404); member_json($row);
    }
    if ($method === 'DELETE') {
      if (!auth_is_admin()) member_err('관리자만 회원을 삭제할 수 있습니다', 403);   // 회원 삭제는 관리자 전용
      if ($id === null) member_err('id 필요');
      $st = $pdo->prepare("DELETE FROM member WHERE id=?"); $st->execute([$id]);
      if ($st->rowCount() === 0) member_err('없는 회원', 404); member_json(['ok' => true]);
    }
  }

  /* ---- 권한 설정 API (관리자 전용): 회원 1명의 is_admin + 메뉴별 CRUD 매트릭스 ---- */
  if ($api === 'perms') {
    if (!auth_is_admin()) member_err('관리자만 접근할 수 있습니다', 403);
    $RES = array_map(fn($r)=>$r['key'], auth_perm_resources());   // 메뉴 트리에서 자동 파생(project_manager 등 자동 포함)
    $ACT = ['access','read','write','update','delete'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) member_err('id 필요');

    if ($method === 'GET') {
      $g = $pdo->prepare("SELECT is_admin FROM member WHERE id=?"); $g->execute([$id]);
      $isAdmin = $g->fetchColumn();
      if ($isAdmin === false) member_err('없는 회원', 404);
      $perms = [];
      foreach ($RES as $r) $perms[$r] = ['access'=>0,'read'=>0,'write'=>0,'update'=>0,'delete'=>0];
      $st = $pdo->prepare("SELECT resource,can_access,can_read,can_write,can_update,can_delete FROM member_perm WHERE member_id=?");
      $st->execute([$id]);
      foreach ($st->fetchAll() as $row) {
        if (!isset($perms[$row['resource']])) continue;
        $perms[$row['resource']] = ['access'=>(int)$row['can_access'],'read'=>(int)$row['can_read'],'write'=>(int)$row['can_write'],'update'=>(int)$row['can_update'],'delete'=>(int)$row['can_delete']];
      }
      member_json(['is_admin' => (bool)(int)$isAdmin, 'perms' => $perms]);
    }
    if ($method === 'PUT') {
      $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
      $isAdmin = !empty($body['is_admin']) ? 1 : 0;
      // 마지막 관리자 해제 방지(락아웃 방지)
      if ($isAdmin === 0) {
        $g = $pdo->prepare("SELECT is_admin FROM member WHERE id=?"); $g->execute([$id]); $was = (int)$g->fetchColumn();
        if ($was === 1) {
          $cnt = (int)$pdo->query("SELECT COUNT(*) FROM member WHERE is_admin=1")->fetchColumn();
          if ($cnt <= 1) member_err('최소 1명의 관리자가 필요합니다. 다른 회원을 먼저 관리자로 지정하세요.', 400);
        }
      }
      $pdo->beginTransaction();
      try {
        $pdo->prepare("UPDATE member SET is_admin=? WHERE id=?")->execute([$isAdmin, $id]);
        $up = $pdo->prepare("INSERT INTO member_perm (member_id,resource,can_access,can_read,can_write,can_update,can_delete) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE can_access=?, can_read=?, can_write=?, can_update=?, can_delete=?");
        foreach ($RES as $r) {
          $p = isset($body['perms'][$r]) && is_array($body['perms'][$r]) ? $body['perms'][$r] : [];
          $a=!empty($p['access'])?1:0; $rd=!empty($p['read'])?1:0; $w=!empty($p['write'])?1:0; $u=!empty($p['update'])?1:0; $d=!empty($p['delete'])?1:0;
          $up->execute([$id,$r,$a,$rd,$w,$u,$d, $a,$rd,$w,$u,$d]);
        }
        $pdo->commit();
      } catch (Throwable $e) { $pdo->rollBack(); member_err('권한 저장 실패: ' . $e->getMessage(), 500); }
      member_json(['ok' => true]);
    }
    member_err('perms: 허용되지 않은 메서드', 405);
  }

  member_err('unknown api: ' . $api, 404);
}

/* MySQL 미연결 시 표시할 시드(읽기전용). db/member.sql · gd.xlsx 와 동일(15명) */
$SEED = json_decode(<<<'JSON'
[
  {"id":1,"name":"강보성","dept":"개발 R&D · R&D파트","phone":"010-4341-3919","email":"bskang@castingn.com"},
  {"id":2,"name":"권수빈","dept":"MSO 해외환자유치","phone":"010-2803-5014","email":"sbkwon@castingn.com"},
  {"id":3,"name":"김기문","dept":"개발 운영/유지보수","phone":"010-7657-5746","email":"kkm@castingn.com"},
  {"id":4,"name":"김대진","dept":"MSO사업부","phone":"010-3341-4160","email":"jaykim@castingn.com"},
  {"id":5,"name":"박진국","dept":"브랜드마케팅파트","phone":"010-2101-6790","email":"jkpark@castingn.com"},
  {"id":6,"name":"신유경","dept":"MSO사업부","phone":"010-3089-9889","email":"ug_shin@castingn.com"},
  {"id":7,"name":"심순영","dept":"해외사업팀","phone":"010-4180-0813","email":"ssy@castingn.com"},
  {"id":8,"name":"양태겸","dept":"해외마케팅파트","phone":"010-7679-4085","email":"tkyang@castingn.com"},
  {"id":9,"name":"용성남","dept":"MSO사업부","phone":"010-9245-1391","email":"snyong@castingn.com"},
  {"id":10,"name":"유정은","dept":"해외마케팅파트","phone":"010-9369-3231","email":"jeryu@castingn.com"},
  {"id":11,"name":"이홍근","dept":"브랜드마케팅파트","phone":"010-7291-1072","email":"hklee@castingn.com"},
  {"id":12,"name":"채성훈","dept":"MSO사업부","phone":"010-8666-0200","email":"shchae@castingn.com"},
  {"id":13,"name":"최준혁","dept":"대표이사","phone":"010-8950-9694","email":"jhchoi@castingn.com"},
  {"id":14,"name":"한영은","dept":"컨설팅&대행파트","phone":"010-4499-2625","email":"yehan@castingn.com"},
  {"id":15,"name":"김한나","dept":"브랜드마케팅파트","phone":"010-2245-3005","email":"hnkim@castingn.com"}
]
JSON
, true);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>메디힘 회원 관리</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  td.email a{color:#2563eb;text-decoration:none;} td.email a:hover{text-decoration:underline;}
  .dept-chip{display:inline-block;background:#eef2ff;color:#4338ca;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
  .muted{color:#9aa3b2;}
  .stat-ico.i5{background:#ede9fe;} .stat.b5 .num{color:#7c3aed;}
  .admin-chip{display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;margin-left:6px;vertical-align:middle;}
  .st-chip{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;border:1px solid transparent;}
  .st-chip.st-on{background:#dff5ea;color:#127c3c;border-color:#bfe7cf;}
  .st-chip.st-off{background:#fde5e5;color:#a52323;border-color:#f1c4c4;}
  /* 이름 셀 — 보라 그라데이션 아바타(이니셜) + 이름 */
  td.m-name{font-weight:700;white-space:nowrap;}
  /* 리스트 모든 텍스트 center (이름 컬럼만 좌측 정렬) */
  #memTable th,#memTable td{text-align:center;}
  #memTable td.m-name{text-align:left;}
  #memTable .row-actions{justify-content:center;}   /* 수정/삭제 버튼 가운데 정렬 */
  td.m-name .m-avatar{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#b66dff,#8e54e9);color:#fff;font-weight:700;font-size:13px;margin-right:8px;vertical-align:middle;box-shadow:0 2px 6px rgba(182,109,255,.25);}
  td.m-name .m-nm{vertical-align:middle;}
  /* 회원 추가 버튼 (15명 카운트 오른쪽) */
  #addBtn{background:#FB64C9;color:#fff;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  #addBtn:hover{background:#e84fb6;}
  /* 검색 카드 — 헤더 + 우측 명시 토글 버튼 */
  .sp-card .card-body{padding:24px 28px;}
  .sp-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line);}
  .sp-head .sp-title{margin:0;font-size:15px;font-weight:700;color:var(--ink);}
  .sp-toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);font-family:inherit;transition:.15s;}
  .sp-toggle-btn:hover{background:var(--brand-soft);color:var(--brand-d);border-color:var(--brand);}
  .sp-toggle-btn .sp-chev{font-size:11px;color:var(--brand);transition:transform .15s;}
  .search-panel{padding-top:18px;}
  .sp-card.collapsed .sp-head{padding-bottom:0;border-bottom:none;}
  .sp-card.collapsed .sp-toggle-btn .sp-chev{transform:rotate(-90deg);}
  .sp-card.collapsed .search-panel{display:none;}
  /* 검색영역 1행 레이아웃 */
  .sp-row{display:flex;align-items:center;gap:8px 14px;flex-wrap:wrap;}
  .sp-field{display:flex;align-items:center;gap:6px;}
  .sp-field label{margin:0;font-size:12.5px;font-weight:700;color:#6c7293;white-space:nowrap;}
  .sp-field .form-select,.sp-field .form-control{height:34px;}
  .sp-field #fDept{min-width:130px;}
  .sp-field #fDateField{min-width:140px;}
  .sp-field #fDateFrom,.sp-field #fDateTo{width:148px;}
  .sp-field #fTextField{width:148px;flex:none;}
  .sp-field .sp-tilde{color:var(--sub);font-weight:700;}
  .sp-grow{flex:0 1 auto;}
  .sp-grow #search{width:440px;max-width:100%;flex:none;}
  .sp-actions-inline{margin-left:auto;gap:8px;}
  @media(max-width:720px){ .sp-actions-inline{margin-left:0;} }
  /* 리스트 카드 헤더(제목 + 액션 분리) */
  .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:16px;}
  .list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">👥 회원 관리</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('member'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="member">회원 관리</h1>
        <div class="pg-sub">임직원 연락처</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="stat-row">
      <div class="stat b1"><div class="stat-top"><span class="stat-ico i1"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="lab">전체 회원</span></div><div class="num" id="sTotal">0</div></div>
      <div class="stat b5"><div class="stat-top"><span class="stat-ico i5"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/></svg></span><span class="lab">부서 수</span></div><div class="num" id="sDept">0</div></div>
      <div class="stat b4"><div class="stat-top"><span class="stat-ico i4"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l12-5v12L3 13z"/><path d="M15 8a3 3 0 0 1 0 6"/><path d="M6 13v4a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2.5"/></svg></span><span class="lab">마케팅</span></div><div class="num" id="sMkt">0</div></div>
      <div class="stat b3"><div class="stat-top"><span class="stat-ico i3"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span><span class="lab">개발</span></div><div class="num" id="sDev">0</div></div>
    </div>

    <!-- 검색 카드 (리스트와 분리, 헤더 클릭으로 collapse) -->
    <div class="card sp-card" id="spCard">
      <div class="card-body">
        <div class="sp-head">
          <h2 class="sp-title">검색</h2>
          <button type="button" class="sp-toggle-btn" id="spToggle" onclick="toggleSearchPanel()" aria-expanded="true">
            <span class="sp-chev">▼</span><span class="sp-toggle-text">접기</span>
          </button>
        </div>
        <div class="search-panel" id="searchPanel">
          <div class="sp-row">
            <div class="sp-field">
              <label>이용여부</label>
              <select id="fStatus" class="form-select form-select-sm">
                <option value="">전체</option>
                <option value="이용중">이용중</option>
                <option value="이용중지">이용중지</option>
              </select>
            </div>
            <div class="sp-field">
              <label>소속부서</label>
              <select id="fDept" class="form-select form-select-sm"><option value="">전체</option></select>
            </div>
            <div class="sp-field">
              <label>기간</label>
              <select id="fDateField" class="form-select form-select-sm">
                <option value="">기준 일자</option>
                <option value="createdAt">최초작성일시</option>
                <option value="updatedAt">최종수정일시</option>
              </select>
              <input type="date" id="fDateFrom" class="form-control form-control-sm">
              <span class="sp-tilde">~</span>
              <input type="date" id="fDateTo" class="form-control form-control-sm">
            </div>
            <div class="sp-field sp-grow">
              <label>통합검색</label>
              <select id="fTextField" class="form-select form-select-sm">
                <option value="">전체</option>
                <option value="name">이름</option>
                <option value="phone">휴대폰번호</option>
                <option value="email">이메일</option>
                <option value="createdBy">최초작성자</option>
                <option value="updatedBy">최종수정자</option>
              </select>
              <input id="search" class="form-control form-control-sm" placeholder="검색어를 입력하세요">
              <button type="button" class="btn primary sm" id="btnSearch">🔍 검색</button>
              <button type="button" class="btn ghost sm" id="btnReset">↺ 초기화</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 리스트 카드 -->
    <div class="card">
      <div class="card-body">
        <div class="list-head">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="card-title" style="margin:0;">회원 목록</h2>
            <span class="count-chip" id="listCount">검색 수 0 / 총 수 0</span>
          </div>
          <div class="list-actions">
            <?php if (auth_is_admin()): ?><a class="btn sm" id="addBtn" href="form.php">＋ 회원 추가</a><?php endif; ?>
          </div>
        </div>
        <div class="table-scroll">
          <table id="memTable" class="sch-look">
            <thead><tr><th>번호</th><th>이름</th><th>이용여부</th><th>소속부서</th><th>휴대폰번호</th><th>이메일</th><th>최초작성일시</th><th>최종수정일시</th><th>최초작성자</th><th>최종수정자</th><?php if (auth_is_admin()): ?><th>관리</th><?php endif; ?></tr></thead>
            <tbody id="memBody"></tbody>
          </table>
        </div>
        <div class="empty hidden" id="emptyMsg">회원이 없습니다<?php if (auth_is_admin()): ?>. 우측 상단 <b>＋ 회원 추가</b>로 등록하세요<?php endif; ?>.</div>
      </div>
    </div>
  </main>
</div>

<div class="toast" id="toast"></div>

<script>
const FIELDS = ['name','dept','phone','email'];
const SEED = <?php echo json_encode($SEED, JSON_UNESCAPED_UNICODE); ?>;
const PERM = <?php echo json_encode(perm_map('member'), JSON_UNESCAPED_UNICODE); ?>;
const IS_ADMIN = <?php echo auth_is_admin() ? 'true' : 'false'; ?>;   // 수정/삭제 버튼은 관리자에게만 노출
const API = '?api=';
const onHttp = location.protocol === 'http:' || location.protocol === 'https:';
let useApi = false, data = [];

async function tryConnect(){
  if(!onHttp) return false;
  try{ const r = await fetch(API+'health',{cache:'no-store'}); if(!r.ok) throw 0; useApi = true; }
  catch(e){ useApi = false; }
  return useApi;
}
async function loadData(){
  if(useApi){ const r = await fetch(API+'members',{cache:'no-store'}); if(!r.ok) throw new Error('목록 조회 실패 ('+r.status+')'); data = await r.json(); }
  else { data = SEED.slice(); }
}
function renderStatus(){
  const el = document.getElementById('dbStatus'); if(!el) return;
  if(useApi){ el.textContent='🟢 DB 연결됨'; el.style.background='#dcfce7'; el.style.color='#166534'; el.title='MySQL 백엔드와 연동 중'; }
  else { el.textContent='💾 로컬 모드'; el.style.background='#fef3c7'; el.style.color='#92400e'; el.title='서버 미연결 — 시드 데이터(읽기전용)'; }
}

function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function depts(){ return [...new Set(data.map(d=>(d.dept||'').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ko')); }
function syncDeptLists(){
  const list = depts();
  const prev = fDept.value;
  fDept.innerHTML = '<option value="">소속부서 전체</option>';
  list.forEach(v=>fDept.appendChild(new Option(v,v)));
  if(list.includes(prev)) fDept.value = prev;
  const dl = document.getElementById('deptList'); if(dl) dl.innerHTML = list.map(v=>`<option value="${esc(v)}">`).join('');   // 부서 datalist는 form.php에 있음
}

/* ---- LNB drawer ---- */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
function openLnb(){ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); }
function closeLnb(){ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); }
document.getElementById('hambBtn').onclick=openLnb;
document.getElementById('lnbClose').onclick=closeLnb;
lnbOverlay.onclick=closeLnb;

/* ---- 추가/수정은 별도 페이지 form.php 에서 처리 (＋ 회원 추가 / 행의 수정 버튼이 form.php 로 이동) ---- */
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLnb(); });
async function delItem(id){
  if(!confirm('이 회원을 삭제하시겠습니까?')) return;
  if(useApi){
    try{ const r=await fetch(API+'members&id='+id,{method:'DELETE'}); if(!r.ok) throw new Error('HTTP '+r.status); await loadData(); }
    catch(err){ alert('삭제 실패: '+err.message); return; }
  } else { data=data.filter(d=>d.id!=id); }
  render(); toast('삭제되었습니다');
}

/* 적용된 검색조건(검색 버튼/Enter로 통합검색어 반영, selectbox·기간은 즉시 반영) */
const SF_INIT = { status:'', dept:'', textField:'', q:'', dateField:'', dateFrom:'', dateTo:'' };
let SF = {...SF_INIT};
const TEXT_KEYS = ['name','phone','email','createdBy','updatedBy'];   // 통합검색 '전체' 대상 — 이름·휴대폰·이메일·최초작성자·최종수정자
function filtered(){
  return data.filter(d=>{
    if(SF.status && ((d.status==='이용중지')?'이용중지':'이용중')!==SF.status) return false;
    if(SF.dept && (d.dept||'')!==SF.dept) return false;
    if(SF.q){
      const keys = SF.textField ? [SF.textField] : TEXT_KEYS;
      const hay  = keys.map(k=>(d[k]||'')).join(' ').toLowerCase();
      if(!hay.includes(SF.q)) return false;
    }
    if(SF.dateField && (SF.dateFrom||SF.dateTo)){
      const v = String(d[SF.dateField]||'').slice(0,10);   // 'YYYY-MM-DD HH:MM:SS' → 앞 10자리
      if(!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;     // 날짜 형식 아닌 값은 제외
      if(SF.dateFrom && v<SF.dateFrom) return false;
      if(SF.dateTo   && v>SF.dateTo)   return false;
    }
    return true;
  });
}
function applySearch(){
  SF = {
    status:     document.getElementById('fStatus').value,
    dept:       document.getElementById('fDept').value,
    textField:  document.getElementById('fTextField').value,
    q:          (document.getElementById('search').value||'').trim().toLowerCase(),
    dateField:  document.getElementById('fDateField').value,
    dateFrom:   document.getElementById('fDateFrom').value,
    dateTo:     document.getElementById('fDateTo').value,
  };
  render();
}
function doSearch(){   // 검색 버튼/Enter: 통합검색어 미입력 시 안내 팝업
  const q=document.getElementById('search'); if(!(q.value||'').trim()){ alert('검색어를 입력해주세요.'); q.focus(); return; }
  applySearch();
}
function resetSearch(){   // 초기화: 최초 진입 상태(전체 컨트롤 빈 값)로 복원
  ['fStatus','fDept','fTextField','search','fDateField','fDateFrom','fDateTo'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
  SF = {...SF_INIT}; render();
}
/* 검색영역 collapse — localStorage('memberSp_collapsed') 영속 + 버튼 텍스트 토글 */
function toggleSearchPanel(){
  const c=document.getElementById('spCard'); if(!c) return;
  c.classList.toggle('collapsed');
  const cl=c.classList.contains('collapsed');
  try{ localStorage.setItem('memberSp_collapsed', cl?'1':'0'); }catch(e){}
  document.getElementById('spToggle')?.setAttribute('aria-expanded', cl?'false':'true');
  const txt=document.querySelector('#spToggle .sp-toggle-text'); if(txt) txt.textContent = cl ? '펼치기' : '접기';
}
/* 관리자 외(비관리자)에게는 휴대폰 중간 4자리·이메일 @앞 로컬부 마지막 2자리 마스킹 */
function maskPhone(p){ p=(p==null?'':String(p)).trim(); if(!p) return p;
  const a=p.split('-'); if(a.length===3){ a[1]='****'; return a.join('-'); }   // 010-1234-5678 → 010-****-5678
  return p.length>=8 ? p.slice(0,3)+'****'+p.slice(7) : p; }
function maskEmail(e){ e=(e==null?'':String(e)).trim(); const at=e.indexOf('@'); if(at<0) return e;
  const local=e.slice(0,at); return local.slice(0,Math.max(0,local.length-2))+'**'+e.slice(at); }   // bskang@.. → bska**@..
function render(){
  syncDeptLists();
  const rows=filtered().sort((a,b)=>(b.id||0)-(a.id||0));  /* 최신순: id 내림차순(최근 등록이 위로) */
  listCount.textContent = `검색 수 ${rows.length} / 총 수 ${data.length}`;
  sTotal.textContent=data.length;
  sDept.textContent=depts().length;
  sMkt.textContent=data.filter(d=>(d.dept||'').includes('마케팅')).length;
  sDev.textContent=data.filter(d=>(d.dept||'').includes('개발')).length;
  const body=document.getElementById('memBody');
  if(rows.length===0){ body.innerHTML=''; emptyMsg.classList.remove('hidden'); return; }
  emptyMsg.classList.add('hidden');
  const dash='<span class="muted">-</span>';
  body.innerHTML=rows.map((d,i)=>{
    const dept=d.dept?`<span class="dept-chip">${esc(d.dept)}</span>`:dash;
    const mail=d.email?(IS_ADMIN?`<a href="mailto:${esc(d.email)}">${esc(d.email)}</a>`:esc(maskEmail(d.email))):dash;
    const admin=d.is_admin?`<span class="admin-chip">관리자</span>`:'';
    const st=(d.status==='이용중지')?'이용중지':'이용중';
    const stChip=`<span class="st-chip ${st==='이용중지'?'st-off':'st-on'}">${st}</span>`;
    const acts=[];
    if(IS_ADMIN && PERM.update) acts.push(`<a class="btn ghost sm" href="form.php?id=${d.id}">수정</a>`);   // 관리자에게만 노출
    if(IS_ADMIN && PERM.delete) acts.push(`<button class="btn danger sm" onclick="delItem(${d.id})">삭제</button>`);
    const actions=acts.length?`<div class="row-actions">${acts.join('')}</div>`:dash;
    return `<tr>
      <td data-label="번호">${rows.length - i}</td>
      <td data-label="이름" class="m-name"><span class="m-avatar">${esc((d.name||'?').substring(0,1))}</span><span class="m-nm">${esc(d.name)||'-'}</span>${admin}</td>
      <td data-label="이용여부">${stChip}</td>
      <td data-label="소속부서">${dept}</td>
      <td data-label="휴대폰번호">${esc(IS_ADMIN?d.phone:maskPhone(d.phone))||dash}</td>
      <td class="email" data-label="이메일">${mail}</td>
      <td data-label="최초작성일시">${fmtDt(d.createdAt)}</td>
      <td data-label="최종수정일시">${fmtDt(d.updatedAt)}</td>
      <td data-label="최초작성자">${esc(d.createdBy)||dash}</td>
      <td data-label="최종수정자">${esc(d.updatedBy)||dash}</td>
      ${IS_ADMIN ? `<td data-label="관리">${actions}</td>` : ''}</tr>`;
  }).join('');
}
function fmtDt(s){ if(!s) return '<span class="muted">-</span>'; return esc(String(s).replace('T',' ').slice(0,19)); }   /* YYYY-MM-DD HH:MM:SS */
/* 이벤트: 소속부서·통합검색 필드·기간 select·날짜 즉시 검색 / 검색 버튼·초기화 / Enter */
['fStatus','fDept','fTextField','fDateField','fDateFrom','fDateTo'].forEach(id=>{
  const e=document.getElementById(id); if(e) e.addEventListener('change', applySearch);
});
document.getElementById('btnSearch').addEventListener('click', doSearch);
document.getElementById('btnReset').addEventListener('click', resetSearch);
document.getElementById('search').addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });

/* 리스트를 어느 위치에서도 좌우 스크롤: 휠(세로/가로)·Shift+휠·트랙패드를 가로 스크롤로 변환.
   가장자리에 닿으면 preventDefault 안 하고 페이지 세로 스크롤이 자연스럽게 이어지게 함. (페이지 내 모든 표) */
document.querySelectorAll('.table-scroll').forEach(ts=>{
  ts.addEventListener('wheel',e=>{
    if(ts.scrollWidth<=ts.clientWidth) return;            // 가로 오버플로 없으면 기본 동작
    const delta=Math.abs(e.deltaX)>=Math.abs(e.deltaY)?e.deltaX:e.deltaY;
    if(!delta) return;
    const atStart=ts.scrollLeft<=0, atEnd=Math.ceil(ts.scrollLeft+ts.clientWidth)>=ts.scrollWidth;
    if((delta<0&&atStart)||(delta>0&&atEnd)) return;      // 가장자리 → 페이지 세로 스크롤 허용
    ts.scrollLeft+=delta;
    e.preventDefault();
  },{passive:false});
});

let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }
/* 권한 설정은 독립 페이지(/authorization_settings/authorization_settings.php)에서 — 행의 🔑 권한 버튼이 ?id=N 으로 이동 */

(async function boot(){
  if(!IS_ADMIN){ const a=document.getElementById('addBtn'); if(a) a.style.display='none'; }   // 회원 추가는 관리자에게만 노출
  await tryConnect();
  try{ await loadData(); }catch(e){ useApi=false; data=SEED.slice(); }
  renderStatus(); render();
  if(localStorage.getItem('memberSp_collapsed')==='1'){ document.getElementById('spCard')?.classList.add('collapsed'); document.getElementById('spToggle')?.setAttribute('aria-expanded','false'); const _t=document.querySelector('#spToggle .sp-toggle-text'); if(_t) _t.textContent='펼치기'; }
  const mmsg=sessionStorage.getItem('memberToast'); if(mmsg){ sessionStorage.removeItem('memberToast'); setTimeout(()=>toast(mmsg),150); }   // form.php 저장 후 복귀 토스트
})();
</script>
</body>
</html>

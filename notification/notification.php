<?php
/* =====================================================================
   공지사항 (notification) — 독립 PHP 단일 파일 (목록 페이지 + ?api= JSON CRUD)
   - 권한: notification.{access/read/write/update/delete}
   - 행 단위 권한은 적용 안 함(자원 권한만으로 판단). 작성자는 비정규화 저장.
   - 등록/수정은 별도 페이지 form.php (모달 미사용)
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

if (isset($_GET['api'])) { notification_api($DB); exit; }
require_perm('notification', 'access');
$PERM = perm_map('notification');

function nj($d, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function nerr($m, $code = 400) { nj(['error' => $m], $code); }

function notification_api($DB) {
  $api = $_GET['api']; $method = $_SERVER['REQUEST_METHOD'];
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { nerr('DB 연결 실패: ' . $e->getMessage(), 500); }

  if ($api === 'health') nj(['ok'=>true]);

  if ($api === 'notifications') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $SEL = "SELECT id, title, category, is_important AS isImportant, publish_date AS publishDate, status, content, view_count AS viewCount, created_at AS createdAt, updated_at AS updatedAt, created_by AS createdBy, updated_by AS updatedBy, (SELECT COUNT(*) FROM notif_comment WHERE notif_id = notification.id AND is_deleted = 0) AS commentCount FROM notification";

    if ($method === 'GET') {
      if (!can('notification', 'read')) nerr('읽기 권한이 없습니다', 403);
      if ($id > 0) {
        $pdo->prepare("UPDATE notification SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);   // 상세 열람 시 조회수 +1
        $st = $pdo->prepare("$SEL WHERE id = ?"); $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) nerr('없는 공지', 404);
        $row['isImportant'] = (int)$row['isImportant'];
        nj($row);
      }
      // 목록(검색/필터 옵션은 추후 확장 — 현재는 status·q만)
      $where = []; $params = [];
      if (!empty($_GET['status']))   { $where[] = "status = ?";   $params[] = $_GET['status']; }
      if (!empty($_GET['category'])) { $where[] = "category = ?"; $params[] = $_GET['category']; }
      if (!empty($_GET['q'])) { $where[] = "(title LIKE ? OR content LIKE ?)"; $l = '%' . $_GET['q'] . '%'; array_push($params, $l, $l); }
      $sql = $SEL . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY is_important DESC, id DESC";
      $st = $pdo->prepare($sql); $st->execute($params);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) $r['isImportant'] = (int)$r['isImportant'];
      unset($r);
      nj($rows);
    }

    $me = auth_user();
    // 본문/검증은 쓰기 메서드(POST/PUT)만 사용 — DELETE에서 title 빈 값으로 400 막히지 않도록 분리
    $title=''; $category='일반'; $isImportant=0; $publishDate=''; $status='게시'; $content='';
    if ($method === 'POST' || $method === 'PUT') {
      $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
      $title       = isset($body['title'])       ? trim((string)$body['title'])       : '';
      $category    = isset($body['category'])    ? trim((string)$body['category'])    : '일반';
      $isImportant = !empty($body['isImportant']) ? 1 : 0;
      $publishDate = isset($body['publishDate']) ? trim((string)$body['publishDate']) : '';
      $status      = isset($body['status'])      ? trim((string)$body['status'])      : '게시';
      $content     = isset($body['content'])     ? (string)$body['content']           : '';
      if ($title === '') nerr('제목은 필수입니다');
      if ($publishDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishDate)) nerr('게시일 형식 오류(YYYY-MM-DD)');
      $allowedCat = ['일반','공지','긴급','시스템'];
      $allowedSt  = ['게시','임시저장','숨김'];
      if (!in_array($category, $allowedCat, true)) nerr('허용되지 않은 카테고리: ' . $category);
      if (!in_array($status, $allowedSt, true))    nerr('허용되지 않은 상태: ' . $status);
    }

    if ($method === 'POST') {
      if (!can('notification', 'write')) nerr('쓰기 권한이 없습니다', 403);
      $st = $pdo->prepare("INSERT INTO notification (title, category, is_important, publish_date, status, content, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?)");
      $st->execute([$title, $category, $isImportant, $publishDate, $status, $content, ($me?$me['name']:null), ($me?$me['name']:null)]);
      $newId = $pdo->lastInsertId();
      $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$newId]);
      $row = $g->fetch(); $row['isImportant'] = (int)$row['isImportant'];
      nj($row, 201);
    }

    if ($method === 'PUT') {
      if (!can('notification', 'update')) nerr('수정 권한이 없습니다', 403);
      if ($id <= 0) nerr('id 필요');
      // 변경이력: 수정 전 표시값 확보 → 수정 후 본문과 필드별 비교
      $oldSt = $pdo->prepare("$SEL WHERE id = ?"); $oldSt->execute([$id]);
      $old = $oldSt->fetch();
      if (!$old) nerr('없는 공지', 404);
      $labels = notif_field_labels();
      $oldVals = [
        'title'       => (string)$old['title'],
        'category'    => (string)$old['category'],
        'isImportant' => ((int)$old['isImportant']) ? '예' : '아니오',
        'publishDate' => (string)$old['publishDate'],
        'status'      => (string)$old['status'],
        'content'     => (string)($old['content'] ?? ''),
      ];
      $newVals = [
        'title'       => $title,
        'category'    => $category,
        'isImportant' => $isImportant ? '예' : '아니오',
        'publishDate' => $publishDate,
        'status'      => $status,
        'content'     => $content,
      ];
      $pdo->beginTransaction();
      try {
        $st = $pdo->prepare("UPDATE notification SET title=?, category=?, is_important=?, publish_date=?, status=?, content=?, updated_at=CURRENT_TIMESTAMP, updated_by=? WHERE id=?");
        $st->execute([$title, $category, $isImportant, $publishDate, $status, $content, ($me?$me['name']:null), $id]);
        // 바뀐 필드마다 한 행씩 변경이력 기록(변경전/후=표시값, 변경자=현재 로그인)
        $hist = $pdo->prepare("INSERT INTO notif_history (notif_id, field_key, field_label, before_val, after_val, changed_by) VALUES (?,?,?,?,?,?)");
        foreach ($oldVals as $key => $bef) {
          $aft = $newVals[$key];
          if ($bef === $aft) continue;
          $hist->execute([$id, $key, ($labels[$key] ?? $key), $bef, $aft, ($me?$me['name']:null)]);
        }
        $pdo->commit();
      } catch (Throwable $e) { $pdo->rollBack(); nerr('수정 저장 실패: '.$e->getMessage(), 500); }
      $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$id]);
      $row = $g->fetch(); $row['isImportant'] = (int)$row['isImportant'];
      nj($row);
    }

    if ($method === 'DELETE') {
      if (!can('notification', 'delete')) nerr('삭제 권한이 없습니다', 403);
      if ($id <= 0) nerr('id 필요');
      $st = $pdo->prepare("DELETE FROM notification WHERE id = ?"); $st->execute([$id]);
      if ($st->rowCount() === 0) nerr('없는 공지', 404);
      nj(['ok' => true]);
    }
  }

  /* ---- 공지 댓글/대댓글 (requirements/todo의 ?api=comments와 동일 구조, FK=notification) ---- */
  if ($api === 'comments') {
    if (!can('notification','read')) nerr('댓글 권한이 없습니다', 403);
    $me = auth_user();
    $SELC = "SELECT id, notif_id AS notifId, parent_id AS parentId, author_id AS authorId, author_name AS authorName, body, is_deleted AS isDeleted, created_at AS createdAt, updated_at AS updatedAt FROM notif_comment";

    if ($method === 'GET') {
      $nid = isset($_GET['notifId']) ? (int)$_GET['notifId'] : 0;
      if ($nid <= 0) nerr('notifId 필요');
      $st = $pdo->prepare("$SELC WHERE notif_id = ? ORDER BY created_at, id"); $st->execute([$nid]);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) { $r['isDeleted'] = (int)$r['isDeleted']; if ($r['isDeleted']) $r['body'] = ''; }   // 삭제 댓글은 본문 비움
      unset($r);
      nj($rows);
    }

    $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];

    if ($method === 'POST') {
      $nid = isset($body['notifId']) ? (int)$body['notifId'] : 0;
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      $parentId = (isset($body['parentId']) && $body['parentId'] !== '' && $body['parentId'] !== null) ? (int)$body['parentId'] : null;
      if ($nid <= 0) nerr('notifId 필요');
      if ($text === '') nerr('댓글 내용을 입력하세요');
      $chk = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id = ?"); $chk->execute([$nid]);
      if (!(int)$chk->fetchColumn()) nerr('없는 공지', 404);
      if ($parentId !== null) {
        $pc = $pdo->prepare("SELECT notif_id FROM notif_comment WHERE id = ?"); $pc->execute([$parentId]);
        $prc = $pc->fetchColumn();
        if ($prc === false) nerr('없는 상위 댓글', 404);
        if ((int)$prc !== $nid) nerr('상위 댓글이 다른 공지입니다');
      }
      $ins = $pdo->prepare("INSERT INTO notif_comment (notif_id, parent_id, author_id, author_name, body) VALUES (?,?,?,?,?)");
      $ins->execute([$nid, $parentId, ($me ? (int)$me['id'] : null), ($me ? $me['name'] : null), $text]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$pdo->lastInsertId()]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      nj($row, 201);
    }

    // PUT/DELETE: 작성 본인만 (관리자도 타인 댓글 불가)
    $cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($cid <= 0) nerr('id 필요');
    $own = $pdo->prepare("SELECT author_id, is_deleted FROM notif_comment WHERE id = ?"); $own->execute([$cid]);
    $oc = $own->fetch();
    if (!$oc) nerr('없는 댓글', 404);
    $isMine = $me && $oc['author_id'] !== null && (int)$oc['author_id'] === (int)$me['id'];
    if (!$isMine) nerr('본인이 작성한 댓글만 수정/삭제할 수 있습니다', 403);

    if ($method === 'PUT') {
      if ((int)$oc['is_deleted'] === 1) nerr('삭제된 댓글은 수정할 수 없습니다');
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      if ($text === '') nerr('댓글 내용을 입력하세요');
      $pdo->prepare("UPDATE notif_comment SET body = ? WHERE id = ?")->execute([$text, $cid]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$cid]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      nj($row);
    }
    if ($method === 'DELETE') {
      $pdo->prepare("UPDATE notif_comment SET is_deleted = 1 WHERE id = ?")->execute([$cid]);   // 소프트 삭제(답글 트리 보존)
      nj(['ok' => true]);
    }
    nerr('comments: 허용되지 않은 메서드', 405);
  }

  /* ---- 공지 변경이력 ---- */
  if ($api === 'history') {
    if ($method !== 'GET') nerr('GET 필요', 405);
    if (!can('notification','read')) nerr('읽기 권한이 없습니다', 403);
    $nid = isset($_GET['notifId']) ? (int)$_GET['notifId'] : 0;
    if ($nid <= 0) nerr('notifId 필요');
    $st = $pdo->prepare("SELECT id, field_key AS fieldKey, field_label AS fieldLabel, before_val AS beforeVal, after_val AS afterVal, changed_at AS changedAt, changed_by AS changedBy FROM notif_history WHERE notif_id = ? ORDER BY id DESC");
    $st->execute([$nid]);
    nj($st->fetchAll());
  }

  nerr('unknown api: ' . $api, 404);
}

/* 변경이력 표시용 필드 라벨 (form.php 라벨과 동일, 기록 시점에 비정규화 저장) */
function notif_field_labels() {
  return [
    'title'=>'제목', 'category'=>'카테고리', 'isImportant'=>'중요 공지',
    'publishDate'=>'게시일', 'status'=>'상태', 'content'=>'내용',
  ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>공지사항</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  /* 공지 목록 카드 */
  .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:16px;}
  .list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  #addBtn{background:#FB64C9;color:#fff;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  #addBtn:hover{background:#e84fb6;}
  /* 표 */
  /* member.php와 동일한 리스트 외형(sch-look) + 항목 center 정렬 */
  #notifTbl th,#notifTbl td{text-align:center;vertical-align:middle;}
  td.title{font-weight:700;color:var(--ink);}
  .tag.cat-일반{background:#eef1f5;color:#5b6472;}
  .tag.cat-공지{background:#cffafe;color:#155e75;}
  .tag.cat-긴급{background:#fee2e2;color:#b91c1c;}
  .tag.cat-시스템{background:#e0e7ff;color:#3730a3;}
  .tag.st-게시{background:#d1fae5;color:#065f46;}
  .tag.st-임시저장{background:#fef3c7;color:#92400e;}
  .tag.st-숨김{background:#e5e7eb;color:#4b5563;}
  .pin{color:#ef4444;font-size:14px;margin-right:4px;}
  .muted{color:#9aa3b2;}
  /* 검색 카드 — 헤더 + 우측 명시 토글 버튼 */
  .sp-card .card-body{padding:24px 28px;}
  .sp-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line);}
  .sp-head .sp-title{margin:0;font-size:15px;font-weight:700;color:var(--ink);padding-bottom:0;border-bottom:none;}
  .sp-toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);font-family:inherit;transition:.15s;}
  .sp-toggle-btn:hover{background:var(--brand-soft);color:var(--brand-d);border-color:var(--brand);}
  .sp-toggle-btn .sp-chev{font-size:11px;color:var(--brand);transition:transform .15s;}
  .search-panel{padding-top:18px;}
  .sp-card.collapsed .sp-head{padding-bottom:0;border-bottom:none;}
  .sp-card.collapsed .sp-toggle-btn .sp-chev{transform:rotate(-90deg);}
  .sp-card.collapsed .search-panel{display:none;}
  /* === notification 검색영역: 모든 컨트롤 1행 가로 배치(라벨 숨겨 공간 절약) === */
  .search-panel{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;padding-top:18px;}
  .search-panel .sp-grid{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;margin:0;}
  .search-panel .sp-grid > div{display:flex;align-items:center;gap:6px;}
  .search-panel .sp-grid > div label,
  .search-panel .sp-date .sp-date-lbl,
  .search-panel .sp-text  .sp-text-lbl{
    display:inline-block;margin:0;padding:0;font-size:12px;font-weight:700;
    color:var(--sub);white-space:nowrap;letter-spacing:-0.2px;
  }
  /* 라벨과 컨트롤 사이 살짝 간격 */
  .search-panel .sp-date .sp-date-lbl{margin-right:2px;}
  .search-panel .sp-text  .sp-text-lbl{margin-right:2px;}
  .search-panel .sp-date,.search-panel .sp-text{display:flex;align-items:center;gap:6px;flex-wrap:nowrap;margin:0;}
  .search-panel .sp-date .form-select,.search-panel .sp-date .form-control,
  .search-panel .sp-text  .form-select,.search-panel .sp-text  .form-control{max-width:none;width:auto;}
  .search-panel .sp-date .sp-tilde{padding:0 2px;color:var(--sub);font-weight:700;}
  .search-panel .sp-text #q{width:180px;min-width:140px;flex:none;}
  .search-panel .sp-actions{display:flex;align-items:center;gap:6px;border:none;padding:0;margin:0;}
  .sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px 12px;margin-bottom:14px;}
  .sp-grid label{display:block;font-size:11.5px;font-weight:600;color:var(--sub);margin-bottom:4px;}
  .sp-text{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-text .sp-text-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-text #fTextField{max-width:150px;}
  .sp-text #q{flex:1;min-width:220px;max-width:440px;}
  .sp-date{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-date .sp-date-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-date .form-select{max-width:190px;} .sp-date .form-control{max-width:165px;}
  .sp-date .sp-tilde{color:var(--sub);font-weight:700;}
  .sp-actions{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:14px;}
  .sp-act-center{display:flex;align-items:center;gap:8px;}
  /* 페이지네이션 — service_todolist와 동일 패턴 */
  .pager{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:18px;flex-wrap:wrap;}
  .pg-btn{min-width:34px;height:34px;padding:0 10px;border:1px solid var(--line);background:#fff;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#6c7293;font-family:inherit;}
  .pg-btn:hover:not(:disabled){background:#f7f6fb;color:var(--brand-d);border-color:#e6def5;}
  .pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand);}
  .pg-btn:disabled{opacity:.45;cursor:default;}
  .pg-ell{color:var(--sub);padding:0 2px;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📢 공지사항</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('notification'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="notification">공지사항</h1>
        <div class="pg-sub">팀 공지·안내 사항</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
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
          <div class="sp-grid">
            <div><label>카테고리</label><select id="fCategory" class="form-select form-select-sm"><option value="">전체</option><option value="일반">일반</option><option value="공지">공지</option><option value="긴급">긴급</option><option value="시스템">시스템</option></select></div>
            <div><label>상태</label><select id="fStatus" class="form-select form-select-sm"><option value="">전체</option><option value="게시">게시</option><option value="임시저장">임시저장</option><option value="숨김">숨김</option></select></div>
          </div>
          <div class="sp-date">
            <label class="sp-date-lbl">기간검색</label>
            <select id="fDateField" class="form-select form-select-sm">
              <option value="">기준 일자 선택</option>
              <option value="publishDate">게시일</option>
              <option value="createdAt">작성일시</option>
              <option value="updatedAt">최종수정일시</option>
            </select>
            <input type="date" id="fDateFrom" class="form-control form-control-sm">
            <span class="sp-tilde">~</span>
            <input type="date" id="fDateTo" class="form-control form-control-sm">
          </div>
          <div class="sp-text">
            <label class="sp-text-lbl">통합검색</label>
            <select id="fTextField" class="form-select form-select-sm">
              <option value="title">제목</option>
              <option value="content">내용</option>
              <option value="both">제목+내용</option>
            </select>
            <input id="q" class="form-control form-control-sm" placeholder="검색어를 입력하세요">
          </div>
          <div class="sp-actions">
            <div class="sp-act-center">
              <button type="button" class="btn primary sm" id="btnSearch">🔍 검색</button>
              <button type="button" class="btn ghost sm" id="btnReset">↺ 초기화</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="list-head">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="card-title" style="margin:0;">공지 목록</h2>
            <span class="count-chip" id="notifCnt">검색 수 0 / 총 수 0</span>
          </div>
          <div class="list-actions">
            <select id="pageSize" class="form-select form-select-sm" style="width:auto" title="페이지당 표시 개수">
              <option value="10">10개씩</option>
              <option value="20">20개씩</option>
              <option value="30">30개씩</option>
              <option value="50">50개씩</option>
              <option value="100">100개씩</option>
            </select>
            <?php if ($PERM['admin']): ?><a class="btn sm" id="addBtn" href="form.php">＋ 공지 등록</a><?php endif; ?>
          </div>
        </div>
        <div class="table-scroll">
          <table id="notifTbl" class="sch-look">
            <thead><tr>
              <th style="width:60px;">번호</th>
              <th style="width:80px;">카테고리</th>
              <th>제목</th>
              <th style="width:70px;">상태</th>
              <th style="width:110px;">게시일</th>
              <th style="width:90px;">작성자</th>
              <th style="width:160px;">작성일시</th>
              <th style="width:160px;">최종수정일시</th>
              <th style="width:100px;">최종수정자</th>
              <th style="width:70px;">💬 댓글</th>
              <th style="width:80px;">조회수</th>
              <?php if ($PERM['admin']): ?><th style="width:130px;">관리</th><?php endif; ?>
            </tr></thead>
            <tbody id="notifBody"></tbody>
          </table>
        </div>
        <div class="empty hidden" id="notifEmpty">등록된 공지가 없습니다.</div>
        <div class="pager" id="pager"></div>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const PERM = <?php echo json_encode($PERM, JSON_UNESCAPED_UNICODE); ?>;
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function fmtDt(s){ return (s||'').replace('T',' ').slice(0,19); }

/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

/* 적용된 검색조건(검색 버튼/Enter로 통합검색어 반영, selectbox·기간은 즉시 반영) */
const SF_INIT = { category:'', status:'', textField:'title', q:'', dateField:'', dateFrom:'', dateTo:'' };
let SF = {...SF_INIT};
let ALL_ROWS = [];
let pageSize = 10, curPage = 1;   // 기본 10행, 페이지당 개수는 selectbox로 변경(10/20/30/50/100)

async function load(){
  try{
    const r=await fetch('?api=notifications',{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    ALL_ROWS = await r.json();
    render();
  }catch(e){ const cs=PERM.admin?12:11; document.getElementById('notifBody').innerHTML='<tr><td colspan="'+cs+'" class="empty">불러오지 못했습니다: '+esc(e.message)+'</td></tr>'; }
}
function filtered(){
  return ALL_ROWS.filter(r=>{
    if(SF.category && (r.category||'')!==SF.category) return false;
    if(SF.status   && (r.status||'')!==SF.status)     return false;
    if(SF.q){   // 통합검색: 제목 / 내용 / 제목+내용
      const ql=SF.q.toLowerCase();
      let hay;
      if(SF.textField==='title')   hay=(r.title||'').toLowerCase();
      else if(SF.textField==='content') hay=(r.content||'').toLowerCase();
      else                         hay=((r.title||'')+' '+(r.content||'')).toLowerCase();   // both
      if(!hay.includes(ql)) return false;
    }
    if(SF.dateField && (SF.dateFrom||SF.dateTo)){
      const v=String(r[SF.dateField]||'').slice(0,10);
      if(!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
      if(SF.dateFrom && v<SF.dateFrom) return false;
      if(SF.dateTo   && v>SF.dateTo)   return false;
    }
    return true;
  });
}
function render(){
  const rows = filtered();
  const total = rows.length;
  document.getElementById('notifCnt').textContent = `검색 수 ${total} / 총 수 ${ALL_ROWS.length}`;
  const body=document.getElementById('notifBody'); const empty=document.getElementById('notifEmpty');
  if(total===0){ body.innerHTML=''; empty.classList.remove('hidden'); renderPager(0,1); return; }
  empty.classList.add('hidden');
  // 페이지네이션: 현재 페이지 슬라이스
  const pages = Math.max(1, Math.ceil(total/pageSize));
  if(curPage>pages) curPage=pages; if(curPage<1) curPage=1;
  const start = (curPage-1)*pageSize;
  const pageRows = rows.slice(start, start+pageSize);
  body.innerHTML = pageRows.map((r,i)=>{
    // 관리 컬럼은 관리자(is_admin)에게만 노출 — th도 PHP가 admin일 때만 렌더하므로 td도 함께 가드
    const mgmtTd = PERM.admin ? `<td><div class="row-actions"><a class="btn ghost sm" href="form.php?id=${r.id}">수정</a><button class="btn danger sm" onclick="delItem(${r.id})">삭제</button></div></td>` : '';
    return `<tr>
      <td>${total - (start + i)}</td>
      <td><span class="tag cat-${esc(r.category)}">${esc(r.category)}</span></td>
      <td class="title">${r.isImportant?'<span class="pin">★</span>':''}<a href="form.php?id=${r.id}" style="color:inherit;text-decoration:none;">${esc(r.title)}</a></td>
      <td><span class="tag st-${esc(r.status)}">${esc(r.status)}</span></td>
      <td>${esc(r.publishDate)||'<span class="muted">-</span>'}</td>
      <td>${esc(r.createdBy)||'<span class="muted">-</span>'}</td>
      <td>${esc(fmtDt(r.createdAt))}</td>
      <td>${esc(fmtDt(r.updatedAt))}</td>
      <td>${esc(r.updatedBy)||'<span class="muted">-</span>'}</td>
      <td>${parseInt(r.commentCount,10)||0}</td>
      <td>${parseInt(r.viewCount,10)||0}</td>
      ${mgmtTd}
    </tr>`;
  }).join('');
  renderPager(total, pages);
}
function renderPager(total, pages){
  const el = document.getElementById('pager'); if(!el) return;
  if(total===0 || pages<=1){ el.innerHTML=''; return; }
  const cur = curPage;
  const btn = (p, label, opts={}) => `<button class="pg-btn${opts.active?' active':''}" ${opts.disabled?'disabled':''} onclick="gotoPage(${p})">${label||p}</button>`;
  let h = btn(cur-1,'‹',{disabled:cur<=1});
  // 페이지 번호: 최대 7개 윈도
  const win=7; let from=Math.max(1, cur-3), to=Math.min(pages, from+win-1); from=Math.max(1, to-win+1);
  if(from>1){ h+=btn(1); if(from>2) h+='<span class="pg-ell">…</span>'; }
  for(let p=from; p<=to; p++) h+=btn(p,null,{active:p===cur});
  if(to<pages){ if(to<pages-1) h+='<span class="pg-ell">…</span>'; h+=btn(pages); }
  h += btn(cur+1,'›',{disabled:cur>=pages});
  el.innerHTML = h;
}
function gotoPage(p){ curPage=p; render(); }
/* 검색: selectbox·기간은 즉시 적용, 통합검색은 검색 버튼/Enter에서 검증(빈 입력 시 팝업) */
function applySearch(){
  SF = {
    category: document.getElementById('fCategory').value,
    status:   document.getElementById('fStatus').value,
    textField:document.getElementById('fTextField').value,
    q:        (document.getElementById('q').value||'').trim(),
    dateField:document.getElementById('fDateField').value,
    dateFrom: document.getElementById('fDateFrom').value,
    dateTo:   document.getElementById('fDateTo').value,
  };
  curPage=1;   // 검색조건 바뀌면 1페이지부터
  render();
}
function doSearch(){   // 검색 버튼/Enter: 통합검색어 미입력 시 안내 팝업
  const q=document.getElementById('q'); if(!(q.value||'').trim()){ alert('검색어를 입력해주세요.'); q.focus(); return; }
  applySearch();
}
function resetSearch(){
  ['fCategory','fStatus','fDateField','fDateFrom','fDateTo','q'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
  document.getElementById('fTextField').value='title';   // 기본 = 제목
  SF = {...SF_INIT};
  curPage=1;
  render();
}
/* 검색영역 collapse — localStorage('notifSp_collapsed') 영속 + 버튼 텍스트 토글 */
function toggleSearchPanel(){
  const c=document.getElementById('spCard'); if(!c) return;
  c.classList.toggle('collapsed');
  const cl=c.classList.contains('collapsed');
  try{ localStorage.setItem('notifSp_collapsed', cl?'1':'0'); }catch(e){}
  document.getElementById('spToggle')?.setAttribute('aria-expanded', cl?'false':'true');
  const txt=document.querySelector('#spToggle .sp-toggle-text'); if(txt) txt.textContent = cl ? '펼치기' : '접기';
}
async function delItem(id){
  if(!confirm('이 공지를 삭제하시겠습니까?')) return;
  try{
    const r=await fetch('?api=notifications&id='+id,{method:'DELETE'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast('삭제되었습니다'); load();
  }catch(e){ alert('삭제 실패: '+e.message); }
}

/* 이벤트 바인딩: selectbox·기간 즉시 검색 / 검색 버튼·초기화 / Enter / 페이지당 개수 */
['fCategory','fStatus','fTextField','fDateField','fDateFrom','fDateTo'].forEach(id=>{
  const e=document.getElementById(id); if(e) e.addEventListener('change', applySearch);
});
document.getElementById('btnSearch').addEventListener('click', doSearch);
document.getElementById('btnReset').addEventListener('click', resetSearch);
document.getElementById('q').addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });
document.getElementById('pageSize').addEventListener('change', e=>{ pageSize=parseInt(e.target.value,10)||10; curPage=1; render(); });

(async function boot(){
  await load();
  if(localStorage.getItem('notifSp_collapsed')==='1'){ document.getElementById('spCard')?.classList.add('collapsed'); document.getElementById('spToggle')?.setAttribute('aria-expanded','false'); const _t=document.querySelector('#spToggle .sp-toggle-text'); if(_t) _t.textContent='펼치기'; }
  const tmsg=sessionStorage.getItem('notifToast'); if(tmsg){ sessionStorage.removeItem('notifToast'); setTimeout(()=>toast(tmsg),150); }
})();
</script>
</body>
</html>

<?php
/* =====================================================================
   자료실 (documents) — 독립 PHP 단일 파일 (목록·검색 페이지 + ?api= JSON CRUD)
   - 권한: documents.{access/read/write/update/delete}
   - 관리 컬럼·+ 자료 등록 버튼은 관리자(is_admin)만 노출
   - 등록/수정은 별도 페이지 form.php
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

if (isset($_GET['api'])) { documents_api($DB); exit; }
require_perm('documents', 'access');
$PERM = perm_map('documents');

function dj($d, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function derr($m, $code = 400) { dj(['error' => $m], $code); }

function documents_api($DB) {
  $api = $_GET['api']; $method = $_SERVER['REQUEST_METHOD'];
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { derr('DB 연결 실패: ' . $e->getMessage(), 500); }

  if ($api === 'health') dj(['ok'=>true]);

  if ($api === 'documents') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $SEL = "SELECT id, title, link, content, view_count AS viewCount, created_at AS createdAt, updated_at AS updatedAt, created_by AS createdBy, updated_by AS updatedBy FROM document";

    if ($method === 'GET') {
      if (!can('documents', 'read')) derr('읽기 권한이 없습니다', 403);
      if ($id > 0) {
        $pdo->prepare("UPDATE document SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);   // 상세 열람 시 조회수 +1
        $st = $pdo->prepare("$SEL WHERE id = ?"); $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) derr('없는 자료', 404);
        dj($row);
      }
      $where = []; $params = [];
      if (!empty($_GET['q'])) {
        $field = $_GET['field'] ?? '';
        if ($field && in_array($field, ['title','content','link','createdBy','updatedBy'], true)) {
          $where[] = "$field LIKE ?"; $params[] = '%' . $_GET['q'] . '%';
        } else {
          $where[] = "(title LIKE ? OR content LIKE ? OR link LIKE ? OR created_by LIKE ? OR updated_by LIKE ?)";
          $l = '%' . $_GET['q'] . '%'; array_push($params, $l, $l, $l, $l, $l);
        }
      }
      // 목록 행마다 댓글 수(미삭제) 부착 → 프런트 '💬 댓글' 컬럼·정렬 기준
      $sqlWithCnt = "SELECT document.*, (SELECT COUNT(*) FROM doc_comment WHERE doc_id = document.id AND is_deleted = 0) AS commentCount FROM document" . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY id DESC";
      // 컬럼명 alias 매핑(view_count→viewCount 등)
      $st = $pdo->prepare($sqlWithCnt); $st->execute($params);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) {
        $r['viewCount'] = (int)$r['view_count']; unset($r['view_count']);
        $r['createdAt'] = $r['created_at']; unset($r['created_at']);
        $r['updatedAt'] = $r['updated_at']; unset($r['updated_at']);
        $r['createdBy'] = $r['created_by']; unset($r['created_by']);
        $r['updatedBy'] = $r['updated_by']; unset($r['updated_by']);
        $r['commentCount'] = (int)$r['commentCount'];
      }
      unset($r);
      dj($rows);
    }

    $me = auth_user();
    $title=''; $link=''; $content='';
    if ($method === 'POST' || $method === 'PUT') {
      $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
      $title   = isset($body['title'])   ? trim((string)$body['title'])   : '';
      $link    = isset($body['link'])    ? trim((string)$body['link'])    : '';
      $content = isset($body['content']) ? (string)$body['content']       : '';
      if ($title === '') derr('제목은 필수입니다');
      if ($link  === '') derr('링크는 필수입니다');
    }

    if ($method === 'POST') {
      if (!can('documents','write')) derr('쓰기 권한이 없습니다', 403);
      $st = $pdo->prepare("INSERT INTO document (title, link, content, created_by, updated_by) VALUES (?,?,?,?,?)");
      $st->execute([$title, $link, $content, ($me?$me['name']:null), ($me?$me['name']:null)]);
      $newId = $pdo->lastInsertId();
      $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$newId]);
      dj($g->fetch(), 201);
    }

    if ($method === 'PUT') {
      if (!can('documents','update')) derr('수정 권한이 없습니다', 403);
      if ($id <= 0) derr('id 필요');
      // 변경이력: 수정 전 표시값 확보 → 수정 후 본문과 필드별 비교
      $oldSt = $pdo->prepare("$SEL WHERE id = ?"); $oldSt->execute([$id]);
      $old = $oldSt->fetch();
      if (!$old) derr('없는 자료', 404);
      $labels = doc_field_labels();
      $oldVals = [
        'title'   => (string)$old['title'],
        'link'    => (string)$old['link'],
        'content' => (string)($old['content'] ?? ''),
      ];
      $newVals = ['title'=>$title, 'link'=>$link, 'content'=>$content];
      $pdo->beginTransaction();
      try {
        $st = $pdo->prepare("UPDATE document SET title=?, link=?, content=?, updated_at=CURRENT_TIMESTAMP, updated_by=? WHERE id=?");
        $st->execute([$title, $link, $content, ($me?$me['name']:null), $id]);
        $hist = $pdo->prepare("INSERT INTO doc_history (doc_id, field_key, field_label, before_val, after_val, changed_by) VALUES (?,?,?,?,?,?)");
        foreach ($oldVals as $key => $bef) {
          $aft = $newVals[$key];
          if ($bef === $aft) continue;
          $hist->execute([$id, $key, ($labels[$key] ?? $key), $bef, $aft, ($me?$me['name']:null)]);
        }
        $pdo->commit();
      } catch (Throwable $e) { $pdo->rollBack(); derr('수정 저장 실패: '.$e->getMessage(), 500); }
      $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$id]);
      dj($g->fetch());
    }

    if ($method === 'DELETE') {
      if (!can('documents','delete')) derr('삭제 권한이 없습니다', 403);
      if ($id <= 0) derr('id 필요');
      $st = $pdo->prepare("DELETE FROM document WHERE id = ?"); $st->execute([$id]);
      if ($st->rowCount() === 0) derr('없는 자료', 404);
      dj(['ok'=>true]);
    }
  }

  /* ---- 자료실 댓글/대댓글 ---- */
  if ($api === 'comments') {
    if (!can('documents','read')) derr('댓글 권한이 없습니다', 403);
    $me = auth_user();
    $SELC = "SELECT id, doc_id AS docId, parent_id AS parentId, author_id AS authorId, author_name AS authorName, body, is_deleted AS isDeleted, created_at AS createdAt, updated_at AS updatedAt FROM doc_comment";
    if ($method === 'GET') {
      $did = isset($_GET['docId']) ? (int)$_GET['docId'] : 0;
      if ($did <= 0) derr('docId 필요');
      $st = $pdo->prepare("$SELC WHERE doc_id = ? ORDER BY created_at, id"); $st->execute([$did]);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) { $r['isDeleted'] = (int)$r['isDeleted']; if ($r['isDeleted']) $r['body'] = ''; }
      unset($r);
      dj($rows);
    }
    $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
    if ($method === 'POST') {
      $did = isset($body['docId']) ? (int)$body['docId'] : 0;
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      $parentId = (isset($body['parentId']) && $body['parentId'] !== '' && $body['parentId'] !== null) ? (int)$body['parentId'] : null;
      if ($did <= 0) derr('docId 필요');
      if ($text === '') derr('댓글 내용을 입력하세요');
      $chk = $pdo->prepare("SELECT COUNT(*) FROM document WHERE id = ?"); $chk->execute([$did]);
      if (!(int)$chk->fetchColumn()) derr('없는 자료', 404);
      if ($parentId !== null) {
        $pc = $pdo->prepare("SELECT doc_id FROM doc_comment WHERE id = ?"); $pc->execute([$parentId]);
        $prc = $pc->fetchColumn();
        if ($prc === false) derr('없는 상위 댓글', 404);
        if ((int)$prc !== $did) derr('상위 댓글이 다른 자료입니다');
      }
      $ins = $pdo->prepare("INSERT INTO doc_comment (doc_id, parent_id, author_id, author_name, body) VALUES (?,?,?,?,?)");
      $ins->execute([$did, $parentId, ($me ? (int)$me['id'] : null), ($me ? $me['name'] : null), $text]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$pdo->lastInsertId()]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      dj($row, 201);
    }
    // PUT/DELETE: 작성 본인만
    $cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($cid <= 0) derr('id 필요');
    $own = $pdo->prepare("SELECT author_id, is_deleted FROM doc_comment WHERE id = ?"); $own->execute([$cid]);
    $oc = $own->fetch();
    if (!$oc) derr('없는 댓글', 404);
    $isMine = $me && $oc['author_id'] !== null && (int)$oc['author_id'] === (int)$me['id'];
    if (!$isMine) derr('본인이 작성한 댓글만 수정/삭제할 수 있습니다', 403);
    if ($method === 'PUT') {
      if ((int)$oc['is_deleted'] === 1) derr('삭제된 댓글은 수정할 수 없습니다');
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      if ($text === '') derr('댓글 내용을 입력하세요');
      $pdo->prepare("UPDATE doc_comment SET body = ? WHERE id = ?")->execute([$text, $cid]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$cid]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      dj($row);
    }
    if ($method === 'DELETE') {
      $pdo->prepare("UPDATE doc_comment SET is_deleted = 1 WHERE id = ?")->execute([$cid]);
      dj(['ok'=>true]);
    }
    derr('comments: 허용되지 않은 메서드', 405);
  }

  /* ---- 자료실 변경이력 ---- */
  if ($api === 'history') {
    if ($method !== 'GET') derr('GET 필요', 405);
    if (!can('documents','read')) derr('읽기 권한이 없습니다', 403);
    $did = isset($_GET['docId']) ? (int)$_GET['docId'] : 0;
    if ($did <= 0) derr('docId 필요');
    $st = $pdo->prepare("SELECT id, field_key AS fieldKey, field_label AS fieldLabel, before_val AS beforeVal, after_val AS afterVal, changed_at AS changedAt, changed_by AS changedBy FROM doc_history WHERE doc_id = ? ORDER BY id DESC");
    $st->execute([$did]);
    dj($st->fetchAll());
  }

  derr('unknown api: ' . $api, 404);
}

/* 변경이력 표시용 필드 라벨 (form.php와 동일, 기록 시점 비정규화 저장) */
function doc_field_labels() {
  return ['title'=>'제목', 'link'=>'링크', 'content'=>'내용'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>자료실</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:16px;}
  .list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  #addBtn{background:#FB64C9;color:#fff;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  #addBtn:hover{background:#e84fb6;}
  /* member.php와 동일한 리스트 외형(sch-look) + 항목 center 정렬 */
  #docTbl th,#docTbl td{text-align:center;vertical-align:middle;}
  td.title{font-weight:700;color:var(--ink);max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  td.link a{color:var(--brand-d);text-decoration:none;font-size:12.5px;}
  td.link a:hover{text-decoration:underline;}
  td.link{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .muted{color:#9aa3b2;}
  /* 검색 카드 — 헤더 + 우측 명시 토글 버튼 */
  .sp-card .card-body{padding:24px 28px;}
  .sp-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line);}
  .sp-head .sp-title{margin:0;font-size:15px;font-weight:700;color:var(--ink);}
  .sp-toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);font-family:inherit;transition:.15s;}
  .sp-toggle-btn:hover{background:var(--brand-soft);color:var(--brand-d);border-color:var(--brand);}
  .sp-toggle-btn .sp-chev{font-size:11px;color:var(--brand);transition:transform .15s;}
  .search-panel{padding-top:18px;}
  /* === documents 검색영역: 모든 컨트롤 1행 가로 배치 === */
  .search-panel{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;}
  .search-panel .sp-grid{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;margin:0;grid-template-columns:none;}
  .search-panel .sp-grid > div{display:flex;align-items:center;gap:6px;}
  .search-panel .sp-grid > div label,
  .search-panel .sp-date .sp-date-lbl,
  .search-panel .sp-text  .sp-text-lbl{
    display:inline-block;margin:0;padding:0;font-size:12px;font-weight:700;
    color:var(--sub);white-space:nowrap;letter-spacing:-0.2px;
  }
  .search-panel .sp-date .sp-date-lbl,
  .search-panel .sp-text  .sp-text-lbl{margin-right:2px;}
  .search-panel .sp-date,.search-panel .sp-text{display:flex;align-items:center;gap:6px;flex-wrap:nowrap;margin:0;}
  .search-panel .sp-date .form-select,.search-panel .sp-date .form-control,
  .search-panel .sp-text  .form-select,.search-panel .sp-text  .form-control{max-width:none;width:auto;}
  .search-panel .sp-date .sp-tilde{padding:0 2px;color:var(--sub);font-weight:700;}
  .search-panel .sp-text #q{width:180px;min-width:140px;max-width:none;flex:none;}
  .search-panel .sp-actions{display:flex;align-items:center;gap:6px;border:none;padding:0;margin:0;}
  .sp-card.collapsed .sp-head{padding-bottom:0;border-bottom:none;}
  .sp-card.collapsed .sp-toggle-btn .sp-chev{transform:rotate(-90deg);}
  .sp-card.collapsed .search-panel{display:none;}
  .sp-text{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-text .sp-text-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-text #fTextField{max-width:150px;}
  .sp-text #q{flex:1;min-width:220px;max-width:440px;}
  .sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px 12px;margin-bottom:14px;}
  .sp-grid label{display:block;font-size:11.5px;font-weight:600;color:var(--sub);margin-bottom:4px;}
  .sp-date{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-date .sp-date-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-date .form-select{max-width:190px;} .sp-date .form-control{max-width:165px;}
  .sp-date .sp-tilde{color:var(--sub);font-weight:700;}
  .sp-actions{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:14px;}
  .sp-act-center{display:flex;align-items:center;gap:8px;}
  /* 페이지네이션 */
  .pager{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:18px;flex-wrap:wrap;}
  .pg-btn{min-width:34px;height:34px;padding:0 10px;border:1px solid var(--line);background:#fff;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#6c7293;font-family:inherit;}
  .pg-btn:hover:not(:disabled){background:#f7f6fb;color:var(--brand-d);border-color:#e6def5;}
  .pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand);}
  .pg-btn:disabled{opacity:.45;cursor:default;}
  .pg-ell{color:var(--sub);padding:0 2px;}
  /* 💬 댓글 수 칩(목록 컬럼) */
  .cmt-cnt{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;background:var(--brand-soft);color:var(--brand-d);font-weight:700;font-size:12px;white-space:nowrap;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📚 자료실</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('documents'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="documents">자료실</h1>
        <div class="pg-sub">참고 자료·문서 링크 공유</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <!-- 검색 카드 -->
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
            <div><label>💬 댓글 정렬</label><select id="fCmtSort" class="form-select form-select-sm"><option value="">기본</option><option value="desc">댓글 많은 순</option><option value="asc">댓글 적은 순</option></select></div>
          </div>
          <div class="sp-date">
            <label class="sp-date-lbl">기간검색</label>
            <select id="fDateField" class="form-select form-select-sm">
              <option value="">기준 일자 선택</option>
              <option value="createdAt">최초작성일시</option>
              <option value="updatedAt">최종수정일시</option>
            </select>
            <input type="date" id="fDateFrom" class="form-control form-control-sm">
            <span class="sp-tilde">~</span>
            <input type="date" id="fDateTo" class="form-control form-control-sm">
          </div>
          <div class="sp-text">
            <label class="sp-text-lbl">통합검색</label>
            <select id="fTextField" class="form-select form-select-sm">
              <option value="">전체</option>
              <option value="title">제목</option>
              <option value="createdBy">최초작성자</option>
              <option value="updatedBy">최종수정자</option>
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

    <!-- 리스트 카드 -->
    <div class="card">
      <div class="card-body">
        <div class="list-head">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="card-title" style="margin:0;">자료 목록</h2>
            <span class="count-chip" id="docCnt">검색 수 0 / 총 수 0</span>
          </div>
          <div class="list-actions">
            <select id="pageSize" class="form-select form-select-sm" style="width:auto" title="페이지당 표시 개수">
              <option value="10">10개씩</option>
              <option value="20">20개씩</option>
              <option value="30">30개씩</option>
              <option value="50">50개씩</option>
              <option value="100">100개씩</option>
            </select>
            <?php if ($PERM['admin']): ?><a class="btn sm" id="addBtn" href="form.php">＋ 자료 등록</a><?php endif; ?>
          </div>
        </div>
        <div class="table-scroll">
          <table id="docTbl" class="sch-look">
            <thead><tr>
              <th style="width:60px;">번호</th>
              <th>제목</th>
              <th style="width:200px;">링크</th>
              <th style="width:160px;">최초작성일시</th>
              <th style="width:160px;">최종작성일시</th>
              <th style="width:100px;">최초작성자</th>
              <th style="width:100px;">최종수정자</th>
              <th style="width:80px;">💬 댓글</th>
              <th style="width:70px;">조회수</th>
              <?php if ($PERM['admin']): ?><th style="width:130px;">관리</th><?php endif; ?>
            </tr></thead>
            <tbody id="docBody"></tbody>
          </table>
        </div>
        <div class="empty hidden" id="docEmpty">등록된 자료가 없습니다.</div>
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

const SF_INIT = { textField:'', q:'', cmtSort:'', dateField:'', dateFrom:'', dateTo:'' };
let SF = {...SF_INIT};
let ALL_ROWS = [];
let pageSize = 10, curPage = 1;   // 기본 10행

async function load(){
  try{
    const r=await fetch('?api=documents',{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    ALL_ROWS = await r.json();
    render();
  }catch(e){ const cs=PERM.admin?10:9; document.getElementById('docBody').innerHTML='<tr><td colspan="'+cs+'" class="empty">불러오지 못했습니다: '+esc(e.message)+'</td></tr>'; }
}
function filtered(){
  let rows = ALL_ROWS.filter(r=>{
    if(SF.q){
      const ql=SF.q.toLowerCase();
      let hay;
      if(SF.textField==='title')          hay=(r.title||'').toLowerCase();
      else if(SF.textField==='createdBy') hay=(r.createdBy||'').toLowerCase();
      else if(SF.textField==='updatedBy') hay=(r.updatedBy||'').toLowerCase();
      else hay = ((r.title||'')+' '+(r.createdBy||'')+' '+(r.updatedBy||'')).toLowerCase();   // 전체: 제목·최초작성자·최종수정자
      if(!hay.includes(ql)) return false;
    }
    if(SF.dateField && (SF.dateFrom||SF.dateTo)){
      const v = String(r[SF.dateField]||'').slice(0,10);
      if(!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
      if(SF.dateFrom && v<SF.dateFrom) return false;
      if(SF.dateTo   && v>SF.dateTo)   return false;
    }
    return true;
  });
  // 댓글 정렬
  if(SF.cmtSort){
    rows = rows.slice().sort((a,b)=>{
      const av=parseInt(a.commentCount,10)||0, bv=parseInt(b.commentCount,10)||0;
      if(SF.cmtSort==='desc') return bv-av || (a.id-b.id);
      return av-bv || (a.id-b.id);
    });
  }
  return rows;
}
function render(){
  const rows = filtered();
  const total = rows.length;
  document.getElementById('docCnt').textContent = `검색 수 ${total} / 총 수 ${ALL_ROWS.length}`;
  const body=document.getElementById('docBody'); const empty=document.getElementById('docEmpty');
  if(total===0){ body.innerHTML=''; empty.classList.remove('hidden'); renderPager(0,1); return; }
  empty.classList.add('hidden');
  const pages = Math.max(1, Math.ceil(total/pageSize));
  if(curPage>pages) curPage=pages; if(curPage<1) curPage=1;
  const start = (curPage-1)*pageSize;
  const pageRows = rows.slice(start, start+pageSize);
  body.innerHTML = pageRows.map((r,i)=>{
    const mgmtTd = PERM.admin ? `<td><div class="row-actions"><a class="btn ghost sm" href="form.php?id=${r.id}">수정</a><button class="btn danger sm" onclick="delItem(${r.id})">삭제</button></div></td>` : '';
    const linkCell = r.link ? `<a href="${esc(r.link)}" target="_blank" rel="noopener" title="${esc(r.link)}">${esc(r.link)}</a>` : '<span class="muted">-</span>';
    const cn = parseInt(r.commentCount,10)||0;
    const cmtCell = cn>0 ? `<span class="cmt-cnt">💬 ${cn}</span>` : '<span class="muted">0</span>';
    return `<tr>
      <td>${total - (start + i)}</td>
      <td class="title"><a href="form.php?id=${r.id}" style="color:inherit;text-decoration:none;" title="${esc(r.title)}">${esc(r.title)}</a></td>
      <td class="link">${linkCell}</td>
      <td>${esc(fmtDt(r.createdAt))}</td>
      <td>${esc(fmtDt(r.updatedAt))}</td>
      <td>${esc(r.createdBy)||'<span class="muted">-</span>'}</td>
      <td>${esc(r.updatedBy)||'<span class="muted">-</span>'}</td>
      <td>${cmtCell}</td>
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
  const win=7; let from=Math.max(1, cur-3), to=Math.min(pages, from+win-1); from=Math.max(1, to-win+1);
  if(from>1){ h+=btn(1); if(from>2) h+='<span class="pg-ell">…</span>'; }
  for(let p=from; p<=to; p++) h+=btn(p,null,{active:p===cur});
  if(to<pages){ if(to<pages-1) h+='<span class="pg-ell">…</span>'; h+=btn(pages); }
  h += btn(cur+1,'›',{disabled:cur>=pages});
  el.innerHTML = h;
}
function gotoPage(p){ curPage=p; render(); }
async function delItem(id){
  if(!confirm('이 자료를 삭제하시겠습니까?')) return;
  try{
    const r=await fetch('?api=documents&id='+id,{method:'DELETE'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast('삭제되었습니다'); load();
  }catch(e){ alert('삭제 실패: '+e.message); }
}
function applySearch(){
  SF = {
    textField:document.getElementById('fTextField').value,
    q:        (document.getElementById('q').value||'').trim(),
    cmtSort:  document.getElementById('fCmtSort').value,
    dateField:document.getElementById('fDateField').value,
    dateFrom: document.getElementById('fDateFrom').value,
    dateTo:   document.getElementById('fDateTo').value,
  };
  curPage=1; render();
}
function doSearch(){
  const q=document.getElementById('q'); if(!(q.value||'').trim()){ alert('검색어를 입력해주세요.'); q.focus(); return; }
  applySearch();
}
function resetSearch(){   // 초기화: 페이지 최초 진입 상태로 복원
  ['fTextField','q','fCmtSort','fDateField','fDateFrom','fDateTo'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
  SF = {...SF_INIT}; curPage=1; render();
}
function toggleSearchPanel(){
  const c=document.getElementById('spCard'); if(!c) return;
  c.classList.toggle('collapsed');
  const cl=c.classList.contains('collapsed');
  try{ localStorage.setItem('docSp_collapsed', cl?'1':'0'); }catch(e){}
  document.getElementById('spToggle')?.setAttribute('aria-expanded', cl?'false':'true');
  const txt=document.querySelector('#spToggle .sp-toggle-text'); if(txt) txt.textContent = cl ? '펼치기' : '접기';
}
/* 이벤트: selectbox·기간 즉시 검색 / 검색 버튼·초기화 / Enter / 페이지당 개수 */
['fTextField','fCmtSort','fDateField','fDateFrom','fDateTo'].forEach(id=>{
  const e=document.getElementById(id); if(e) e.addEventListener('change', applySearch);
});
document.getElementById('btnSearch').addEventListener('click', doSearch);
document.getElementById('btnReset').addEventListener('click', resetSearch);
document.getElementById('q').addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });
document.getElementById('pageSize').addEventListener('change', e=>{ pageSize=parseInt(e.target.value,10)||10; curPage=1; render(); });

(async function boot(){
  await load();
  if(localStorage.getItem('docSp_collapsed')==='1'){ document.getElementById('spCard')?.classList.add('collapsed'); document.getElementById('spToggle')?.setAttribute('aria-expanded','false'); const _t=document.querySelector('#spToggle .sp-toggle-text'); if(_t) _t.textContent='펼치기'; }
  const tmsg=sessionStorage.getItem('docToast'); if(tmsg){ sessionStorage.removeItem('docToast'); setTimeout(()=>toast(tmsg),150); }
})();
</script>
</body>
</html>

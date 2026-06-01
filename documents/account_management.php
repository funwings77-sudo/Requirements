<?php
/* =====================================================================
   계정관리 (documents/account_management.php)
   - 메디힘 계정관리 대장(xlsx → account_data.php) 분류별 탭 리스트
   - 로그인 + 계정관리(account_management) 접속 권한 필요
   - 거래업체·모바일 개발자계정 탭 및 등록/수정 버튼은 관리자(member.is_admin) 전용
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
$SECTION_COLS = [];   // 분류별 컬럼(NO 제외) — 검증·정렬용
foreach (require __DIR__ . '/account_data.php' as $s) {
  $SECTION_COLS[$s['key']] = array_values(array_filter($s['cols'], fn($c) => $c !== 'NO'));
}

function acct_pj($d, $c = 200){ http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function acct_filter_data($data, $cols){ $out = []; if (is_array($data)) foreach ($cols as $c) { if (isset($data[$c])) { $v = trim((string)$data[$c]); if ($v !== '') $out[$c] = $v; } } return $out; }

/* ---- 계정관리 항목 CRUD API: ?api=accounts (GET=목록/단건, POST/PUT/DELETE=관리자 전용) ---- */
if (isset($_GET['api'])) {
  global $DB, $SECTION_COLS;
  if ($_GET['api'] !== 'accounts') acct_pj(['error' => '알 수 없는 api'], 404);
  try {
    $pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { acct_pj(['error' => 'DB 연결 실패: ' . $e->getMessage()], 500); }
  $method = $_SERVER['REQUEST_METHOD'];
  $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $cat = isset($_GET['cat']) ? (string)$_GET['cat'] : '';

  if ($method === 'GET') {
    if (!can('account_management', 'read')) acct_pj(['error' => '읽기 권한이 없습니다'], 403);
    // 거래업체·모바일은 관리자만 조회
    if ($id > 0) {
      $st = $pdo->prepare("SELECT id,category,data FROM account_item WHERE id=?"); $st->execute([$id]);
      $r = $st->fetch(); if (!$r) acct_pj(['error' => '없는 항목'], 404);
      if (in_array($r['category'], ['vendor','mobile'], true) && !auth_is_admin()) acct_pj(['error' => '접근 권한이 없습니다'], 403);
      $r['data'] = json_decode($r['data'] ?: '{}', true); acct_pj($r);
    }
    $where = ''; $params = [];
    if ($cat !== '') { $where = 'WHERE category=?'; $params[] = $cat; }
    $st = $pdo->prepare("SELECT id,category,data,sort_order FROM account_item $where ORDER BY category,sort_order,id"); $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
      if (in_array($r['category'], ['vendor','mobile'], true) && !auth_is_admin()) continue;
      $r['data'] = json_decode($r['data'] ?: '{}', true); $rows[] = $r;
    }
    acct_pj($rows);
  }

  // 등록/수정/삭제: 관리자 전용(member.is_admin)
  if (!auth_is_admin()) acct_pj(['error' => '관리자만 등록/수정/삭제할 수 있습니다'], 403);
  $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
  $me = auth_user(); $who = $me ? $me['name'] : null;

  if ($method === 'POST') {
    $cat = $body['category'] ?? $cat;
    if (!isset($SECTION_COLS[$cat])) acct_pj(['error' => '알 수 없는 분류'], 400);
    $data = acct_filter_data($body['data'] ?? [], $SECTION_COLS[$cat]);
    $g = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM account_item WHERE category=?"); $g->execute([$cat]); $so = (int)$g->fetchColumn();
    $st = $pdo->prepare("INSERT INTO account_item(category,sort_order,data,created_by,updated_by) VALUES(?,?,?,?,?)");
    $st->execute([$cat, $so, json_encode($data, JSON_UNESCAPED_UNICODE), $who, $who]);
    acct_pj(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
  }
  if ($method === 'PUT') {
    if ($id <= 0) acct_pj(['error' => 'id 필요'], 400);
    $st = $pdo->prepare("SELECT category FROM account_item WHERE id=?"); $st->execute([$id]); $cur = $st->fetchColumn();
    if ($cur === false) acct_pj(['error' => '없는 항목'], 404);
    $data = acct_filter_data($body['data'] ?? [], $SECTION_COLS[$cur] ?? []);
    $st = $pdo->prepare("UPDATE account_item SET data=?, updated_by=? WHERE id=?");
    $st->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $who, $id]);
    acct_pj(['ok' => true]);
  }
  if ($method === 'DELETE') {
    if ($id <= 0) acct_pj(['error' => 'id 필요'], 400);
    $st = $pdo->prepare("DELETE FROM account_item WHERE id=?"); $st->execute([$id]);
    acct_pj(['ok' => true, 'deleted' => $st->rowCount()]);
  }
  acct_pj(['error' => '지원하지 않는 메서드'], 405);
}

require_perm('account_management', 'access');
$PERM = perm_map('account_management');
$IS_ADMIN = auth_is_admin();
$SECTIONS = require __DIR__ . '/account_data.php';
$ADMIN_ONLY_TABS = ['vendor', 'mobile'];   // 관리자만 접속 가능한 탭
$SECTIONS = array_values(array_filter($SECTIONS, function ($s) use ($IS_ADMIN, $ADMIN_ONLY_TABS) {
  return $IS_ADMIN || !in_array($s['key'], $ADMIN_ONLY_TABS, true);
}));
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* 항목은 DB(account_item)에서 로드 — 분류별 그룹화 */
$ACCT = [];
try {
  $pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $st = $pdo->query("SELECT id,category,data FROM account_item ORDER BY category,sort_order,id");
  foreach ($st->fetchAll() as $r) { $ACCT[$r['category']][] = ['id' => (int)$r['id'], 'data' => json_decode($r['data'] ?: '{}', true)]; }
} catch (Throwable $e) { $ACCT = []; }
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>계정관리 · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .acct-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:4px 0 14px;}
  .acct-tab{border:1px solid var(--line);background:#fff;border-radius:8px;padding:7px 13px;font-size:13px;font-weight:700;color:var(--sub);cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;transition:.12s;}
  .acct-tab:hover{border-color:var(--brand);color:var(--brand-d);}
  .acct-tab.active{background:var(--brand-soft);border-color:var(--brand);color:var(--brand-d);}
  .acct-tab .cnt{background:var(--brand);color:#fff;border-radius:10px;font-size:11px;padding:1px 7px;font-weight:700;}
  .acct-panel.hidden{display:none;}
  .acct-panel td{max-width:340px;white-space:normal;word-break:break-word;vertical-align:middle;text-align:center;}
  .acct-panel td.acct-left{text-align:left;}
  .acct-panel td a{color:var(--brand-d);word-break:break-all;}
  .acct-panel .muted{color:#c8cdd6;}
  .acct-empty td{color:#9aa3b2;text-align:center;padding:24px 0;}
  /* 검색영역(탭별) */
  .acct-search-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 14px;margin-bottom:12px;background:#fbfbfe;border:1px solid var(--line);border-radius:12px;}
  .acct-search-bar .acct-q{width:300px;max-width:100%;}
  .acct-search-bar .acct-count{font-size:12.5px;color:var(--sub);font-weight:700;}
  .acct-search-bar .acct-add{margin-left:auto;background:#FB64C9;color:#fff;border:none;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  td.acct-actions{white-space:nowrap;text-align:center;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🔑 계정관리</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('account_management'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="account_management">계정관리</h1>
        <div class="pg-sub">㈜메디힘 계정관리 대장</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="acct-tabs">
          <?php foreach ($SECTIONS as $i => $s): $cnt = count($ACCT[$s['key']] ?? []); ?>
            <button type="button" class="acct-tab<?php echo $i===0?' active':''; ?>" data-tab="<?php echo $i; ?>">
              <?php echo h($s['title']); ?> <span class="cnt"><?php echo $cnt; ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <?php foreach ($SECTIONS as $i => $s): $rows = $ACCT[$s['key']] ?? []; ?>
          <div class="acct-panel<?php echo $i===0?'':' hidden'; ?>" data-panel="<?php echo $i; ?>">
            <!-- 검색영역 -->
            <div class="acct-search-bar">
              <input type="text" class="acct-q form-control form-control-sm" placeholder="<?php echo h($s['title']); ?>에서 검색...">
              <span class="acct-count">총 <?php echo count($rows); ?>건</span>
              <?php if ($IS_ADMIN): ?>
                <a class="btn primary sm acct-add" href="account_form.php?cat=<?php echo h($s['key']); ?>">＋ 등록</a>
              <?php endif; ?>
            </div>
            <!-- 리스트영역 -->
            <div class="table-scroll acct-list">
              <table class="sch-look">
                <thead><tr><?php foreach ($s['cols'] as $c) echo '<th>'.h($c==='NO'?'번호':$c).'</th>'; if ($IS_ADMIN) echo '<th>관리</th>'; ?></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                  <tr class="acct-empty"><td colspan="<?php echo count($s['cols']) + ($IS_ADMIN?1:0); ?>">등록된 항목이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $ri => $item):
                  $d = $item['data'];
                  $q = strtolower(implode(' ', array_map('strval', $d))); ?>
                  <tr data-q="<?php echo h($q); ?>" data-id="<?php echo (int)$item['id']; ?>">
                    <?php foreach ($s['cols'] as $col):
                      $isNo  = ($col === 'NO');
                      $v = $isNo ? (string)($ri + 1) : (string)($d[$col] ?? '');
                      $isUrl = (stripos($col, 'URL') !== false);
                      $leftCol = in_array($col, ['URL','주요기능','비고'], true);   // URL·주요기능·비고만 좌측, 나머지 center
                      echo '<td'.($leftCol?' class="acct-left"':'').'>';
                      if ($v === '') {
                        echo '<span class="muted">·</span>';
                      } elseif ($isUrl && preg_match('~^https?://~i', $v)) {
                        echo '<a href="'.h($v).'" target="_blank" rel="noopener">'.h($v).'</a>';
                      } else {
                        echo h($v);
                      }
                      echo '</td>';
                    endforeach; ?>
                    <?php if ($IS_ADMIN): ?>
                      <td class="acct-actions">
                        <a class="btn ghost sm" href="account_form.php?cat=<?php echo h($s['key']); ?>&id=<?php echo (int)$item['id']; ?>">수정</a>
                        <button type="button" class="btn danger sm" onclick="delAccount(<?php echo (int)$item['id']; ?>, this)">삭제</button>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

/* 탭 전환 */
document.querySelectorAll('.acct-tab').forEach(btn=>{
  btn.onclick=()=>{
    const t=btn.dataset.tab;
    document.querySelectorAll('.acct-tab').forEach(b=>b.classList.toggle('active', b===btn));
    document.querySelectorAll('.acct-panel').forEach(p=>p.classList.toggle('hidden', p.dataset.panel!==t));
  };
});
/* 탭별 검색(각 패널의 검색영역 → 해당 리스트만 필터) */
document.querySelectorAll('.acct-panel').forEach(p=>{
  const q=p.querySelector('.acct-q'), cnt=p.querySelector('.acct-count');
  const total=p.querySelectorAll('tbody tr[data-id]').length;
  if(!q) return;
  q.addEventListener('input', ()=>{
    const v=(q.value||'').toLowerCase().trim(); let n=0;
    p.querySelectorAll('tbody tr[data-id]').forEach(tr=>{ const ok=(!v||(tr.dataset.q||'').includes(v)); tr.style.display=ok?'':'none'; if(ok)n++; });
    if(cnt) cnt.textContent = v ? `검색 ${n} / 총 ${total}건` : `총 ${total}건`;
  });
});

/* 토스트 */
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }
/* 등록/수정 후 안내 토스트 */
try{ const m=sessionStorage.getItem('acctToast'); if(m){ sessionStorage.removeItem('acctToast'); setTimeout(()=>toast(m),100); } }catch(e){}
/* 삭제(관리자) */
async function delAccount(id, btn){
  if(!confirm('이 항목을 삭제하시겠습니까?')) return;
  try{
    const r=await fetch('account_management.php?api=accounts&id='+id,{method:'DELETE',cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const tr=btn.closest('tr'); if(tr) tr.remove();
    toast('삭제되었습니다');
  }catch(e){ alert('삭제 실패: '+e.message); }
}
</script>
</body>
</html>

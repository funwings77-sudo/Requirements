<?php
/* =====================================================================
   계정관리 — 분류별 등록/수정 폼 (documents/account_form.php)
   - ?cat=site|vendor|mobile|test (&id=account_item.id)
   - 관리자 전용(member.is_admin). 저장/삭제는 account_management.php?api=accounts 사용
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_perm('account_management', 'access');
require_admin();   // 등록/수정/삭제는 관리자 전용

$DB = [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '3306',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
  'name' => getenv('DB_NAME') ?: 'medihim',
];
$SECTIONS = require __DIR__ . '/account_data.php';
$cat = isset($_GET['cat']) ? (string)$_GET['cat'] : '';
$id  = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : 0;
$DATA = [];

// 수정: DB에서 항목 로드(분류는 DB값을 신뢰)
if ($id > 0) {
  try {
    $pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $st = $pdo->prepare("SELECT category,data FROM account_item WHERE id=?"); $st->execute([$id]);
    $row = $st->fetch();
    if ($row) { $cat = $row['category']; $DATA = json_decode($row['data'] ?: '{}', true) ?: []; }
    else { http_response_code(404); echo '없는 항목입니다.'; exit; }
  } catch (Throwable $e) { http_response_code(500); echo 'DB 오류: ' . htmlspecialchars($e->getMessage()); exit; }
}

$SEC = null;
foreach ($SECTIONS as $s) { if ($s['key'] === $cat) { $SEC = $s; break; } }
if (!$SEC) { http_response_code(404); echo '알 수 없는 분류입니다.'; exit; }

$isEdit = ($id > 0);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$LONG = ['주요기능', '비고'];   // textarea로 표시할 컬럼
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>계정관리 등록/수정 · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .form-foot{display:flex;gap:10px;padding:18px 2px 4px;border-top:1px solid var(--line);margin-top:18px;}
  .form-foot .right{margin-left:auto;display:flex;gap:10px;}
  .panel.form-panel{padding:22px 24px;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🔑 계정관리 등록/수정</span>
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
        <h1><?php echo $isEdit ? '계정 수정' : '계정 등록'; ?></h1>
        <div class="pg-sub"><a href="account_management.php" data-lnb-title="account_management" style="color:var(--sub);text-decoration:none">계정관리</a> › <?php echo h($SEC['title']); ?></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="panel form-panel">
      <form id="acctForm" onsubmit="return false;">
        <div class="grid">
          <?php foreach ($SEC['cols'] as $col):
            if ($col === 'NO') continue;   // 번호는 자동(표시 순번)
            $v = (string)($DATA[$col] ?? '');
            $isLong = in_array($col, $LONG, true);
            $isUrl  = (stripos($col, 'URL') !== false);
            $cls = $isLong ? 'c12' : 'c6';
          ?>
            <div class="field <?php echo $cls; ?>">
              <label><?php echo h($col); ?></label>
              <?php if ($isLong): ?>
                <textarea data-col="<?php echo h($col); ?>" rows="3"><?php echo h($v); ?></textarea>
              <?php else: ?>
                <input data-col="<?php echo h($col); ?>" type="<?php echo $isUrl ? 'url' : 'text'; ?>" value="<?php echo h($v); ?>"<?php echo $isUrl ? ' placeholder="https://"' : ''; ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="form-foot">
          <button type="button" class="btn primary" id="btnSave">💾 <?php echo $isEdit ? '수정 저장' : '등록'; ?></button>
          <button type="button" class="btn ghost" onclick="location.href='account_management.php'">취소</button>
        </div>
      </form>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const API = 'account_management.php?api=accounts';
const CAT = <?php echo json_encode($SEC['key'], JSON_UNESCAPED_UNICODE); ?>;
const ID  = <?php echo $isEdit ? (int)$id : 'null'; ?>;

/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

function collect(){ const data={}; document.querySelectorAll('#acctForm [data-col]').forEach(el=>{ data[el.dataset.col]=(el.value||'').trim(); }); return data; }

document.getElementById('btnSave').onclick = async ()=>{
  const data = collect();
  try{
    const url = ID ? (API+'&id='+ID) : API;
    const r = await fetch(url, {method: ID?'PUT':'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({category:CAT, data})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    sessionStorage.setItem('acctToast', ID?'수정되었습니다':'등록되었습니다');
    location.href='account_management.php';
  }catch(e){ alert('저장 실패: '+e.message); }
};
</script>
</body>
</html>

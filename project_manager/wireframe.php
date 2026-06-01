<?php
/* =====================================================================
   프로젝트 매니저 — 와이어프레임 (project_manager/wireframe.php)
   - 로그인 + 프로젝트 매니저 접속 권한 필요
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_perm('project_manager', 'access');
$PERM = perm_map('project_manager');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>와이어프레임 · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🧩 와이어프레임</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('wireframe'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="wireframe">와이어프레임</h1>
        <div class="pg-sub">프로젝트 와이어프레임</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="card">
      <div class="card-body">
        <p style="color:var(--sub);margin:0;">와이어프레임 페이지입니다.</p>
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
</script>
</body>
</html>

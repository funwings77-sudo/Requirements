<?php
/* =====================================================================
   회원 등록 / 수정 (모달 대신 별도 페이지)
   - 데이터 API는 member.php(?api=members)를 재사용
   - 진입: form.php(등록) / form.php?id=N(수정)
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
$__id = (isset($_GET['id']) && $_GET['id'] !== '') ? (int)$_GET['id'] : 0;
$__me = auth_user();
$__isSelfEdit = $__me && $__id > 0 && (int)$__me['id'] === $__id;
if (!$__isSelfEdit) require_admin();   // 본인 수정은 모든 로그인 회원 허용(LNB ⚙️ 모달), 나머지는 관리자 전용
$__modal = !empty($_GET['modal']);     // ?modal=1: LNB/topbar 숨김 + 저장 시 부모창 postMessage
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>회원 등록 / 수정</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .form-foot{display:flex;gap:10px;padding:18px 2px 4px;border-top:1px solid var(--line);margin-top:18px;}
  .panel.form-panel{padding:22px 24px;}
</style>
</head>
<body class="<?= $__modal ? 'modal-mode' : '' ?>">
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">👥 회원 등록/수정</span>
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
        <h1 id="formTitle">회원 등록</h1>
        <div class="pg-sub"><a href="member.php" data-lnb-title="member" style="color:var(--sub);text-decoration:none">회원 관리</a> › <span id="crumb">새 회원</span></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="panel form-panel">
      <form id="memForm">
        <input type="hidden" id="f_id">
        <div class="grid">
          <?php if (auth_is_admin() && !$__modal): ?>
          <div class="field c12"><label>이용여부</label>
            <select id="f_status" class="form-select">
              <option value="이용중">이용중</option>
              <option value="이용중지">이용중지 (로그인 불가)</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="field c12"><label>이름 <span class="req">*</span></label><input id="f_name" required placeholder="이름"></div>
          <div class="field c12"><label>휴대폰번호 <span class="req">*</span></label><input id="f_phone" type="tel" inputmode="numeric" required placeholder="010-0000-0000" maxlength="13"></div>
          <div class="field c12"><label>소속부서</label><input id="f_dept" list="deptList" placeholder="예: 브랜드마케팅파트"><datalist id="deptList"></datalist></div>
          <div class="field c12"><label>이메일</label><input id="f_email" type="email" placeholder="name@castingn.com"></div>
        </div>
        <div class="form-foot">
          <button type="submit" class="btn primary">💾 저장</button>
          <button type="button" class="btn ghost" onclick="if(window.parent!==window){window.parent.postMessage({type:'profile-cancel'},'*')}else{location.href='member.php'}">취소</button>
        </div>
      </form>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const API='member.php?api=';
const FIELDS=['name','dept','phone','email','status'];   // status는 관리자에게만 select 노출(없으면 전송값 무시·기존값 보존)
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

/* 휴대폰번호: 숫자만 입력 + 자동 하이픈(010-0000-0000) */
function formatPhone(v){
  const d=(v||'').replace(/\D/g,'').slice(0,11);
  if(d.length<4) return d;
  if(d.length<8) return d.slice(0,3)+'-'+d.slice(3);
  return d.slice(0,3)+'-'+d.slice(3,7)+'-'+d.slice(7);
}
const phoneEl=document.getElementById('f_phone');
if(phoneEl){ phoneEl.addEventListener('input',()=>{ phoneEl.value=formatPhone(phoneEl.value); }); }

async function loadDeptList(){
  try{
    const ms = await (await fetch(API+'members',{cache:'no-store'})).json();
    if(!Array.isArray(ms)) return;
    const depts=[...new Set(ms.map(m=>(m.dept||'').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ko'));
    document.getElementById('deptList').innerHTML = depts.map(v=>`<option value="${esc(v)}">`).join('');
  }catch(e){}
}
async function setDb(){
  const el=document.getElementById('dbStatus'); if(!el) return;   // DB연결됨 라벨 제거됨 — 요소 없으면 no-op
  try{ const h=await (await fetch(API+'health',{cache:'no-store'})).json();
    if(h&&h.ok){ el.textContent='🟢 DB 연결됨'; el.style.background='#dcfce7'; el.style.color='#166534'; }
  }catch(e){ el.textContent='⚠ 연결 안 됨'; el.style.background='#fee2e2'; el.style.color='#b91c1c'; }
}

const form=document.getElementById('memForm');
form.addEventListener('submit', async e=>{
  e.preventDefault();
  const obj={}; FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); obj[k]=el?(el.value||'').trim():''; });
  const idVal=document.getElementById('f_id').value;
  try{
    const url=API+'members'+(idVal?'&id='+idVal:'');
    const r=await fetch(url,{method:idVal?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
    if(!r.ok){ const er=await r.json().catch(()=>({})); throw new Error(er.error||('HTTP '+r.status)); }
    // 모달 모드(부모창에 임베드)에선 부모로 알림(부모가 모달 닫고 리로드해서 LNB 프로필 갱신). 일반 모드는 기존대로.
    if(window.parent !== window){ window.parent.postMessage({type:'profile-saved'}, '*'); return; }
    sessionStorage.setItem('memberToast', idVal?'회원이 수정되었습니다':'회원이 추가되었습니다');
    location.href='member.php';
  }catch(err){ alert('저장 실패: '+err.message); }
});

(async function boot(){
  setDb(); loadDeptList();
  const id=new URLSearchParams(location.search).get('id');
  if(id){
    document.getElementById('formTitle').textContent='회원 수정';
    document.getElementById('crumb').textContent='#'+id+' 수정';
    try{
      const r=await fetch(API+'members&id='+id,{cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      const d=await r.json();
      FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); if(el) el.value=(d[k]==null?'':d[k]); });
      if(phoneEl) phoneEl.value=formatPhone(phoneEl.value);
      document.getElementById('f_id').value=id;
      if(d.name) document.getElementById('crumb').textContent=esc(d.name)+' 수정';
    }catch(e){ alert('회원을 불러오지 못했습니다: '+e.message); }
  } else {
    setTimeout(()=>document.getElementById('f_name').focus(),60);
  }
})();
</script>
</body>
</html>

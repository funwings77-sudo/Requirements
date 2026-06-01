<?php
/* =====================================================================
   권한 설정 (독립 페이지) — 관리자 전용
   - 회원별 메뉴(requirements/dashboard/todolist/member)×액션(접속/읽기/쓰기/수정/삭제) 매트릭스 + 관리자 토글
   - 데이터 API는 회원관리(member.php)의 것을 재사용:
       GET  /member/member.php?api=members        회원 목록(is_admin 포함)
       GET  /member/member.php?api=perms&id=N      회원 1명 권한
       PUT  /member/member.php?api=perms&id=N      회원 1명 권한 저장 (관리자 전용)
   - 진입점: http://localhost:8000/authorization_settings/authorization_settings.php  (?id=N 로 회원 선택 가능)
   - BOM 없는 UTF-8
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_admin();
$preId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>메디힘 권한 설정</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .authz-grid{display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;}
  /* 회원 목록 (좌측 패널) */
  .mlist{max-height:70vh;overflow:auto;border:1px solid var(--line);border-radius:12px;}
  .mrow{display:flex;align-items:center;gap:12px;padding:13px 14px;border-bottom:1px solid var(--line);cursor:pointer;transition:.12s;border-left:3px solid transparent;}
  .mrow:last-child{border-bottom:none;}
  .mrow:hover{background:#f6f7f9;}
  .mrow.sel{background:var(--brand-soft);border-left-color:var(--brand);}
  .mrow .m-avatar{width:34px;height:34px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#b66dff,#8e54e9);color:#fff;font-weight:700;font-size:13px;}
  .mrow.sel .m-avatar{box-shadow:0 0 0 3px rgba(182,109,255,.25);}
  .mrow .nm{font-weight:700;color:var(--ink);}
  .mrow .dp{font-size:12px;color:var(--sub);margin-top:1px;}
  .mrow .who{flex:1;min-width:0;}
  .mrow .who .nm{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:6px;}
  .admin-chip{display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;}

  /* ===== 권한 매트릭스 (우측 패널) — 디자인 개선 ===== */
  /* 회원 헤더 카드 */
  .pm-head{display:flex;align-items:center;gap:14px;padding:16px 18px;background:linear-gradient(135deg,#faf8ff,#f3effc);border:1px solid #ece4fa;border-radius:14px;margin-bottom:18px;}
  .pm-head .pm-avatar{width:48px;height:48px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#b66dff,#8e54e9);color:#fff;font-weight:700;font-size:18px;box-shadow:0 4px 12px rgba(182,109,255,.35);}
  .pm-head .pm-who{flex:1;min-width:0;}
  .pm-head .pm-nm{font-size:17px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .pm-head .pm-dp{font-size:12.5px;color:var(--sub);margin-top:2px;}
  .pm-stats{display:flex;gap:14px;padding-left:14px;border-left:1px solid #ece4fa;}
  .pm-stats .st-n{font-size:18px;font-weight:800;color:var(--brand-d);}
  .pm-stats .st-l{font-size:11px;color:var(--sub);font-weight:600;text-align:center;}

  /* 관리자 토글 — 우측 스위치 디자인 (안내는 1줄, 길면 말줄임) */
  .pm-admin{display:flex;align-items:center;gap:14px;padding:14px 18px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;margin-bottom:18px;}
  .pm-admin .pm-admin-text{flex:1;min-width:0;font-size:13.5px;color:#7c5d10;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .pm-admin .pm-admin-text b{color:#92400e;display:inline;font-size:14px;}
  .pm-admin .pm-admin-text .muted{font-size:12.5px;color:#a17e2a;}
  .pm-switch{position:relative;display:inline-block;width:48px;height:26px;flex:none;}
  .pm-switch input{opacity:0;width:0;height:0;}
  .pm-switch .slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:26px;transition:.2s;}
  .pm-switch .slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 2px 4px rgba(0,0,0,.15);}
  .pm-switch input:checked + .slider{background:#f59e0b;}
  .pm-switch input:checked + .slider:before{transform:translateX(22px);}

  /* 일괄 설정 칩 버튼 */
  .pm-bulk{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:center;}
  .pm-bulk .pm-bulk-lbl{font-size:12px;color:var(--sub);font-weight:600;margin-right:4px;}
  .pm-bulk-btn{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid var(--line);color:#5b6472;padding:7px 13px;border-radius:20px;font-size:12.5px;font-weight:600;cursor:pointer;transition:.12s;font-family:inherit;}
  .pm-bulk-btn:hover{border-color:var(--brand);color:var(--brand-d);background:var(--brand-soft);}
  .pm-bulk-btn.all{border-color:#86efac;color:#166534;background:#dcfce7;} .pm-bulk-btn.all:hover{background:#bbf7d0;}
  .pm-bulk-btn.read{border-color:#a5b4fc;color:#3730a3;background:#e0e7ff;} .pm-bulk-btn.read:hover{background:#c7d2fe;}
  .pm-bulk-btn.none{border-color:#fca5a5;color:#b91c1c;background:#fee2e2;} .pm-bulk-btn.none:hover{background:#fecaca;}

  /* 매트릭스 표 — 색상 코딩·아이콘·커스텀 체크박스 */
  .pm-tbl-wrap{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#fff;}
  table.pm-tbl{width:100%;border-collapse:collapse;min-width:0;font-size:13px;}
  table.pm-tbl th,table.pm-tbl td{padding:12px 10px;text-align:center;border-bottom:1px solid #f1f0f5;}
  table.pm-tbl thead th{background:#fafafd;font-weight:700;font-size:12px;color:#5b6472;border-bottom:1.5px solid var(--line);}
  table.pm-tbl thead th .col-toggle{display:inline-flex;align-items:center;gap:6px;cursor:pointer;user-select:none;}
  table.pm-tbl thead th .act-dot{display:inline-block;width:8px;height:8px;border-radius:50%;vertical-align:middle;}
  table.pm-tbl thead th.act-access .act-dot{background:#3b82f6;}
  table.pm-tbl thead th.act-read   .act-dot{background:#06b6d4;}
  table.pm-tbl thead th.act-write  .act-dot{background:#f59e0b;}
  table.pm-tbl thead th.act-update .act-dot{background:#8b5cf6;}
  table.pm-tbl thead th.act-delete .act-dot{background:#ef4444;}
  table.pm-tbl tbody tr:last-child td{border-bottom:none;}
  table.pm-tbl tbody tr:hover{background:#fbfaff;}
  table.pm-tbl td.pm{text-align:left;font-weight:700;white-space:nowrap;color:var(--ink);padding-left:18px;}
  table.pm-tbl td.pm .pm-ico{display:inline-block;width:22px;text-align:center;margin-right:6px;font-size:14px;}
  table.pm-tbl td .na{color:#cbd5e1;font-weight:700;}
  /* 커스텀 체크박스 (브랜드 보라) */
  .pm-chk{appearance:none;-webkit-appearance:none;width:22px;height:22px;border:1.5px solid #d1d5db;border-radius:6px;cursor:pointer;position:relative;transition:.12s;vertical-align:middle;background:#fff;}
  .pm-chk:hover:not(:disabled){border-color:var(--brand);}
  .pm-chk:checked{background:var(--brand);border-color:var(--brand);}
  .pm-chk:checked::after{content:'✓';color:#fff;font-weight:700;font-size:14px;line-height:1;position:absolute;left:50%;top:50%;transform:translate(-50%,-52%);}
  .pm-chk:disabled{opacity:.4;cursor:not-allowed;background:#f3f4f6;}
  table.pm-tbl.admin-on tbody{opacity:.45;pointer-events:none;}

  .pm-note{color:var(--sub);font-size:12px;margin-top:14px;line-height:1.7;padding:12px 14px;background:#f8f9fb;border-radius:10px;border-left:3px solid var(--brand);}
  .pm-foot{display:flex;gap:10px;justify-content:flex-end;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--line);}
  .pm-foot .dirty-ind{margin-right:auto;font-size:12.5px;color:var(--sub);}
  .pm-foot .dirty-ind.on{color:#f59e0b;font-weight:700;}

  .authz-empty{padding:80px 20px;text-align:center;color:var(--sub);}
  .authz-empty .em-ico{font-size:48px;margin-bottom:14px;opacity:.5;}
  .authz-empty .em-text{font-size:14px;}

  @media(max-width:900px){ .authz-grid{grid-template-columns:1fr;} .mlist{max-height:none;} .pm-stats{display:none;} }
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🔐 권한 설정</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('authz'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="authz">권한 설정</h1>
        <div class="pg-sub">회원별 메뉴 접속 · 읽기/쓰기/수정/삭제 권한 관리 (관리자 전용)</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="authz-grid">
      <!-- 회원 목록 -->
      <div class="panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px;">
          <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--ink);">회원 목록</h2>
          <span class="count-chip" id="memCount">0명</span>
        </div>
        <div class="toolbar" style="margin-bottom:12px">
          <input id="search" placeholder="🔍 이름 / 부서 검색" style="width:100%">
        </div>
        <div class="mlist" id="mlist"></div>
      </div>

      <!-- 권한 매트릭스 -->
      <div class="panel">
        <div class="authz-empty" id="permEmpty">
          <div class="em-ico">🔐</div>
          <div class="em-text">왼쪽에서 회원을 선택하면<br>권한을 설정할 수 있습니다.</div>
        </div>
        <div id="permArea" class="hidden">
          <!-- 회원 헤더 카드 -->
          <div class="pm-head">
            <div class="pm-avatar" id="permAvatar">?</div>
            <div class="pm-who">
              <div class="pm-nm"><span id="permName">—</span><span id="permAdminChip"></span></div>
              <div class="pm-dp" id="permDept">—</div>
            </div>
            <div class="pm-stats"><div><div class="st-n" id="permCntCells">0</div><div class="st-l">부여 권한</div></div></div>
          </div>

          <!-- 관리자 토글(스위치) — 안내는 1줄(긴 화면에선 전체 노출, 좁은 화면은 말줄임) -->
          <div class="pm-admin">
            <div class="pm-admin-text" title="체크 시 이 회원은 관리자로 표기되고 매트릭스와 무관하게 모든 메뉴·기능에 전권을 가집니다.">
              <b>🔐 권한 설정 권한 부여 (관리자)</b><span class="muted"> — 체크 시 이 회원은 <b>관리자</b>로 표기되고 매트릭스와 무관하게 <b>모든 메뉴·기능에 전권</b>을 가집니다.</span>
            </div>
            <label class="pm-switch"><input type="checkbox" id="permAdmin"><span class="slider"></span></label>
          </div>

          <!-- 일괄 설정 -->
          <div class="pm-bulk">
            <span class="pm-bulk-lbl">일괄 설정</span>
            <button type="button" class="pm-bulk-btn all" id="bulkAll">✓ 전체 선택</button>
            <button type="button" class="pm-bulk-btn read" id="bulkRead">👀 읽기 전용</button>
            <button type="button" class="pm-bulk-btn none" id="bulkNone">✕ 전체 해제</button>
          </div>

          <!-- 매트릭스 표 -->
          <div class="pm-tbl-wrap">
            <table class="pm-tbl" id="permTbl">
              <thead><tr>
                <th style="text-align:left;padding-left:18px;">메뉴</th>
                <th class="act-access"><label class="col-toggle"><input type="checkbox" class="pm-chk col-chk" data-col-act="access"  onchange="toggleColumn(this)"><span class="act-dot"></span>접속</label></th>
                <th class="act-read"><label class="col-toggle"><input type="checkbox" class="pm-chk col-chk" data-col-act="read"    onchange="toggleColumn(this)"><span class="act-dot"></span>읽기</label></th>
                <th class="act-write"><label class="col-toggle"><input type="checkbox" class="pm-chk col-chk" data-col-act="write"   onchange="toggleColumn(this)"><span class="act-dot"></span>쓰기</label></th>
                <th class="act-update"><label class="col-toggle"><input type="checkbox" class="pm-chk col-chk" data-col-act="update"  onchange="toggleColumn(this)"><span class="act-dot"></span>수정</label></th>
                <th class="act-delete"><label class="col-toggle"><input type="checkbox" class="pm-chk col-chk" data-col-act="delete"  onchange="toggleColumn(this)"><span class="act-dot"></span>삭제</label></th>
              </tr></thead>
              <tbody id="permBody"></tbody>
            </table>
          </div>

          <div class="pm-note">· <b>접속</b>=메뉴/페이지 진입 · <b>읽기</b>=목록·내용 조회 · <b>쓰기</b>=신규 등록 · <b>수정</b>=편집 · <b>삭제</b>=삭제<br>· 대시보드는 조회 전용이라 쓰기/수정/삭제가 없습니다.<br>· <b>관리자 토글</b>이 켜진 회원만 ‘권한 설정’ 메뉴에 접근하며, 매트릭스와 무관하게 모든 권한을 가집니다.</div>

          <div class="pm-foot">
            <span class="dirty-ind" id="permDirty"></span>
            <button type="button" class="btn ghost" id="permReset">↺ 되돌리기</button>
            <button type="button" class="btn primary" id="permSave">💾 저장</button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const MAPI = '/member/member.php?api=';
const PRE_ID = <?php echo $preId ?: 'null'; ?>;
// 메뉴 트리(auth_lnb_tree)에서 자동 파생 — 메뉴 신규/수정/삭제 시 권한 매트릭스에 자동 반영
const RES = <?php echo json_encode(array_map(fn($r)=>[$r['key'],$r['label'],$r['crud'],$r['ico']], auth_perm_resources()), JSON_UNESCAPED_UNICODE); ?>;
const ACTS = ['access','read','write','update','delete'];
let members = [], selId = null, lastPerms = null;

function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* ---- LNB drawer (mobile) ---- */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

async function loadMembers(){
  const r = await fetch(MAPI+'members',{cache:'no-store'});
  if(!r.ok) throw new Error('회원 목록 조회 실패 ('+r.status+')');
  members = await r.json();
}
function renderList(){
  const q=(document.getElementById('search').value||'').trim().toLowerCase();
  const list = members.filter(m=> !q || ((m.name||'')+' '+(m.dept||'')).toLowerCase().includes(q));
  document.getElementById('memCount').textContent = members.length+'명';
  document.getElementById('mlist').innerHTML = list.map(m=>{
    const admin = m.is_admin ? '<span class="admin-chip">관리자</span>' : '';
    const ini = (m.name||'?').substring(0,1);
    return `<div class="mrow${m.id==selId?' sel':''}" data-id="${m.id}" onclick="selectMember(${m.id})">
      <div class="m-avatar">${esc(ini)}</div>
      <div class="who"><div class="nm">${esc(m.name)||'-'} ${admin}</div><div class="dp">${esc(m.dept)||'-'}</div></div>
    </div>`;
  }).join('') || '<div class="authz-empty"><div class="em-text">검색 결과가 없습니다.</div></div>';
}

function buildMatrix(perms){
  document.getElementById('permBody').innerHTML = RES.map(([res,lbl,crud,ico])=>{
    const p=(perms&&perms[res])||{};
    const cells=ACTS.map(a=>{
      const na=(!crud && (a==='write'||a==='update'||a==='delete'));
      return `<td>${na?'<span class="na">-</span>':`<input type="checkbox" class="pm-chk" data-res="${res}" data-act="${a}" ${p[a]?'checked':''} onchange="markDirty()">`}</td>`;
    }).join('');
    // 메뉴명은 LNB 사용자 편집 이름과 동기화 — data-lnb-title이 있으면 lnbApplyNames()가 자동 적용
    return `<tr><td class="pm"><span class="pm-ico">${ico||'•'}</span><span data-lnb-title="${res}">${esc(lbl)}</span></td>${cells}</tr>`;
  }).join('');
  if (window.lnbApplyNames) window.lnbApplyNames();   // LNB 편집 라벨 즉시 적용
  updateCountCells();
  syncColumnHeaders();
}
function updateCountCells(){
  const n = document.querySelectorAll('#permBody input.pm-chk:checked').length;
  const adminOn = document.getElementById('permAdmin').checked;
  document.getElementById('permCntCells').textContent = adminOn ? '전권' : n;
}
function syncAdmin(){ const on=document.getElementById('permAdmin').checked;
  document.querySelectorAll('#permBody input.pm-chk').forEach(c=>c.disabled=on);
  document.querySelectorAll('.col-chk').forEach(c=>c.disabled=on);   // 컬럼 일괄 체크박스도 disable
  document.getElementById('permTbl').classList.toggle('admin-on', on);
  updateCountCells();
  syncColumnHeaders();
}
/* 컬럼 헤더 체크박스: 클릭 시 해당 컬럼 모든 row checkbox 일괄 on/off */
function toggleColumn(chk){
  const act = chk.dataset.colAct;
  document.querySelectorAll('#permBody input.pm-chk[data-act="'+act+'"]').forEach(c=>{ if(!c.disabled) c.checked = chk.checked; });
  markDirty();
}
/* 컬럼 헤더 체크박스의 상태를 row 체크박스 상태에 맞춰 동기화 (모두 체크=checked, 일부=indeterminate, 없음=unchecked) */
function syncColumnHeaders(){
  document.querySelectorAll('.col-chk').forEach(col=>{
    const act = col.dataset.colAct;
    const rows = document.querySelectorAll('#permBody input.pm-chk[data-act="'+act+'"]');
    let checked=0, total=0;
    rows.forEach(r=>{ total++; if(r.checked) checked++; });
    col.checked = total>0 && checked===total;
    col.indeterminate = checked>0 && checked<total;
  });
}
function markDirty(){ const d=document.getElementById('permDirty'); if(d){ d.textContent='● 저장하지 않은 변경 사항이 있습니다'; d.classList.add('on'); } updateCountCells(); syncColumnHeaders(); }
function clearDirty(){ const d=document.getElementById('permDirty'); if(d){ d.textContent=''; d.classList.remove('on'); } }
async function selectMember(id){
  selId=id; renderList(); clearDirty();
  const m=members.find(x=>x.id==id);
  document.getElementById('permEmpty').classList.add('hidden');
  document.getElementById('permArea').classList.remove('hidden');
  document.getElementById('permName').textContent = (m&&m.name)||('#'+id);
  document.getElementById('permDept').textContent = (m&&m.dept)||'-';
  document.getElementById('permAvatar').textContent = ((m&&m.name)||'?').substring(0,1);
  try{
    const r=await fetch(MAPI+'perms&id='+id,{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    lastPerms=await r.json();
    document.getElementById('permAdmin').checked=!!lastPerms.is_admin;
    document.getElementById('permAdminChip').innerHTML = lastPerms.is_admin?'<span class="admin-chip">관리자</span>':'';
    buildMatrix(lastPerms.perms); syncAdmin(); clearDirty();
  }catch(err){ alert('권한 조회 실패: '+err.message); }
}
function collect(){
  const perms={}; RES.forEach(([res])=>perms[res]={access:0,read:0,write:0,update:0,delete:0});
  document.querySelectorAll('#permBody input[type=checkbox]').forEach(c=>{ if(c.checked) perms[c.dataset.res][c.dataset.act]=1; });
  return { is_admin: document.getElementById('permAdmin').checked?1:0, perms };
}
async function savePerm(){
  if(selId==null) return;
  try{
    const r=await fetch(MAPI+'perms&id='+selId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(collect())});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadMembers();        // is_admin 칩 갱신
    await selectMember(selId);  // 저장값 재조회 + dirty clear
    toast('권한이 저장되었습니다');
  }catch(err){ alert('권한 저장 실패: '+err.message); }
}
/* 일괄 설정 버튼 */
function bulkSet(mode){
  document.getElementById('permAdmin').checked=false; syncAdmin();
  document.querySelectorAll('#permBody input.pm-chk').forEach(c=>{
    if(mode==='all') c.checked=true;
    else if(mode==='none') c.checked=false;
    else if(mode==='read') c.checked=(c.dataset.act==='access'||c.dataset.act==='read');
  });
  markDirty();
}

document.getElementById('search').addEventListener('input',renderList);
document.getElementById('permAdmin').addEventListener('change',()=>{ syncAdmin(); markDirty(); });
document.getElementById('permSave').onclick=savePerm;
document.getElementById('permReset').onclick=()=>{ if(lastPerms){ document.getElementById('permAdmin').checked=!!lastPerms.is_admin; buildMatrix(lastPerms.perms); syncAdmin(); clearDirty(); } };
document.getElementById('bulkAll').onclick=()=>bulkSet('all');
document.getElementById('bulkRead').onclick=()=>bulkSet('read');
document.getElementById('bulkNone').onclick=()=>bulkSet('none');

(async function boot(){
  try{ await loadMembers(); }catch(e){ document.getElementById('mlist').innerHTML='<div class="authz-empty">'+esc(e.message)+'</div>'; return; }
  renderList();
  if(PRE_ID && members.some(m=>m.id==PRE_ID)) selectMember(PRE_ID);
})();
</script>
</body>
</html>

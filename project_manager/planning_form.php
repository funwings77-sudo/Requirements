<?php
/* =====================================================================
   기획서 등록/수정 (project_manager/planning_form.php)
   - 신규=쓰기, 수정(?id=)=수정 권한 / 저장·이력·댓글: planning.php?api=
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_perm('planning', 'access');
$__id = (isset($_GET['id']) && $_GET['id'] !== '') ? (int)$_GET['id'] : 0;
$isEdit = $__id > 0;
if ($isEdit) { if (!can('planning','update')) { http_response_code(403); echo '수정 권한이 없습니다.'; exit; } }
else { if (!can('planning','write')) { http_response_code(403); echo '등록 권한이 없습니다.'; exit; } }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>기획서 <?php echo $isEdit?'수정':'등록'; ?> · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  .form-foot{display:flex;gap:10px;padding:18px 2px 4px;border-top:1px solid var(--line);margin-top:18px;}
  .panel.form-panel{padding:22px 24px;}
  /* 변경이력 */
  .hist-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
  .hist-panel h2{margin:0;font-size:16px;display:flex;align-items:center;gap:8px;}
  .hist-size{height:32px;border:1px solid #e2e6ec;border-radius:8px;padding:0 10px;font-size:13px;font-family:inherit;background:#fff;color:var(--ink);cursor:pointer;}
  .hist-scroll{overflow-x:auto;}
  .hist-table{width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;min-width:0;}
  .hist-table th,.hist-table td{padding:9px 12px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .hist-table td{text-align:left;}
  .hist-table thead th{background:#f8f9fb;color:var(--sub);font-weight:700;font-size:12.5px;white-space:nowrap;}
  .hist-table tbody tr:hover{background:#f8f9fb;}
  .hist-no{width:50px;text-align:center;color:var(--sub);white-space:nowrap;}
  .hist-dt{width:150px;white-space:nowrap;color:var(--sub);}
  .hist-by{width:84px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .hist-chg{word-break:break-word;} .hist-chg b{color:var(--sub);font-weight:700;}
  .hist-pager{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:14px;}
  .hist-pageinfo{font-size:13px;color:var(--sub);}
  /* 댓글 */
  .cmt-panel h2{margin:0 0 14px;font-size:16px;display:flex;align-items:center;gap:8px;}
  .cmt-new textarea,.cmt-reply-box textarea,.cmt-edit-box textarea{width:100%;border:1px solid #e2e6ec;border-radius:10px;padding:10px 12px;font-size:13.5px;font-family:inherit;resize:vertical;min-height:46px;background:#fff;color:var(--ink);}
  .cmt-new{margin-bottom:6px;}
  .cmt{padding:14px 0;border-top:1px solid var(--line);}
  #cmtList > .cmt:first-child{border-top:none;}
  .cmt-head{display:flex;align-items:center;gap:8px;}
  .cmt-avatar{width:30px;height:30px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#34507f,#5a7bb0);color:#fff;font-weight:700;font-size:13px;}
  .cmt-author{font-weight:700;font-size:13.5px;}
  .cmt-time{color:var(--sub);font-size:12px;}
  .cmt-mine{font-size:11px;font-weight:700;color:var(--brand-d);background:var(--brand-soft);border-radius:20px;padding:1px 8px;}
  .cmt-body{white-space:pre-wrap;word-break:break-word;margin:7px 0 7px 38px;font-size:13.5px;line-height:1.6;}
  .cmt.deleted .cmt-body{color:var(--sub);font-style:italic;}
  .cmt-actions{margin-left:38px;display:flex;gap:14px;}
  .cmt-actions button{background:none;border:none;color:var(--sub);font-size:12.5px;font-weight:600;cursor:pointer;padding:0;font-family:inherit;}
  .cmt-actions button:hover{color:var(--brand-d);text-decoration:underline;}
  .cmt-children{margin-left:30px;border-left:2px solid var(--line);padding-left:14px;margin-top:4px;}
  .cmt-slot .cmt-reply-box,.cmt-slot .cmt-edit-box{margin:8px 0 4px 38px;}
  .cmt-box-foot{display:flex;justify-content:flex-end;gap:8px;margin-top:7px;}
  .empty{color:var(--sub);font-size:13px;padding:12px 0;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📝 기획서 <?php echo $isEdit?'수정':'등록'; ?></span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('planning'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1>기획서 <?php echo $isEdit?'수정':'등록'; ?></h1>
        <div class="pg-sub"><a href="planning.php" data-lnb-title="planning" style="color:var(--sub);text-decoration:none">기획서</a> › <?php echo $isEdit?('#'.$__id.' 수정'):'새 기획서'; ?></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="panel form-panel">
      <form id="plForm" onsubmit="return false;">
        <div class="grid">
          <div class="field c6"><label>구분</label><input id="f_gubun" placeholder="예: 정산관리"></div>
          <div class="field c6"><label>제목 <span class="req">*</span></label><input id="f_title" required placeholder="기획서 제목"></div>
          <div class="field c4"><label>작성여부</label>
            <select id="f_done" class="form-select">
              <option value="">(미지정)</option>
              <option value="완료">완료</option>
              <option value="진행 중">진행 중</option>
            </select>
          </div>
          <div class="field c8"><label>시스템</label><input id="f_system" placeholder="예: 관리자 web 병원 web"></div>
          <div class="field c12"><label>와이어프레임 (URL)</label><input id="f_wireframe" type="url" placeholder="https:// 와이어프레임 링크"></div>
          <div class="field c6"><label>최초 작성자</label><input id="f_author" placeholder="이름"></div>
          <div class="field c6"><label>최초작성일자</label><input id="f_doc_date" type="date"></div>
        </div>
        <div class="form-foot">
          <button type="button" class="btn primary" id="btnSave">💾 <?php echo $isEdit?'수정 저장':'등록'; ?></button>
          <button type="button" class="btn ghost" onclick="location.href='planning.php'">취소</button>
        </div>
      </form>
    </div>

    <?php if ($isEdit): ?>
    <div class="panel hist-panel" id="historyPanel">
      <div class="hist-head">
        <h2>🕓 변경이력 <span class="count-chip" id="histCount">0</span></h2>
        <div class="hist-tools">
          <select id="histPageSize" class="hist-size" title="페이지당 표시 개수">
            <option value="10">10개씩</option><option value="20">20개씩</option><option value="30">30개씩</option><option value="50">50개씩</option><option value="100">100개씩</option>
          </select>
        </div>
      </div>
      <div class="hist-scroll">
        <table class="hist-table">
          <thead><tr><th class="hist-no">번호</th><th>변경전</th><th>변경후</th><th class="hist-dt">변경일시</th><th class="hist-by">변경자</th></tr></thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="empty hidden" id="histEmpty">변경 이력이 없습니다.</div>
      <div class="hist-pager hidden" id="histPager"></div>
    </div>

    <div class="panel cmt-panel" id="commentsPanel">
      <h2>💬 댓글 <span class="count-chip" id="cmtCount">0</span></h2>
      <div class="cmt-new">
        <textarea id="cmtInput" rows="3" placeholder="댓글을 입력하세요... (기획서에 대한 의견·질문을 남길 수 있습니다)"></textarea>
        <div class="cmt-box-foot"><button type="button" class="btn primary sm" id="cmtAddBtn">댓글 등록</button></div>
      </div>
      <div id="cmtList"></div>
      <div class="empty hidden" id="cmtEmpty">아직 댓글이 없습니다. 첫 댓글을 남겨보세요.</div>
    </div>
    <?php endif; ?>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const API='planning.php?api=';
const ID=<?php echo $isEdit?(int)$__id:'null'; ?>;
const PLAN_ID=ID;
const ME=<?php $u=auth_user(); echo json_encode(['id'=>(int)($u['id']??0),'name'=>($u['name']??'')], JSON_UNESCAPED_UNICODE); ?>;
const FIELDS=['gubun','title','done','system','wireframe','author','doc_date'];
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

async function loadItem(){
  if(!ID) return;
  try{
    const r=await fetch(API+'planning&id='+ID,{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const d=await r.json();
    FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); if(el) el.value=(d[k]==null?'':d[k]); });
  }catch(e){ alert('불러오기 실패: '+e.message); }
}
document.getElementById('btnSave').onclick=async ()=>{
  const obj={}; FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); obj[k]=el?(el.value||'').trim():''; });
  if(!obj.title){ alert('제목은 필수입니다.'); document.getElementById('f_title').focus(); return; }
  try{
    const url=ID?(API+'planning&id='+ID):(API+'planning');
    const r=await fetch(url,{method:ID?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    sessionStorage.setItem('planToast', ID?'수정되었습니다':'등록되었습니다');
    location.href='planning.php';
  }catch(e){ alert('저장 실패: '+e.message); }
};

/* ===== 변경이력 ===== */
let HISTORY=[]; let histPage=1;
function histDt(s){ return (s||'').replace('T',' ').slice(0,19); }
function histCell(label,v){ const t=(v==null||v==='')?'(없음)':v; return '<b>'+esc(label)+':</b> '+esc(t); }
function renderHistory(){
  const sizeSel=document.getElementById('histPageSize'); const size=parseInt(sizeSel?sizeSel.value:'10',10)||10;
  const total=HISTORY.length; document.getElementById('histCount').textContent=total;
  const body=document.getElementById('histBody'), emptyEl=document.getElementById('histEmpty'), pagerEl=document.getElementById('histPager');
  if(total===0){ body.innerHTML=''; emptyEl.classList.remove('hidden'); pagerEl.classList.add('hidden'); pagerEl.innerHTML=''; return; }
  emptyEl.classList.add('hidden');
  const pages=Math.ceil(total/size); if(histPage>pages)histPage=pages; if(histPage<1)histPage=1;
  const start=(histPage-1)*size; const slice=HISTORY.slice(start,start+size);
  body.innerHTML=slice.map((hh,i)=>{ const no=total-(start+i);
    return '<tr><td class="hist-no">'+no+'</td><td class="hist-chg">'+histCell(hh.fieldLabel,hh.beforeVal)+'</td><td class="hist-chg">'+histCell(hh.fieldLabel,hh.afterVal)+'</td><td class="hist-dt">'+esc(histDt(hh.changedAt))+'</td><td class="hist-by">'+esc(hh.changedBy||'')+'</td></tr>'; }).join('');
  if(pages<=1){ pagerEl.classList.add('hidden'); pagerEl.innerHTML=''; }
  else{ pagerEl.classList.remove('hidden'); pagerEl.innerHTML='<button type="button" class="btn ghost sm" '+(histPage<=1?'disabled':'')+' onclick="gotoHistPage('+(histPage-1)+')">이전</button><span class="hist-pageinfo">'+histPage+' / '+pages+'</span><button type="button" class="btn ghost sm" '+(histPage>=pages?'disabled':'')+' onclick="gotoHistPage('+(histPage+1)+')">다음</button>'; }
}
function gotoHistPage(p){ histPage=p; renderHistory(); }
async function loadHistory(){
  if(!PLAN_ID) return;
  try{ const r=await fetch(API+'history&planId='+PLAN_ID,{cache:'no-store'}); if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); } HISTORY=await r.json(); histPage=1; renderHistory(); }
  catch(e){ document.getElementById('histBody').innerHTML='<tr><td colspan="5" class="empty">변경 이력을 불러오지 못했습니다: '+esc(e.message)+'</td></tr>'; }
}
document.getElementById('histPageSize')?.addEventListener('change',()=>{ histPage=1; renderHistory(); });

/* ===== 댓글/대댓글 ===== */
let cmtMap={};
function fmtTime(s){ return (s||'').slice(0,16).replace('T',' '); }
function buildBox(kind,id){ const ph=kind==='reply'?'답글을 입력하세요...':''; const okLbl=kind==='reply'?'답글 등록':'수정 완료'; const fn=kind==='reply'?'submitReply':'submitEdit';
  return `<div class="cmt-${kind}-box"><textarea rows="2" placeholder="${ph}"></textarea><div class="cmt-box-foot"><button type="button" class="btn primary sm" onclick="${fn}(${id},this)">${okLbl}</button><button type="button" class="btn ghost sm" onclick="closeBoxes()">취소</button></div></div>`; }
function renderNode(c){
  const deleted=!!c.isDeleted; const children=(c.children||[]).map(renderNode).join('');
  if(deleted && !children) return '';
  const mine=ME.id && c.authorId===ME.id; const av=esc((c.authorName||'?').substring(0,1));
  const bodyHtml=deleted?'삭제된 댓글입니다.':esc(c.body).replace(/\n/g,'<br>');
  const canEdit=!deleted && mine;
  const actions=deleted?'':`<div class="cmt-actions"><button onclick="startReply(${c.id})">답글</button>`+(canEdit?`<button onclick="startEdit(${c.id})">수정</button><button onclick="delComment(${c.id})">삭제</button>`:'')+`</div>`;
  return `<div class="cmt${deleted?' deleted':''}" data-id="${c.id}"><div class="cmt-head"><span class="cmt-avatar">${av}</span><span class="cmt-author">${esc(c.authorName||'알 수 없음')}</span>`+(mine?'<span class="cmt-mine">나</span>':'')+`<span class="cmt-time">${fmtTime(c.createdAt)}</span></div><div class="cmt-body" id="cbody-${c.id}">${bodyHtml}</div>`+actions+`<div class="cmt-slot" id="cslot-${c.id}"></div>`+(children?`<div class="cmt-children">${children}</div>`:'')+`</div>`;
}
async function loadComments(){
  if(!PLAN_ID) return;
  try{
    const r=await fetch(API+'comments&planId='+PLAN_ID,{cache:'no-store'}); if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const flat=await r.json(); cmtMap={}; flat.forEach(c=>{ c.children=[]; cmtMap[c.id]=c; });
    const roots=[]; flat.forEach(c=>{ if(c.parentId && cmtMap[c.parentId]) cmtMap[c.parentId].children.push(c); else roots.push(c); });
    document.getElementById('cmtCount').textContent=flat.filter(c=>!c.isDeleted).length;
    const list=document.getElementById('cmtList'); const html=roots.map(renderNode).join('');
    if(!html){ list.innerHTML=''; document.getElementById('cmtEmpty').classList.remove('hidden'); return; }
    document.getElementById('cmtEmpty').classList.add('hidden'); list.innerHTML=html;
  }catch(e){ document.getElementById('cmtList').innerHTML='<div class="empty">댓글을 불러오지 못했습니다: '+esc(e.message)+'</div>'; }
}
function closeBoxes(){ document.querySelectorAll('.cmt-slot').forEach(s=>s.innerHTML=''); document.querySelectorAll('.cmt-body').forEach(b=>b.style.display=''); }
function startReply(id){ closeBoxes(); const slot=document.getElementById('cslot-'+id); slot.innerHTML=buildBox('reply',id); slot.querySelector('textarea').focus(); }
function startEdit(id){ closeBoxes(); const c=cmtMap[id]; if(!c) return; document.getElementById('cbody-'+id).style.display='none'; const slot=document.getElementById('cslot-'+id); slot.innerHTML=buildBox('edit',id); const ta=slot.querySelector('textarea'); ta.value=c.body; ta.focus(); }
async function postComment(text,parentId){
  try{ const r=await fetch(API+'comments',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({planId:PLAN_ID, parentId:parentId||null, body:text})}); if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); } await loadComments(); toast(parentId?'답글이 등록되었습니다':'댓글이 등록되었습니다'); }
  catch(e){ alert('등록 실패: '+e.message); }
}
async function submitReply(parentId,btn){ const ta=btn.closest('.cmt-reply-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } await postComment(t,parentId); }
async function submitEdit(id,btn){ const ta=btn.closest('.cmt-edit-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; }
  try{ const r=await fetch(API+'comments&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({body:t})}); if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); } await loadComments(); toast('댓글이 수정되었습니다'); }
  catch(e){ alert('수정 실패: '+e.message); }
}
async function delComment(id){ if(!confirm('이 댓글을 삭제하시겠습니까? (답글은 그대로 유지됩니다)')) return;
  try{ const r=await fetch(API+'comments&id='+id,{method:'DELETE'}); if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); } await loadComments(); toast('댓글이 삭제되었습니다'); }
  catch(e){ alert('삭제 실패: '+e.message); }
}

(async function boot(){
  await loadItem();
  if(ID){
    const addBtn=document.getElementById('cmtAddBtn');
    if(addBtn) addBtn.onclick=()=>{ const ta=document.getElementById('cmtInput'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } postComment(t,null).then(()=>{ ta.value=''; }); };
    loadHistory(); loadComments();
  } else {
    setTimeout(()=>document.getElementById('f_title').focus(),60);
  }
})();
</script>
</body>
</html>

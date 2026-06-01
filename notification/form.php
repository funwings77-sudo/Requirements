<?php
/* =====================================================================
   공지사항 등록 / 수정 (form.php)
   - 데이터 API는 notification.php(?api=notifications)를 재사용
   - 진입: form.php(등록) / form.php?id=N(수정)
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_perm('notification', (isset($_GET['id']) && $_GET['id'] !== '') ? 'update' : 'write');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>공지사항 등록 / 수정</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<!-- Toast UI Editor (NHN) — 내용 HTML 편집기 -->
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<script src="https://uicdn.toast.com/editor/latest/i18n/ko-kr.js"></script>
<style>
  .form-foot{display:flex;gap:10px;padding:18px 2px 4px;border-top:1px solid var(--line);margin-top:18px;}
  .panel.form-panel{padding:22px 24px;}
  .imp-row{display:flex;align-items:center;gap:8px;}   /* 단일 컬럼 배치 — 다른 필드와 동일하게 label 아래 노출(상단 padding 보정 제거) */
  .imp-row input[type=checkbox]{width:18px;height:18px;cursor:pointer;}
  .imp-row label{cursor:pointer;font-weight:600;color:var(--ink);}
  /* Toast UI Editor 컨테이너 — 폼 폭에 맞춤 */
  #editorRoot{border-radius:10px;overflow:hidden;}
  /* ===== 변경이력 ===== */
  .hist-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
  .hist-panel h2{margin:0;font-size:16px;display:flex;align-items:center;gap:8px;}
  .hist-tools{display:flex;align-items:center;gap:8px;}
  .hist-size{height:32px;border:1px solid #e2e6ec;border-radius:8px;padding:0 10px;font-size:13px;font-family:inherit;background:#fff;color:var(--ink);cursor:pointer;}
  .hist-size:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(47,107,255,.14);}
  .hist-scroll{overflow-x:auto;}
  .hist-table{width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;min-width:0;}
  .hist-table th,.hist-table td{padding:9px 12px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .hist-table td{text-align:left;}
  .hist-table thead th{background:#f8f9fb;color:var(--sub);font-weight:700;font-size:12.5px;white-space:nowrap;}
  .hist-table tbody tr:hover{background:#f8f9fb;}
  .hist-no{width:50px;text-align:center;color:var(--sub);white-space:nowrap;}
  .hist-dt{width:150px;white-space:nowrap;color:var(--sub);}
  .hist-by{width:84px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .hist-chg{word-break:break-word;}
  .hist-chg b{color:var(--sub);font-weight:700;}
  .hist-pager{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:14px;}
  .hist-pageinfo{font-size:13px;color:var(--sub);}
  /* ===== 댓글 ===== */
  .cmt-panel h2{margin:0 0 14px;font-size:16px;display:flex;align-items:center;gap:8px;}
  .cmt-new textarea,.cmt-reply-box textarea,.cmt-edit-box textarea{width:100%;border:1px solid #e2e6ec;border-radius:10px;padding:10px 12px;font-size:13.5px;font-family:inherit;resize:vertical;min-height:46px;background:#fff;color:var(--ink);}
  .cmt-new textarea:focus,.cmt-reply-box textarea:focus,.cmt-edit-box textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(47,107,255,.14);}
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
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📢 공지 등록/수정</span>
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
        <h1 id="formTitle">공지 등록</h1>
        <div class="pg-sub"><a href="notification.php" data-lnb-title="notification" style="color:var(--sub);text-decoration:none">공지사항</a> › <span id="crumb">새 공지</span></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="panel form-panel">
      <form id="notifForm">
        <input type="hidden" id="f_id">
        <div class="grid">
          <div class="field c12"><label>제목 <span class="req">*</span></label><input id="f_title" required placeholder="공지 제목" maxlength="300"></div>
          <div class="field c12"><label>카테고리</label>
            <select id="f_category">
              <option value="일반">일반</option>
              <option value="공지">공지</option>
              <option value="긴급">긴급</option>
              <option value="시스템">시스템</option>
            </select>
          </div>
          <div class="field c12"><label>중요 공지</label><div class="imp-row"><input type="checkbox" id="f_isImportant"><label for="f_isImportant">⭐ 중요 공지로 표시</label></div></div>
          <div class="field c12"><label>게시일</label><input id="f_publishDate" type="date"></div>
          <div class="field c12"><label>상태</label>
            <select id="f_status">
              <option value="게시">게시</option>
              <option value="임시저장">임시저장</option>
              <option value="숨김">숨김</option>
            </select>
          </div>
          <div class="field c12"><label>내용</label>
            <div id="editorRoot"></div>
            <textarea id="f_content" hidden></textarea>
          </div>
        </div>
        <div class="form-foot">
          <?php if (auth_is_admin()): ?><button type="submit" class="btn primary">💾 저장</button><?php endif; ?>
          <button type="button" class="btn ghost" onclick="location.href='notification.php'"><?= auth_is_admin() ? '취소' : '목록으로' ?></button>
        </div>
      </form>
    </div>

    <?php if (isset($_GET['id']) && $_GET['id'] !== ''): ?>
    <div class="panel hist-panel" id="historyPanel">
      <div class="hist-head">
        <h2>🕓 변경이력 <span class="count-chip" id="histCount">0</span></h2>
        <div class="hist-tools">
          <select id="histPageSize" class="hist-size" title="페이지당 표시 개수">
            <option value="10">10개씩</option>
            <option value="20">20개씩</option>
            <option value="30">30개씩</option>
            <option value="50">50개씩</option>
            <option value="100">100개씩</option>
          </select>
        </div>
      </div>
      <div class="hist-scroll">
        <table class="hist-table">
          <thead><tr>
            <th class="hist-no">번호</th><th>변경전</th><th>변경후</th>
            <th class="hist-dt">변경일시</th><th class="hist-by">변경자</th>
          </tr></thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="empty hidden" id="histEmpty">변경 이력이 없습니다.</div>
      <div class="hist-pager hidden" id="histPager"></div>
    </div>

    <div class="panel cmt-panel" id="commentsPanel">
      <h2>💬 댓글 <span class="count-chip" id="cmtCount">0</span></h2>
      <div class="cmt-new">
        <textarea id="cmtInput" rows="3" placeholder="댓글을 입력하세요... (공지에 대한 의견·질문을 남길 수 있습니다)"></textarea>
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
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

const API='notification.php?api=';
const IS_ADMIN = <?php echo auth_is_admin() ? 'true' : 'false'; ?>;   // 비관리자는 저장 버튼 비노출 + Enter 제출 가드
const ME = <?php $__me=auth_user(); echo json_encode(['id'=>(int)($__me['id']??0),'name'=>($__me['name']??''),'admin'=>auth_is_admin()], JSON_UNESCAPED_UNICODE); ?>;
const NOTIF_ID = <?php echo (isset($_GET['id']) && $_GET['id']!=='') ? (int)$_GET['id'] : 'null'; ?>;
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
const form=document.getElementById('notifForm');

/* 게시일 기본값 = 오늘 (등록 시) */
function todayStr(){ const d=new Date(); const m=String(d.getMonth()+1).padStart(2,'0'); const dd=String(d.getDate()).padStart(2,'0'); return d.getFullYear()+'-'+m+'-'+dd; }

/* ===== 내용 HTML 편집기 (Toast UI Editor v3+, CDN 로드) =====
 *   - 관리자(IS_ADMIN)면 편집모드, 비관리자면 뷰어모드(읽기 전용)
 *   - 내부는 마크다운/위지윅이지만 DB 저장/로드는 HTML로 통일 — setHTML/getHTML */
let __editor = null;
function initEditor(canEdit, html){
  const root = document.getElementById('editorRoot'); if(!root) return;
  if(canEdit){
    __editor = new toastui.Editor({
      el: root, height: '420px',
      initialEditType: 'wysiwyg', previewStyle: 'vertical', language: 'ko-KR',
      hideModeSwitch: false, usageStatistics: false,
      initialValue: '',
    });
  } else {
    __editor = toastui.Editor.factory({ el: root, viewer: true, language: 'ko-KR', initialValue: html || '' });
  }
  if(canEdit && html) setEditorHTML(html);
}
function setEditorHTML(html){ if(!__editor) return; if(__editor.setHTML) __editor.setHTML(html||''); else if(__editor.setMarkdown) __editor.setMarkdown(html||''); }
function syncEditor(){ const ta=document.getElementById('f_content'); if(!ta||!__editor) return; ta.value = (__editor.getHTML?__editor.getHTML():'') || ''; }

form.addEventListener('submit', async e=>{
  e.preventDefault();
  if(!IS_ADMIN) return;   // 비관리자: Enter 등 제출 시도 무효(저장 버튼도 숨겨져 있음)
  syncEditor();   // Toast UI 편집기 내용을 f_content에 반영
  const idVal=document.getElementById('f_id').value;
  const obj={
    title:       (document.getElementById('f_title').value||'').trim(),
    category:    document.getElementById('f_category').value,
    isImportant: document.getElementById('f_isImportant').checked ? 1 : 0,
    publishDate: document.getElementById('f_publishDate').value,
    status:      document.getElementById('f_status').value,
    content:     document.getElementById('f_content').value,
  };
  if(!obj.title){ alert('제목을 입력하세요'); document.getElementById('f_title').focus(); return; }
  try{
    const url=API+'notifications'+(idVal?'&id='+idVal:'');
    const r=await fetch(url,{method:idVal?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
    if(!r.ok){ const er=await r.json().catch(()=>({})); throw new Error(er.error||('HTTP '+r.status)); }
    sessionStorage.setItem('notifToast', idVal?'공지가 수정되었습니다':'공지가 등록되었습니다');
    location.href='notification.php';
  }catch(err){ alert('저장 실패: '+err.message); }
});

/* ===== 변경이력 ===== */
let HISTORY = [];      // 서버에서 받은 변경이력(최신순 DESC)
let histPage = 1;
function histDt(s){ return (s||'').replace('T',' ').slice(0,19); }
function histCell(label, v){ const t=(v==null||v==='')?'(없음)':v; return '<b>'+esc(label)+':</b> '+esc(t); }
function renderHistory(){
  const sizeSel=document.getElementById('histPageSize');
  const size=parseInt(sizeSel?sizeSel.value:'10',10)||10;
  const total=HISTORY.length;
  document.getElementById('histCount').textContent=total;
  const body=document.getElementById('histBody');
  const emptyEl=document.getElementById('histEmpty');
  const pagerEl=document.getElementById('histPager');
  if(total===0){ body.innerHTML=''; emptyEl.classList.remove('hidden'); pagerEl.classList.add('hidden'); pagerEl.innerHTML=''; return; }
  emptyEl.classList.add('hidden');
  const pages=Math.ceil(total/size);
  if(histPage>pages) histPage=pages; if(histPage<1) histPage=1;
  const start=(histPage-1)*size;
  const slice=HISTORY.slice(start,start+size);
  body.innerHTML=slice.map((h,i)=>{
    const no=total-(start+i);
    return '<tr><td class="hist-no">'+no+'</td>'
      +'<td class="hist-chg">'+histCell(h.fieldLabel,h.beforeVal)+'</td>'
      +'<td class="hist-chg">'+histCell(h.fieldLabel,h.afterVal)+'</td>'
      +'<td class="hist-dt">'+esc(histDt(h.changedAt))+'</td>'
      +'<td class="hist-by">'+esc(h.changedBy||'')+'</td></tr>';
  }).join('');
  if(pages<=1){ pagerEl.classList.add('hidden'); pagerEl.innerHTML=''; }
  else{
    pagerEl.classList.remove('hidden');
    pagerEl.innerHTML='<button type="button" class="btn ghost sm" '+(histPage<=1?'disabled':'')+' onclick="gotoHistPage('+(histPage-1)+')">이전</button>'
      +'<span class="hist-pageinfo">'+histPage+' / '+pages+'</span>'
      +'<button type="button" class="btn ghost sm" '+(histPage>=pages?'disabled':'')+' onclick="gotoHistPage('+(histPage+1)+')">다음</button>';
  }
}
function gotoHistPage(p){ histPage=p; renderHistory(); }
async function loadHistory(){
  if(!NOTIF_ID) return;
  try{
    const r=await fetch(API+'history&notifId='+NOTIF_ID,{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    HISTORY=await r.json(); histPage=1; renderHistory();
  }catch(e){ document.getElementById('histBody').innerHTML='<tr><td colspan="5" class="empty">변경 이력을 불러오지 못했습니다: '+esc(e.message)+'</td></tr>'; }
}
document.getElementById('histPageSize')?.addEventListener('change',()=>{ histPage=1; renderHistory(); });

/* ===== 댓글/대댓글 ===== */
let cmtMap = {};
function fmtTime(s){ return (s||'').slice(0,16).replace('T',' '); }
function buildBox(kind, id, val){
  const ph = kind==='reply' ? '답글을 입력하세요...' : '';
  const okLbl = kind==='reply' ? '답글 등록' : '수정 완료';
  const fn = kind==='reply' ? 'submitReply' : 'submitEdit';
  return `<div class="cmt-${kind}-box"><textarea rows="2" placeholder="${ph}"></textarea>`
    + `<div class="cmt-box-foot"><button type="button" class="btn primary sm" onclick="${fn}(${id},this)">${okLbl}</button>`
    + `<button type="button" class="btn ghost sm" onclick="closeBoxes()">취소</button></div></div>`;
}
function renderNode(c){
  const deleted = !!c.isDeleted;
  const children = (c.children||[]).map(renderNode).join('');
  if(deleted && !children) return '';
  const mine = ME.id && c.authorId === ME.id;
  const av = esc((c.authorName||'?').substring(0,1));
  const bodyHtml = deleted ? '삭제된 댓글입니다.' : esc(c.body).replace(/\n/g,'<br>');
  const canEdit = !deleted && mine;
  const actions = deleted ? '' : `<div class="cmt-actions">`
    + `<button onclick="startReply(${c.id})">답글</button>`
    + (canEdit ? `<button onclick="startEdit(${c.id})">수정</button><button onclick="delComment(${c.id})">삭제</button>` : '')
    + `</div>`;
  return `<div class="cmt${deleted?' deleted':''}" data-id="${c.id}">`
    + `<div class="cmt-head"><span class="cmt-avatar">${av}</span><span class="cmt-author">${esc(c.authorName||'알 수 없음')}</span>`
    + (mine?'<span class="cmt-mine">나</span>':'') + `<span class="cmt-time">${fmtTime(c.createdAt)}</span></div>`
    + `<div class="cmt-body" id="cbody-${c.id}">${bodyHtml}</div>`
    + actions
    + `<div class="cmt-slot" id="cslot-${c.id}"></div>`
    + (children ? `<div class="cmt-children">${children}</div>` : '')
    + `</div>`;
}
async function loadComments(){
  if(!NOTIF_ID) return;
  try{
    const r = await fetch(API+'comments&notifId='+NOTIF_ID,{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const flat = await r.json();
    cmtMap = {}; flat.forEach(c=>{ c.children=[]; cmtMap[c.id]=c; });
    const roots = [];
    flat.forEach(c=>{ if(c.parentId && cmtMap[c.parentId]) cmtMap[c.parentId].children.push(c); else roots.push(c); });
    document.getElementById('cmtCount').textContent = flat.filter(c=>!c.isDeleted).length;
    const list = document.getElementById('cmtList');
    const html = roots.map(renderNode).join('');
    if(!html){ list.innerHTML=''; document.getElementById('cmtEmpty').classList.remove('hidden'); return; }
    document.getElementById('cmtEmpty').classList.add('hidden');
    list.innerHTML = html;
  }catch(e){ document.getElementById('cmtList').innerHTML = '<div class="empty">댓글을 불러오지 못했습니다: '+esc(e.message)+'</div>'; }
}
function closeBoxes(){
  document.querySelectorAll('.cmt-slot').forEach(s=>s.innerHTML='');
  document.querySelectorAll('.cmt-body').forEach(b=>b.style.display='');
}
function startReply(id){ closeBoxes(); const slot=document.getElementById('cslot-'+id); slot.innerHTML=buildBox('reply',id); slot.querySelector('textarea').focus(); }
function startEdit(id){ closeBoxes(); const c=cmtMap[id]; if(!c) return; document.getElementById('cbody-'+id).style.display='none';
  const slot=document.getElementById('cslot-'+id); slot.innerHTML=buildBox('edit',id); const ta=slot.querySelector('textarea'); ta.value=c.body; ta.focus(); }
async function postComment(text, parentId){
  try{
    const r=await fetch(API+'comments',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({notifId:NOTIF_ID, parentId:parentId||null, body:text})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast(parentId?'답글이 등록되었습니다':'댓글이 등록되었습니다');
  }catch(e){ alert('등록 실패: '+e.message); }
}
async function submitReply(parentId, btn){ const ta=btn.closest('.cmt-reply-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } await postComment(t, parentId); }
async function submitEdit(id, btn){
  const ta=btn.closest('.cmt-edit-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; }
  try{
    const r=await fetch(API+'comments&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({body:t})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast('댓글이 수정되었습니다');
  }catch(e){ alert('수정 실패: '+e.message); }
}
async function delComment(id){
  if(!confirm('이 댓글을 삭제하시겠습니까? (답글은 그대로 유지됩니다)')) return;
  try{
    const r=await fetch(API+'comments&id='+id,{method:'DELETE'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast('댓글이 삭제되었습니다');
  }catch(e){ alert('삭제 실패: '+e.message); }
}
if(NOTIF_ID){
  const addBtn=document.getElementById('cmtAddBtn');
  if(addBtn) addBtn.onclick=()=>{ const ta=document.getElementById('cmtInput'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } postComment(t,null).then(()=>{ ta.value=''; }); };
}

(async function boot(){
  const id=new URLSearchParams(location.search).get('id');
  if(id){
    document.getElementById('formTitle').textContent = IS_ADMIN ? '공지 수정' : '공지 상세';
    document.getElementById('crumb').textContent='#'+id+' '+(IS_ADMIN?'수정':'상세');
    try{
      const r=await fetch(API+'notifications&id='+id,{cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      const d=await r.json();
      document.getElementById('f_id').value=id;
      document.getElementById('f_title').value       = d.title || '';
      document.getElementById('f_category').value    = d.category || '일반';
      document.getElementById('f_isImportant').checked = !!d.isImportant;
      document.getElementById('f_publishDate').value = d.publishDate || '';
      document.getElementById('f_status').value      = d.status || '게시';
      initEditor(IS_ADMIN, d.content || '');                          // Toast UI: 관리자=편집모드, 비관리자=뷰어
    }catch(e){ alert('공지를 불러오지 못했습니다: '+e.message); initEditor(IS_ADMIN, ''); }
    loadHistory();
    loadComments();
  } else {
    initEditor(IS_ADMIN, '');
    document.getElementById('f_publishDate').value = todayStr();   // 신규 등록 시 게시일 = 오늘
    setTimeout(()=>document.getElementById('f_title').focus(),60);
  }
})();
</script>
</body>
</html>

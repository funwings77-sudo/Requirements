<?php require __DIR__ . '/../auth.php'; require_login(); require __DIR__ . '/../lib.php';
  // 등록 폼 = 쓰기 권한, 수정 폼(?id=) = 수정 권한 필요
  require_perm('requirements', (isset($_GET['id']) && $_GET['id'] !== '') ? 'update' : 'write'); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>요구사항 등록 / 수정</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<!-- Toast UI Editor (NHN) — 상세설명 HTML 편집기 (오프라인용 로컬 호스팅 v3.2.2) -->
<link rel="stylesheet" href="../assets/vendor/toastui-editor/toastui-editor.min.css">
<script src="../assets/vendor/toastui-editor/toastui-editor-all.min.js"></script>
<script src="../assets/vendor/toastui-editor/i18n/ko-kr.js"></script>
<style>
  .form-foot{display:flex;gap:10px;padding:18px 2px 4px;border-top:1px solid var(--line);margin-top:18px;}
  .panel.form-panel{padding:22px 24px;}
  /* Toast UI Editor 컨테이너 — 폼 폭에 맞춤 */
  #editorRoot{border-radius:10px;overflow:hidden;}
  /* ===== 변경이력 ===== */
  .hist-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
  .hist-panel h2{margin:0;font-size:16px;display:flex;align-items:center;gap:8px;}
  .hist-tools{display:flex;align-items:center;gap:8px;}
  .hist-size{height:32px;border:1px solid #e2e6ec;border-radius:8px;padding:0 10px;font-size:13px;font-family:inherit;background:#fff;color:var(--ink);cursor:pointer;}
  .hist-size:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(47,107,255,.14);}
  .hist-scroll{overflow-x:auto;}
  .hist-table{width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;min-width:0;}   /* style.css 전역 table{min-width:1100px} 무력화 */
  .hist-table th,.hist-table td{padding:9px 12px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .hist-table td{text-align:left;}
  .hist-table thead th{background:#f8f9fb;color:var(--sub);font-weight:700;font-size:12.5px;white-space:nowrap;}
  .hist-table tbody tr:hover{background:#f8f9fb;}
  .hist-no{width:50px;text-align:center;color:var(--sub);white-space:nowrap;}
  .hist-dt{width:150px;white-space:nowrap;color:var(--sub);}
  .hist-by{width:84px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .hist-chg{word-break:break-word;}   /* table-layout:fixed → 변경전/후가 남은 폭을 균등 분배(가로 스크롤 없이 5컬럼 모두 노출) */
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
  /* 보기 전용(타인 글·비관리자): 입력 컨트롤을 읽기전용 톤으로 */
  .view-only input[readonly],.view-only textarea[readonly],.view-only select:disabled{background:#f5f6fa;color:#4b5160;cursor:default;opacity:1;-webkit-text-fill-color:#4b5160;}
  .view-only .rte{background:#f5f6fa;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📋 요구사항 등록/수정</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('requirements'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 id="formTitle">요구사항 등록</h1>
        <div class="pg-sub"><a href="requirements.php" data-lnb-title="requirements" style="color:var(--sub);text-decoration:none">요구사항 목록</a> › <span id="crumb">새 요구사항</span></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <div class="panel form-panel">
      <form id="reqForm">
        <input type="hidden" id="f_id">
        <div class="grid">
          <div class="field c2"><label>우선순위(순번)</label><select id="f_rank"></select></div>
          <div class="field c2"><label>구분 <span class="req">*</span></label><select id="f_category" required></select></div>
          <div class="field c4"><label>대분류 (1st Depth)</label><input id="f_major" placeholder="예: 유입 채널 다변화"></div>
          <div class="field c4"><label>중분류 (2nd Depth)</label><input id="f_middle" placeholder="예: 성과 추적"></div>

          <div class="field c6"><label>요구사항명 <span class="req">*</span></label><input id="f_name" required placeholder="요구사항 제목"></div>
          <div class="field c6"><label>목적 &amp; 필요성</label><input id="f_purpose" placeholder="왜 필요한지 한 줄 요약"></div>

          <div class="field c12"><label>상세 설명</label>
            <div id="editorRoot"></div>
            <textarea id="f_detail" hidden></textarea>
          </div>

          <div class="field c6"><label>참고링크</label><input id="f_link" placeholder="https://"></div>
          <div class="field c6"><label>참고링크</label><input id="f_doc" placeholder="문서명 / 링크"></div>

          <div class="field c3"><label>요청자</label><select id="f_requester"></select></div>
          <div class="field c3"><label>요청파트</label><select id="f_reqPart"></select></div>
          <div class="field c3"><label>요청일자</label><input id="f_reqDate" type="date"></div>
          <div class="field c3"><label>수행파트</label><select id="f_doPart"></select></div>

          <div class="field c3"><label>수행담당자</label><select id="f_doPerson"></select></div>
          <div class="field c3"><label>우선순위(중요)</label><select id="f_priority"></select></div>
          <div class="field c3"><label>중요도</label><select id="f_importance"></select></div>
          <div class="field c3"><label>난이도</label><select id="f_difficulty"></select></div>

          <div class="field c3"><label>예상공수(MD)</label><input id="f_effort" type="number" min="0" step="0.5"></div>
          <div class="field c3"><label>목표 버전</label><select id="f_version"></select></div>
          <div class="field c3"><label>상태</label><select id="f_status"></select></div>
          <div class="field c3"><label>검토자</label><select id="f_reviewer"></select></div>

          <div class="field c3"><label>승인일</label><input id="f_approveDate" type="date"></div>
          <div class="field c3"><label>시작일자</label><input id="f_startDate" type="date"></div>
          <div class="field c3"><label>종료일자(개발반영)</label><input id="f_endDate" type="date"></div>
          <div class="field c3"><label>운영서버반영일자</label><input id="f_prodDate" type="date"></div>

          <div class="field c12"><label>비고</label><textarea id="f_remark" placeholder="기타 참고사항"></textarea></div>
        </div>
        <div class="form-foot">
          <button type="submit" class="btn primary">💾 저장</button>
          <button type="button" class="btn ghost" onclick="location.href='requirements.php'">취소</button>
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
        <textarea id="cmtInput" rows="3" placeholder="댓글을 입력하세요... (요구사항에 대한 의견·질문을 남길 수 있습니다)"></textarea>
        <div class="cmt-box-foot"><button type="button" class="btn primary sm" id="cmtAddBtn">댓글 등록</button></div>
      </div>
      <div id="cmtList"></div>
      <div class="empty hidden" id="cmtEmpty">아직 댓글이 없습니다. 첫 댓글을 남겨보세요.</div>
    </div>
    <?php endif; ?>
  </main>
</div>
<div class="toast" id="toast"></div>

<script src="../app.js"></script>
<script>
const ME = <?php $__me = auth_user(); echo json_encode(['id'=>(int)($__me['id'] ?? 0), 'name'=>($__me['name'] ?? ''), 'admin'=>auth_is_admin()], JSON_UNESCAPED_UNICODE); ?>;
const REQ_ID = <?php echo (isset($_GET['id']) && $_GET['id'] !== '') ? (int)$_GET['id'] : 'null'; ?>;
let CAN_EDIT = true;   // 수정 가능 여부: 신규=항상 true, 수정=관리자||작성자||요청자. false면 보기 전용(저장 버튼 제거)
/* 보기 전용: 저장 버튼 제거 + 입력 컨트롤 읽기전용 + 상세설명 편집기 비활성 */
function applyViewOnly(){
  const form=document.getElementById('reqForm');
  const sb=form.querySelector('button[type="submit"]'); if(sb) sb.remove();
  form.querySelectorAll('input,textarea').forEach(el=>{ if(el.type!=='hidden') el.readOnly=true; });
  form.querySelectorAll('select').forEach(el=>{ el.disabled=true; });
  /* Toast UI 편집기는 boot에서 CAN_EDIT 결과에 따라 viewer 모드로 초기화되므로 여기서 추가 처리 불필요 */
  const tb=document.querySelector('.rte-toolbar'); if(tb) tb.style.display='none';
  const cancel=form.querySelector('.form-foot .btn.ghost'); if(cancel) cancel.textContent='목록으로';
  form.classList.add('view-only');
}
function fillSel(){
  fillSelect(f_rank, OPT.rank);
  fillSelect(f_category, OPT.category, true);
  fillSelect(f_reqPart, OPT.part);
  fillSelect(f_doPart, OPT.part);
  fillSelect(f_priority, OPT.priority, true);
  fillSelect(f_importance, OPT.level, true);
  fillSelect(f_difficulty, OPT.level, true);
  fillSelect(f_version, OPT.version, true);
  fillSelect(f_status, OPT.status, true);
}
/* 요청자·수행담당자: 회원 목록(member.php)에서 선택 */
let MEMBERS=[];
async function loadMembers(){
  try{ const r=await fetch('/member/member.php?api=members',{cache:'no-store'});
    if(r.ok){ const ms=await r.json(); if(Array.isArray(ms)) MEMBERS=[...new Set(ms.map(m=>(m.name||'').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ko')); }
  }catch(e){}
  ['f_requester','f_doPerson','f_reviewer'].forEach(id=>{ const sel=document.getElementById(id); if(!sel) return;
    sel.innerHTML='<option value="">— 선택 —</option>'; MEMBERS.forEach(n=>sel.appendChild(new Option(n,n))); });
}
function ensureOption(sel,val){ if(!val||!sel||sel.tagName!=='SELECT') return; if(![...sel.options].some(o=>o.value===String(val))) sel.appendChild(new Option(val+' (목록 외)',val)); }
/* ===== 상세설명 HTML 편집기 (Toast UI Editor v3+, CDN 로드) =====
 *   - CAN_EDIT면 편집모드, 아니면 뷰어모드(읽기 전용)
 *   - 내부는 위지윅이지만 DB 저장/로드는 HTML로 통일 — setHTML/getHTML */
let __editor = null;
function initEditor(canEdit, html){
  const root = document.getElementById('editorRoot'); if(!root) return;
  if(canEdit){
    __editor = new toastui.Editor({
      el: root, height: '380px',
      initialEditType: 'wysiwyg', previewStyle: 'vertical', language: 'ko-KR',
      hideModeSwitch: false, usageStatistics: false, initialValue: '',
    });
    if(html) setEditorHTML(html);
  } else {
    __editor = toastui.Editor.factory({ el: root, viewer: true, language: 'ko-KR', initialValue: html || '' });
  }
}
function setEditorHTML(html){ if(!__editor) return; if(__editor.setHTML) __editor.setHTML(html||''); else if(__editor.setMarkdown) __editor.setMarkdown(html||''); }
function syncEditor(){ const ta=document.getElementById('f_detail'); if(!ta||!__editor) return; ta.value = (__editor.getHTML?__editor.getHTML():'') || ''; }
const form=document.getElementById('reqForm');
form.addEventListener('submit', async e=>{
  e.preventDefault();
  if(!CAN_EDIT){ return; }   // 보기 전용(타인 글·비관리자)은 저장 불가 — Enter 제출 등도 차단
  syncEditor();   // Toast UI 편집기 내용을 f_detail에 반영
  const obj={}; FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); obj[k]=el?(el.value||'').trim():''; });
  const idVal=document.getElementById('f_id').value;
  if(!useApi){ alert('DB에 연결되어 있지 않아 저장할 수 없습니다.'); return; }
  try{
    const url='?api=requirements'+(idVal?'&id='+idVal:'');
    const r=await fetch(url,{method:idVal?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
    if(!r.ok){ const er=await r.json().catch(()=>({})); throw new Error(er.error||('HTTP '+r.status)); }
    sessionStorage.setItem('reqToast', idVal?'요구사항이 수정되었습니다':'요구사항이 등록되었습니다');
    location.href='requirements.php';
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
    const no=total-(start+i);   // 시간순 번호(가장 오래된=1, 최신=N), 최신순 표시라 위에서부터 큰 번호
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
  if(!REQ_ID) return;
  try{
    const r=await fetch('?api=history&reqId='+REQ_ID,{cache:'no-store'});
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
  if(deleted && !children) return '';   // 답글(보이는 자식)이 없는 삭제 댓글은 숨김 — 답글이 있으면 자리표시로 유지
  const mine = ME.id && c.authorId === ME.id;
  const av = esc((c.authorName||'?').substring(0,1));
  const bodyHtml = deleted ? '삭제된 댓글입니다.' : esc(c.body).replace(/\n/g,'<br>');
  const canEdit = !deleted && mine;   // 작성 본인만 수정/삭제 버튼 노출
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
  if(!REQ_ID) return;
  try{
    const r = await fetch('?api=comments&reqId='+REQ_ID,{cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const flat = await r.json();
    cmtMap = {}; flat.forEach(c=>{ c.children=[]; cmtMap[c.id]=c; });
    const roots = [];
    flat.forEach(c=>{ if(c.parentId && cmtMap[c.parentId]) cmtMap[c.parentId].children.push(c); else roots.push(c); });
    document.getElementById('cmtCount').textContent = flat.filter(c=>!c.isDeleted).length;
    const list = document.getElementById('cmtList');
    const html = roots.map(renderNode).join('');   // 답글 없는 삭제 댓글이 모두 숨겨지면 빈 문자열일 수 있음
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
  if(!useApi){ alert('DB에 연결되어 있지 않아 댓글을 저장할 수 없습니다.'); return; }
  try{
    const r=await fetch('?api=comments',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({reqId:REQ_ID, parentId:parentId||null, body:text})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast(parentId?'답글이 등록되었습니다':'댓글이 등록되었습니다');
  }catch(e){ alert('등록 실패: '+e.message); }
}
async function submitReply(parentId, btn){ const ta=btn.closest('.cmt-reply-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } await postComment(t, parentId); }
async function submitEdit(id, btn){
  const ta=btn.closest('.cmt-edit-box').querySelector('textarea'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; }
  try{
    const r=await fetch('?api=comments&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({body:t})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast('댓글이 수정되었습니다');
  }catch(e){ alert('수정 실패: '+e.message); }
}
async function delComment(id){
  if(!confirm('이 댓글을 삭제하시겠습니까? (답글은 그대로 유지됩니다)')) return;
  try{
    const r=await fetch('?api=comments&id='+id,{method:'DELETE'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    await loadComments(); toast('댓글이 삭제되었습니다');
  }catch(e){ alert('삭제 실패: '+e.message); }
}
if(REQ_ID){
  const addBtn=document.getElementById('cmtAddBtn');
  if(addBtn) addBtn.onclick=()=>{ const ta=document.getElementById('cmtInput'); const t=(ta.value||'').trim(); if(!t){ ta.focus(); return; } postComment(t,null).then(()=>{ ta.value=''; }); };
}

(async function boot(){
  await tryConnectApi();      // OPT를 DB 옵션으로 갱신 + useApi
  renderStatus();
  fillSel();
  await loadMembers();        // 요청자·수행담당자·검토자 셀렉트 채움
  const id=new URLSearchParams(location.search).get('id');
  if(id){
    document.getElementById('formTitle').textContent='요구사항 (#'+id+')';   // 로드 전 중립 제목(수정/상세는 권한 판별 후 확정)
    document.getElementById('crumb').textContent='#'+id;
    try{
      const r=await fetch('?api=requirements&id='+id,{cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      const d=await r.json();
      FIELDS.forEach(k=>{ const el=document.getElementById('f_'+k); if(!el) return; const v=(d[k]==null?'':d[k]); if(el.tagName==='SELECT') ensureOption(el,v); el.value=v; });
      document.getElementById('f_id').value=id;
      CAN_EDIT = !!(ME.admin || (d.createdBy && d.createdBy === ME.name) || (d.requester && d.requester === ME.name));   // 관리자/작성자/요청자만 수정
      if(CAN_EDIT){
        document.getElementById('formTitle').textContent='요구사항 수정 (#'+id+')';
        document.getElementById('crumb').textContent='#'+id+' 수정';
      } else {
        document.getElementById('formTitle').textContent='요구사항 상세 (#'+id+')';
        document.getElementById('crumb').textContent='#'+id+' 상세';
        applyViewOnly();   // 보기 전용: 저장 버튼 제거
      }
      initEditor(CAN_EDIT, d.detail || '');   // Toast UI: 권한별 편집/뷰어 + 기존 상세설명 로드
    }catch(e){ alert('요구사항을 불러오지 못했습니다: '+e.message); initEditor(true, ''); }
    loadHistory();
    loadComments();
  } else {
    initEditor(true, '');                                              // 신규 등록은 항상 편집 가능
    setTimeout(()=>f_category.focus(),60);
  }
})();
</script>
</body>
</html>

<?php require __DIR__ . '/../auth.php'; require_login(); require __DIR__ . '/../lib.php'; require_perm('requirements', 'access'); $PERM = perm_map('requirements');
  // 탭(목록/대시보드) — ?tab=dash면 대시보드 탭. LNB 활성도 그에 맞춤
  $__tab = (isset($_GET['tab']) && $_GET['tab'] === 'dash' && can('dashboard', 'access')) ? 'dashboard' : 'requirements'; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>메디힘 업무관리 시스템</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  /* 전체 항목 노출: 상세설명 외 모든 셀 한 줄(nowrap) */
  #reqTable th, #reqTable td{ white-space:nowrap; vertical-align:middle; }
  #reqTable td.detail{ white-space:pre-wrap; min-width:240px; max-width:360px; }
  #reqTable th.detail-h{ white-space:normal; }
  #reqTable td.name{ font-weight:700; } 
  #reqTable .muted{ color:#9aa3b2; }
  #reqBody tr{ cursor:pointer; }   /* 행 클릭 시 상세페이지 이동 */
  /* 드래그 정렬 */
  #reqTable td.rownum{ cursor:grab; user-select:none; }
  #reqTable td.rownum:active{ cursor:grabbing; }
  #reqTable td.rownum .drag-h{ color:#c2c8d2; margin-right:6px; letter-spacing:-1px; }
  #reqTable tbody tr.dragging{ opacity:.4; }
  #reqTable tbody tr.drop-before > td{ box-shadow: inset 0 2px 0 0 var(--brand); }
  #reqTable tbody tr.drop-after  > td{ box-shadow: inset 0 -2px 0 0 var(--brand); }
  .table-scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; background:#fff; }
  /* === schedule.php 리스트와 동일한 외형 === */
  #reqTable{ border-collapse:separate; border-spacing:0; font-size:12.5px; --bs-table-bg:transparent; --bs-table-hover-bg:transparent; }
  #reqTable thead th{ background:#f3f1fb; color:var(--brand-d); font-weight:800; font-size:12px; padding:8px 10px; text-transform:none; letter-spacing:normal; border-bottom:1px solid var(--line); border-right:1px solid var(--line); }
  #reqTable thead th:last-child{ border-right:none; }
  #reqTable tbody td{ padding:8px 10px; font-size:12.5px; border-bottom:1px solid #eef0f4; border-right:1px solid #eef0f4; color:var(--ink); }
  #reqTable tbody td:last-child{ border-right:none; }
  #reqTable tbody tr:hover td{ background:#fafaff; }
  /* 좌측 컬럼 틀고정: 번호·우선순위(순번)·구분·대분류·중분류·요구사항명 까지 가로 스크롤 시 고정 (left는 JS가 계산) */
  @media(min-width:641px){
    #reqTable .frz{ position:sticky; z-index:2; background:#fff; }
    #reqHead .frz{ z-index:5; background:#ede7fc; color:var(--brand-d); }   /* 고정 헤더도 schedule처럼 보라 */
    #reqBody tr:hover .frz{ background:#fafaff; }
    #reqTable .frz-edge{ box-shadow:6px 0 8px -6px rgba(0,0,0,.18); }
  }
  /* 상단 탭 — account_management.php와 동일한 알약형 UI */
  #reqTabs{ border-bottom:none; display:flex; gap:6px; flex-wrap:wrap; margin:4px 0 16px; }
  #reqTabs .nav-item{ margin:0; }
  #reqTabs .nav-link{ display:inline-flex; align-items:center; gap:6px; border:1px solid var(--line); background:#fff; border-radius:8px; padding:7px 13px; font-size:13px; font-weight:700; color:var(--sub); margin:0; }
  #reqTabs .nav-link .tico{ display:block; }
  #reqTabs .nav-link:hover{ border-color:var(--brand); color:var(--brand-d); }
  #reqTabs .nav-link.active{ background:var(--brand-soft); border-color:var(--brand); color:var(--brand-d); }
  /* 본문 텍스트 center(요구사항명·목적&필요성·비고 제외) */
  #reqTable tbody td{ text-align:center; }
  #reqTable tbody td.rq-left{ text-align:left; }
  /* 인라인 수정 */
  #reqTable td.rq-edit{ cursor:text; }
  #reqTable td.rq-edit:hover{ outline:1px dashed var(--brand); outline-offset:-2px; }
  #reqTable td.editing{ padding:2px !important; }
  .rq-input{ width:100%; box-sizing:border-box; border:1px solid var(--brand); border-radius:5px; padding:4px 6px; font:inherit; font-size:12.5px; background:#fff; color:var(--ink); }
  /* 참고링크 '확인' 버튼 — schedule.php 첨부와 동일 UI */
  .attach-y.attach-open{ cursor:pointer; text-decoration:none; border:1px solid #bfe7cf; background:#dff5ea; color:#127c3c; border-radius:12px; padding:1px 9px; font-size:11px; font-weight:800; }
  .attach-y.attach-open:hover{ background:#127c3c; color:#fff; }
  /* 요구사항 등록 버튼 (Excel 오른쪽) */
  #addBtn{ background:#FB64C9; color:#fff; text-decoration:none; box-shadow:0 4px 12px rgba(251,100,201,.30); }
  #addBtn:hover{ background:#e84fb6; text-decoration:none; }
  /* 검색 패널 (리스트와 분리된 별도 카드) */
  /* 검색영역 collapse 토글 (헤더 + 우측 명시 버튼) */
  .sp-card .card-body{padding:24px 28px;}   /* 검색카드 내부 여백 확장(상하 24·좌우 28) */
  .sp-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line);}
  .sp-head .sp-title{margin:0;font-size:15px;font-weight:700;color:var(--ink);}
  .sp-toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);font-family:inherit;transition:.15s;}
  .sp-toggle-btn:hover{background:var(--brand-soft);color:var(--brand-d);border-color:var(--brand);}
  .sp-toggle-btn .sp-chev{font-size:11px;color:var(--brand);transition:transform .15s;}
  .search-panel{padding-top:18px;}
  .sp-card.collapsed .sp-head{padding-bottom:0;border-bottom:none;}
  .sp-card.collapsed .sp-toggle-btn .sp-chev{transform:rotate(-90deg);}
  .sp-card.collapsed .search-panel{display:none;}
  .sp-text{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
  .sp-text .sp-text-lbl{ font-size:12.5px; font-weight:700; color:#6c7293; margin-right:2px; }
  .sp-text #fTextField{ max-width:150px; }
  .sp-text #search{ flex:1; min-width:220px; max-width:440px; }
  .sp-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:10px 12px; margin-bottom:14px; }
  .sp-grid label{ display:block; font-size:11.5px; font-weight:600; color:var(--sub); margin-bottom:4px; }
  .sp-date{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
  .sp-date .sp-date-lbl{ font-size:12.5px; font-weight:700; color:#6c7293; margin-right:2px; }
  .sp-date .form-select{ max-width:190px; } .sp-date .form-control{ max-width:165px; }
  .sp-date .sp-tilde{ color:var(--sub); font-weight:700; }
  .sp-actions{ display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; border-top:1px solid var(--line); padding-top:14px; }
  .sp-act-center{ display:flex; align-items:center; gap:8px; }
  /* 리스트 카드 헤더: 제목(좌) + DB상태·건수·Excel·등록(우) */
  .list-head{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding-bottom:14px; border-bottom:1px solid var(--line); margin-bottom:16px; }
  .list-head .card-title{ margin:0; }
  .list-actions{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  /* 댓글 수 칩 (목록 '💬 댓글' 컬럼) */
  .cmt-cnt{ display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:20px; background:var(--brand-soft); color:var(--brand-d); font-weight:700; font-size:12px; white-space:nowrap; }
</style>
</head>
<body><header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📋 메디힘 요구사항</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb($__tab === 'dashboard' ? 'dashboard' : 'requirements'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 id="pageTitle" data-lnb-title="<?= $__tab === 'dashboard' ? 'dashboard' : 'requirements' ?>"><?= $__tab === 'dashboard' ? '요구사항 대시보드' : '요구사항 목록' ?></h1>
        <div class="pg-sub" id="pageSub">메디힘 전부서 / 팀원들의 시스템 구축 요청사항</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <ul class="nav nav-tabs" id="reqTabs">
      <li class="nav-item"><button type="button" class="nav-link active" data-v="list"><svg class="tico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg> 요구사항 목록</button></li>
      <?php if (can('dashboard', 'access')): ?><li class="nav-item"><button type="button" class="nav-link" data-v="dash"><svg class="tico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg> 요구사항 대시보드</button></li><?php endif; ?>
    </ul>

    <section id="view-list">
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
              <div><label>우선순위(순번)</label><select id="fRank" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>구분</label><select id="fCat" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>대분류</label><select id="fMajor" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>중분류</label><select id="fMiddle" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>요청파트</label><select id="fReqPart" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>수행파트</label><select id="fDoPart" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>우선순위</label><select id="fPri" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>중요도</label><select id="fImp" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>난이도</label><select id="fDiff" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>목표버전</label><select id="fVer" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>상태</label><select id="fStatus" class="form-select form-select-sm"><option value="">전체</option></select></div>
              <div><label>💬 댓글 정렬</label><select id="fCmtSort" class="form-select form-select-sm"><option value="">기본</option><option value="desc">댓글 많은 순</option><option value="asc">댓글 적은 순</option></select></div>
            </div>
            <div class="sp-date">
              <label class="sp-date-lbl">기간검색</label>
              <select id="fDateField" class="form-select form-select-sm">
                <option value="">기준 일자 선택</option>
                <option value="reqDate">요청일자</option>
                <option value="approveDate">승인일</option>
                <option value="startDate">시작일자</option>
                <option value="endDate">종료일자(개발반영)</option>
                <option value="prodDate">운영서버반영일자</option>
                <option value="updatedAt">최종수정일자</option>
              </select>
              <input type="date" id="fDateFrom" class="form-control form-control-sm">
              <span class="sp-tilde">~</span>
              <input type="date" id="fDateTo" class="form-control form-control-sm">
            </div>
            <div class="sp-text">
              <label class="sp-text-lbl">통합검색</label>
              <select id="fTextField" class="form-select form-select-sm">
                <option value="">전체</option>
                <option value="name">요구사항명</option>
                <option value="requester">요청자</option>
                <option value="reviewer">검토자</option>
                <option value="doPerson">수행담당자</option>
                <option value="purpose">목적&amp;필요성</option>
                <option value="link">참고링크</option>
                <option value="doc">참고링크</option>
                <option value="updatedBy">최종수정자</option>
                <option value="remark">비고</option>
              </select>
              <input id="search" class="form-control form-control-sm" placeholder="검색어를 입력하세요">
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
              <h2 class="card-title">요구사항 목록</h2>
              <span class="count-chip" id="listCount">검색 수 0 / 총 수 0</span>
            </div>
            <div class="list-actions">
              <button type="button" class="btn primary sm" id="exportXlsx">⬇ Excel (.xlsx)</button>
              <a class="btn sm" href="form.php" id="addBtn">＋ 요구사항 등록</a>
            </div>
          </div>
          <div class="table-scroll">
            <table class="table table-hover align-middle" id="reqTable">
              <thead id="reqHead"></thead>
              <tbody id="reqBody"></tbody>
            </table>
          </div>
          <div class="empty hidden" id="emptyMsg">등록된 요구사항이 없습니다. <b>＋ 요구사항 등록</b>으로 등록하세요.</div>
        </div>
      </div>
    </section>

    <?php if (can('dashboard', 'access')): ?>
    <section id="view-dash" class="hidden">
      <div class="stat-row">
        <div class="card stat b1"><div class="stat-top"><span class="stat-ico i1">📋</span><span class="lab">전체 요구사항</span></div><div class="num" id="sTotal">0</div><div class="sub" id="sTotalSub"></div></div>
        <div class="card stat b2"><div class="stat-top"><span class="stat-ico i2">⭐</span><span class="lab">필수 요구사항</span></div><div class="num" id="sReq">0</div><div class="sub" id="sReqSub"></div></div>
        <div class="card stat b3"><div class="stat-top"><span class="stat-ico i3">✅</span><span class="lab">승인/완료</span></div><div class="num" id="sDone">0</div><div class="sub" id="sDoneSub"></div></div>
        <div class="card stat b4"><div class="stat-top"><span class="stat-ico i4">⏱️</span><span class="lab">총 예상공수(MD)</span></div><div class="num" id="sEffort">0</div><div class="sub" id="sEffortSub"></div></div>
      </div>
      <div class="dash-grid">
        <div class="card"><div class="card-body"><h2>구분별 현황</h2><div id="dashCat"></div></div></div>
        <div class="card"><div class="card-body"><h2>우선순위별 현황</h2><div id="dashPri"></div></div></div>
        <div class="card"><div class="card-body"><h2>상태별 현황</h2><div id="dashStatus"></div></div></div>
        <div class="card"><div class="card-body"><h2>목표 버전별 현황</h2><div id="dashVer"></div></div></div>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>
<div class="toast" id="toast"></div>
<script src="../app.js"></script>
<script>
const PERM = <?php echo json_encode($PERM, JSON_UNESCAPED_UNICODE); ?>;
const ME = <?php $__me=auth_user(); echo json_encode(['name'=>$__me?$__me['name']:'', 'admin'=>auth_is_admin()], JSON_UNESCAPED_UNICODE); ?>;   // 현재 로그인 사용자(행 단위 수정/삭제 권한 판별)
const INIT_TAB = <?php echo json_encode($__tab === 'dashboard' ? 'dash' : 'list'); ?>;
/* 행 단위 권한: 관리자는 전체, 비관리자는 본인이 작성(createdBy)했거나 요청자(requester)인 글만 수정/삭제 가능 */
function canModify(d){ return !!(ME.admin || (d && ((d.createdBy && d.createdBy === ME.name) || (d.requester && d.requester === ME.name)))); }
function initSelects(){   // 코드테이블 기반 필터 셀렉트 채움 (각 셀렉트엔 '전체' 옵션이 마크업에 존재)
  const opt=(el,arr)=>arr.filter(v=>v!=='선택').forEach(v=>el.appendChild(new Option(v,v)));  // '선택' 플레이스홀더 제외
  opt(fRank,OPT.rank); opt(fCat,OPT.category); opt(fReqPart,OPT.part); opt(fDoPart,OPT.part);
  opt(fPri,OPT.priority); opt(fImp,OPT.level); opt(fDiff,OPT.level); opt(fVer,OPT.version); opt(fStatus,OPT.status);
}
function fillDynamicFilters(){   // 대분류/중분류는 데이터의 distinct 값으로 채움
  const distinct=k=>[...new Set(data.map(d=>(d[k]||'').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ko'));
  [['fMajor','major'],['fMiddle','middle']].forEach(([id,key])=>{
    const sel=document.getElementById(id); const prev=sel.value;
    sel.innerHTML='<option value="">전체</option>'; distinct(key).forEach(v=>sel.appendChild(new Option(v,v)));
    if([...sel.options].some(o=>o.value===prev)) sel.value=prev;
  });
}
function editItem(id){   // 수정: 본인 글 또는 관리자만 폼 이동, 그 외 안내 팝업
  const d=data.find(x=>x.id===id);
  if(!canModify(d)){ alert('수정이 불가합니다. 본인이 작성한 글만 수정가능합니다.'); return; }
  location.href='form.php?id='+id;
}
async function delItem(id){
  const d=data.find(x=>x.id===id);
  if(!canModify(d)){ alert('삭제가 불가합니다. 본인이 작성한 글만 삭제가능합니다.'); return; }
  if(!confirm('이 요구사항을 삭제하시겠습니까?')) return;
  if(useApi){
    try{
      const r = await fetch(API + 'requirements&id=' + id, {method:'DELETE'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      await loadData();
    }catch(err){ alert('삭제 실패: '+err.message); return; }
  } else {
    data=data.filter(d=>d.id!=id); save();
  }
  renderTable(); updateCounts(); toast('삭제되었습니다');
}
/* 적용된 검색조건(검색 버튼을 눌러야 반영). 페이지 진입 초기값 = 전부 빈 값(=전체 노출). */
let SF={ q:'', textField:'', rank:'', category:'', major:'', middle:'', reqPart:'', doPart:'', priority:'', importance:'', difficulty:'', version:'', status:'', dateField:'', dateFrom:'', dateTo:'', cmtSort:'' };   // cmtSort: ''(기본=수동 드래그 순서)/'desc'(많은순)/'asc'(적은순)
const SF_INIT=Object.freeze({...SF});
/* 통합검색 대상 텍스트 항목: 요구사항명·요청자·검토자·수행담당자·목적&필요성·참고링크·참고문서·최종수정자·비고 */
const TEXT_KEYS=['name','requester','reviewer','doPerson','purpose','link','doc','updatedBy','remark'];
function filtered(){
  return data.filter(d=>{
    if(SF.rank && d.rank!==SF.rank) return false;
    if(SF.category && d.category!==SF.category) return false;
    if(SF.major && d.major!==SF.major) return false;
    if(SF.middle && d.middle!==SF.middle) return false;
    if(SF.reqPart && d.reqPart!==SF.reqPart) return false;
    if(SF.doPart && d.doPart!==SF.doPart) return false;
    if(SF.priority && d.priority!==SF.priority) return false;
    if(SF.importance && d.importance!==SF.importance) return false;
    if(SF.difficulty && d.difficulty!==SF.difficulty) return false;
    if(SF.version && d.version!==SF.version) return false;
    if(SF.status && d.status!==SF.status) return false;
    if(SF.dateField){ const dv=(d[SF.dateField]||'').slice(0,10);
      if(SF.dateFrom && (!dv || dv<SF.dateFrom)) return false;
      if(SF.dateTo   && (!dv || dv>SF.dateTo))   return false; }
    if(SF.q){ const keys = SF.textField ? [SF.textField] : TEXT_KEYS;   // 필드 선택 시 해당 항목만, '전체'면 전체 텍스트 항목
      const hay=keys.map(k=>d[k]==null?'':String(d[k])).join(' ').toLowerCase();
      if(!hay.includes(SF.q)) return false; }
    return true;
  }).slice().sort((a,b)=>{   // 댓글 정렬: SF.cmtSort 빈값이면 data 순서(수동 드래그) 유지, 'desc'/'asc'면 댓글 수 정렬
    if(SF.cmtSort==='desc') return (parseInt(b.commentCount,10)||0)-(parseInt(a.commentCount,10)||0) || (a.id-b.id);
    if(SF.cmtSort==='asc')  return (parseInt(a.commentCount,10)||0)-(parseInt(b.commentCount,10)||0) || (a.id-b.id);
    return 0;
  });
}
function doSearch(){   // 검색 버튼: 검색어 미입력 시 안내 팝업, 입력 시 검색 실행
  if(!(search.value||'').trim()){ alert('검색어를 입력해주세요'); search.focus(); return; }
  applySearch();
}
function applySearch(){   // 컨트롤 값 → SF 반영 후 렌더
  SF={ q:(search.value||'').trim().toLowerCase(), textField:fTextField.value, rank:fRank.value, category:fCat.value, major:fMajor.value, middle:fMiddle.value,
       reqPart:fReqPart.value, doPart:fDoPart.value, priority:fPri.value, importance:fImp.value, difficulty:fDiff.value,
       version:fVer.value, status:fStatus.value, dateField:fDateField.value, dateFrom:fDateFrom.value, dateTo:fDateTo.value,
       cmtSort:(document.getElementById('fCmtSort')?.value||'') };
  renderTable(); updateCounts();
}
/* 검색영역 collapse — localStorage('reqSp_collapsed') 영속 + 버튼 텍스트(접기/펼치기) 토글 */
function toggleSearchPanel(){
  const c=document.getElementById('spCard'); if(!c) return;
  c.classList.toggle('collapsed');
  const cl=c.classList.contains('collapsed');
  try{ localStorage.setItem('reqSp_collapsed', cl?'1':'0'); }catch(e){}
  document.getElementById('spToggle')?.setAttribute('aria-expanded', cl?'false':'true');
  const txt=document.querySelector('#spToggle .sp-toggle-text'); if(txt) txt.textContent = cl ? '펼치기' : '접기';
}
function resetSearch(){   // 초기화 버튼: 컨트롤·SF를 페이지 최초 진입 상태로 복원
  search.value='';
  ['fTextField','fRank','fCat','fMajor','fMiddle','fReqPart','fDoPart','fPri','fImp','fDiff','fVer','fStatus','fDateField','fDateFrom','fDateTo','fCmtSort'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
  SF={...SF_INIT};
  renderTable(); updateCounts();
}
LABELS.updatedAt='최종수정일시'; LABELS.updatedBy='최종수정자'; LABELS.commentCount='💬 댓글';
const COLS = FIELDS.filter(k=>k!=='detail').concat(['updatedAt','updatedBy','commentCount']);   // 상세설명 비노출 + 최종수정일시·최종수정자·댓글 수 추가
/* ---- 인라인 수정 설정: 번호·참고링크(link/doc)·최종수정일시·최종수정자·댓글 제외 ---- */
const RQ_NOEDIT  = ['link','doc','updatedAt','updatedBy','commentCount'];
const RQ_EDITABLE= new Set(COLS.filter(k=>!RQ_NOEDIT.includes(k)));
const RQ_SELECT  = {rank:'rank',category:'category',priority:'priority',importance:'level',difficulty:'level',version:'version',status:'status',reqPart:'part',doPart:'part'};
const RQ_DATE    = new Set(['reqDate','approveDate','startDate','endDate','prodDate']);
const RQ_NUM     = new Set(['effort']);
const CAN_INLINE = !!PERM.update;   // 인라인 수정은 수정 권한 필요(서버에서도 검증)
function renderTable(){
  const rows=filtered();
  listCount.textContent = `검색 수 ${rows.length} / 총 수 ${data.length}`;   // 우측 카운트칩: 필터 적용 결과 / 전체 적재 건수
  buildHead();
  const body=document.getElementById('reqBody');
  if(rows.length===0){ body.innerHTML=''; emptyMsg.classList.remove('hidden'); return; }
  emptyMsg.classList.add('hidden');
  const canDrag=PERM.update;   // 순서 변경(드래그)은 수정 권한 필요
  body.innerHTML=rows.map((d,i)=>{
    const cells=COLS.map(k=>{
      const editable=CAN_INLINE && RQ_EDITABLE.has(k);
      const classes=[]; if(k==='name') classes.push('name'); if(editable) classes.push('rq-edit');
      if(k==='name'||k==='purpose'||k==='remark') classes.push('rq-left');   // 좌측 정렬 유지 컬럼
      const clsAttr=classes.length?` class="${classes.join(' ')}"`:'';
      const kAttr=editable?` data-k="${k}"`:'';
      return `<td${clsAttr}${kAttr} data-label="${esc(LABELS[k])}">${cellHtml(k,d[k])}</td>`;
    }).join('');
    const acts=[];
    if(PERM.update) acts.push(`<button class="btn ghost sm" onclick="editItem(${d.id})">수정</button>`);
    if(PERM.delete) acts.push(`<button class="btn danger sm" onclick="delItem(${d.id})">삭제</button>`);
    const actions=acts.length?`<div class="row-actions">${acts.join('')}</div>`:'<span class="muted">-</span>';
    const dragAttr=canDrag?` draggable="true" ondragstart="dragStart(event,${d.id})" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dragDrop(event,${d.id})" ondragend="dragEnd(event)"`:'';
    const handle=canDrag?`<span class="drag-h" title="드래그하여 순서 변경">⠿</span>`:'';
    return `<tr data-id="${d.id}"${dragAttr}>`+
      `<td class="rownum" data-label="번호">${handle}${i+1}</td>${cells}<td data-label="관리">${actions}</td></tr>`;
  }).join('');
  applyFreeze();
}
/* 좌측 N개 컬럼 틀고정: 헤더 너비로 누적 left를 계산해 헤더·본문 셀에 sticky 적용 */
const FREEZE_COUNT = 6;   // 번호 + 우선순위(순번)·구분·대분류·중분류·요구사항명
function applyFreeze(){
  if(window.matchMedia('(max-width:640px)').matches) return;   // 모바일 카드 레이아웃은 고정 제외
  const head=document.getElementById('reqHead');
  const ths=head?[...head.querySelectorAll('tr>th')]:[];
  if(ths.length<FREEZE_COUNT) return;
  const widths=ths.slice(0,FREEZE_COUNT).map(th=>th.getBoundingClientRect().width);
  if(widths.some(w=>w===0)) return;   // 숨김(대시보드 탭) 등 폭 0이면 보류
  const lefts=[]; let acc=0; for(let i=0;i<FREEZE_COUNT;i++){ lefts[i]=acc; acc+=widths[i]; }
  const setRow=(cells)=>{ for(let i=0;i<FREEZE_COUNT && i<cells.length;i++){ const c=cells[i]; c.classList.add('frz'); if(i===FREEZE_COUNT-1) c.classList.add('frz-edge'); c.style.left=Math.round(lefts[i])+'px'; } };
  setRow(ths);
  document.querySelectorAll('#reqBody tr').forEach(tr=> setRow([...tr.children]));
}
window.addEventListener('resize', ()=>{ clearTimeout(applyFreeze._t); applyFreeze._t=setTimeout(applyFreeze,120); });
/* ---- 드래그 앤 드롭으로 목록 순서 변경 (전체 목록 한 화면, 전체 순서 저장) ---- */
let dragId=null;
function dragStart(e,id){
  dragId=id;
  if(e.dataTransfer){ e.dataTransfer.effectAllowed='move'; try{ e.dataTransfer.setData('text/plain',String(id)); }catch(_){} }
  e.currentTarget.classList.add('dragging');
}
function dragOver(e){
  e.preventDefault();
  if(e.dataTransfer) e.dataTransfer.dropEffect='move';
  const tr=e.currentTarget; if(!tr||tr.classList.contains('dragging')) return;
  const r=tr.getBoundingClientRect();
  const after=(e.clientY-r.top)>r.height/2;
  tr.classList.toggle('drop-after',after);
  tr.classList.toggle('drop-before',!after);
}
function dragLeave(e){ e.currentTarget.classList.remove('drop-before','drop-after'); }
function dragEnd(){
  dragId=null;
  document.querySelectorAll('#reqBody tr').forEach(tr=>tr.classList.remove('dragging','drop-before','drop-after'));
}
function dragDrop(e,targetId){
  e.preventDefault();
  const tr=e.currentTarget;
  const after=tr.classList.contains('drop-after');
  tr.classList.remove('drop-before','drop-after');
  if(dragId!=null && dragId!==targetId) applyReorder(dragId,targetId,after);
}
function applyReorder(srcId,targetId,after){
  const from=data.findIndex(d=>d.id===srcId);
  if(from<0) return;
  const [moved]=data.splice(from,1);
  const to=data.findIndex(d=>d.id===targetId);
  if(to<0){ data.splice(from,0,moved); return; }   // 타깃이 사라진 경우 원복
  data.splice(after?to+1:to,0,moved);
  renderTable();
  persistOrder();
}
async function persistOrder(){
  if(useApi){
    try{
      const ids=data.map(d=>d.id);
      const r=await fetch(API+'reorder',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});
      if(!r.ok) throw new Error('HTTP '+r.status);
      toast('순서를 저장했습니다');
    }catch(err){ toast('순서 저장 실패: '+err.message); }
  } else {
    save();
    toast('순서를 저장했습니다');
  }
}
/* 셀 포맷터 + 헤더 빌더 (등록된 모든 필드 노출) */
function cellHtml(k,v){
  if(k==='commentCount'){ const n=parseInt(v,10)||0; return n>0?`<span class="cmt-cnt">💬 ${n}</span>`:'<span class="muted">0</span>'; }   // 댓글 수: 0이면 '0' 흐리게, 1+이면 브랜드 칩
  if(v==null||String(v).trim()==='') return '<span class="muted">-</span>';
  if(k==='category') return `<span class="tag cat">${esc(v)}</span>`;
  if(k==='priority') return `<span class="tag pri-${esc(v)}">${esc(v)}</span>`;
  if(k==='status')   return `<span class="tag st-${esc(v)}">${esc(v)}</span>`;
  if(k==='importance'||k==='difficulty') return `<span class="lvl lvl-${esc(v)}">${esc(v)}</span>`;
  if(k==='link'||k==='doc'){ const s=String(v).trim();
    if(/^https?:\/\//i.test(s) || /^www\./i.test(s)){ const href = /^https?:\/\//i.test(s) ? s : ('https://'+s); return `<a class="attach-y attach-open" href="${esc(href)}" target="_blank" rel="noopener" title="${esc(href)}">확인</a>`; }
    return esc(s); }
  if(k==='updatedAt') return esc(String(v).replace('T',' ').slice(0,19));   // YYYY-MM-DD HH:MM:SS
  return esc(v);
}
function buildHead(){
  const h=document.getElementById('reqHead'); if(!h) return;
  h.innerHTML='<tr><th title="행을 드래그하여 순서를 변경할 수 있습니다">번호</th>'+COLS.map(k=>`<th>${esc(LABELS[k])}</th>`).join('')+'<th>관리</th></tr>';
}
document.getElementById('btnSearch').onclick=doSearch;
document.getElementById('btnReset').onclick=resetSearch;
search.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });
search.addEventListener('input',()=>{ clearTimeout(search._t); search._t=setTimeout(applySearch,250); });   // 타이핑 즉시(250ms 디바운스) 검색 — 비우면 전체 표시
document.querySelectorAll('.search-panel select').forEach(sel=>{ sel.addEventListener('change', applySearch); });   // 셀렉트 선택 시 버튼 없이 즉시 검색
/* 행(리스트) 클릭 → 상세페이지 이동. 수정/삭제 버튼·드래그 핸들 클릭은 제외.
   본인 글이 아니고 관리자도 아니면 상세페이지는 보기 전용(저장 버튼 없음, form.php에서 판별). */
document.getElementById('reqBody').addEventListener('click',e=>{
  if(e.target.closest('.row-actions')||e.target.closest('.drag-h')||e.target.closest('.rq-edit')||e.target.closest('.attach-open')) return;   // 수정셀=인라인편집, 참고링크 '확인'=새 창 이동(상세이동 제외)
  const tr=e.target.closest('tr[data-id]'); if(!tr) return;
  location.href='form.php?id='+tr.dataset.id;
});
/* ---- 인라인 수정(번호·참고링크·최종수정일시·최종수정자·댓글 제외 전 항목) ---- */
document.getElementById('reqBody').addEventListener('click',e=>{ const td=e.target.closest('td.rq-edit'); if(td) rqBeginEdit(td); });
function rqBeginEdit(td){
  if(!CAN_INLINE || !td.classList.contains('rq-edit') || td.classList.contains('editing')) return;
  const k=td.dataset.k, tr=td.closest('tr[data-id]'); if(!tr) return;
  const id=+tr.dataset.id, row=data.find(d=>d.id===id); if(!row) return;
  const ov=(row[k]==null?'':String(row[k]));
  let ed;
  if(RQ_SELECT[k]){
    ed=document.createElement('select'); ed.className='rq-input';
    ed.appendChild(new Option('— 선택 —',''));
    (OPT[RQ_SELECT[k]]||[]).forEach(o=>ed.appendChild(new Option(o,o)));
    if(ov && ![...ed.options].some(o=>o.value===ov)) ed.appendChild(new Option(ov,ov));
    ed.value=ov;
  } else if(RQ_DATE.has(k)){ ed=document.createElement('input'); ed.type='date'; ed.className='rq-input'; ed.value=ov; }
  else if(RQ_NUM.has(k)){ ed=document.createElement('input'); ed.type='number'; ed.step='0.5'; ed.min='0'; ed.className='rq-input'; ed.value=ov; }
  else { ed=document.createElement('input'); ed.type='text'; ed.className='rq-input'; ed.value=ov; }
  td.classList.add('editing'); td.innerHTML=''; td.appendChild(ed); ed.focus(); if(ed.select){try{ed.select();}catch(_){}}
  let done=false;
  const finish=(saveIt)=>{
    if(done) return; done=true; td.classList.remove('editing');
    const nv=ed.value;
    if(!saveIt || nv===ov){ td.innerHTML=cellHtml(k,ov); return; }
    row[k]=nv; td.innerHTML=cellHtml(k,nv); rqSaveRow(row,td,k,ov);
  };
  ed.addEventListener('blur',()=>finish(true));
  ed.addEventListener('keydown',ev=>{ if(ev.key==='Escape'){ev.preventDefault();finish(false);} else if(ev.key==='Enter'&&ed.tagName!=='TEXTAREA'){ev.preventDefault();ed.blur();} });
  if(ed.tagName==='SELECT') ed.addEventListener('change',()=>ed.blur());
}
async function rqSaveRow(row,td,k,ov){
  try{
    if(useApi){
      const obj={}; FIELDS.forEach(f=>obj[f]=(row[f]==null?'':row[f]));
      const r=await fetch(API+'requirements&id='+row.id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
      if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    } else { save(); }
    toast('저장되었습니다');
  }catch(e){ row[k]=ov; td.innerHTML=cellHtml(k,ov); alert('저장 실패: '+e.message); }
}
/* 리스트를 어느 위치에서도 좌우 스크롤: 휠(세로/가로)·Shift+휠·트랙패드를 가로 스크롤로 변환.
   가장자리에 닿으면 preventDefault 안 하고 페이지 세로 스크롤이 자연스럽게 이어지게 함. (페이지 내 모든 표) */
document.querySelectorAll('.table-scroll').forEach(ts=>{
  ts.addEventListener('wheel',e=>{
    if(ts.scrollWidth<=ts.clientWidth) return;            // 가로 오버플로 없으면 기본 동작
    const delta=Math.abs(e.deltaX)>=Math.abs(e.deltaY)?e.deltaX:e.deltaY;
    if(!delta) return;
    const atStart=ts.scrollLeft<=0, atEnd=Math.ceil(ts.scrollLeft+ts.clientWidth)>=ts.scrollWidth;
    if((delta<0&&atStart)||(delta>0&&atEnd)) return;      // 가장자리 → 페이지 세로 스크롤 허용
    ts.scrollLeft+=delta;
    e.preventDefault();
  },{passive:false});
});
/* ---- counts ---- */
function updateCounts(){ navCount.textContent=data.length; renderTable(); }

/* ---- Excel(.xlsx) 내보내기 (라이브러리 없이 순수 JS로 생성) ---- */
function crc32(bytes){
  let t=crc32.t;
  if(!t){ t=crc32.t=[]; for(let n=0;n<256;n++){ let c=n; for(let k=0;k<8;k++) c=c&1?0xEDB88320^(c>>>1):c>>>1; t[n]=c>>>0; } }
  let crc=0^(-1);
  for(let i=0;i<bytes.length;i++) crc=(crc>>>8)^t[(crc^bytes[i])&0xFF];
  return (crc^(-1))>>>0;
}
function zipStored(files){
  const enc=new TextEncoder(), chunks=[], central=[]; let offset=0;
  const u16=v=>[v&0xFF,(v>>>8)&0xFF];
  const u32=v=>[v&0xFF,(v>>>8)&0xFF,(v>>>16)&0xFF,(v>>>24)&0xFF];
  files.forEach(f=>{
    const name=enc.encode(f.name), data=f.bytes, crc=crc32(data);
    chunks.push(new Uint8Array([].concat(
      u32(0x04034b50),u16(20),u16(0),u16(0),u16(0),u16(0),
      u32(crc),u32(data.length),u32(data.length),u16(name.length),u16(0))));
    chunks.push(name); chunks.push(data);
    central.push(new Uint8Array([].concat(
      u32(0x02014b50),u16(20),u16(20),u16(0),u16(0),u16(0),u16(0),
      u32(crc),u32(data.length),u32(data.length),u16(name.length),
      u16(0),u16(0),u16(0),u16(0),u32(0),u32(offset))));
    central.push(name);
    offset += 30+name.length+data.length;
  });
  const cdStart=offset; let cdSize=0;
  central.forEach(c=>{ chunks.push(c); cdSize+=c.length; });
  chunks.push(new Uint8Array([].concat(
    u32(0x06054b50),u16(0),u16(0),
    u16(central.length/2),u16(central.length/2),
    u32(cdSize),u32(cdStart),u16(0))));
  let total=chunks.reduce((s,c)=>s+c.length,0), out=new Uint8Array(total), p=0;
  chunks.forEach(c=>{ out.set(c,p); p+=c.length; });
  return new Blob([out],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
}
function buildXlsxBlob(){
  const enc=new TextEncoder();
  const xesc=s=>String(s==null?'':s).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
  const colL=n=>{ let s=''; n++; while(n>0){ const m=(n-1)%26; s=String.fromCharCode(65+m)+s; n=(n-m-1)/26; } return s; };
  const H=v=>({v,header:true}), T=v=>({v:v==null?'':v}), N=v=>({v,num:true});
  function sheetXml(rows, colsXml){
    let body='';
    rows.forEach((cells,ri)=>{
      let r='<row r="'+(ri+1)+'">';
      cells.forEach((cell,ci)=>{
        const ref=colL(ci)+(ri+1);
        if(!cell){ return; }
        const s=cell.header?' s="1"':'';
        if(cell.num && cell.v!=='' && cell.v!=null && !isNaN(cell.v)){ r+='<c r="'+ref+'"'+s+'><v>'+cell.v+'</v></c>'; }
        else if(cell.v===''||cell.v==null){ if(cell.header) r+='<c r="'+ref+'"'+s+' t="inlineStr"><is><t></t></is></c>'; }
        else { r+='<c r="'+ref+'"'+s+' t="inlineStr"><is><t xml:space="preserve">'+xesc(cell.v)+'</t></is></c>'; }
      });
      body+=r+'</row>';
    });
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'+
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'+
      (colsXml||'')+'<sheetData>'+body+'</sheetData></worksheet>';
  }
  /* sheet1: 요구사항 목록 */
  const rows1=[ FIELDS.map(k=>H(LABELS[k])) ];
  data.forEach(d=>{
    rows1.push(FIELDS.map(k=>{
      if(k==='effort' && d[k]!=='' && d[k]!=null && !isNaN(d[k])) return N(d[k]);
      return T(d[k]);
    }));
  });
  const cols1='<cols><col min="1" max="2" width="11"/><col min="3" max="4" width="16"/>'+
    '<col min="5" max="5" width="22"/><col min="6" max="7" width="34"/>'+
    '<col min="8" max="26" width="14"/></cols>';
  /* sheet2: 요약 대시보드 */
  const tot=data.length, reqN=data.filter(d=>d.priority==='필수').length;
  const doneN=data.filter(d=>d.status==='승인'||d.status==='완료').length;
  const effN=data.reduce((s,d)=>s+(parseFloat(d.effort)||0),0);
  const rows2=[
    [H('📊 요구사항 현황 대시보드')], [],
    [H('전체 요구사항'), N(tot)],
    [H('필수 요구사항'), N(reqN)],
    [H('승인/완료'), N(doneN)],
    [H('총 예상공수(MD)'), N(effN)], []
  ];
  const section=(title, key, order)=>{
    rows2.push([H(title)]);
    rows2.push([H('항목'),H('건수'),H('비율(%)')]);
    tally(key, order).forEach(r=>rows2.push([T(r.k), N(r.n), N(tot?Math.round(r.n/tot*100):0)]));
    rows2.push([]);
  };
  section('구분별 현황','category',OPT.category);
  section('우선순위별 현황','priority',OPT.priority);
  section('상태별 현황','status',OPT.status);
  section('목표 버전별 현황','version',OPT.version);
  const cols2='<cols><col min="1" max="1" width="20"/><col min="2" max="3" width="12"/></cols>';

  const files=[
    {name:'[Content_Types].xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'+
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'+
      '<Default Extension="xml" ContentType="application/xml"/>'+
      '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'+
      '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'+
      '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'+
      '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>')},
    {name:'_rels/.rels', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')},
    {name:'xl/workbook.xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'+
      '<sheets><sheet name="요구사항 목록" sheetId="1" r:id="rId1"/><sheet name="요약 대시보드" sheetId="2" r:id="rId2"/></sheets></workbook>')},
    {name:'xl/_rels/workbook.xml.rels', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'+
      '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'+
      '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>')},
    {name:'xl/styles.xml', bytes:enc.encode(
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
      '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'+
      '<fonts count="2"><font><sz val="11"/><name val="맑은 고딕"/></font>'+
      '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="맑은 고딕"/></font></fonts>'+
      '<fills count="3"><fill><patternFill patternType="none"/></fill>'+
      '<fill><patternFill patternType="gray125"/></fill>'+
      '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'+
      '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'+
      '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'+
      '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'+
      '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs>'+
      '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>')},
    {name:'xl/worksheets/sheet1.xml', bytes:enc.encode(sheetXml(rows1, cols1))},
    {name:'xl/worksheets/sheet2.xml', bytes:enc.encode(sheetXml(rows2, cols2))},
  ];
  return zipStored(files);
}
document.getElementById('exportXlsx').onclick=()=>{
  try{ dl(buildXlsxBlob(),'메디힘_요구사항.xlsx'); toast('Excel 파일을 내보냈습니다'); }
  catch(e){ alert('Excel 생성 실패: '+e.message); }
};
/* ===== 대시보드 탭 (구 dashboard.php 통합 — 같은 data 사용) ===== */
function bars(el, group, rows){
  if(!el) return;
  if(!rows.length){ el.innerHTML='<p class="chart-empty">데이터 없음</p>'; return; }
  const tot=data.length||1, max=Math.max(1,...rows.map(r=>r.n));
  el.innerHTML='<div class="bars">'+rows.map((r,i)=>{
    const c=colorFor(group,r.k,i), pct=Math.round(r.n/tot*100);
    return `<div class="bar-row"><div class="bl">${esc(r.k)}</div>`+
      `<div class="bv">${r.n}건<small>${pct}%</small></div>`+
      `<div class="bar-track"><div class="bar-fill" data-w="${(r.n/max*100).toFixed(1)}" style="background:${c}"></div></div></div>`;
  }).join('')+'</div>';
  requestAnimationFrame(()=>el.querySelectorAll('.bar-fill').forEach(f=>{ f.style.width=f.dataset.w+'%'; }));
}
function donut(el, group, rows){
  if(!el) return;
  const tot=rows.reduce((s,r)=>s+r.n,0);
  if(!tot){ el.innerHTML='<p class="chart-empty">데이터 없음</p>'; return; }
  let acc=0; const segs=rows.map((r,i)=>{ const c=colorFor(group,r.k,i), a0=acc/tot*360; acc+=r.n; return {r,c,a0,a1:acc/tot*360}; });
  const grad=segs.map(s=>`${s.c} ${s.a0.toFixed(2)}deg ${s.a1.toFixed(2)}deg`).join(',');
  const legend=segs.map(s=>{ const pct=Math.round(s.r.n/tot*100);
    return `<div class="lg"><span class="sw" style="background:${s.c}"></span><span class="ln">${esc(s.r.k)}</span><span class="lc"><b>${s.r.n}</b>건 · ${pct}%</span></div>`;
  }).join('');
  el.innerHTML=`<div class="donut-wrap"><div class="donut" style="background:conic-gradient(${grad})"><div class="donut-c"><div class="t">${tot}</div><div class="s">건</div></div></div><div class="legend">${legend}</div></div>`;
}
function setSub(id,t){ const e=document.getElementById(id); if(e) e.textContent=t; }
function renderDash(){
  if(!document.getElementById('sTotal')) return;   // 대시보드 탭 없으면(권한X) 무시
  const tot=data.length;
  const reqN=data.filter(d=>d.priority==='필수').length;
  const doneN=data.filter(d=>d.status==='승인'||d.status==='완료').length;
  const effN=data.reduce((s,d)=>s+(parseFloat(d.effort)||0),0);
  sTotal.textContent=tot; sReq.textContent=reqN; sDone.textContent=doneN; sEffort.textContent=effN;
  setSub('sTotalSub','전체 등록 항목');
  setSub('sReqSub', tot?Math.round(reqN/tot*100)+'% 비중':'—');
  setSub('sDoneSub', tot?Math.round(doneN/tot*100)+'% 처리':'—');
  setSub('sEffortSub', tot?'평균 '+(effN/tot).toFixed(1)+' MD/건':'—');
  donut(document.getElementById('dashCat'), 'category', tally('category', OPT.category));
  donut(document.getElementById('dashPri'),  'priority', tally('priority', OPT.priority));
  bars(document.getElementById('dashStatus'),'status',   tally('status', OPT.status));
  bars(document.getElementById('dashVer'),   'version',  tally('version', OPT.version));
}
function switchTab(v){
  document.querySelectorAll('#reqTabs button').forEach(x=>x.classList.toggle('active', x.dataset.v===v));
  document.getElementById('view-list').classList.toggle('hidden', v!=='list');
  const vd=document.getElementById('view-dash'); if(vd) vd.classList.toggle('hidden', v!=='dash');
  if(v==='list') applyFreeze();   // 목록 표시 시 틀고정 left 재계산(대시보드 우선 진입 대비)
  const pt=document.getElementById('pageTitle');
  pt.dataset.lnbTitle = (v==='dash'?'dashboard':'requirements');
  const def = (v==='dash'?'요구사항 대시보드':'요구사항 목록');
  pt.dataset.lnbOrig = def;   // applyNames가 dataset.lnbOrig를 기준으로 폴백하므로 탭 전환 시 기본값도 갱신
  pt.textContent = (window.lnbResolveName?window.lnbResolveName('d3:'+pt.dataset.lnbTitle):null) || def;
  document.getElementById('pageSub').textContent  = v==='dash'?'요구사항 현황 실시간 집계':'메디힘 전부서 / 팀원들의 시스템 구축 요청사항';
  if(v==='dash') renderDash();
  window.scrollTo({top:0});
}
document.querySelectorAll('#reqTabs button').forEach(b=>{ b.onclick=()=>switchTab(b.dataset.v); });

/* ---- init ---- */
(async function boot(){
  if(!PERM.write){ const a=document.getElementById('addBtn'); if(a) a.style.display='none'; }   // 쓰기 권한 없으면 등록 버튼 숨김
  await tryConnectApi();        // 서버 있으면 DB 옵션으로 OPT 갱신 + useApi=true
  initSelects();
  try{ await loadData(); } catch(e){ useApi=false; load(); }
  fillDynamicFilters();         // 대분류/중분류 셀렉트를 적재된 데이터 기준으로 채움
  renderStatus();
  renderTable();
  updateCounts();
  if(localStorage.getItem('reqSp_collapsed')==='1'){ document.getElementById('spCard')?.classList.add('collapsed'); document.getElementById('spToggle')?.setAttribute('aria-expanded','false'); const _t=document.querySelector('#spToggle .sp-toggle-text'); if(_t) _t.textContent='펼치기'; }
  if(INIT_TAB==='dash' && document.getElementById('view-dash')) switchTab('dash');   // ?tab=dash 진입 시 대시보드 탭
  try{ const _m=sessionStorage.getItem('reqToast'); if(_m){ toast(_m); sessionStorage.removeItem('reqToast'); } }catch(e){}
})();
</script>
</body>
</html>

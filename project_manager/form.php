<?php
/* =====================================================================
   프로젝트 매니저 — 항목 등록/수정 폼 (form.php)
   - id 없음: 등록(POST)
   - id 있음: 수정(PUT)
   - 권한: write(등록) / update(수정) — 관리자 전권
   ===================================================================== */
require __DIR__ . '/../auth.php';
require_login();
require_perm('project_manager', 'access');

$ME   = auth_user();
$PERM = perm_map('project_manager');
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$STATUS_FINAL = ['','진행예정','기획진행중','기획완료','디자인진행중','퍼블리싱진행중','개발진행중','개발완료','검수완료','운영서버반영완료','보류','작업대상아님'];
$STATUS_STEP  = ['','진행예정','진행중','완료','검수완료','작업대상아님','대상아님','선택','보류'];
$ATTACH       = ['','확인'];
$PHASE        = ['','1차','2차','1차 요건 + 2차 고도화','3차','기타'];
$PRIORITY     = ['','1','2','3','4','5'];
$PLATFORM     = ['','APP','BO','APP/BO','APP(WEB)','IF','법률검토','기타'];
$QA           = ['','선택','검수완료','반려','보류'];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title><?php echo $isEdit ? '프로젝트 수정' : '프로젝트 등록'; ?> · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<!-- Toast UI Editor (NHN) — 요건정의·이슈/History HTML 편집기 (오프라인용 로컬 호스팅 v3.2.2) -->
<link rel="stylesheet" href="../assets/vendor/toastui-editor/toastui-editor.min.css">
<script src="../assets/vendor/toastui-editor/toastui-editor-all.min.js"></script>
<script src="../assets/vendor/toastui-editor/i18n/ko-kr.js"></script>
<style>
  .form-wrap{display:flex;flex-direction:column;gap:14px;}
  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px 22px;box-shadow:0 1px 2px rgba(28,30,40,.03);}
  .form-card h3{margin:0 0 14px;font-size:14px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:6px;padding-bottom:8px;border-bottom:1px solid var(--line);}
  .form-card h3 .ico{font-size:16px;}
  .form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 16px;}
  .form-row.tri{grid-template-columns:repeat(3,minmax(180px,1fr));}
  .form-row.solo{grid-template-columns:1fr;}
  @media (max-width:900px){ .form-row,.form-row.tri{grid-template-columns:1fr;} }
  .field{display:flex;flex-direction:column;gap:5px;}
  .field label{font-size:12px;font-weight:700;color:var(--sub);}
  .field label .req{color:#e8364c;margin-left:3px;}
  .field label .auto-tag{display:inline-block;margin-left:5px;padding:1px 7px;border-radius:10px;background:var(--brand-soft);color:var(--brand-d);font-size:10.5px;font-weight:700;vertical-align:middle;}
  .field input[readonly]{background:#f5f6fa;color:#4b5160;cursor:default;}
  .field input,.field select,.field textarea{font-family:inherit;}
  .field textarea{min-height:90px;resize:vertical;}
  /* Toast UI Editor 컨테이너 — 폼 폭에 맞춤 */
  .rt-editor{border-radius:10px;overflow:hidden;}
  .form-bar{display:flex;gap:8px;justify-content:space-between;align-items:center;padding-top:18px;border-top:1px solid var(--line);margin-top:6px;}
  .form-bar .left{display:flex;gap:8px;}
  .form-bar .right{display:flex;gap:8px;}
  .badge-id{display:inline-flex;align-items:center;padding:4px 10px;border-radius:14px;background:var(--brand-soft);color:var(--brand-d);font-weight:700;font-size:12px;}
  .meta-info{font-size:11.5px;color:var(--sub);margin-left:8px;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🗓️ 프로젝트 <?php echo $isEdit ? '수정' : '등록'; ?></span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb('project_manager'); ?>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 data-lnb-title="project_manager">프로젝트 <?php echo $isEdit ? '수정' : '등록'; ?></h1>
        <div class="pg-sub">진척관리대장 — 항목 <?php echo $isEdit ? '수정' : '신규 등록'; ?>
          <?php if ($isEdit): ?><span class="badge-id" id="badgeId">id #<?php echo (int)$id; ?></span><span class="meta-info" id="metaInfo"></span><?php endif; ?>
        </div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <form id="projForm" autocomplete="off" onsubmit="return false;">
      <div class="form-wrap">

        <!-- 1) 기본 -->
        <div class="form-card">
          <h3><span class="ico">📌</span> 기본 정보</h3>
          <div class="form-row">
            <div class="field">
              <label>번호 <span class="auto-tag">자동</span></label>
              <input id="f_no" type="number" min="0" class="form-control form-control-sm" readonly title="등록 시 자동 부여됩니다" placeholder="자동 부여">
            </div>
            <div class="field">
              <label>차수</label>
              <select id="f_phase" class="form-select form-select-sm">
                <?php foreach ($PHASE as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>우선순위</label>
              <select id="f_priority" class="form-select form-select-sm">
                <?php foreach ($PRIORITY as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>플랫폼 (APP/BO)</label>
              <select id="f_platform" class="form-select form-select-sm">
                <?php foreach ($PLATFORM as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row solo" style="margin-top:12px;">
            <div class="field">
              <label>항목명 <span class="req">*</span></label>
              <input id="f_item" type="text" class="form-control form-control-sm" maxlength="200" required placeholder="예: AI 리포트">
            </div>
          </div>
        </div>

        <!-- 2) 내용 -->
        <div class="form-card">
          <h3><span class="ico">📝</span> 내용</h3>
          <div class="form-row solo">
            <div class="field">
              <label>주요 요건정의</label>
              <div id="editor_requirement" class="rt-editor"></div>
              <textarea id="f_requirement" hidden></textarea>
            </div>
            <div class="field">
              <label>이슈/History</label>
              <div id="editor_history" class="rt-editor"></div>
              <textarea id="f_history" hidden></textarea>
            </div>
          </div>
          <div class="form-row" style="margin-top:12px;">
            <div class="field">
              <label>진행률 (지난주)</label>
              <input id="f_progress_prev" type="text" class="form-control form-control-sm" placeholder="예: 0.9, 90%">
            </div>
            <div class="field">
              <label>진행률 (이번주)</label>
              <input id="f_progress_curr" type="text" class="form-control form-control-sm" placeholder="예: 1, 100%">
            </div>
          </div>
        </div>

        <!-- 3) 일정·상태 -->
        <div class="form-card">
          <h3><span class="ico">📅</span> 일정 · 최종 상태</h3>
          <div class="form-row">
            <div class="field">
              <label>최초 시작일</label>
              <input id="f_start_date" type="date" class="form-control form-control-sm">
            </div>
            <div class="field">
              <label>최종 종료일</label>
              <input id="f_end_date" type="date" class="form-control form-control-sm">
            </div>
            <div class="field">
              <label>작업일수 <span class="auto-tag">자동(영업일)</span></label>
              <input id="f_workdays" type="text" class="form-control form-control-sm" readonly title="시작일~종료일의 영업일(주말·공휴일 제외) 자동 계산" placeholder="시작일·종료일 입력 시 자동">
            </div>
            <div class="field">
              <label>최종 상태</label>
              <select id="f_final_status" class="form-select form-select-sm">
                <?php foreach ($STATUS_FINAL as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- 4) 첨부 표시 -->
        <div class="form-card">
          <h3><span class="ico">📎</span> 첨부 확인</h3>
          <div class="form-row tri">
            <div class="field">
              <label>기획서 첨부 (URL)</label>
              <input id="f_attach_plan" type="url" class="form-control form-control-sm" placeholder="https:// 기획서 링크">
            </div>
            <div class="field">
              <label>디자인 첨부 (URL)</label>
              <input id="f_attach_des" type="url" class="form-control form-control-sm" placeholder="https:// 디자인 링크">
            </div>
            <div class="field">
              <label>퍼블리싱 첨부 (URL)</label>
              <input id="f_attach_pub" type="url" class="form-control form-control-sm" placeholder="https:// 퍼블리싱 링크">
            </div>
          </div>
        </div>

        <!-- 5) 기획 -->
        <div class="form-card">
          <h3><span class="ico">📋</span> 기획</h3>
          <div class="form-row tri">
            <div class="field">
              <label>상태</label>
              <select id="f_plan_status" class="form-select form-select-sm">
                <?php foreach ($STATUS_STEP as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label>담당</label><input id="f_plan_owner" type="text" class="form-control form-control-sm" placeholder="이름(쉼표 구분 가능)"></div>
            <div class="field"><label>종료일</label><input id="f_plan_end" type="date" class="form-control form-control-sm"></div>
          </div>
        </div>

        <!-- 6) 디자인 -->
        <div class="form-card">
          <h3><span class="ico">🎨</span> 디자인</h3>
          <div class="form-row tri">
            <div class="field"><label>상태</label><select id="f_des_status" class="form-select form-select-sm"><?php foreach ($STATUS_STEP as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>담당</label><input id="f_des_owner" type="text" class="form-control form-control-sm"></div>
            <div class="field"><label>종료일</label><input id="f_des_end" type="date" class="form-control form-control-sm"></div>
          </div>
        </div>

        <!-- 7) 퍼블리싱 -->
        <div class="form-card">
          <h3><span class="ico">🧱</span> 퍼블리싱</h3>
          <div class="form-row tri">
            <div class="field"><label>상태</label><select id="f_pub_status" class="form-select form-select-sm"><?php foreach ($STATUS_STEP as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>담당</label><input id="f_pub_owner" type="text" class="form-control form-control-sm"></div>
            <div class="field"><label>종료일</label><input id="f_pub_end" type="date" class="form-control form-control-sm"></div>
          </div>
        </div>

        <!-- 8) 개발 -->
        <div class="form-card">
          <h3><span class="ico">💻</span> 개발</h3>
          <div class="form-row">
            <div class="field"><label>상태</label><select id="f_dev_status" class="form-select form-select-sm"><?php foreach ($STATUS_STEP as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>담당</label><input id="f_dev_owner" type="text" class="form-control form-control-sm" placeholder="이름(쉼표 구분 가능)"></div>
            <div class="field"><label>개발 종료일</label><input id="f_dev_end" type="date" class="form-control form-control-sm"></div>
            <div class="field"><label>개발서버 반영일</label><input id="f_dev_deploy" type="date" class="form-control form-control-sm"></div>
          </div>
        </div>

        <!-- 9) 운영 검수 -->
        <div class="form-card">
          <h3><span class="ico">✅</span> 운영 · 검수</h3>
          <div class="form-row">
            <div class="field">
              <label>검수여부</label>
              <select id="f_qa" class="form-select form-select-sm">
                <?php foreach ($QA as $v): ?><option value="<?php echo h($v); ?>"><?php echo $v===''?'(미지정)':h($v); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label>운영서버 반영일</label><input id="f_prod_deploy" type="date" class="form-control form-control-sm"></div>
          </div>
        </div>

        <!-- 액션 바 -->
        <div class="form-bar">
          <div class="left">
            <button type="button" class="btn primary sm" id="btnSave" style="background:var(--brand);color:#fff;border:none;">💾 <?php echo $isEdit ? '수정 저장' : '등록'; ?></button>
            <a class="btn ghost sm" href="schedule.php">취소</a>
          </div>
          <div class="right">
            <?php if ($isEdit && $PERM['admin']): ?>
              <button type="button" class="btn danger sm" id="btnDelete">🗑️ 삭제</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </form>
  </main>
</div>

<div class="toast" id="toast"></div>

<script>
const IS_EDIT = <?php echo $isEdit ? 'true' : 'false'; ?>;
const ID      = <?php echo (int)$id; ?>;
const PERM    = <?php echo json_encode($PERM, JSON_UNESCAPED_UNICODE); ?>;
const FIELDS  = ['no','phase','priority','platform','item','requirement','history','progress_prev','progress_curr',
                 'start_date','end_date','final_status','workdays','attach_plan','attach_des','attach_pub',
                 'plan_status','plan_owner','plan_end','des_status','des_owner','des_end',
                 'pub_status','pub_owner','pub_end','dev_status','dev_owner','dev_end',
                 'dev_deploy','qa','prod_deploy'];

/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

/* 토스트 */
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* === 상세 HTML 편집기 (Toast UI Editor v3+, CDN 로드 — requirements/form.php와 동일 방식) ===
 *   - 요건정의·이슈/History 각각 위지윅 편집기, DB 저장/로드는 HTML로 통일(getHTML/setHTML)
 *   - 숨긴 textarea(f_requirement·f_history)를 데이터 홀더로 사용 → 기존 getVal/save 흐름 유지 */
const RICH = [
  { key:'requirement', el:'editor_requirement', ph:'- 요건 1 / - 요건 2 ...' },
  { key:'history',     el:'editor_history',     ph:'[작성자_YYYY.M.D] 메모...' },
];
const __editors = {}; // key -> toastui editor instance
function initEditors(){
  RICH.forEach(c => {
    const root = document.getElementById(c.el); if(!root) return;
    __editors[c.key] = new toastui.Editor({
      el: root, height: '250px',
      initialEditType: 'wysiwyg', previewStyle: 'vertical', language: 'ko-KR',
      hideModeSwitch: false, usageStatistics: false, placeholder: c.ph, initialValue: '',
    });
  });
}
function setEditorHTML(key, html){
  const ed = __editors[key]; if(!ed) return;
  if(ed.setHTML) ed.setHTML(html||''); else if(ed.setMarkdown) ed.setMarkdown(html||'');
}
function syncEditors(){   // 편집기 내용을 숨긴 textarea로 반영(저장 직전 호출)
  RICH.forEach(c => {
    const ed = __editors[c.key], ta = document.getElementById('f_'+c.key);
    if(ed && ta) ta.value = (ed.getHTML ? ed.getHTML() : '') || '';
  });
}
initEditors();

function setVal(k, v){
  const el = document.getElementById('f_' + k);
  if (!el) return;
  if (v === null || v === undefined) v = '';
  // select에 없는 옵션이면 동적으로 추가(데이터 보존)
  if (el.tagName === 'SELECT' && v !== '' && ![...el.options].some(o=>o.value===String(v))) {
    el.add(new Option(String(v), String(v)));
  }
  el.value = v;
  // Toast UI 편집기와 동기화
  if (el.id === 'f_requirement') setEditorHTML('requirement', v);
  else if (el.id === 'f_history') setEditorHTML('history', v);
}
function getVal(k){
  const el = document.getElementById('f_' + k);
  return el ? (el.value ?? '') : '';
}

/* === 작업일수 자동 계산 (영업일 = 주말·공휴일 제외, 시작·종료일 양끝 포함) — 수정 불가 ===
 *   대체공휴일 포함 공휴일 목록. 연 1회 갱신 필요(특히 음력 기준 설날·추석·부처님오신날). */
const HOLIDAYS = new Set([
  // 2025
  '2025-01-01','2025-01-28','2025-01-29','2025-01-30','2025-03-01','2025-03-03',
  '2025-05-05','2025-05-06','2025-06-06','2025-08-15',
  '2025-10-03','2025-10-05','2025-10-06','2025-10-07','2025-10-08','2025-10-09','2025-12-25',
  // 2026
  '2026-01-01','2026-02-16','2026-02-17','2026-02-18','2026-03-01','2026-03-02',
  '2026-05-05','2026-05-24','2026-05-25','2026-06-06','2026-08-15',
  '2026-09-24','2026-09-25','2026-09-26','2026-09-28','2026-10-03','2026-10-05','2026-10-09','2026-12-25',
  // 2027
  '2027-01-01','2027-02-06','2027-02-07','2027-02-08','2027-02-09','2027-03-01',
  '2027-05-05','2027-05-13','2027-06-06','2027-08-15','2027-08-16',
  '2027-09-14','2027-09-15','2027-09-16','2027-10-03','2027-10-04','2027-10-09','2027-10-11','2027-12-25',
]);
function isoLocal(d){ const m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0'); return d.getFullYear()+'-'+m+'-'+dd; }
function calcWorkdays(){
  const out = document.getElementById('f_workdays');
  const s = (document.getElementById('f_start_date')||{}).value;
  const e = (document.getElementById('f_end_date')||{}).value;
  if (!s || !e) { if (out) out.value = ''; return; }
  const sd = new Date(s+'T00:00:00'), ed = new Date(e+'T00:00:00');
  if (isNaN(sd) || isNaN(ed) || ed < sd) { if (out) out.value = ''; return; }
  let count = 0;
  for (const d = new Date(sd); d <= ed; d.setDate(d.getDate()+1)) {
    const dow = d.getDay();
    if (dow === 0 || dow === 6) continue;       // 주말 제외
    if (HOLIDAYS.has(isoLocal(d))) continue;    // 공휴일 제외
    count++;
  }
  if (out) out.value = count;
}
['f_start_date','f_end_date'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('change', calcWorkdays);
});

/* === 번호 자동 부여 (신규 등록 시 기존 max(no)+1) — 수정 불가 === */
async function assignNextNo(){
  if (IS_EDIT) return;   // 수정 시엔 기존 번호 유지(loadDetail이 채움)
  try{
    const r = await fetch('schedule.php?api=projects', {cache:'no-store'});
    if (!r.ok) return;
    const rows = await r.json();
    const max = Array.isArray(rows) ? rows.reduce((m,x)=>Math.max(m, parseInt(x.no,10)||0), 0) : 0;
    const el = document.getElementById('f_no'); if (el) el.value = max + 1;
  }catch(e){}
}

async function loadDetail(){
  if (!IS_EDIT) return;
  try{
    const r = await fetch(`schedule.php?api=projects&id=${ID}`, {cache:'no-store'});
    if (!r.ok){ const e = await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const row = await r.json();
    FIELDS.forEach(k => setVal(k, row[k]));
    const mi = document.getElementById('metaInfo');
    if (mi) mi.textContent = ` · 최종수정: ${row.updated_at||'-'} by ${row.updated_by||'-'}`;
  }catch(e){ alert('불러오기 실패: ' + e.message); }
}

async function save(){
  syncEditors();   // Toast UI 편집기 내용을 숨긴 textarea(f_requirement·f_history)에 반영
  const body = {};
  FIELDS.forEach(k => { body[k] = getVal(k); });
  if (!body.item || !body.item.trim()) { alert('항목명은 필수입니다.'); document.getElementById('f_item').focus(); return; }
  try{
    const url = IS_EDIT ? `schedule.php?api=projects&id=${ID}` : `schedule.php?api=projects`;
    const r = await fetch(url, {method: IS_EDIT?'PUT':'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
    if (!r.ok){ const e = await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast(IS_EDIT ? '수정되었습니다' : '등록되었습니다');
    setTimeout(()=>{ location.href = 'schedule.php'; }, 700);
  }catch(e){ alert('저장 실패: ' + e.message); }
}

async function del(){
  if (!confirm(`이 프로젝트 항목을 삭제하시겠습니까? (id=${ID})`)) return;
  try{
    const r = await fetch(`schedule.php?api=projects&id=${ID}`, {method:'DELETE'});
    if (!r.ok){ const e = await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast('삭제되었습니다');
    setTimeout(()=>{ location.href = 'schedule.php'; }, 700);
  }catch(e){ alert('삭제 실패: ' + e.message); }
}

document.getElementById('btnSave').onclick = save;
const btnDel = document.getElementById('btnDelete'); if (btnDel) btnDel.onclick = del;

(async function boot(){
  await loadDetail();    // 수정: 기존 값 로드
  await assignNextNo();  // 신규: 번호 자동 부여
  calcWorkdays();        // 시작·종료일 기준 작업일수 자동 표기
})();
</script>
</body>
</html>

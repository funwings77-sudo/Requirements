<?php
/* =====================================================================
   프로젝트 매니저 — 기획서 (project_manager/planning.php)
   - 기획서 목록(planning_item DB) + 등록/수정/삭제(권한: planning)
   - API: ?api=planning (GET/POST/PUT/DELETE)
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
$PFIELDS = ['gubun','title','done','system','wireframe','author','doc_date'];

function pl_pj($d, $c = 200){ http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

/* ---- 기획서 API: ?api=planning | history | comments ---- */
if (isset($_GET['api'])) {
  global $DB, $PFIELDS;
  $api = $_GET['api'];
  if (!in_array($api, ['planning','history','comments'], true)) pl_pj(['error' => '알 수 없는 api'], 404);
  try { $pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
  catch (Throwable $e) { pl_pj(['error' => 'DB 연결 실패: ' . $e->getMessage()], 500); }
  $method = $_SERVER['REQUEST_METHOD'];
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $me = auth_user(); $who = $me ? $me['name'] : null;
  $LABELS = ['gubun'=>'구분','title'=>'제목','done'=>'작성여부','system'=>'시스템','wireframe'=>'와이어프레임','author'=>'최초 작성자','doc_date'=>'최초 작성일자'];

  /* 변경이력 */
  if ($api === 'history') {
    if ($method !== 'GET') pl_pj(['error'=>'GET 필요'],405);
    if (!can('planning','read')) pl_pj(['error'=>'읽기 권한이 없습니다'],403);
    $pid = isset($_GET['planId']) ? (int)$_GET['planId'] : 0; if ($pid<=0) pl_pj(['error'=>'planId 필요']);
    $st=$pdo->prepare("SELECT id, field_key AS fieldKey, field_label AS fieldLabel, before_val AS beforeVal, after_val AS afterVal, changed_at AS changedAt, changed_by AS changedBy FROM planning_history WHERE plan_id=? ORDER BY id DESC");
    $st->execute([$pid]); pl_pj($st->fetchAll());
  }

  /* 댓글/대댓글 */
  if ($api === 'comments') {
    if (!can('planning','read')) pl_pj(['error'=>'댓글 권한이 없습니다'],403);
    $SELC="SELECT id, plan_id AS planId, parent_id AS parentId, author_id AS authorId, author_name AS authorName, body, is_deleted AS isDeleted, created_at AS createdAt, updated_at AS updatedAt FROM planning_comment";
    if ($method==='GET') {
      $pid=isset($_GET['planId'])?(int)$_GET['planId']:0; if($pid<=0) pl_pj(['error'=>'planId 필요']);
      $st=$pdo->prepare("$SELC WHERE plan_id=? ORDER BY created_at, id"); $st->execute([$pid]);
      $rows=$st->fetchAll(); foreach($rows as &$r){ $r['isDeleted']=(int)$r['isDeleted']; if($r['isDeleted'])$r['body']=''; } unset($r);
      pl_pj($rows);
    }
    $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body))$body=[];
    if ($method==='POST') {
      $pid=isset($body['planId'])?(int)$body['planId']:0; $text=isset($body['body'])?trim((string)$body['body']):'';
      $parentId=(isset($body['parentId'])&&$body['parentId']!==''&&$body['parentId']!==null)?(int)$body['parentId']:null;
      if($pid<=0) pl_pj(['error'=>'planId 필요']); if($text==='') pl_pj(['error'=>'댓글 내용을 입력하세요']);
      $chk=$pdo->prepare("SELECT COUNT(*) FROM planning_item WHERE id=?"); $chk->execute([$pid]); if(!(int)$chk->fetchColumn()) pl_pj(['error'=>'없는 항목'],404);
      if($parentId!==null){ $pc=$pdo->prepare("SELECT plan_id FROM planning_comment WHERE id=?"); $pc->execute([$parentId]); $prc=$pc->fetchColumn(); if($prc===false)pl_pj(['error'=>'없는 상위 댓글'],404); if((int)$prc!==$pid)pl_pj(['error'=>'상위 댓글이 다른 항목입니다']); }
      $ins=$pdo->prepare("INSERT INTO planning_comment(plan_id,parent_id,author_id,author_name,body) VALUES(?,?,?,?,?)");
      $ins->execute([$pid,$parentId,($me?(int)$me['id']:null),($me?$me['name']:null),$text]);
      $g=$pdo->prepare("$SELC WHERE id=?"); $g->execute([$pdo->lastInsertId()]); $row=$g->fetch(); $row['isDeleted']=(int)$row['isDeleted']; pl_pj($row,201);
    }
    $cid=isset($_GET['id'])?(int)$_GET['id']:0; if($cid<=0) pl_pj(['error'=>'id 필요']);
    $own=$pdo->prepare("SELECT author_id,is_deleted FROM planning_comment WHERE id=?"); $own->execute([$cid]); $oc=$own->fetch();
    if(!$oc) pl_pj(['error'=>'없는 댓글'],404);
    $isMine=$me&&$oc['author_id']!==null&&(int)$oc['author_id']===(int)$me['id']; if(!$isMine) pl_pj(['error'=>'본인이 작성한 댓글만 수정/삭제할 수 있습니다'],403);
    if ($method==='PUT') { if((int)$oc['is_deleted']===1)pl_pj(['error'=>'삭제된 댓글은 수정할 수 없습니다']); $text=isset($body['body'])?trim((string)$body['body']):''; if($text==='')pl_pj(['error'=>'댓글 내용을 입력하세요']); $pdo->prepare("UPDATE planning_comment SET body=? WHERE id=?")->execute([$text,$cid]); pl_pj(['ok'=>true]); }
    if ($method==='DELETE') { $pdo->prepare("UPDATE planning_comment SET is_deleted=1 WHERE id=?")->execute([$cid]); pl_pj(['ok'=>true]); }
    pl_pj(['error'=>'허용되지 않은 메서드'],405);
  }

  /* 기획서 항목 CRUD */
  $SEL = "SELECT id,gubun,title,done,`system`,wireframe,author,doc_date FROM planning_item";
  if ($method === 'GET') {
    if (!can('planning','read')) pl_pj(['error'=>'읽기 권한이 없습니다'], 403);
    if ($id > 0) { $pdo->prepare("UPDATE planning_item SET view_count=view_count+1 WHERE id=?")->execute([$id]); $st=$pdo->prepare("$SEL WHERE id=?"); $st->execute([$id]); $r=$st->fetch(); if(!$r) pl_pj(['error'=>'없는 항목'],404); pl_pj($r); }
    pl_pj($pdo->query("$SEL ORDER BY sort_order, id")->fetchAll());
  }
  $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];
  $vals = [];
  foreach ($PFIELDS as $f) { $v = isset($body[$f]) ? trim((string)$body[$f]) : ''; $vals[$f] = ($v === '') ? null : $v; }
  if (($method==='POST'||$method==='PUT')) {
    if ($vals['title'] === null) pl_pj(['error'=>'제목은 필수입니다']);
    if ($vals['doc_date'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vals['doc_date'])) pl_pj(['error'=>'최초 작성일자 형식 오류(YYYY-MM-DD)']);
  }

  if ($method === 'POST') {
    if (!can('planning','write')) pl_pj(['error'=>'등록 권한이 없습니다'], 403);
    $so = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM planning_item")->fetchColumn();
    $st = $pdo->prepare("INSERT INTO planning_item(sort_order,gubun,title,done,`system`,wireframe,author,doc_date,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$so,$vals['gubun'],$vals['title'],$vals['done'],$vals['system'],$vals['wireframe'],$vals['author'],$vals['doc_date'],$who,$who]);
    pl_pj(['ok'=>true,'id'=>(int)$pdo->lastInsertId()], 201);
  }
  if ($method === 'PUT') {
    if (!can('planning','update')) pl_pj(['error'=>'수정 권한이 없습니다'], 403);
    if ($id<=0) pl_pj(['error'=>'id 필요']);
    $oldSt=$pdo->prepare("SELECT gubun,title,done,`system`,wireframe,author,doc_date FROM planning_item WHERE id=?"); $oldSt->execute([$id]); $old=$oldSt->fetch();
    if(!$old) pl_pj(['error'=>'없는 항목'],404);
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("UPDATE planning_item SET gubun=?,title=?,done=?,`system`=?,wireframe=?,author=?,doc_date=?,updated_by=? WHERE id=?");
      $st->execute([$vals['gubun'],$vals['title'],$vals['done'],$vals['system'],$vals['wireframe'],$vals['author'],$vals['doc_date'],$who,$id]);
      $hist=$pdo->prepare("INSERT INTO planning_history (plan_id, field_key, field_label, before_val, after_val, changed_by) VALUES (?,?,?,?,?,?)");
      foreach ($PFIELDS as $f) {
        $bef=(string)($old[$f]??''); $aft=(string)($vals[$f]??'');
        if ($bef===$aft) continue;
        $hist->execute([$id,$f,($LABELS[$f]??$f),$bef,$aft,$who]);
      }
      $pdo->commit();
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); pl_pj(['error'=>'수정 저장 실패: '.$e->getMessage()],500); }
    pl_pj(['ok'=>true]);
  }
  if ($method === 'DELETE') {
    if (!can('planning','delete')) pl_pj(['error'=>'삭제 권한이 없습니다'], 403);
    if ($id<=0) pl_pj(['error'=>'id 필요']);
    $st=$pdo->prepare("DELETE FROM planning_item WHERE id=?"); $st->execute([$id]);
    pl_pj(['ok'=>true,'deleted'=>$st->rowCount()]);
  }
  pl_pj(['error'=>'지원하지 않는 메서드'], 405);
}

require_perm('planning', 'access');
$PERM = perm_map('planning');
$CAN_WRITE = !empty($PERM['write']); $CAN_UPDATE = !empty($PERM['update']); $CAN_DELETE = !empty($PERM['delete']);
$CAN_MANAGE = $CAN_UPDATE || $CAN_DELETE;
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$COLS = ['구분','제목','작성여부','시스템','와이어프레임','최초 작성자','최초 작성일자','최종수정일시','최종수정자','댓글','조회수'];
$ROWS = [];
try {
  $pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $ROWS = $pdo->query("SELECT id,gubun,title,done,`system`,wireframe,author,doc_date,updated_at AS updatedAt,updated_by AS updatedBy,view_count AS viewCount,
      (SELECT COUNT(*) FROM planning_comment WHERE plan_id=planning_item.id AND is_deleted=0) AS commentCount
      FROM planning_item ORDER BY sort_order, id")->fetchAll();
} catch (Throwable $e) { $ROWS = []; }
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>기획서 · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  /* 검색영역(리스트영역과 분리) */
  .pl-search-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .pl-f-lbl{font-size:12px;font-weight:700;color:#4a5363;margin-left:6px;}
  .pl-tilde{color:#9aa3b2;}
  .pl-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 14px;}
  .pl-head-right{display:flex;align-items:center;gap:10px;}
  .pl-search-bar .count-chip{background:var(--brand-soft);color:var(--brand-d);font-weight:700;padding:6px 12px;border-radius:20px;font-size:12.5px;}
  .pl-search-bar input{width:300px;max-width:100%;}
  .pl-search-bar .pl-add{margin-left:auto;background:#FB64C9;color:#fff;border:none;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  #plTbl td{vertical-align:middle;text-align:center;}
  #plTbl td.col-title{text-align:left;font-weight:700;color:var(--ink);min-width:220px;max-width:360px;white-space:normal;word-break:break-word;}
  #plTbl td.col-gubun{text-align:left;white-space:normal;max-width:200px;}
  #plTbl td.col-sys{text-align:left;white-space:normal;max-width:180px;color:#555;font-size:12px;}
  .pl-status{display:inline-block;padding:2px 10px;border-radius:14px;font-size:11.5px;font-weight:700;white-space:nowrap;border:1px solid transparent;}
  .pl-status.done{background:#dff5ea;color:#127c3c;border-color:#bfe7cf;}
  .pl-status.ing{background:#fff4d8;color:#a06212;border-color:#f0dcad;}
  .pl-view{cursor:pointer;text-decoration:none;border:1px solid #bfe7cf;background:#dff5ea;color:#127c3c;border-radius:12px;padding:1px 10px;font-size:11px;font-weight:800;}
  .pl-view:hover{background:#127c3c;color:#fff;}
  .pl-cmt{display:inline-block;background:var(--brand-soft);color:var(--brand-d);font-weight:700;border-radius:12px;padding:1px 9px;font-size:11.5px;}
  td.pl-actions{white-space:nowrap;text-align:center;}
  .pl-empty td{color:#9aa3b2;text-align:center;padding:24px 0;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">📝 기획서</span>
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
        <h1 data-lnb-title="planning">기획서</h1>
        <div class="pg-sub">프로젝트 기획서 목록</div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <!-- 검색영역 (별도 카드) -->
    <div class="card">
      <div class="card-body">
        <h2 class="card-title" style="margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--line);font-size:15px;font-weight:700;">검색</h2>
        <div class="pl-search-bar">
          <label class="pl-f-lbl">작성여부</label>
          <select id="plDone" class="form-select form-select-sm" style="max-width:120px;">
            <option value="">전체</option>
            <option value="완료">완료</option>
            <option value="진행 중">진행 중</option>
            <option value="__none__">미지정</option>
          </select>
          <label class="pl-f-lbl">댓글정렬</label>
          <select id="plSort" class="form-select form-select-sm" style="max-width:130px;">
            <option value="">기본순</option>
            <option value="desc">댓글 많은 순</option>
            <option value="asc">댓글 적은 순</option>
          </select>
          <label class="pl-f-lbl">기간검색</label>
          <select id="plDateField" class="form-select form-select-sm" style="max-width:150px;">
            <option value="">기준 일자 선택</option>
            <option value="docdate">최초 작성일자</option>
            <option value="updatedat">최종수정일시</option>
          </select>
          <input type="date" id="plDateFrom" class="form-control form-control-sm" style="max-width:160px;">
          <span class="pl-tilde">~</span>
          <input type="date" id="plDateTo" class="form-control form-control-sm" style="max-width:160px;">
          <label class="pl-f-lbl">통합검색</label>
          <select id="plField" class="form-select form-select-sm" style="max-width:130px;">
            <option value="gubun">구분</option>
            <option value="title">제목</option>
            <option value="system">시스템</option>
            <option value="author">최초 작성자</option>
            <option value="updatedby">최종수정자</option>
          </select>
          <input id="plSearch" class="form-control form-control-sm" placeholder="검색어를 입력하세요" style="max-width:200px;">
          <button type="button" class="btn primary sm" id="plSearchBtn">🔍 검색</button>
          <button type="button" class="btn ghost sm" id="plResetBtn">↺ 초기화</button>
        </div>
      </div>
    </div>

    <!-- 리스트영역 (별도 카드) -->
    <div class="card">
      <div class="card-body">
        <div class="pl-list-head">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="card-title" style="margin:0;font-size:15px;font-weight:700;">기획서 목록</h2>
            <span class="count-chip" id="plCount">검색 수 <?php echo count($ROWS); ?> / 총 수 <?php echo count($ROWS); ?></span>
          </div>
          <div class="pl-head-right">
            <?php if ($CAN_WRITE): ?><a class="btn primary sm pl-add" href="planning_form.php">＋ 등록</a><?php endif; ?>
          </div>
        </div>
        <div class="table-scroll pl-list">
          <table class="sch-look" id="plTbl">
            <thead><tr>
              <th>번호</th>
              <?php foreach ($COLS as $c) echo '<th>'.h($c).'</th>'; if ($CAN_MANAGE) echo '<th>관리</th>'; ?>
            </tr></thead>
            <tbody id="plBody">
            <?php if (!$ROWS): ?>
              <tr class="pl-empty"><td colspan="<?php echo count($COLS)+1+($CAN_MANAGE?1:0); ?>">등록된 기획서가 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($ROWS as $ri => $r): ?>
              <tr data-id="<?php echo (int)$r['id']; ?>"
                  data-ord="<?php echo (int)$ri; ?>"
                  data-cc="<?php echo (int)$r['commentCount']; ?>"
                  data-gubun="<?php echo h(mb_strtolower((string)$r['gubun'])); ?>"
                  data-title="<?php echo h(mb_strtolower((string)$r['title'])); ?>"
                  data-system="<?php echo h(mb_strtolower((string)$r['system'])); ?>"
                  data-author="<?php echo h(mb_strtolower((string)$r['author'])); ?>"
                  data-updatedby="<?php echo h(mb_strtolower((string)$r['updatedBy'])); ?>"
                  data-done="<?php echo h((string)$r['done']); ?>"
                  data-docdate="<?php echo h((string)$r['doc_date']); ?>"
                  data-updatedat="<?php echo h(substr((string)($r['updatedAt']??''),0,10)); ?>">
                <td><?php echo $ri + 1; ?></td>
                <td class="col-gubun"><?php echo $r['gubun']!==null&&$r['gubun']!==''?h($r['gubun']):'<span class="muted">-</span>'; ?></td>
                <td class="col-title"><?php echo $r['title']!==null&&$r['title']!==''?h($r['title']):'<span class="muted">-</span>'; ?></td>
                <td><?php
                  $d=(string)$r['done'];
                  if ($d==='완료') echo '<span class="pl-status done">완료</span>';
                  elseif ($d!=='') echo '<span class="pl-status ing">'.h($d).'</span>';
                  else echo '<span class="muted">-</span>';
                ?></td>
                <td class="col-sys"><?php echo $r['system']!==null&&$r['system']!==''?h($r['system']):'<span class="muted">-</span>'; ?></td>
                <td><?php
                  $w=(string)$r['wireframe'];
                  if (preg_match('~^https?://~i',$w)) echo '<a class="pl-view" href="'.h($w).'" target="_blank" rel="noopener" title="'.h($w).'">보기</a>';
                  elseif ($w!=='') echo h($w); else echo '<span class="muted">-</span>';
                ?></td>
                <td><?php echo $r['author']!==null&&$r['author']!==''?h($r['author']):'<span class="muted">-</span>'; ?></td>
                <td><?php echo $r['doc_date']!==null&&$r['doc_date']!==''?h($r['doc_date']):'<span class="muted">-</span>'; ?></td>
                <td><?php $ua=(string)($r['updatedAt']??''); echo $ua!==''?h(substr($ua,0,19)):'<span class="muted">-</span>'; ?></td>
                <td><?php echo $r['updatedBy']!==null&&$r['updatedBy']!==''?h($r['updatedBy']):'<span class="muted">-</span>'; ?></td>
                <td><?php $cc=(int)$r['commentCount']; echo $cc>0?'<span class="pl-cmt">💬 '.$cc.'</span>':'<span class="muted">0</span>'; ?></td>
                <td><?php echo (int)$r['viewCount']; ?></td>
                <?php if ($CAN_MANAGE): ?>
                  <td class="pl-actions">
                    <?php if ($CAN_UPDATE): ?><a class="btn ghost sm" href="planning_form.php?id=<?php echo (int)$r['id']; ?>">수정</a><?php endif; ?>
                    <?php if ($CAN_DELETE): ?><button type="button" class="btn danger sm" onclick="delPlan(<?php echo (int)$r['id']; ?>, this)">삭제</button><?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

const plField=document.getElementById('plField'), plSearch=document.getElementById('plSearch'), plCount=document.getElementById('plCount');
const plDone=document.getElementById('plDone'), plDateField=document.getElementById('plDateField'), plDateFrom=document.getElementById('plDateFrom'), plDateTo=document.getElementById('plDateTo');
const total=document.querySelectorAll('#plBody tr[data-id]').length;
function plFilter(){
  const f=plField.value, q=(plSearch.value||'').toLowerCase().trim();
  const dv=plDone.value, df=plDateField.value, from=plDateFrom.value, to=plDateTo.value;
  let n=0;
  document.querySelectorAll('#plBody tr[data-id]').forEach(tr=>{
    let ok=true;
    if(q) ok = ok && (tr.dataset[f]||'').includes(q);
    if(dv==='__none__') ok = ok && (tr.dataset.done||'')==='';
    else if(dv) ok = ok && (tr.dataset.done||'')===dv;
    if(df){
      const dval=tr.dataset[df]||'';   // docdate / updatedat (YYYY-MM-DD)
      if(from) ok = ok && (dval!=='' && dval>=from);
      if(to)   ok = ok && (dval!=='' && dval<=to);
    }
    tr.style.display=ok?'':'none'; if(ok)n++;
  });
  plCount.textContent = `검색 수 ${n} / 총 수 ${total}`;
}
function plDoSearch(){
  const hasText=!!plSearch.value.trim(), hasDone=!!plDone.value, hasDate=!!plDateField.value && (!!plDateFrom.value || !!plDateTo.value);
  if(!hasText && !hasDone && !hasDate){ alert('검색어를 입력하세요'); plSearch.focus(); return; }
  plFilter();
}
plSearch.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); plDoSearch(); } });
plDone.addEventListener('change',plFilter);   // 작성여부: 선택 즉시 필터
document.getElementById('plSearchBtn').addEventListener('click',plDoSearch);
document.getElementById('plResetBtn').addEventListener('click',()=>{ plField.selectedIndex=0; plSearch.value=''; plDone.selectedIndex=0; plDateField.selectedIndex=0; plDateFrom.value=''; plDateTo.value=''; plFilter(); plSearch.focus(); });

/* 댓글 수 정렬 (많은 순/적은 순/기본순) + 번호 재부여 */
const plSort=document.getElementById('plSort'), plBody=document.getElementById('plBody');
function plRenumber(){ let i=1; plBody.querySelectorAll('tr[data-id]').forEach(tr=>{ if(tr.firstElementChild) tr.firstElementChild.textContent=i++; }); }
function plApplySort(){
  const v=plSort.value;
  const rows=[...plBody.querySelectorAll('tr[data-id]')];
  rows.sort((a,b)=>{
    if(!v) return (+a.dataset.ord)-(+b.dataset.ord);
    const ca=+(a.dataset.cc||0), cb=+(b.dataset.cc||0);
    return v==='asc' ? (ca-cb)||((+a.dataset.ord)-(+b.dataset.ord)) : (cb-ca)||((+a.dataset.ord)-(+b.dataset.ord));
  });
  rows.forEach(tr=>plBody.appendChild(tr));
  plRenumber();
}
plSort.addEventListener('change',plApplySort);
plApplySort();   // 로드 시(브라우저가 셀렉트 값을 복원한 경우 포함) 현재 선택값 기준으로 즉시 정렬

try{ const m=sessionStorage.getItem('planToast'); if(m){ sessionStorage.removeItem('planToast'); setTimeout(()=>toast(m),100); } }catch(e){}
async function delPlan(id, btn){
  if(!confirm('이 기획서 항목을 삭제하시겠습니까?')) return;
  try{
    const r=await fetch('planning.php?api=planning&id='+id,{method:'DELETE',cache:'no-store'});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const tr=btn.closest('tr'); if(tr) tr.remove(); toast('삭제되었습니다');
  }catch(e){ alert('삭제 실패: '+e.message); }
}
</script>
</body>
</html>

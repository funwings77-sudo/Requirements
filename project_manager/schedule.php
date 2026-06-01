<?php
/* =====================================================================
   프로젝트 매니저 — 일정 (schedule.php)
   - 상단 메타·인력·통계·완료일정: schedule_data.php(정적, xlsx 추출)
   - 진척 항목 32+건: project_item 테이블 — CRUD 지원
   - API: ?api=projects (GET/POST/PUT/DELETE)
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

if (isset($_GET['api'])) { schedule_api($DB); exit; }
require_perm('project_manager', 'access');
$PERM = perm_map('project_manager');

function pj($d,$c=200){ http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function perr($m,$c=400){ pj(['error'=>$m], $c); }

/* 프로젝트 매니저 편집 전권자: 박진국 / 휴대폰 01021016790 (등록·관리·수정·드래그 전용)
   세션엔 전화번호가 없으므로 member 테이블에서 조회해 이름+번호로 확정 */
function pm_is_owner($pdo){
  $u = auth_user();
  if (!$u || ($u['name'] ?? '') !== '박진국') return false;
  if (!$pdo) return false;
  try {
    $st = $pdo->prepare("SELECT phone FROM member WHERE id = ?");
    $st->execute([(int)($u['id'] ?? 0)]);
    return preg_replace('/\D+/', '', (string)$st->fetchColumn()) === '01021016790';
  } catch (Throwable $e) { return false; }
}

function schedule_api($DB) {
  $api = $_GET['api']; $method = $_SERVER['REQUEST_METHOD'];
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { perr('DB 연결 실패: ' . $e->getMessage(), 500); }

  // 드래그 정렬 저장: { order:[id,id,...] } → sort_order 0..N-1
  if ($api === 'reorder') {
    if (!pm_is_owner($pdo)) perr('순서 변경 권한이 없습니다', 403);
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT'], true)) perr('지원하지 않는 메서드', 405);
    $body = json_decode(file_get_contents('php://input'), true);
    $order = null;
    if (is_array($body)) {
      if (isset($body['ids']) && is_array($body['ids'])) $order = $body['ids'];           // requirements와 동일 계약
      elseif (isset($body['order']) && is_array($body['order'])) $order = $body['order'];  // 하위호환
    }
    if (!$order) perr('ids 배열이 필요합니다');
    try {
      // 드래그 위치에 맞게 번호(no)를 1..N으로 재정립
      $pdo->beginTransaction();
      $up = $pdo->prepare("UPDATE project_item SET `no` = ? WHERE id = ?");
      $n = 1; foreach (array_values($order) as $pid) { $up->execute([$n++, (int)$pid]); }
      $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); perr('순서 저장 실패: '.$e->getMessage(), 500); }
    pj(['ok'=>true, 'count'=>count($order)]);
  }

  // 인력구성 이름 인라인 수정 — project_meta.staff_json(인덱스→이름)에 오버라이드 저장
  if ($api === 'staff') {
    if ($method !== 'PUT') perr('지원하지 않는 메서드', 405);
    if (!auth_is_admin()) perr('수정 권한이 없습니다(관리자 전용)', 403);
    $b = json_decode(file_get_contents('php://input'), true);
    if (!is_array($b) || !isset($b['idx'])) perr('idx 필요');
    $idx = (int)$b['idx'];
    $name = trim((string)($b['name'] ?? ''));
    $cur = [];
    try { $j = $pdo->query("SELECT staff_json FROM project_meta WHERE id=1")->fetchColumn();
          if ($j) { $d = json_decode($j, true); if (is_array($d)) $cur = $d; } } catch (Throwable $e) {}
    $cur[(string)$idx] = $name;
    $pdo->prepare("INSERT INTO project_meta(id,staff_json) VALUES(1,?) ON DUPLICATE KEY UPDATE staff_json=VALUES(staff_json)")
        ->execute([json_encode($cur, JSON_UNESCAPED_UNICODE)]);
    pj(['ok'=>true, 'idx'=>$idx, 'name'=>$name]);
  }

  if ($api !== 'projects') perr('알 수 없는 api: ' . $api, 404);

  $FIELDS = ['no','phase','priority','platform','item','requirement','history','progress_prev','progress_curr',
             'start_date','end_date','final_status','workdays','attach_plan','attach_des','attach_pub',
             'plan_status','plan_owner','plan_end','des_status','des_owner','des_end',
             'pub_status','pub_owner','pub_end','dev_status','dev_owner','dev_end',
             'dev_deploy','qa','prod_deploy'];
  $DATEF = ['start_date','end_date','plan_end','des_end','pub_end','dev_end','dev_deploy','prod_deploy'];
  $INTF  = ['no'];

  $SEL = "SELECT id," . implode(',', array_map(fn($f)=>"`$f`", $FIELDS))
       . ",DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') AS created_at"
       . ",DATE_FORMAT(updated_at,'%Y-%m-%d %H:%i:%s') AS updated_at"
       . ",created_by, updated_by FROM project_item";

  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($method === 'GET') {
    if (!can('project_manager','read')) perr('읽기 권한이 없습니다', 403);
    if ($id > 0) {
      $st = $pdo->prepare("$SEL WHERE id = ?"); $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) perr('없는 항목', 404);
      pj($row);
    }
    $rows = $pdo->query("$SEL ORDER BY (final_status='운영서버반영완료') DESC, COALESCE(`no`, 2147483647) ASC, id ASC")->fetchAll();
    pj($rows);
  }

  $me = auth_user();
  $body = []; $item = '';
  if ($method === 'POST' || $method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];
    // 항목명 필수 — 신규는 항상, 수정은 item을 보낼 때만(인라인 부분 수정 지원)
    if ($method === 'POST' || array_key_exists('item', $body)) {
      $item = isset($body['item']) ? trim((string)$body['item']) : '';
      if ($item === '') perr('항목명은 필수입니다');
    }
    // 날짜 형식 점검(전달된 값만, 빈 값은 허용)
    foreach ($DATEF as $f) {
      if (!array_key_exists($f, $body)) continue;
      $v = trim((string)$body[$f]);
      if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) perr("{$f} 형식 오류(YYYY-MM-DD)");
    }
  }

  if ($method === 'POST') {
    if (!pm_is_owner($pdo)) perr('등록 권한이 없습니다', 403);
    $vals = []; $cols = [];
    foreach ($FIELDS as $f) {
      $cols[] = "`$f`";
      $v = isset($body[$f]) ? trim((string)$body[$f]) : '';
      if (in_array($f, $DATEF, true)) $v = ($v === '' ? null : $v);
      elseif (in_array($f, $INTF, true)) $v = ($v === '' ? null : (int)$v);
      else $v = ($v === '' ? null : $v);
      $vals[] = $v;
    }
    $cols[] = '`created_by`'; $vals[] = ($me ? $me['name'] : null);
    $cols[] = '`updated_by`'; $vals[] = ($me ? $me['name'] : null);
    $ph = implode(',', array_fill(0, count($vals), '?'));
    $st = $pdo->prepare("INSERT INTO project_item (" . implode(',', $cols) . ") VALUES ($ph)");
    $st->execute($vals);
    try { $pdo->exec("INSERT INTO project_meta(id,rev,last_updated) VALUES(1,2,CURDATE()) ON DUPLICATE KEY UPDATE rev=rev+1, last_updated=CURDATE()"); } catch (Throwable $e) {}   // 등록 시 버전/최종수정일 자동 갱신
    $newId = $pdo->lastInsertId();
    $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$newId]);
    pj($g->fetch(), 201);
  }

  if ($method === 'PUT') {
    if (!pm_is_owner($pdo)) perr('수정 권한이 없습니다', 403);
    if ($id <= 0) perr('id 필요');
    $chk = $pdo->prepare("SELECT id FROM project_item WHERE id = ?"); $chk->execute([$id]);
    if (!$chk->fetchColumn()) perr('없는 항목', 404);
    $sets = []; $vals = [];
    foreach ($FIELDS as $f) {
      if (!array_key_exists($f, $body)) continue;   // 전달된 필드만 수정(인라인 부분 저장)
      $v = trim((string)$body[$f]);
      if (in_array($f, $DATEF, true)) $v = ($v === '' ? null : $v);
      elseif (in_array($f, $INTF, true)) $v = ($v === '' ? null : (int)$v);
      else $v = ($v === '' ? null : $v);
      $sets[] = "`$f` = ?"; $vals[] = $v;
    }
    if (!$sets) perr('수정할 필드가 없습니다');
    $sets[] = "`updated_by` = ?"; $vals[] = ($me ? $me['name'] : null);
    $vals[] = $id;
    $st = $pdo->prepare("UPDATE project_item SET " . implode(',', $sets) . " WHERE id = ?");
    $st->execute($vals);
    try { $pdo->exec("INSERT INTO project_meta(id,rev,last_updated) VALUES(1,2,CURDATE()) ON DUPLICATE KEY UPDATE rev=rev+1, last_updated=CURDATE()"); } catch (Throwable $e) {}   // 수정 시 버전/최종수정일 자동 갱신
    $g = $pdo->prepare("$SEL WHERE id = ?"); $g->execute([$id]);
    pj($g->fetch());
  }

  if ($method === 'DELETE') {
    if (!pm_is_owner($pdo)) perr('삭제 권한이 없습니다', 403);
    if ($id <= 0) perr('id 필요');
    $st = $pdo->prepare("DELETE FROM project_item WHERE id = ?"); $st->execute([$id]);
    pj(['ok'=>true, 'deleted'=>$st->rowCount()]);
  }
  perr('지원하지 않는 메서드', 405);
}

$ME   = auth_user();
$DATA = require __DIR__ . '/schedule_data.php';

// 진척 항목은 DB에서 로드 (상단 메타·통계는 schedule_data 정적 유지)
try {
  $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
  $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $ITEMS = $pdo->query("SELECT id," . implode(',', array_map(fn($f)=>"`$f`",
      ['no','phase','priority','platform','item','requirement','history','progress_prev','progress_curr',
       'start_date','end_date','final_status','workdays','attach_plan','attach_des','attach_pub',
       'plan_status','plan_owner','plan_end','des_status','des_owner','des_end',
       'pub_status','pub_owner','pub_end','dev_status','dev_owner','dev_end',
       'dev_deploy','qa','prod_deploy']))
      . " FROM project_item ORDER BY (final_status='운영서버반영완료') DESC, COALESCE(`no`, 2147483647) ASC, id ASC")->fetchAll();
} catch (Throwable $e) { $ITEMS = []; }
$DATA['items'] = $ITEMS;

// 버전/최종수정일: project_meta에서 동적 로드(프로젝트 등록·수정마다 자동 증가)
if (isset($pdo)) {
  try {
    $mv = $pdo->query("SELECT rev, last_updated FROM project_meta WHERE id=1")->fetch();
    if ($mv) {
      $lu = !empty($mv['last_updated']) ? date('Y.n.j', strtotime($mv['last_updated'])) : '';
      $DATA['meta']['version_line'] = 'Version: 1.' . (int)$mv['rev'] . ($lu !== '' ? ' Last Updated: ' . $lu : '');
    }
    // 인력구성 이름 오버라이드(페이지에서 수정한 값) 반영
    $sj = $pdo->query("SELECT staff_json FROM project_meta WHERE id=1")->fetchColumn();
    if ($sj) { $ov = json_decode($sj, true);
      if (is_array($ov)) foreach ($DATA['staff'] as $i => $pair) { if (array_key_exists((string)$i, $ov)) $DATA['staff'][$i][1] = $ov[(string)$i]; }
    }
  } catch (Throwable $e) {}
}

/* 인라인 편집용 선택지(form.php와 동일) */
$STATUS_FINAL = ['','진행예정','기획진행중','기획완료','디자인진행중','퍼블리싱진행중','개발진행중','개발완료','검수완료','운영서버반영완료','보류','작업대상아님'];
$STATUS_STEP  = ['','진행예정','진행중','완료','검수완료','작업대상아님','대상아님','선택','보류'];
$PHASE        = ['','1차','2차','1차 요건 + 2차 고도화','3차','기타'];
$PRIORITY     = ['','1','2','3','4','5'];
$PLATFORM     = ['','APP','BO','APP/BO','APP(WEB)','IF','법률검토','기타'];
$QA           = ['','선택','검수완료','반려','보류'];
/* 좌측 고정(틀고정) 컬럼: 번호~항목 / 인라인 수정 제외 컬럼: 번호·작업일수 */
$FROZEN = ['no','phase','priority','platform','item'];
$NOEDIT = ['no','workdays'];
$IS_OWNER = pm_is_owner($pdo ?? null);   // 박진국/01021016790 — 등록·관리·수정·드래그 전권자
$canDrag  = $IS_OWNER;                     // 순서 변경(드래그)도 전용자만

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtPct($s){ if($s===''||$s===null) return ''; if(is_numeric($s)) { $f=(float)$s; return rtrim(rtrim(number_format($f*100,2),'0'),'.').'%'; } return $s; }
function statusClass($s){
  $m = [
    '검수완료'         => 'st-done',
    '운영서버반영완료' => 'st-prod',
    '완료'             => 'st-done2',
    '개발완료'         => 'st-done2',
    '기획완료'         => 'st-done2',
    '진행중'           => 'st-prog',
    '기획진행중'       => 'st-prog',
    '디자인진행중'     => 'st-prog',
    '개발진행중'       => 'st-prog',
    '진행예정'         => 'st-ready',
    '작업대상아님'     => 'st-na',
    '대상아님'         => 'st-na',
    '보류'             => 'st-hold',
    '선택'             => 'st-na',
  ];
  return $m[$s] ?? '';
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>일정 · 메디힘</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css">
<style>
  /* === 페이지 전용 스타일 === */
  /* 메인 컨테이너가 큰 테이블 때문에 늘어나지 않도록 — sch-grid를 페이지 폭에 고정 */
  main.main{min-width:0;}
  .sch-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1.4fr) minmax(0,1fr);gap:14px;margin-bottom:18px;}
  @media (max-width: 1280px){ .sch-grid{grid-template-columns:minmax(0,1fr) minmax(0,1fr);} }
  @media (max-width: 900px){  .sch-grid{grid-template-columns:1fr;} }
  .info-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(28,30,40,.03);min-width:0;}
  .info-card h3{margin:0 0 12px;font-size:14px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:6px;}
  .info-card h3 .info-emoji{font-size:16px;}
  .meta-card{display:flex;flex-direction:column;gap:8px;}
  .meta-card .mt{display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--ink);line-height:1.5;}
  .meta-card .mt-lbl{min-width:88px;font-weight:700;color:var(--sub);font-size:12px;}
  .meta-card .mt-val{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;}
  .meta-card .mt-val.wrap{white-space:normal;overflow:visible;text-overflow:clip;}
  .meta-card a{color:var(--brand-d);text-decoration:none;}
  .meta-card a:hover{text-decoration:underline;}

  /* 인력구성 */
  table.staff-tbl{width:100%;border-collapse:collapse;font-size:13px;}
  table.staff-tbl th,table.staff-tbl td{padding:6px 8px;border-bottom:1px solid var(--line);text-align:left;}
  table.staff-tbl th{background:#f8f9fb;color:var(--sub);font-weight:700;font-size:12px;width:140px;}
  .staff-name{display:inline-block;min-width:60px;padding:1px 6px;border-radius:6px;cursor:text;outline:none;border:1px dashed transparent;}
  .staff-name:hover{border-color:var(--line);background:#fafafe;}
  .staff-name:focus{border-color:#EC70C8;background:#fff;box-shadow:0 0 0 2px rgba(236,112,200,.15);}

  /* 통계 매트릭스 */
  table.stats-tbl{width:100%;border-collapse:collapse;font-size:12.5px;}
  table.stats-tbl th,table.stats-tbl td{padding:6px 8px;border:1px solid var(--line);text-align:center;}
  table.stats-tbl thead th{background:#f3f1fb;color:var(--brand-d);font-weight:800;}
  table.stats-tbl tbody th{background:#f8f9fb;color:var(--sub);font-weight:700;text-align:left;white-space:nowrap;}
  table.stats-tbl .row-done th{background:#e8f5ee;color:#1b7a3a;}
  table.stats-tbl .row-rate th{background:#ede7fc;color:var(--brand-d);}
  table.stats-tbl .row-rate td{font-weight:800;color:var(--brand-d);}

  /* 최종 완료일 */
  .final-list{display:flex;flex-direction:column;gap:8px;}
  .final-list .fl{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8f7fc;border:1px solid #ede7fc;border-radius:10px;font-size:13px;}
  .final-list .fl-lbl{font-weight:700;color:var(--ink);display:flex;align-items:center;gap:6px;}
  .final-list .fl-date{font-weight:800;color:var(--brand-d);font-variant-numeric:tabular-nums;}

  /* 메인 테이블 */
  .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:14px;}
  .list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .count-chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:20px;background:var(--brand-soft);color:var(--brand-d);font-weight:700;font-size:12.5px;}
  /* 리스트 좌우 스크롤: requirements 리스트와 동일(.table-scroll = overflow-x만, 세로는 페이지 스크롤) */
  .table-scroll{border:1px solid var(--line);border-radius:12px;overflow-x:auto;background:#fff;}
  table.sch-tbl{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px;}
  table.sch-tbl thead th{background:#f3f1fb;color:var(--brand-d);font-weight:800;padding:8px 10px;border-bottom:1px solid var(--line);border-right:1px solid var(--line);white-space:nowrap;font-size:12px;}
  table.sch-tbl thead th:last-child{border-right:none;}
  table.sch-tbl tbody td{padding:8px 10px;border-bottom:1px solid #eef0f4;border-right:1px solid #eef0f4;vertical-align:middle;color:var(--ink);}
  table.sch-tbl tbody td:last-child{border-right:none;}
  table.sch-tbl tbody tr:hover td{background:#fafaff;}
  /* 좌측 틀고정(requirements 방식): min-width:641px에서만 sticky, left는 JS(applyFreeze)가 측정 부여 */
  @media(min-width:641px){
    table.sch-tbl .frz{position:sticky;z-index:2;background:#fff;}
    table.sch-tbl thead .frz{z-index:5;background:#ede7fc;color:var(--brand-d);}
    table.sch-tbl tbody tr:hover .frz{background:#faf9fd;}
    table.sch-tbl .frz-edge{box-shadow:6px 0 8px -6px rgba(0,0,0,.18);}
  }
  /* 고정 컬럼 폭 안정화 */
  td.t-no,th[data-ci="0"]{width:78px;min-width:78px;}
  td.t-phase{min-width:74px;} td.t-prio{min-width:64px;} td.t-pf{min-width:78px;}
  /* 드래그 정렬 (requirements 리스트와 동일 동작: 행 전체 draggable + 위/아래 드롭선) */
  table.sch-tbl td.t-no{cursor:grab;user-select:none;}
  table.sch-tbl td.t-no:active{cursor:grabbing;}
  .t-no .drag-h{color:#c2c8d2;margin-right:6px;letter-spacing:-1px;}
  .t-no .no-v{font-weight:800;}
  table.sch-tbl tbody tr.dragging{opacity:.4;}
  table.sch-tbl tbody tr.drop-before > td{box-shadow:inset 0 2px 0 0 var(--brand);}
  table.sch-tbl tbody tr.drop-after  > td{box-shadow:inset 0 -2px 0 0 var(--brand);}
  /* 인라인 편집 */
  td.cell-edit{cursor:text;}
  td.cell-edit:hover{outline:1px dashed var(--brand);outline-offset:-2px;}
  td.editing{padding:2px !important;}
  .cell-input{width:100%;box-sizing:border-box;border:1px solid var(--brand);border-radius:5px;padding:4px 6px;font:inherit;font-size:12px;background:#fff;color:var(--ink);}
  textarea.cell-area{min-height:72px;resize:vertical;line-height:1.4;}
  .attach-y.attach-open{cursor:pointer;text-decoration:none;border:1px solid #bfe7cf;background:#dff5ea;border-radius:12px;padding:1px 9px;font-size:11px;}
  .attach-y.attach-open:hover{background:#127c3c;color:#fff;}
  /* 셀 타입별 */
  td.t-item{font-weight:700;color:var(--ink);min-width:160px;max-width:220px;white-space:normal;}
  td.t-text{white-space:pre-line;max-width:320px;color:#555;font-size:12px;line-height:1.45;}
  td.t-date{font-variant-numeric:tabular-nums;color:#444;white-space:nowrap;}
  td.t-pct{font-weight:700;text-align:right;font-variant-numeric:tabular-nums;color:var(--brand-d);}
  td.t-pf,td.t-no,td.t-phase,td.t-prio,td.t-attach{text-align:center;white-space:nowrap;}
  /* 본문 텍스트 center(주요 요건정의·이슈/History 제외) */
  table.sch-tbl tbody td{text-align:center;}
  table.sch-tbl tbody td.t-text{text-align:left;}
  /* 상태 뱃지 */
  .st-pill{display:inline-block;padding:2px 8px;border-radius:14px;font-size:11.5px;font-weight:700;white-space:nowrap;border:1px solid transparent;}
  .st-done{background:#dff5ea;color:#127c3c;border-color:#bfe7cf;}
  .st-prod{background:#e8f1ff;color:#1e4ec7;border-color:#cad9f7;}
  /* 최종상태 '운영서버반영완료' 행 배경 강조 */
  tr.row-prod > td{background:#F9CFED !important;}
  .st-done2{background:#e0f0ff;color:#2160c0;border-color:#bcd4f1;}
  .st-prog{background:#fff4d8;color:#a06212;border-color:#f0dcad;}
  .st-ready{background:#eef0f4;color:#5a6373;border-color:#dde1e8;}
  .st-na{background:#f6f6f7;color:#9aa3b2;border-color:#e7e8eb;}
  .st-hold{background:#fde5e5;color:#a52323;border-color:#f1c4c4;}
  /* 첨부 표시 */
  .attach-y{color:#127c3c;font-weight:800;}
  .attach-n{color:#c8cdd6;}
  /* 관리 컬럼 */
  td.t-actions{white-space:nowrap;text-align:center;background:#fdfdff;}
  .row-actions{display:inline-flex;gap:4px;}
  .row-actions .btn{padding:3px 9px;font-size:11.5px;}
  td.t-item a{color:inherit;text-decoration:none;border-bottom:1px dashed transparent;}
  td.t-item a:hover{color:var(--brand-d);border-bottom-color:var(--brand-d);}
  /* 검색바 */
  .search-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .search-bar input,.search-bar select{height:34px;}
  .search-bar input.q{width:240px;}
  .empty-row td{color:#9aa3b2;text-align:center;padding:30px 0;}
</style>
</head>
<body>
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">🗓️ 일정</span>
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
        <h1 data-lnb-title="project_manager">일정</h1>
        <div class="pg-sub"><?php echo h($DATA['meta']['title']); ?></div>
      </div>
      <span class="spacer"></span>
      <?php auth_bell(); ?>
    </div>

    <!-- 상단 요약: 메타 + 인력구성 + 통계 + 최종완료일 -->
    <div class="sch-grid">
      <!-- 프로젝트 메타 -->
      <div class="info-card meta-card">
        <h3><span class="info-emoji">📄</span> 프로젝트 정보</h3>
        <div class="mt"><span class="mt-lbl">제목</span><span class="mt-val wrap"><?php echo h($DATA['meta']['title']); ?></span></div>
        <div class="mt"><span class="mt-lbl">버전</span><span class="mt-val wrap"><?php echo h($DATA['meta']['version_line']); ?></span></div>
        <div class="mt"><span class="mt-lbl">총 개체수</span><span class="mt-val"><strong><?php echo h($DATA['meta']['total']); ?></strong> 건</span></div>
        <?php
          // 운영개발 SHEETS URL 추출
          if (preg_match('~https?://\S+~', $DATA['meta']['sheets_url'] ?? '', $m)) {
            $url = $m[0];
            echo '<div class="mt"><span class="mt-lbl">운영개발 SHEETS</span><span class="mt-val"><a href="'.h($url).'" target="_blank" rel="noopener">🔗 시트 열기</a></span></div>';
          }
        ?>
      </div>

      <!-- 인력구성 -->
      <div class="info-card">
        <h3><span class="info-emoji">👥</span> 인력구성</h3>
        <table class="staff-tbl">
          <tbody>
          <?php foreach ($DATA['staff'] as $si => [$role, $name]): ?>
            <tr><th><?php echo h($role); ?></th><td>
              <?php if (auth_is_admin()): ?>
                <span class="staff-name" contenteditable="true" data-idx="<?php echo (int)$si; ?>" data-orig="<?php echo h($name); ?>" title="클릭하여 이름 수정(관리자)"><?php echo h($name); ?></span>
              <?php else: echo h($name) ?: '<span class="muted">-</span>'; endif; ?>
            </td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 최종 완료일 -->
      <div class="info-card">
        <h3><span class="info-emoji">🏁</span> 최종 완료 일정</h3>
        <div class="final-list">
          <?php foreach ($DATA['final_dates'] as $k=>$d): ?>
            <div class="fl">
              <span class="fl-lbl">
                <?php echo ['전체'=>'🎯','디자인'=>'🎨','퍼블리싱'=>'🧱','개발'=>'💻'][$k] ?? '•'; ?>
                <?php echo h($k); ?>
              </span>
              <span class="fl-date"><?php echo $d ? h($d) : '<span class="muted">-</span>'; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 통계 매트릭스 -->
    <div class="info-card" style="margin-bottom:18px;">
      <h3><span class="info-emoji">📊</span> 진척 통계</h3>
      <table class="stats-tbl">
        <thead><tr><th style="width:140px;text-align:left;">구분</th>
          <?php foreach ($DATA['stat_cols'] as $c): ?><th><?php echo h($c); ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($DATA['stats'] as $row):
            $name = $row[0];
            $rowCls = '';
            if ($name === '검수완료(최종)' || $name === '완료') $rowCls='row-done';
            if ($name === '완료율') $rowCls='row-rate';
          ?>
            <tr class="<?php echo $rowCls; ?>">
              <th><?php echo h($name); ?></th>
              <?php for($i=1;$i<=4;$i++): $v=$row[$i]??'';
                if ($name==='완료율' && is_numeric($v)) $v = rtrim(rtrim(number_format(((float)$v)*100,2),'0'),'.').'%';
              ?>
                <td><?php echo h($v); ?></td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 진척 항목 -->
    <div class="card">
      <div class="card-body">
        <div class="list-head">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="card-title" style="margin:0;display:inline-flex;align-items:center;gap:7px;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13l2 2 4-4"/></svg>진척 항목</h2>
            <span class="count-chip" id="schCnt">검색 수 <?php echo count($DATA['items']); ?> / 총 수 <?php echo count($DATA['items']); ?></span>
          </div>
          <div class="list-actions">
            <?php if ($IS_OWNER): ?>
              <a id="addBtn" class="btn primary sm" href="form.php" style="background:#FB64C9;color:#fff;border:none;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);">+ 프로젝트 등록</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="search-bar">
          <label style="font-size:12px;font-weight:700;color:var(--sub);">통합검색</label>
          <input id="q" class="form-control form-control-sm q" placeholder="항목/요건/담당자/상태 검색...">
          <label style="font-size:12px;font-weight:700;color:var(--sub);margin-left:6px;">최종 상태</label>
          <select id="fStatus" class="form-select form-select-sm" style="width:auto;">
            <option value="">전체</option>
            <?php
              $st_set = [];
              foreach ($DATA['items'] as $it) if (!empty($it['final_status'])) $st_set[$it['final_status']]=1;
              foreach (array_keys($st_set) as $s) echo '<option value="'.h($s).'">'.h($s).'</option>';
            ?>
          </select>
          <label style="font-size:12px;font-weight:700;color:var(--sub);margin-left:6px;">플랫폼</label>
          <select id="fPlatform" class="form-select form-select-sm" style="width:auto;">
            <option value="">전체</option>
            <?php
              $pf_set = [];
              foreach ($DATA['items'] as $it) if (!empty($it['platform'])) $pf_set[$it['platform']]=1;
              foreach (array_keys($pf_set) as $s) echo '<option value="'.h($s).'">'.h($s).'</option>';
            ?>
          </select>
          <button type="button" class="btn ghost sm" id="btnReset">↺ 초기화</button>
        </div>

        <div class="table-scroll" id="tableWrap">
          <table class="sch-tbl" id="schTbl">
            <thead><tr>
              <?php $ci=0; foreach ($DATA['cols'] as $c): $frz = in_array($c['key'],$FROZEN,true); ?>
                <th<?php echo $frz?' class="frz"':''; ?> data-ci="<?php echo $ci; ?>"><?php echo h($c['label']); ?></th>
              <?php $ci++; endforeach; ?>
              <?php if ($IS_OWNER): ?><th style="width:140px;">관리</th><?php endif; ?>
            </tr></thead>
            <tbody id="schBody">
            <?php foreach ($DATA['items'] as $row):
                $rid = (int)($row['id']??0);
                $dragAttr = $canDrag ? ' draggable="true" ondragstart="dragStart(event,'.$rid.')" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dragDrop(event,'.$rid.')" ondragend="dragEnd(event)"' : '';
            ?>
              <tr<?php echo $dragAttr; ?> class="<?php echo (($row['final_status']??'')==='운영서버반영완료')?'row-prod':''; ?>"
                data-id="<?php echo $rid; ?>"
                data-q="<?php echo h(strtolower(($row['item']??'').' '.strip_tags($row['requirement']??'').' '.strip_tags($row['history']??'').' '.($row['plan_owner']??'').' '.($row['des_owner']??'').' '.($row['pub_owner']??'').' '.($row['dev_owner']??'').' '.($row['final_status']??''))); ?>"
                data-status="<?php echo h($row['final_status']??''); ?>"
                data-platform="<?php echo h($row['platform']??''); ?>"
              >
                <?php $ci=0; foreach ($DATA['cols'] as $c):
                  $k = $c['key']; $t = $c['type']; $v = $row[$k] ?? '';
                  $isStatusCol = in_array($k, ['final_status','plan_status','des_status','pub_status','dev_status','qa'], true);
                  $isAttach    = in_array($k, ['attach_plan','attach_des','attach_pub'], true);
                  $cls = 't-'.$t;
                  if ($k === 'no') $cls = 't-no';
                  elseif ($k === 'phase')    $cls = 't-phase';
                  elseif ($k === 'priority') $cls = 't-prio';
                  elseif ($k === 'platform') $cls = 't-pf';
                  elseif ($k === 'item')     $cls = 't-item';
                  elseif (in_array($k, ['requirement','history'], true)) $cls = 't-text';
                  elseif ($isAttach) $cls = 't-attach';
                  $frz = in_array($k, $FROZEN, true);
                  $editable = $IS_OWNER && !in_array($k, $NOEDIT, true) && !$isAttach;   // 전용자만 / 첨부는 확인버튼만(인라인 편집 제외)
                  $classes = $cls . ($frz ? ' frz' : '') . ($editable ? ' cell-edit' : '');
                  ob_start();
                  if ($k === 'no') {
                    echo ($canDrag ? '<span class="drag-h" title="드래그하여 순서 변경">⠿</span>' : '').'<span class="no-v">'.h($v).'</span>';
                  } elseif ($isStatusCol && $v !== '') {
                    echo '<span class="st-pill '.statusClass($v).'">'.h($v).'</span>';
                  } elseif ($isAttach) {
                    if (preg_match('~^https?://~i', (string)$v)) {
                      echo '<a class="attach-y attach-open" href="'.h($v).'" target="_blank" rel="noopener" title="'.h($v).'" onclick="event.stopPropagation()">확인</a>';
                    } elseif ($v === '확인') {
                      echo '<span class="attach-y">확인</span>';
                    } elseif ($v !== '') {
                      echo h($v);
                    } else {
                      echo '<span class="attach-n">·</span>';
                    }
                  } elseif (in_array($k, ['requirement','history'], true)) {
                    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v)));
                    echo $plain === '' ? '<span class="muted">·</span>'
                      : h(mb_strlen($plain) > 120 ? mb_substr($plain, 0, 120).'…' : $plain);
                  } elseif ($t === 'date' && !$v) {
                    echo '<span class="muted">-</span>';
                  } elseif ($t === 'pct') {
                    echo $v !== '' ? h(fmtPct($v)) : '<span class="muted">·</span>';
                  } elseif (!$v && $v !== '0') {
                    echo '<span class="muted">·</span>';
                  } else {
                    echo h($v);
                  }
                  $inner = ob_get_clean();
                  echo '<td class="'.$classes.'" data-ci="'.$ci.'" data-k="'.h($k).'" data-v="'.h($v).'"'.($editable?'':' data-noedit').'>'.$inner.'</td>';
                  $ci++;
                endforeach; ?>
                <?php if ($IS_OWNER): ?>
                  <td class="t-actions">
                    <div class="row-actions">
                      <a class="btn ghost sm" href="form.php?id=<?php echo (int)$row['id']; ?>">수정</a>
                      <button class="btn danger sm" onclick="delProject(<?php echo (int)$row['id']; ?>, this)">삭제</button>
                    </div>
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

<script>
/* LNB drawer */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
document.getElementById('hambBtn').onclick=()=>{ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); };
const closeLnb=()=>{ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); };
document.getElementById('lnbClose').onclick=closeLnb; lnbOverlay.onclick=closeLnb;

/* 검색·필터 */
const rows = [...document.querySelectorAll('#schBody tr')];
const $q   = document.getElementById('q');
const $st  = document.getElementById('fStatus');
const $pf  = document.getElementById('fPlatform');
const $cnt = document.getElementById('schCnt');
const TOTAL= rows.length;
function applyFilter(){
  const ql = ($q.value||'').toLowerCase().trim();
  const st = $st.value, pf = $pf.value;
  let shown = 0;
  rows.forEach(tr=>{
    let ok = true;
    if (ql && !tr.dataset.q.includes(ql)) ok = false;
    if (ok && st && tr.dataset.status !== st) ok = false;
    if (ok && pf && tr.dataset.platform !== pf) ok = false;
    tr.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });
  $cnt.textContent = `검색 수 ${shown} / 총 수 ${TOTAL}`;
}
$q.addEventListener('input', applyFilter);
$st.addEventListener('change', applyFilter);
$pf.addEventListener('change', applyFilter);
document.getElementById('btnReset').onclick = ()=>{ $q.value=''; $st.value=''; $pf.value=''; applyFilter(); };

/* 토스트 */
let toT; function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>t.classList.remove('show'),2200); }

/* 삭제 */
async function delProject(id, btn){
  if (!confirm(`이 프로젝트 항목을 삭제하시겠습니까? (id=${id})`)) return;
  try{
    const r = await fetch(`?api=projects&id=${id}`, {method:'DELETE', cache:'no-store'});
    if (!r.ok){ const e = await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    const tr = btn.closest('tr');
    if (tr) tr.remove();
    // 카운트 갱신
    const remaining = document.querySelectorAll('#schBody tr').length;
    document.getElementById('schCnt').textContent = `검색 수 ${remaining} / 총 수 ${remaining}`;
    toast('삭제되었습니다');
  }catch(e){ alert('삭제 실패: ' + e.message); }
}

/* =====================================================================
   인라인 편집 · 드래그 정렬 · 좌측 틀고정 · 가로 스크롤
   ===================================================================== */
const CAN_EDIT = <?php echo $IS_OWNER ? 'true' : 'false'; ?>;
const SELECT_OPTS = <?php echo json_encode([
  'phase'=>$PHASE,'priority'=>$PRIORITY,'platform'=>$PLATFORM,'final_status'=>$STATUS_FINAL,
  'plan_status'=>$STATUS_STEP,'des_status'=>$STATUS_STEP,'pub_status'=>$STATUS_STEP,'dev_status'=>$STATUS_STEP,'qa'=>$QA,
], JSON_UNESCAPED_UNICODE); ?>;
const DATE_KEYS   = ['start_date','end_date','plan_end','des_end','pub_end','dev_end','dev_deploy','prod_deploy'];
const PCT_KEYS    = ['progress_prev','progress_curr'];
const TEXT_KEYS   = ['requirement','history'];
const STATUS_KEYS = ['final_status','plan_status','des_status','pub_status','dev_status','qa'];
const ATTACH_KEYS = ['attach_plan','attach_des','attach_pub'];
const FROZEN_KEYS = ['no','phase','priority','platform','item'];
const ST_CLS = {'검수완료':'st-done','운영서버반영완료':'st-prod','완료':'st-done2','개발완료':'st-done2','기획완료':'st-done2','진행중':'st-prog','기획진행중':'st-prog','디자인진행중':'st-prog','개발진행중':'st-prog','진행예정':'st-ready','작업대상아님':'st-na','대상아님':'st-na','보류':'st-hold','선택':'st-na'};
const schBody = document.getElementById('schBody');
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function statusClass(s){ return ST_CLS[s]||''; }
function fmtPct(s){ if(s===''||s==null) return ''; if(s!=='' && !isNaN(s)){ const p=(parseFloat(s)*100).toFixed(2).replace(/\.?0+$/,''); return p+'%'; } return s; }

/* 셀 표시 HTML(서버 렌더와 동일 규칙) */
function cellInner(k, v){
  v = v==null ? '' : String(v);
  if(k==='no') return `${CAN_EDIT?'<span class="drag-h" title="드래그하여 순서 변경">⠿</span>':''}<span class="no-v">${esc(v)}</span>`;
  if(STATUS_KEYS.includes(k) && v!=='') return `<span class="st-pill ${statusClass(v)}">${esc(v)}</span>`;
  if(ATTACH_KEYS.includes(k)){
    if(/^https?:\/\//i.test(v)) return `<a class="attach-y attach-open" href="${esc(v)}" target="_blank" rel="noopener" title="${esc(v)}" onclick="event.stopPropagation()">확인</a>`;
    if(v==='확인') return '<span class="attach-y">확인</span>';
    if(v!=='') return esc(v);
    return '<span class="attach-n">·</span>';
  }
  if(TEXT_KEYS.includes(k)){
    const plain = v.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    return plain==='' ? '<span class="muted">·</span>' : esc(plain.length>120 ? plain.slice(0,120)+'…' : plain);
  }
  if(DATE_KEYS.includes(k) && !v) return '<span class="muted">-</span>';
  if(PCT_KEYS.includes(k)) return v!=='' ? esc(fmtPct(v)) : '<span class="muted">·</span>';
  if(!v && v!=='0') return '<span class="muted">·</span>';
  return esc(v);
}

/* 행의 검색·필터용 메타 갱신 */
function rebuildRowMeta(tr){
  const g = k => { const td=tr.querySelector(`td[data-k="${k}"]`); return td?(td.dataset.v||''):''; };
  const strip = s => s.replace(/<[^>]*>/g,' ');
  tr.dataset.q = [g('item'),strip(g('requirement')),strip(g('history')),g('plan_owner'),g('des_owner'),g('pub_owner'),g('dev_owner'),g('final_status')].join(' ').toLowerCase();
  tr.dataset.status = g('final_status');
  tr.dataset.platform = g('platform');
}

/* 최종상태 '운영서버반영완료' → 행 배경(row-prod) + 위치 동적 갱신
 *   - 운영서버반영완료로 변경: 맨 위로 이동 (이후 드래그로 변경 가능)
 *   - 다른 상태로 변경: 배경 제거 + 운영서버반영완료 블록 바로 아래로 이동 */
function applyProdState(tr){
  const tb = tr.parentNode; if(!tb) return;
  const isProd = (tr.dataset.status||'') === '운영서버반영완료';
  const wasProd = tr.classList.contains('row-prod');
  if(isProd === wasProd) return;            // 상태 변동 없음
  tr.classList.toggle('row-prod', isProd);
  if(isProd){ tb.insertBefore(tr, tb.firstElementChild); }   // 제일 상단
  else {
    let last=null; tb.querySelectorAll('tr.row-prod').forEach(x=>last=x);   // 운영서버반영완료 블록의 마지막 행
    if(last){ tb.insertBefore(tr, last.nextSibling); }                       // 그 바로 아래
    else { tb.insertBefore(tr, tb.firstElementChild); }                      // prod 없으면 맨 위
  }
}

/* 셀 저장(부분 PUT) */
async function saveCell(id, k, nv, ov, td){
  try{
    const r = await fetch(`?api=projects&id=${id}`, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({[k]:nv})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast('저장되었습니다');
    if(k==='final_status'){ const tr=td.closest('tr'); rebuildRowMeta(tr); applyProdState(tr); }
  }catch(e){
    td.dataset.v = ov; td.innerHTML = cellInner(k, ov);
    rebuildRowMeta(td.closest('tr')); if(FROZEN_KEYS.includes(k)) layoutFrozen();
    alert('저장 실패: ' + e.message);
  }
}

/* 인라인 편집 시작 */
function beginEdit(td){
  if(!CAN_EDIT || td.hasAttribute('data-noedit') || td.classList.contains('editing')) return;
  const k = td.dataset.k, ov = td.dataset.v || '';
  let ed;
  if(SELECT_OPTS[k]){
    ed = document.createElement('select'); ed.className='cell-input';
    SELECT_OPTS[k].forEach(opt=>{ const o=document.createElement('option'); o.value=opt; o.textContent = opt===''?'(미지정)':opt; ed.appendChild(o); });
    if(![...ed.options].some(o=>o.value===ov)){ const o=document.createElement('option'); o.value=ov; o.textContent=ov; ed.appendChild(o); }
    ed.value = ov;
  } else if(TEXT_KEYS.includes(k)){
    ed = document.createElement('textarea'); ed.className='cell-input cell-area'; ed.value = ov;
  } else if(DATE_KEYS.includes(k)){
    ed = document.createElement('input'); ed.type='date'; ed.className='cell-input'; ed.value = ov;
  } else {
    ed = document.createElement('input'); ed.type='text'; ed.className='cell-input'; ed.value = ov;
    if(ATTACH_KEYS.includes(k)) ed.placeholder='https:// 링크';
  }
  td.classList.add('editing'); td.innerHTML=''; td.appendChild(ed);
  ed.focus(); if(ed.select){ try{ ed.select(); }catch(_){} }
  let done = false;
  const finish = (save) => {
    if(done) return; done = true; td.classList.remove('editing');
    const nv = ed.value;
    if(!save || nv === ov){ td.innerHTML = cellInner(k, ov); td.dataset.v = ov; return; }
    td.dataset.v = nv; td.innerHTML = cellInner(k, nv);
    rebuildRowMeta(td.closest('tr')); if(FROZEN_KEYS.includes(k)) layoutFrozen();
    saveCell(td.closest('tr').dataset.id, k, nv, ov, td);
  };
  ed.addEventListener('blur', ()=>finish(true));
  ed.addEventListener('keydown', e=>{
    if(e.key==='Escape'){ e.preventDefault(); finish(false); }
    else if(e.key==='Enter' && ed.tagName!=='TEXTAREA'){ e.preventDefault(); ed.blur(); }
  });
  if(ed.tagName==='SELECT') ed.addEventListener('change', ()=>ed.blur());
}
schBody.addEventListener('click', e=>{
  if(e.target.closest('.attach-open') || e.target.closest('.drag-h')) return;
  const td = e.target.closest('td.cell-edit'); if(!td) return;
  beginEdit(td);
});

/* 좌측 틀고정 — requirements/requirements.php와 동일 방식
   (헤더 앞 N개 컬럼 너비로 누적 left 계산 → 헤더·본문 셀에 sticky, 모바일 제외) */
const FREEZE_COUNT = 5;   // 번호·차수·우선순위·APP/BO·항목
function layoutFrozen(){
  if(window.matchMedia('(max-width:640px)').matches) return;
  const tbl = document.getElementById('schTbl'); if(!tbl||!tbl.tHead) return;
  const ths = [...tbl.tHead.rows[0].cells];
  if(ths.length < FREEZE_COUNT) return;
  const widths = ths.slice(0,FREEZE_COUNT).map(th=>th.getBoundingClientRect().width);
  if(widths.some(w=>w===0)) return;
  const lefts = []; let acc = 0; for(let i=0;i<FREEZE_COUNT;i++){ lefts[i]=acc; acc+=widths[i]; }
  const setRow = (cells)=>{ for(let i=0;i<FREEZE_COUNT && i<cells.length;i++){ const c=cells[i]; c.classList.add('frz'); if(i===FREEZE_COUNT-1) c.classList.add('frz-edge'); c.style.left=Math.round(lefts[i])+'px'; } };
  setRow(ths);
  schBody.querySelectorAll('tr').forEach(tr=> setRow([...tr.children]));
}
/* 드래그로 위치 변경 시 번호(no)를 현재 화면 순서대로 1..N 재정립 */
function renumberRows(){
  let n = 1;
  schBody.querySelectorAll('tr').forEach(tr=>{
    const td = tr.querySelector('td[data-k="no"]');
    if(td){ td.dataset.v = String(n); const nv = td.querySelector('.no-v'); if(nv) nv.textContent = n; }
    n++;
  });
}

/* 드래그 정렬 — requirements/requirements.php 리스트와 동일 동작
   (행 전체 draggable + 드롭 위치 위/아래 선 표시 + 드롭 시 이동·저장) */
let dragId = null;
function dragStart(e, id){
  if(!CAN_EDIT){ e.preventDefault(); return; }
  dragId = id;
  if(e.dataTransfer){ e.dataTransfer.effectAllowed='move'; try{ e.dataTransfer.setData('text/plain', String(id)); }catch(_){} }
  e.currentTarget.classList.add('dragging');
}
function dragOver(e){
  e.preventDefault();
  if(e.dataTransfer) e.dataTransfer.dropEffect='move';
  const tr = e.currentTarget; if(!tr || tr.classList.contains('dragging')) return;
  const r = tr.getBoundingClientRect();
  const after = (e.clientY - r.top) > r.height/2;
  tr.classList.toggle('drop-after', after);
  tr.classList.toggle('drop-before', !after);
}
function dragLeave(e){ e.currentTarget.classList.remove('drop-before','drop-after'); }
function dragEnd(){
  dragId = null;
  schBody.querySelectorAll('tr').forEach(tr=>tr.classList.remove('dragging','drop-before','drop-after'));
}
function dragDrop(e, targetId){
  e.preventDefault();
  const tr = e.currentTarget;
  const after = tr.classList.contains('drop-after');
  tr.classList.remove('drop-before','drop-after');
  if(dragId != null && dragId !== targetId) applyReorder(dragId, targetId, after);
}
function applyReorder(srcId, targetId, after){
  const src = schBody.querySelector(`tr[data-id="${srcId}"]`);
  const tgt = schBody.querySelector(`tr[data-id="${targetId}"]`);
  if(!src || !tgt || src === tgt) return;
  if(after) tgt.after(src); else tgt.before(src);
  renumberRows();   // 위치에 맞게 번호(no) 재정립(화면)
  layoutFrozen();
  persistOrder();   // 서버도 no=1..N 재기록
}
async function persistOrder(){
  const ids = [...schBody.querySelectorAll('tr')].map(tr=>parseInt(tr.dataset.id,10));
  try{
    const r = await fetch('?api=reorder', {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids})});
    if(!r.ok){ const e=await r.json().catch(()=>({})); throw new Error(e.error||('HTTP '+r.status)); }
    toast('순서를 저장했습니다');
  }catch(err){ toast('순서 저장 실패: ' + err.message); }
}

/* 리스트 좌우 스크롤 — requirements/requirements.php와 동일 방식
   휠(세로/가로)·Shift+휠·트랙패드를 가로 스크롤로 변환, 가장자리에선 페이지 세로 스크롤 양보 */
document.querySelectorAll('.table-scroll').forEach(ts=>{
  ts.addEventListener('wheel', e=>{
    if(ts.scrollWidth <= ts.clientWidth) return;
    const delta = Math.abs(e.deltaX) >= Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    if(!delta) return;
    const atStart = ts.scrollLeft <= 0, atEnd = Math.ceil(ts.scrollLeft + ts.clientWidth) >= ts.scrollWidth;
    if((delta<0 && atStart) || (delta>0 && atEnd)) return;
    ts.scrollLeft += delta;
    e.preventDefault();
  }, {passive:false});
});

function relayout(){ layoutFrozen(); }
window.addEventListener('resize', ()=>{ clearTimeout(relayout._t); relayout._t=setTimeout(relayout,120); });
window.addEventListener('load', relayout);
relayout();
</script>
<div class="toast" id="toast"></div>
<script>
/* 인력구성 이름 인라인 수정 → ?api=staff 저장(소유자만 노출) */
(function(){
  document.querySelectorAll('.staff-name').forEach(function(el){
    function commit(){
      var nv=(el.textContent||'').trim();
      if(nv===(el.dataset.orig||'')) return;
      fetch('?api=staff',{method:'PUT',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({idx:Number(el.dataset.idx),name:nv})})
        .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})
        .then(function(res){ if(res.ok){ el.dataset.orig=nv; el.textContent=nv; if(window.toast)toast('인력구성 저장됨'); } else { el.textContent=el.dataset.orig||''; if(window.toast)toast((res.j&&res.j.error)||'저장 실패'); } })
        .catch(function(){ el.textContent=el.dataset.orig||''; if(window.toast)toast('저장 실패'); });
    }
    el.addEventListener('blur',commit);
    el.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); el.blur(); } });
  });
})();
</script>
</body>
</html>

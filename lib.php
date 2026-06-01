<?php
/* =====================================================================
   메디힘 요구사항 - 공유 백엔드 (lib.php)
   - requirements.php / dashboard.php 가 맨 위에서 require 함
   - ?api=... 요청    : JSON API (PDO 로 MySQL 연동) 처리 후 종료
   실행: php -c php.ini -S localhost:8000  →  http://localhost:8000/requirements/requirements.php
   ===================================================================== */

$DB = [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '3306',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
  'name' => getenv('DB_NAME') ?: 'medihim',
];

if (isset($_GET['api'])) { medihim_api($DB); exit; }   // API 요청이면 JSON 반환 후 종료

function medihim_json($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function medihim_err($msg, $code = 400) { medihim_json(['error' => $msg], $code); }

function medihim_api($DB) {
  // 프런트엔드 키 ↔ DB 컬럼 ↔ 코드 테이블
  $FIELDS = [
    ['key'=>'rank',       'col'=>'rank_id',       'type'=>'fk',   'group'=>'rank'],
    ['key'=>'category',   'col'=>'category_id',   'type'=>'fk',   'group'=>'category', 'required'=>true],
    ['key'=>'major',      'col'=>'major',         'type'=>'text'],
    ['key'=>'middle',     'col'=>'middle',        'type'=>'text'],
    ['key'=>'name',       'col'=>'name',          'type'=>'text', 'required'=>true],
    ['key'=>'purpose',    'col'=>'purpose',       'type'=>'text'],
    ['key'=>'detail',     'col'=>'detail',        'type'=>'text'],
    ['key'=>'link',       'col'=>'link',          'type'=>'text'],
    ['key'=>'doc',        'col'=>'doc',           'type'=>'text'],
    ['key'=>'requester',  'col'=>'requester',     'type'=>'text'],
    ['key'=>'reqPart',    'col'=>'req_part_id',   'type'=>'fk',   'group'=>'part'],
    ['key'=>'reqDate',    'col'=>'req_date',      'type'=>'date'],
    ['key'=>'doPart',     'col'=>'do_part_id',    'type'=>'fk',   'group'=>'part'],
    ['key'=>'doPerson',   'col'=>'do_person',     'type'=>'text'],
    ['key'=>'priority',   'col'=>'priority_id',   'type'=>'fk',   'group'=>'priority'],
    ['key'=>'importance', 'col'=>'importance_id', 'type'=>'fk',   'group'=>'level'],
    ['key'=>'difficulty', 'col'=>'difficulty_id', 'type'=>'fk',   'group'=>'level'],
    ['key'=>'effort',     'col'=>'effort',        'type'=>'num'],
    ['key'=>'version',    'col'=>'version_id',    'type'=>'fk',   'group'=>'version'],
    ['key'=>'status',     'col'=>'status_id',     'type'=>'fk',   'group'=>'status'],
    ['key'=>'reviewer',   'col'=>'reviewer',      'type'=>'text'],
    ['key'=>'approveDate','col'=>'approve_date',  'type'=>'date'],
    ['key'=>'startDate',  'col'=>'start_date',    'type'=>'date'],
    ['key'=>'endDate',    'col'=>'end_date',      'type'=>'date'],
    ['key'=>'prodDate',   'col'=>'prod_date',     'type'=>'date'],
    ['key'=>'remark',     'col'=>'remark',        'type'=>'text'],
  ];
  $GROUP_TABLE = [
    'category'=>'cat_category', 'part'=>'cat_part', 'priority'=>'cat_priority',
    'level'=>'cat_level', 'version'=>'cat_version', 'status'=>'cat_status', 'rank'=>'cat_rank',
  ];

  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    medihim_err('DB 연결 실패: ' . $e->getMessage(), 500);
  }

  $api = $_GET['api'];
  $method = $_SERVER['REQUEST_METHOD'];

  if ($api === 'health') medihim_json(['ok' => true]);

  // 코드(드롭다운) 로드
  $lookups = []; $labelToId = [];
  foreach ($GROUP_TABLE as $g => $t) {
    $rows = $pdo->query("SELECT id, code, label FROM `$t` ORDER BY sort_order, id")->fetchAll();
    $lookups[$g] = $rows;
    $map = [];
    foreach ($rows as $r) $map[$r['label']] = (int)$r['id'];
    $labelToId[$g] = $map;
  }
  if ($api === 'options') medihim_json($lookups);
  /* 참고: 요구사항 대시보드는 requirements.php의 '대시보드' 탭에서 client-side(tally)로 집계하므로
     PHP `?api=dashboard`/v_dashboard_* 뷰는 라이브 PHP 앱에선 미사용(병행 Node server.js만 사용). */

  // 목록 순서 저장: { ids: [id, id, ...] } 순서대로 sort_order = 1..N
  if ($api === 'reorder') {
    if ($method !== 'PUT' && $method !== 'POST') medihim_err('PUT 필요', 405);
    if (!can('requirements', 'update')) medihim_err('수정 권한이 없습니다', 403);
    $body = json_decode(file_get_contents('php://input'), true);
    $ids = (is_array($body) && isset($body['ids']) && is_array($body['ids'])) ? $body['ids'] : null;
    if (!$ids) medihim_err('ids 배열이 필요합니다');
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("UPDATE requirement SET sort_order = ? WHERE id = ?");
      $i = 1;
      foreach ($ids as $rid) { $st->execute([$i++, (int)$rid]); }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      medihim_err('순서 저장 실패: ' . $e->getMessage(), 500);
    }
    medihim_json(['ok' => true, 'count' => count($ids)]);
  }

  if ($api === 'requirements') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($method === 'GET') {
      if (!can('requirements', 'read')) medihim_err('읽기 권한이 없습니다', 403);
      if ($id !== null) {
        $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) medihim_err('없는 요구사항', 404);
        medihim_json($row);
      }
      $where = []; $params = [];
      foreach (['category','priority','status'] as $f) {
        if (!empty($_GET[$f])) { $where[] = "$f = ?"; $params[] = $_GET[$f]; }
      }
      if (!empty($_GET['q'])) {
        $where[] = "(name LIKE ? OR detail LIKE ? OR purpose LIKE ? OR major LIKE ? OR middle LIKE ?)";
        $like = '%' . $_GET['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like);
      }
      // 목록 행마다 댓글 수(미삭제만) 부착 → 프런트 '💬 댓글' 컬럼
      $sql = "SELECT v_requirement.*, (SELECT COUNT(*) FROM req_comment WHERE requirement_id = v_requirement.id AND is_deleted = 0) AS commentCount FROM v_requirement" . ($where ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY sortOrder, id";
      $st = $pdo->prepare($sql); $st->execute($params);
      medihim_json($st->fetchAll());
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    if ($method === 'POST') {
      if (!can('requirements', 'write')) medihim_err('쓰기 권한이 없습니다', 403);
      list($cols, $vals) = medihim_to_row($body, $FIELDS, $labelToId);
      $cols[] = 'sort_order';                                                       // 신규 항목은 목록 맨 아래
      $vals[] = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM requirement")->fetchColumn();
      $me = auth_user();
      $cols[] = 'updated_by'; $vals[] = $me ? $me['name'] : null;   // 최종수정자 = 작성자(생성 시점)
      $cols[] = 'created_by'; $vals[] = $me ? $me['name'] : null;   // 최초작성자(이후 PUT에서 불변) — 행 단위 수정/삭제 권한 판별용
      $sql = "INSERT INTO requirement (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
      $pdo->prepare($sql)->execute($vals);
      $newId = $pdo->lastInsertId();
      $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?"); $st->execute([$newId]);
      medihim_json($st->fetch(), 201);
    }

    if ($method === 'PUT') {
      if (!can('requirements', 'update')) medihim_err('수정 권한이 없습니다', 403);
      if ($id === null) medihim_err('id 필요');
      $me = auth_user();
      if (!auth_is_admin()) {   // 관리자가 아니면 본인이 작성했거나 요청자인 글만 수정 가능
        $own = $pdo->prepare("SELECT created_by, requester FROM requirement WHERE id = ?"); $own->execute([$id]);
        $r = $own->fetch(PDO::FETCH_ASSOC);
        if (!$r) medihim_err('없는 요구사항', 404);
        $mine = $me !== null && (($r['created_by'] !== null && $r['created_by'] === $me['name']) || ($r['requester'] !== null && $r['requester'] === $me['name']));
        if (!$mine) medihim_err('본인이 작성했거나 요청자인 글만 수정 가능합니다', 403);
      }
      // 변경이력: 수정 전 표시값(v_requirement = 라벨/텍스트) 확보 → 수정 후 본문과 필드별 비교
      $oldSt = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?"); $oldSt->execute([$id]);
      $old = $oldSt->fetch();
      if (!$old) medihim_err('없는 요구사항', 404);

      list($cols, $vals) = medihim_to_row($body, $FIELDS, $labelToId);
      $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, $cols)) . ", updated_at = CURRENT_TIMESTAMP, updated_by = ?";   // 최종수정일시·수정자 갱신
      $vals[] = $me ? $me['name'] : null;
      $vals[] = $id;

      $labels = medihim_field_labels();
      $pdo->beginTransaction();
      try {
        $pdo->prepare("UPDATE requirement SET $set WHERE id = ?")->execute($vals);
        // 바뀐 필드마다 한 행씩 변경이력 기록(변경전/후는 표시값, 변경자=현재 로그인)
        $hist = $pdo->prepare("INSERT INTO req_history (requirement_id, field_key, field_label, before_val, after_val, changed_by) VALUES (?,?,?,?,?,?)");
        foreach ($FIELDS as $d) {
          $k = $d['key'];
          $bef = isset($old[$k]) ? (string)$old[$k] : '';
          $aft = isset($body[$k]) ? trim((string)$body[$k]) : '';
          if ($bef === $aft) continue;
          $hist->execute([$id, $k, ($labels[$k] ?? $k), $bef, $aft, ($me ? $me['name'] : null)]);
        }
        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        medihim_err('수정 저장 실패: ' . $e->getMessage(), 500);
      }

      $st = $pdo->prepare("SELECT * FROM v_requirement WHERE id = ?"); $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) medihim_err('없는 요구사항', 404);
      medihim_json($row);
    }

    if ($method === 'DELETE') {
      if (!can('requirements', 'delete')) medihim_err('삭제 권한이 없습니다', 403);
      if ($id === null) medihim_err('id 필요');
      if (!auth_is_admin()) {   // 관리자가 아니면 본인이 작성했거나 요청자인 글만 삭제 가능
        $me = auth_user();
        $own = $pdo->prepare("SELECT created_by, requester FROM requirement WHERE id = ?"); $own->execute([$id]);
        $r = $own->fetch(PDO::FETCH_ASSOC);
        if (!$r) medihim_err('없는 요구사항', 404);
        $mine = $me !== null && (($r['created_by'] !== null && $r['created_by'] === $me['name']) || ($r['requester'] !== null && $r['requester'] === $me['name']));
        if (!$mine) medihim_err('본인이 작성했거나 요청자인 글만 삭제 가능합니다', 403);
      }
      $st = $pdo->prepare("DELETE FROM requirement WHERE id = ?"); $st->execute([$id]);
      if ($st->rowCount() === 0) medihim_err('없는 요구사항', 404);
      medihim_json(['ok' => true]);
    }
  }

  /* ---- 요구사항 댓글/대댓글 ---- */
  if ($api === 'comments') {
    $me = auth_user();
    $SELC = "SELECT id, requirement_id AS reqId, parent_id AS parentId, author_id AS authorId, author_name AS authorName, body, is_deleted AS isDeleted, created_at AS createdAt, updated_at AS updatedAt FROM req_comment";

    if ($method === 'GET') {
      if (!can('requirements', 'read')) medihim_err('읽기 권한이 없습니다', 403);
      $reqId = isset($_GET['reqId']) ? (int)$_GET['reqId'] : 0;
      if ($reqId <= 0) medihim_err('reqId 필요');
      $st = $pdo->prepare("$SELC WHERE requirement_id = ? ORDER BY created_at, id");
      $st->execute([$reqId]);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) { $r['isDeleted'] = (int)$r['isDeleted']; if ($r['isDeleted']) $r['body'] = ''; }   // 삭제 댓글은 본문 비움
      unset($r);
      medihim_json($rows);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    if ($method === 'POST') {
      if (!can('requirements', 'read')) medihim_err('댓글 작성 권한이 없습니다', 403);
      $reqId = isset($body['reqId']) ? (int)$body['reqId'] : 0;
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      $parentId = (isset($body['parentId']) && $body['parentId'] !== '' && $body['parentId'] !== null) ? (int)$body['parentId'] : null;
      if ($reqId <= 0) medihim_err('reqId 필요');
      if ($text === '') medihim_err('댓글 내용을 입력하세요');
      $chk = $pdo->prepare("SELECT COUNT(*) FROM requirement WHERE id = ?"); $chk->execute([$reqId]);
      if (!(int)$chk->fetchColumn()) medihim_err('없는 요구사항', 404);
      if ($parentId !== null) {
        $pc = $pdo->prepare("SELECT requirement_id FROM req_comment WHERE id = ?"); $pc->execute([$parentId]);
        $prc = $pc->fetchColumn();
        if ($prc === false) medihim_err('없는 상위 댓글', 404);
        if ((int)$prc !== $reqId) medihim_err('상위 댓글이 다른 요구사항입니다');
      }
      $ins = $pdo->prepare("INSERT INTO req_comment (requirement_id, parent_id, author_id, author_name, body) VALUES (?,?,?,?,?)");
      $ins->execute([$reqId, $parentId, ($me ? (int)$me['id'] : null), ($me ? $me['name'] : null), $text]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$pdo->lastInsertId()]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      medihim_json($row, 201);
    }

    // PUT/DELETE: 본인 댓글 또는 관리자만
    $cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($cid <= 0) medihim_err('id 필요');
    $own = $pdo->prepare("SELECT author_id, is_deleted FROM req_comment WHERE id = ?"); $own->execute([$cid]);
    $oc = $own->fetch();
    if (!$oc) medihim_err('없는 댓글', 404);
    $isMine = $me && $oc['author_id'] !== null && (int)$oc['author_id'] === (int)$me['id'];
    if (!$isMine) medihim_err('본인이 작성한 댓글만 수정/삭제할 수 있습니다', 403);   // 관리자도 타인 댓글은 불가

    if ($method === 'PUT') {
      if ((int)$oc['is_deleted'] === 1) medihim_err('삭제된 댓글은 수정할 수 없습니다');
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      if ($text === '') medihim_err('댓글 내용을 입력하세요');
      $pdo->prepare("UPDATE req_comment SET body = ? WHERE id = ?")->execute([$text, $cid]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$cid]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      medihim_json($row);
    }
    if ($method === 'DELETE') {
      $pdo->prepare("UPDATE req_comment SET is_deleted = 1 WHERE id = ?")->execute([$cid]);   // 소프트 삭제(답글 트리 보존)
      medihim_json(['ok' => true]);
    }
    medihim_err('comments: 허용되지 않은 메서드', 405);
  }

  /* ---- 요구사항 변경이력 ---- */
  if ($api === 'history') {
    if ($method !== 'GET') medihim_err('GET 필요', 405);
    if (!can('requirements', 'read')) medihim_err('읽기 권한이 없습니다', 403);
    $reqId = isset($_GET['reqId']) ? (int)$_GET['reqId'] : 0;
    if ($reqId <= 0) medihim_err('reqId 필요');
    $st = $pdo->prepare("SELECT id, field_key AS fieldKey, field_label AS fieldLabel, before_val AS beforeVal, after_val AS afterVal, changed_at AS changedAt, changed_by AS changedBy FROM req_history WHERE requirement_id = ? ORDER BY id DESC");
    $st->execute([$reqId]);
    medihim_json($st->fetchAll());
  }

  medihim_err('unknown api: ' . $api, 404);
}

function medihim_to_row($body, $FIELDS, $labelToId) {
  $cols = []; $vals = [];
  foreach ($FIELDS as $d) {
    $v = isset($body[$d['key']]) ? trim((string)$body[$d['key']]) : '';
    $type = $d['type']; $val = null;
    if ($type === 'text') {
      $val = ($v === '') ? null : $v;
      if (!empty($d['required']) && $val === null) medihim_err('필수 항목 누락: ' . $d['key']);
    } elseif ($type === 'date') {
      if ($v === '') $val = null;
      elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $val = $v;
      else medihim_err('날짜 형식 오류(' . $d['key'] . '): YYYY-MM-DD');
    } elseif ($type === 'num') {
      if ($v === '') $val = null;
      elseif (is_numeric($v)) $val = 0 + $v;
      else medihim_err('숫자 형식 오류(' . $d['key'] . ')');
    } elseif ($type === 'fk') {
      if ($v === '') {
        if (!empty($d['required'])) medihim_err('필수 항목 누락: ' . $d['key']);
        $val = null;
      } else {
        $g = $d['group'];
        if (!isset($labelToId[$g][$v])) medihim_err('허용되지 않은 값(' . $d['key'] . '): "' . $v . '"');
        $val = $labelToId[$g][$v];
      }
    }
    $cols[] = $d['col']; $vals[] = $val;
  }
  return [$cols, $vals];
}

/* 변경이력 표시용 필드 라벨 (form.php 라벨과 동일, 기록 시점에 비정규화 저장) */
function medihim_field_labels() {
  return [
    'rank'=>'우선순위(순번)', 'category'=>'구분', 'major'=>'대분류', 'middle'=>'중분류',
    'name'=>'요구사항명', 'purpose'=>'목적/필요성', 'detail'=>'상세 설명', 'link'=>'참고링크',
    'doc'=>'참고링크', 'requester'=>'요청자', 'reqPart'=>'요청파트', 'reqDate'=>'요청일자',
    'doPart'=>'수행파트', 'doPerson'=>'수행담당자', 'priority'=>'우선순위(중요)', 'importance'=>'중요도',
    'difficulty'=>'난이도', 'effort'=>'예상공수(MD)', 'version'=>'목표 버전', 'status'=>'상태',
    'reviewer'=>'검토자', 'approveDate'=>'승인일', 'startDate'=>'시작일자', 'endDate'=>'종료일자(개발반영)',
    'prodDate'=>'운영서버반영일자', 'remark'=>'비고',
  ];
}
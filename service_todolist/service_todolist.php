<?php
/* =====================================================================
   서비스팀 TO-DO LIST (서비스 기획_작업 목록 엑셀 기반) - 독립 PHP 단일 파일
   - 일반 요청     : 작업 목록 페이지 (엑셀 19개 작업이 파일에 내장됨)
   - ?api=... 요청 : JSON API (PDO, MySQL `todo`/`v_todo`)
   동작:
     · MySQL 연결됨  → DB에서 조회/추가/수정/삭제 (실시간 저장)
     · MySQL 미연결  → 아래 $SEED(엑셀 임베드) 데이터를 읽기 전용으로 표시
   실행: 프로젝트 루트에서  php -c php.ini -S localhost:8000
        → http://localhost:8000/service_todolist/service_todolist.php
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

/* 선택 목록(분류/구분/작업구간/우선순위/상태) — 엑셀 기준 표준값 */
$OPT = [
  'class'    => ['운영','구축','협업'],
  'type'     => ['신규 시스템구축','사이트개편','백엔드','운영','리서치','기타'],
  'phase'    => ['기획','디자인','퍼블리싱','개발','QA','회의','기타'],
  'priority' => ['높음','중간','낮음'],
  'status'   => ['진행전','진행중','완료','보류','취소'],
];

/* =====================================================================
   엑셀 임베드 데이터 — '서비스 기획_작업 목록(TO-DO LIST).xlsx' / 작업 목록 시트
   (T001~T019, 2026-05-26 기준). MySQL 미연결 시 이 데이터가 그대로 표시됩니다.
   ===================================================================== */
$SEED = json_decode(<<<'JSON'
[
  {
    "id": 1,
    "code": "T001",
    "class": "",
    "phaseRound": "유지보수",
    "title": "운영, 개 시스템 Sync 작업",
    "type": "기타",
    "workPhase": "개발",
    "worker": "김기문",
    "collaborator": "",
    "participants": "강보성, 박진국, 한영은",
    "priority": "높음",
    "status": "완료",
    "startDate": "2026-05-21",
    "due": "2026-05-21",
    "workDays": 1,
    "progress": 1.0,
    "tags": "#개발 #QA",
    "remark": "",
    "link1": "",
    "link2": ""
  },
  {
    "id": 2,
    "code": "T002",
    "class": "운영",
    "phaseRound": "유지보수_상시",
    "title": "메디힘 요구사항 수집 관리",
    "type": "운영",
    "workPhase": "기타",
    "worker": "박진국",
    "collaborator": "",
    "participants": "박진국, 김기문, 강보성, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-20",
    "due": "상시",
    "workDays": "",
    "progress": "",
    "tags": "#기획 #디자인 #퍼블리싱 #개발",
    "remark": "",
    "link1": "요구사항수집 리스트",
    "link2": ""
  },
  {
    "id": 3,
    "code": "T003",
    "class": "",
    "phaseRound": "유지보수_상시",
    "title": "운영개발 (QA)",
    "type": "운영",
    "workPhase": "기타",
    "worker": "박진국",
    "collaborator": "",
    "participants": "박진국, 김기문, 강보성, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-19",
    "due": "상시",
    "workDays": "",
    "progress": "",
    "tags": "#기획 #디자인 #퍼블리싱 #개발",
    "remark": "",
    "link1": "레드마인",
    "link2": ""
  },
  {
    "id": 4,
    "code": "T004",
    "class": "구축",
    "phaseRound": "UI/UX 2.0 개편",
    "title": "UI/UX 2.0 개편 (홈 컨셉)",
    "type": "사이트개편",
    "workPhase": "회의",
    "worker": "한영은, 박진국",
    "collaborator": "",
    "participants": "박진국, 김기문, 강보성, 한영은",
    "priority": "높음",
    "status": "진행전",
    "startDate": "2026-05-22",
    "due": "",
    "workDays": "",
    "progress": "",
    "tags": "#기획 #디자인 #퍼블리싱 #개발",
    "remark": "",
    "link1": "",
    "link2": ""
  },
  {
    "id": 5,
    "code": "T005",
    "class": "",
    "phaseRound": "유지보수_2차",
    "title": "상담프로세스 개선",
    "type": "사이트개편",
    "workPhase": "디자인",
    "worker": "한영은",
    "collaborator": "",
    "participants": "박진국, 김기문, 강보성",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-18",
    "due": "2026-06-30",
    "workDays": 32,
    "progress": 0.3,
    "tags": "#기획 #디자인 #퍼블리싱 #개발",
    "remark": "- 기획서작성 완료\n- FlowChart작성중/ 기획서 리뷰예정",
    "link1": "",
    "link2": ""
  },
  {
    "id": 6,
    "code": "T006",
    "class": "",
    "phaseRound": "유지보수_2차",
    "title": "정산관리",
    "type": "백엔드",
    "workPhase": "기획",
    "worker": "박진국",
    "collaborator": "",
    "participants": "박진국, 김기문",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-04-20",
    "due": "2026-07-24",
    "workDays": 2.0,
    "progress": 0.3,
    "tags": "#기획 #개발",
    "remark": "기획완료",
    "link1": "",
    "link2": ""
  },
  {
    "id": 7,
    "code": "T007",
    "class": "",
    "phaseRound": "유지보수_1_2차",
    "title": "수수료 기능 2차 고도화",
    "type": "백엔드",
    "workPhase": "개발",
    "worker": "김기문",
    "collaborator": "",
    "participants": "박진국, 김기문",
    "priority": "중간",
    "status": "진행중",
    "startDate": "2026-04-20",
    "due": "",
    "workDays": "",
    "progress": 0.9,
    "tags": "#기획 #개발",
    "remark": "(결제 연동 미적용)\n개발팀 확인중",
    "link1": "",
    "link2": ""
  },
  {
    "id": 8,
    "code": "T008",
    "class": "",
    "phaseRound": "유지보수_2차",
    "title": "결제관리(결제내역)",
    "type": "백엔드",
    "workPhase": "개발",
    "worker": "김기문",
    "collaborator": "",
    "participants": "박진국, 김기문",
    "priority": "중간",
    "status": "진행중",
    "startDate": "2026-04-20",
    "due": "",
    "workDays": "",
    "progress": 1.0,
    "tags": "#기획 #개발",
    "remark": "개발팀 확인중",
    "link1": "",
    "link2": ""
  },
  {
    "id": 9,
    "code": "T009",
    "class": "",
    "phaseRound": "유지보수_2차",
    "title": "시술/수술 예약현황",
    "type": "백엔드",
    "workPhase": "개발",
    "worker": "김기문",
    "collaborator": "",
    "participants": "박진국, 김기문",
    "priority": "중간",
    "status": "진행중",
    "startDate": "2026-04-20",
    "due": "",
    "workDays": "",
    "progress": 0.99,
    "tags": "#기획 #개발",
    "remark": "(관리자 링크 연동 미적용)\n개발팀 확인중",
    "link1": "",
    "link2": ""
  },
  {
    "id": 10,
    "code": "T010",
    "class": "",
    "phaseRound": "유지보수_2차",
    "title": "예약관리",
    "type": "백엔드",
    "workPhase": "개발",
    "worker": "김기문",
    "collaborator": "",
    "participants": "박진국, 김기문",
    "priority": "중간",
    "status": "진행중",
    "startDate": "2026-04-20",
    "due": "",
    "workDays": "",
    "progress": 0.3,
    "tags": "#기획 #개발",
    "remark": "개발팀 확인중",
    "link1": "",
    "link2": ""
  },
  {
    "id": 11,
    "code": "T011",
    "class": "",
    "phaseRound": "1차",
    "title": "글로벌 병원 구축",
    "type": "신규 시스템구축",
    "workPhase": "기획",
    "worker": "이홍근",
    "collaborator": "",
    "participants": "이홍근, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "수시",
    "workDays": "",
    "progress": 0.8,
    "tags": "#기획",
    "remark": "핵심 기능 / 메뉴 구조 / 콘텐츠 정의",
    "link1": "",
    "link2": ""
  },
  {
    "id": 12,
    "code": "T012",
    "class": "",
    "phaseRound": "1차",
    "title": "병원 관리자",
    "type": "신규 시스템구축",
    "workPhase": "기획",
    "worker": "이홍근",
    "collaborator": "",
    "participants": "이홍근, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "2026-05-22",
    "workDays": 2.0,
    "progress": 0.8,
    "tags": "#기획",
    "remark": "1차 프로토 타입",
    "link1": "",
    "link2": ""
  },
  {
    "id": 13,
    "code": "T013",
    "class": "",
    "phaseRound": "1차",
    "title": "사용자 페이지 템플릿 디자인",
    "type": "신규 시스템구축",
    "workPhase": "디자인",
    "worker": "한영은",
    "collaborator": "",
    "participants": "이홍근, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "2026-05-22",
    "workDays": 2.0,
    "progress": 0.5,
    "tags": "#디자인",
    "remark": "시안 디자인",
    "link1": "",
    "link2": ""
  },
  {
    "id": 14,
    "code": "T014",
    "class": "",
    "phaseRound": "1차",
    "title": "플랫폼(메디힘) 관리자",
    "type": "신규 시스템구축",
    "workPhase": "기획",
    "worker": "이홍근",
    "collaborator": "",
    "participants": "이홍근, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "2026-05-27",
    "workDays": 3.0,
    "progress": 0.3,
    "tags": "#기획",
    "remark": "1차 프로토 타입",
    "link1": "",
    "link2": ""
  },
  {
    "id": 15,
    "code": "T015",
    "class": "",
    "phaseRound": "1차",
    "title": "사용자 페이지 템플릿",
    "type": "신규 시스템구축",
    "workPhase": "기획",
    "worker": "이홍근",
    "collaborator": "",
    "participants": "이홍근, 한영은",
    "priority": "높음",
    "status": "진행전",
    "startDate": "",
    "due": "",
    "workDays": "",
    "progress": "",
    "tags": "#기획",
    "remark": "",
    "link1": "",
    "link2": ""
  },
  {
    "id": 16,
    "code": "T016",
    "class": "협업",
    "phaseRound": "유지보수_1_2차",
    "title": "GA 셋팅",
    "type": "백엔드",
    "workPhase": "기획",
    "worker": "박진국",
    "collaborator": "유정은(마케팅), 신유경(운영)",
    "participants": "박진국, 김기문,  APP개발자",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "2026-06-12",
    "workDays": 17,
    "progress": 0.3,
    "tags": "#기획 #개발",
    "remark": "- 기본셋팅완료(개발자 Support 필요)\n- firebase 설정완료(APP)\n- 관리자페이지 기본 설정\n- 기안준비",
    "link1": "",
    "link2": ""
  },
  {
    "id": 17,
    "code": "T017",
    "class": "",
    "phaseRound": "유지보수_1_2차",
    "title": "Amplitude 셋팅",
    "type": "백엔드",
    "workPhase": "기획",
    "worker": "박진국",
    "collaborator": "유정은(마케팅), 신유경(운영)",
    "participants": "박진국, 김기문,  APP개발자",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-21",
    "due": "2026-06-12",
    "workDays": 17,
    "progress": 0.1,
    "tags": "#기획 #개발",
    "remark": "- 기본셋팅완료(개발자 Support 필요)\n- 계정등록\n- 기안준비",
    "link1": "",
    "link2": ""
  },
  {
    "id": 18,
    "code": "T018",
    "class": "",
    "phaseRound": "유지보수_1_2차",
    "title": "채널톡 셋팅",
    "type": "백엔드",
    "workPhase": "기획",
    "worker": "박진국",
    "collaborator": "유정은(마케팅), 신유경(운영)",
    "participants": "박진국, 김기문, 강보성, 한영은",
    "priority": "높음",
    "status": "진행전",
    "startDate": "",
    "due": "",
    "workDays": "",
    "progress": "",
    "tags": "#기획 #디자인 #개발",
    "remark": "- 기안준비\n- 아이콘 디자인 완료",
    "link1": "",
    "link2": ""
  },
  {
    "id": 19,
    "code": "T019",
    "class": "",
    "phaseRound": "유지보수_1_2차",
    "title": "알림(PUSH, SMS, 카카오알림, LINE) 셋팅",
    "type": "기타",
    "workPhase": "기획",
    "worker": "박진국",
    "collaborator": "유정은(마케팅), 신유경(운영), 강리내(번역)",
    "participants": "박진국, 김기문, 강보성, 한영은",
    "priority": "높음",
    "status": "진행중",
    "startDate": "2026-05-22",
    "due": "2026-06-30",
    "workDays": "",
    "progress": "",
    "tags": "#기획 #디자인 #개발",
    "remark": "요구사항수집중(2026.5.22)",
    "link1": "알림 문구리스트",
    "link2": ""
  }
]
JSON, true);

if (isset($_GET['api'])) { todo_api($DB, $SEED, $OPT); exit; }   // API는 todo_api 내부에서 액션별 권한 검사
require_perm('todolist', 'access');                              // 페이지(HTML) 진입은 '접속' 권한 필요
$PERM = perm_map('todolist');
$__ttab = (isset($_GET['tab']) && $_GET['tab'] === 'dash') ? 'dash' : 'list';   // ?tab=dash 진입 시 대시보드 탭 자동 활성 + LNB 활성도 그에 맞춤

function tj($d, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function terr($m, $code = 400) { tj(['error' => $m], $code); }

function todo_api($DB, $SEED, $OPT) {
  $GROUP_TABLE = ['class'=>'td_class','type'=>'td_type','phase'=>'td_phase','priority'=>'td_priority','status'=>'td_status'];
  $FIELDS = [
    ['key'=>'class',        'col'=>'class_id',      'type'=>'fk', 'group'=>'class'],
    ['key'=>'phaseRound',   'col'=>'phase_round',   'type'=>'text'],
    ['key'=>'title',        'col'=>'title',         'type'=>'text', 'required'=>true],
    ['key'=>'type',         'col'=>'type_id',       'type'=>'fk', 'group'=>'type'],
    ['key'=>'workPhase',    'col'=>'work_phase_id', 'type'=>'fk', 'group'=>'phase'],
    ['key'=>'worker',       'col'=>'worker',        'type'=>'text'],
    ['key'=>'collaborator', 'col'=>'collaborator',  'type'=>'text'],
    ['key'=>'participants', 'col'=>'participants',  'type'=>'text'],
    ['key'=>'priority',     'col'=>'priority_id',   'type'=>'fk', 'group'=>'priority'],
    ['key'=>'status',       'col'=>'status_id',     'type'=>'fk', 'group'=>'status'],
    ['key'=>'startDate',    'col'=>'start_date',    'type'=>'date'],
    ['key'=>'due',          'col'=>'due',           'type'=>'text'],
    ['key'=>'workDays',     'col'=>'work_days',     'type'=>'num'],
    ['key'=>'progress',     'col'=>'progress',      'type'=>'num'],
    ['key'=>'tags',         'col'=>'tags',          'type'=>'text'],
    ['key'=>'remark',       'col'=>'remark',        'type'=>'text'],
    ['key'=>'link1',        'col'=>'link1',         'type'=>'text'],
    ['key'=>'link2',        'col'=>'link2',         'type'=>'text'],
  ];
  $api = $_GET['api']; $method = $_SERVER['REQUEST_METHOD'];
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { $pdo = null; }

  if ($api === 'health') tj(['ok' => true, 'db' => $pdo !== null]);

  /* ---- MySQL 미연결: 엑셀 임베드 데이터를 읽기 전용으로 제공 ---- */
  if ($pdo === null) {
    if ($api === 'options') {
      $lk = [];
      foreach ($OPT as $g => $arr) { $lk[$g] = array_map(function($v){ return ['label'=>$v]; }, $arr); }
      tj($lk);
    }
    if ($api === 'todos') {
      if (!can('todolist','read')) terr('읽기 권한이 없습니다', 403);
      if ($method === 'GET') tj(seed_query($SEED, $_GET));
      terr('MySQL 미연결: 엑셀 임베드 읽기 전용 모드입니다 (추가/수정/삭제 불가)', 503);
    }
    if ($api === 'comments' || $api === 'history') {   // 엑셀 임베드(읽기전용) 모드: 댓글/이력은 빈 목록, 쓰기는 불가
      if (!can('todolist','read')) terr('읽기 권한이 없습니다', 403);
      if ($method === 'GET') tj([]);
      terr('MySQL 미연결: 읽기 전용 모드입니다', 503);
    }
    terr('unknown api: ' . $api, 404);
  }

  $lookups = []; $labelToId = [];
  foreach ($GROUP_TABLE as $g => $t) {
    $rows = $pdo->query("SELECT id, code, label FROM `$t` ORDER BY sort_order, id")->fetchAll();
    $lookups[$g] = $rows; $m = []; foreach ($rows as $r) $m[$r['label']] = (int)$r['id']; $labelToId[$g] = $m;
  }
  if ($api === 'options') { try { $lookups['members'] = $pdo->query("SELECT name FROM member ORDER BY name")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $lookups['members'] = []; } tj($lookups); }

  if ($api === 'todos') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($method === 'GET') {
      if (!can('todolist','read')) terr('읽기 권한이 없습니다', 403);
      if ($id !== null) { $st=$pdo->prepare("SELECT * FROM v_todo WHERE id=?"); $st->execute([$id]); $row=$st->fetch(); if(!$row) terr('없는 작업',404); tj($row); }
      $where=[]; $params=[];
      foreach (['status','type','priority'] as $f) if (!empty($_GET[$f])) { $where[]="$f = ?"; $params[]=$_GET[$f]; }
      if (!empty($_GET['q'])) { $where[]="(code LIKE ? OR title LIKE ? OR worker LIKE ? OR participants LIKE ? OR tags LIKE ?)"; $l='%'.$_GET['q'].'%'; array_push($params,$l,$l,$l,$l,$l); }
      // 목록 행마다 댓글 수(미삭제만) 부착 → 프런트 '💬 댓글' 컬럼
      $sql = "SELECT v_todo.*, (SELECT COUNT(*) FROM todo_comment WHERE todo_id = v_todo.id AND is_deleted = 0) AS commentCount FROM v_todo" . ($where ? " WHERE ".implode(' AND ',$where) : "") . " ORDER BY id";
      $st=$pdo->prepare($sql); $st->execute($params); tj($st->fetchAll());
    }
    $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body=[];
    if ($method === 'POST') {
      if (!can('todolist','write')) terr('쓰기 권한이 없습니다', 403);
      list($c,$v)=to_row($body,$FIELDS,$labelToId);
      $u=auth_user(); $c[]='created_by'; $v[]=$u?$u['name']:null; $c[]='updated_by'; $v[]=$u?$u['name']:null;   // 최초작성자=최종수정자=로그인 사용자(생성 시점)
      $pdo->prepare("INSERT INTO todo (".implode(', ',$c).") VALUES (".implode(', ',array_fill(0,count($c),'?')).")")->execute($v);
      $st=$pdo->prepare("SELECT * FROM v_todo WHERE id=?"); $st->execute([$pdo->lastInsertId()]); tj($st->fetch(),201);
    }
    if ($method === 'PUT') {
      if (!can('todolist','update')) terr('수정 권한이 없습니다', 403);
      if ($id===null) terr('id 필요');
      // 변경이력: 수정 전 표시값(v_todo = 라벨/텍스트) 확보 → 수정 후 본문과 필드별 비교
      $oldSt=$pdo->prepare("SELECT * FROM v_todo WHERE id=?"); $oldSt->execute([$id]); $old=$oldSt->fetch();
      if(!$old) terr('없는 작업',404);
      list($c,$v)=to_row($body,$FIELDS,$labelToId);
      $u=auth_user(); $c[]='updated_by'; $v[]=$u?$u['name']:null;   // 최종수정자 = 로그인 사용자
      $set=implode(', ',array_map(function($x){return "$x = ?";},$c)); $v[]=$id;
      $labels=todo_field_labels();
      $pdo->beginTransaction();
      try {
        $pdo->prepare("UPDATE todo SET $set WHERE id=?")->execute($v);
        // 바뀐 필드마다 한 행씩 변경이력 기록(변경전/후=표시값, 변경자=현재 로그인)
        $hist=$pdo->prepare("INSERT INTO todo_history (todo_id, field_key, field_label, before_val, after_val, changed_by) VALUES (?,?,?,?,?,?)");
        foreach ($FIELDS as $d) {
          $k=$d['key'];
          $bef=isset($old[$k])?(string)$old[$k]:'';
          $aft=isset($body[$k])?trim((string)$body[$k]):'';
          if($bef===$aft) continue;
          if($d['type']==='num' && is_numeric($bef) && is_numeric($aft) && (float)$bef === (float)$aft) continue;   // 0.30↔0.3 같은 표기차 무시
          $hist->execute([$id,$k,($labels[$k]??$k),$bef,$aft,($u?$u['name']:null)]);
        }
        $pdo->commit();
      } catch (Throwable $e) { $pdo->rollBack(); terr('수정 저장 실패: '.$e->getMessage(),500); }
      $st=$pdo->prepare("SELECT * FROM v_todo WHERE id=?"); $st->execute([$id]); $row=$st->fetch(); if(!$row) terr('없는 작업',404); tj($row);
    }
    if ($method === 'DELETE') {
      if (!can('todolist','delete')) terr('삭제 권한이 없습니다', 403);
      if ($id===null) terr('id 필요');
      $st=$pdo->prepare("DELETE FROM todo WHERE id=?"); $st->execute([$id]);
      if($st->rowCount()===0) terr('없는 작업',404); tj(['ok'=>true]);
    }
  }

  /* ---- 작업 댓글/대댓글 (requirements의 ?api=comments와 동일 구조, FK=todo) ---- */
  if ($api === 'comments') {
    if (!can('todolist','read')) terr('댓글 권한이 없습니다', 403);
    $me = auth_user();
    $SELC = "SELECT id, todo_id AS todoId, parent_id AS parentId, author_id AS authorId, author_name AS authorName, body, is_deleted AS isDeleted, created_at AS createdAt, updated_at AS updatedAt FROM todo_comment";

    if ($method === 'GET') {
      $tid = isset($_GET['todoId']) ? (int)$_GET['todoId'] : 0;
      if ($tid <= 0) terr('todoId 필요');
      $st = $pdo->prepare("$SELC WHERE todo_id = ? ORDER BY created_at, id"); $st->execute([$tid]);
      $rows = $st->fetchAll();
      foreach ($rows as &$r) { $r['isDeleted'] = (int)$r['isDeleted']; if ($r['isDeleted']) $r['body'] = ''; }   // 삭제 댓글은 본문 비움
      unset($r);
      tj($rows);
    }

    $body = json_decode(file_get_contents('php://input'), true); if (!is_array($body)) $body = [];

    if ($method === 'POST') {
      $tid = isset($body['todoId']) ? (int)$body['todoId'] : 0;
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      $parentId = (isset($body['parentId']) && $body['parentId'] !== '' && $body['parentId'] !== null) ? (int)$body['parentId'] : null;
      if ($tid <= 0) terr('todoId 필요');
      if ($text === '') terr('댓글 내용을 입력하세요');
      $chk = $pdo->prepare("SELECT COUNT(*) FROM todo WHERE id = ?"); $chk->execute([$tid]);
      if (!(int)$chk->fetchColumn()) terr('없는 작업', 404);
      if ($parentId !== null) {
        $pc = $pdo->prepare("SELECT todo_id FROM todo_comment WHERE id = ?"); $pc->execute([$parentId]);
        $prc = $pc->fetchColumn();
        if ($prc === false) terr('없는 상위 댓글', 404);
        if ((int)$prc !== $tid) terr('상위 댓글이 다른 작업입니다');
      }
      $ins = $pdo->prepare("INSERT INTO todo_comment (todo_id, parent_id, author_id, author_name, body) VALUES (?,?,?,?,?)");
      $ins->execute([$tid, $parentId, ($me ? (int)$me['id'] : null), ($me ? $me['name'] : null), $text]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$pdo->lastInsertId()]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      tj($row, 201);
    }

    // PUT/DELETE: 작성 본인만 (관리자도 타인 댓글 불가)
    $cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($cid <= 0) terr('id 필요');
    $own = $pdo->prepare("SELECT author_id, is_deleted FROM todo_comment WHERE id = ?"); $own->execute([$cid]);
    $oc = $own->fetch();
    if (!$oc) terr('없는 댓글', 404);
    $isMine = $me && $oc['author_id'] !== null && (int)$oc['author_id'] === (int)$me['id'];
    if (!$isMine) terr('본인이 작성한 댓글만 수정/삭제할 수 있습니다', 403);

    if ($method === 'PUT') {
      if ((int)$oc['is_deleted'] === 1) terr('삭제된 댓글은 수정할 수 없습니다');
      $text = isset($body['body']) ? trim((string)$body['body']) : '';
      if ($text === '') terr('댓글 내용을 입력하세요');
      $pdo->prepare("UPDATE todo_comment SET body = ? WHERE id = ?")->execute([$text, $cid]);
      $g = $pdo->prepare("$SELC WHERE id = ?"); $g->execute([$cid]);
      $row = $g->fetch(); $row['isDeleted'] = (int)$row['isDeleted'];
      tj($row);
    }
    if ($method === 'DELETE') {
      $pdo->prepare("UPDATE todo_comment SET is_deleted = 1 WHERE id = ?")->execute([$cid]);   // 소프트 삭제(답글 트리 보존)
      tj(['ok' => true]);
    }
    terr('comments: 허용되지 않은 메서드', 405);
  }

  /* ---- 작업 변경이력 ---- */
  if ($api === 'history') {
    if ($method !== 'GET') terr('GET 필요', 405);
    if (!can('todolist','read')) terr('읽기 권한이 없습니다', 403);
    $tid = isset($_GET['todoId']) ? (int)$_GET['todoId'] : 0;
    if ($tid <= 0) terr('todoId 필요');
    $st = $pdo->prepare("SELECT id, field_key AS fieldKey, field_label AS fieldLabel, before_val AS beforeVal, after_val AS afterVal, changed_at AS changedAt, changed_by AS changedBy FROM todo_history WHERE todo_id = ? ORDER BY id DESC");
    $st->execute([$tid]);
    tj($st->fetchAll());
  }

  terr('unknown api: ' . $api, 404);
}

/* 변경이력 표시용 필드 라벨 (form.php 라벨과 동일, 기록 시점에 비정규화 저장) */
function todo_field_labels() {
  return [
    'class'=>'분류', 'phaseRound'=>'차수', 'title'=>'프로젝트명', 'type'=>'구분',
    'workPhase'=>'현재 작업구간', 'worker'=>'현재 작업자', 'collaborator'=>'협업자/파트/외부업체',
    'participants'=>'프로젝트 참여자', 'priority'=>'우선순위', 'status'=>'상태',
    'startDate'=>'시작일', 'due'=>'마감일', 'workDays'=>'작업일수(d)', 'progress'=>'총 진행률',
    'tags'=>'태그', 'remark'=>'비고', 'link1'=>'참고링크 1', 'link2'=>'참고링크 2',
  ];
}

function to_row($body, $FIELDS, $labelToId) {
  $cols=[]; $vals=[];
  foreach ($FIELDS as $d) {
    $v = isset($body[$d['key']]) ? trim((string)$body[$d['key']]) : '';
    $type=$d['type']; $val=null;
    if ($type==='text') { $val = ($v==='')?null:$v; if(!empty($d['required'])&&$val===null) terr('필수 항목 누락: '.$d['key']); }
    elseif ($type==='date') { if($v==='') $val=null; elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) $val=$v; else terr('날짜 형식 오류('.$d['key'].'): YYYY-MM-DD'); }
    elseif ($type==='num')  { if($v==='') $val=null; elseif(is_numeric($v)) $val=0+$v; else terr('숫자 형식 오류('.$d['key'].')'); }
    elseif ($type==='fk')   { if($v===''){ if(!empty($d['required'])) terr('필수 항목 누락: '.$d['key']); $val=null; } else { $g=$d['group']; if(!isset($labelToId[$g][$v])) terr('허용되지 않은 값('.$d['key'].'): "'.$v.'"'); $val=$labelToId[$g][$v]; } }
    $cols[]=$d['col']; $vals[]=$val;
  }
  return [$cols, $vals];
}

/* 엑셀 임베드($SEED) 조회: id 단건 또는 status/type/priority/q 필터 */
function seed_query($SEED, $g) {
  if (isset($g['id'])) {
    foreach ($SEED as $r) if ((string)$r['id'] === (string)$g['id']) { $r['commentCount'] = 0; tj($r); }   // 엑셀 임베드(읽기전용)는 댓글 0
    terr('없는 작업', 404);
  }
  $out = [];
  foreach ($SEED as $r) {
    foreach (['status','type','priority'] as $f) if (!empty($g[$f]) && ($r[$f] ?? '') !== $g[$f]) continue 2;
    if (!empty($g['q'])) {
      $q = mb_strtolower($g['q']);
      $hay = mb_strtolower(($r['code'] ?? '').' '.($r['title'] ?? '').' '.($r['worker'] ?? '').' '.($r['participants'] ?? '').' '.($r['tags'] ?? ''));
      if (mb_strpos($hay, $q) === false) continue;
    }
    $r['commentCount'] = 0;   // 엑셀 임베드(읽기전용)는 댓글 0
    $out[] = $r;
  }
  return $out;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>서비스 기획 · 작업 목록(TO-DO LIST)</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  body{background:var(--bg);}
  .wrap{max-width:none;margin:0;}  /* requirements.php와 동일하게 .main 풀폭 사용(폭 제약 해제) */
  .brandbar{display:flex;align-items:center;gap:11px;margin-bottom:16px;}
  .brandbar .brand-logo{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#2f6bff,#5b8df9);color:#fff;font-weight:800;font-size:16px;box-shadow:0 4px 10px rgba(47,107,255,.35);}
  .brandbar .bt{font-size:15px;font-weight:800;letter-spacing:-.3px;} .brandbar .bs{font-size:11px;color:var(--sub);}
  .tag.pr-높음{background:#fee2e2;color:#b91c1c;} .tag.pr-중간{background:#fef3c7;color:#b45309;} .tag.pr-낮음{background:#dcfce7;color:#166534;}
  .tag.st-진행전{background:#eef1f5;color:#5b6472;} .tag.st-진행중{background:#cffafe;color:#155e75;}
  .tag.st-완료{background:#d1fae5;color:#065f46;} .tag.st-보류{background:#fef9c3;color:#854d0e;} .tag.st-취소{background:#fee2e2;color:#991b1b;}
  .tag.type{background:#eef2ff;color:#4338ca;}
  .pbar{height:7px;background:#eef1f5;border-radius:5px;overflow:hidden;width:90px;display:inline-block;vertical-align:middle;}
  .pbar>div{height:100%;background:linear-gradient(90deg,#2f6bff,#6f9bff);}
  td.title{font-weight:700;min-width:180px;}
  .subtle{color:#9aa3b2;font-weight:400;font-size:11.5px;}
  /* form.php 전체 필드 노출 + 한 줄 표시: 줄바꿈 없이 1행, 길면 …말줄임(hover로 전체보기). 가로 스크롤은 .table-scroll */
  #tbl td, #tbl th{white-space:nowrap;}
  #tbl td{overflow:hidden;text-overflow:ellipsis;}
  td.title{max-width:240px;}
  td.col-wrap{max-width:180px;}
  td.col-tags{max-width:150px;color:#6b7280;font-size:12px;}
  td.col-remark{max-width:240px;color:#5b6472;font-size:12px;}
  td.col-link{max-width:150px;font-size:12px;}
  /* 참고링크 '확인' 버튼 — schedule.php 첨부와 동일 UI */
  .attach-y.attach-open{cursor:pointer;text-decoration:none;border:1px solid #bfe7cf;background:#dff5ea;color:#127c3c;border-radius:12px;padding:1px 9px;font-size:11px;font-weight:800;}
  .attach-y.attach-open:hover{background:#127c3c;color:#fff;}
  .tag.cls{background:#e0e7ff;color:#3730a3;}
  /* 댓글 수 칩 (목록 '💬 댓글' 컬럼) */
  .cmt-cnt{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;background:var(--brand-soft);color:var(--brand-d);font-weight:700;font-size:12px;white-space:nowrap;}
  /* 좌측 컬럼 틀고정(ID·분류·차수·프로젝트명) — left 값은 JS(applyFreeze)가 헤더 너비로 설정 */
  #tbl .frz{position:sticky;z-index:2;background:#fff;}
  #tbl thead .frz{z-index:5;background:#f3f1fb;color:var(--brand-d);}   /* 고정 헤더도 다른 헤더와 동일(보라) */
  #tbl tbody tr:hover .frz{background:#faf9fd;}
  #tbl .frz-edge{box-shadow:6px 0 8px -6px rgba(0,0,0,.18);}
  /* 본문 텍스트 center(프로젝트명·비고 제외) */
  #tbl tbody td{text-align:center;}
  #tbl tbody td.title,#tbl tbody td.col-remark{text-align:left;}
  @media(max-width:560px){ #tbl td{white-space:normal;max-width:none;overflow:visible;} #tbl .frz{position:static;box-shadow:none;left:auto!important;} }   /* 모바일 카드 레이아웃은 원래대로(고정 해제) */
  /* 작업 추가 버튼 (Excel 오른쪽) */
  #addBtn{background:#FB64C9;color:#fff;text-decoration:none;box-shadow:0 4px 12px rgba(251,100,201,.30);}
  #addBtn:hover{background:#e84fb6;}
  /* 검색 카드 (리스트와 분리된 별도 카드) — requirements.php 참고 */
  /* 검색영역 collapse 토글 (헤더 + 우측 명시 버튼) */
  .sp-card .card-body{padding:24px 28px;}
  .sp-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line);}
  .sp-head .sp-title{margin:0;font-size:15px;font-weight:700;color:var(--ink);}
  .sp-toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);font-family:inherit;transition:.15s;}
  .sp-toggle-btn:hover{background:var(--brand-soft);color:var(--brand-d);border-color:var(--brand);}
  .sp-toggle-btn .sp-chev{font-size:11px;color:var(--brand);transition:transform .15s;}
  .search-panel{padding-top:18px;}
  .sp-card.collapsed .sp-head{padding-bottom:0;border-bottom:none;}
  .sp-card.collapsed .sp-toggle-btn .sp-chev{transform:rotate(-90deg);}
  .sp-card.collapsed .search-panel{display:none;}
  .sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px 12px;margin-bottom:14px;}
  .sp-grid label{display:block;font-size:11.5px;font-weight:600;color:var(--sub);margin-bottom:4px;}
  .sp-text{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-text .sp-text-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-text #fTextField{max-width:150px;}
  .sp-text #q{flex:1;min-width:220px;max-width:440px;}
  .sp-date{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .sp-date .sp-date-lbl{font-size:12.5px;font-weight:700;color:#6c7293;margin-right:2px;}
  .sp-date .form-select{max-width:190px;} .sp-date .form-control{max-width:165px;}
  .sp-date .sp-tilde{color:var(--sub);font-weight:700;}
  .sp-actions{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:14px;}
  .sp-act-center{display:flex;align-items:center;gap:8px;}
  /* 목록 카드 헤더(제목 + 액션 분리) */
  .list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:16px;}
  .list-head .card-title{margin:0;}
  .list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  /* 페이지네이션 */
  .pager{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:18px;flex-wrap:wrap;}
  .pg-btn{min-width:34px;height:34px;padding:0 10px;border:1px solid var(--line);background:#fff;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#6c7293;font-family:inherit;}
  .pg-btn:hover:not(:disabled){background:#f7f6fb;color:var(--brand-d);border-color:#e6def5;}
  .pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand);}
  .pg-btn:disabled{opacity:.45;cursor:default;}
  .pg-ell{color:var(--sub);padding:0 2px;}
  /* tabs */
  /* 상단 탭 — account_management.php와 동일한 알약형 UI */
  .tabs{display:flex;gap:6px;flex-wrap:wrap;margin:4px 0 18px;}
  .tabs button{border:1px solid var(--line);background:#fff;border-radius:8px;padding:7px 13px;font-size:13px;font-weight:700;color:var(--sub);cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;transition:.12s;}
  .tabs button:hover{border-color:var(--brand);color:var(--brand-d);}
  .tabs button.active{background:var(--brand-soft);border-color:var(--brand);color:var(--brand-d);}
  /* dashboard kpi */
  .kpi{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;}
  .kc{border:1px solid var(--line);border-radius:12px;padding:14px 16px;background:#fcfcfd;}
  .kc .kl{font-size:12px;color:var(--sub);font-weight:600;}
  .kc .kn{font-size:24px;font-weight:800;margin-top:4px;letter-spacing:-.5px;}
  .kc.hi{background:#eff4ff;border-color:#dbe6ff;} .kc.hi .kn{color:var(--brand);}
  .ro-banner{background:#eef2ff;border:1px solid #dbe6ff;color:#4338ca;border-radius:10px;
    padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;}
  @media(max-width:900px){ .kpi{grid-template-columns:repeat(3,1fr);} }
  @media(max-width:560px){ .kpi{grid-template-columns:repeat(2,1fr);} }
</style>
</head>
<body>
<!-- ===== mobile top bar ===== -->
<header class="mobile-top">
  <button class="hamb" id="hambBtn" aria-label="메뉴 열기">☰</button>
  <span class="mt-title">✅ 서비스기획 TO-DO</span>
</header>
<div class="lnb-overlay" id="lnbOverlay"></div>

<div class="app">
  <!-- ================= LNB ================= -->
  <aside class="lnb">
    <div class="lnb-brand">
      <button class="lnb-x" id="lnbClose" aria-label="메뉴 닫기">×</button>
      <span class="brand-logo">M</span>
      <div class="brand-text"><div class="logo">메디힘</div><div class="sub">Requirements</div></div>
    </div>
    <?php auth_lnb($__ttab === 'dash' ? 'todolist_dash' : 'todolist'); ?>
  </aside>

  <!-- ================= MAIN ================= -->
  <main class="main">
  <div class="wrap">

  <div class="topbar">
    <div>
      <h1 id="pageTitle" data-lnb-title="<?= $__ttab === 'dash' ? 'todolist_dash' : 'todolist' ?>"><?= $__ttab === 'dash' ? '대시보드' : '작업 목록' ?></h1>
      <div class="pg-sub">서비스 기획 작업 관리</div>
    </div>
    <span class="spacer"></span>
    <?php auth_bell(); ?>
  </div>

  <div class="tabs">
    <button data-v="list" class="active"><svg class="tico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg> 작업 목록</button>
    <button data-v="dash"><svg class="tico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg> 대시보드</button>
  </div>

  <section id="view-list">
  <div class="ro-banner hidden" id="roBanner">📄 <b>엑셀 임베드(읽기전용) 모드</b> — 파일에 내장된 엑셀 작업 목록을 표시 중입니다. MySQL 연결 시 추가·수정·삭제가 활성화됩니다.</div>

  <!-- 검색 카드 (리스트와 분리, 헤더 클릭으로 collapse) -->
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
          <div><label>분류</label><select id="fClass" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>차수</label><select id="fPhaseRound" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>구분</label><select id="fType" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>작업구간</label><select id="fWorkPhase" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>작업자</label><select id="fWorker" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>협업자/파트/외부업체</label><select id="fCollab" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>프로젝트 참여자</label><select id="fParticipants" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>우선순위</label><select id="fPriority" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>상태</label><select id="fStatus" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>작업일수(d)</label><select id="fWorkDays" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>진행률</label><select id="fProgress" class="form-select form-select-sm"><option value="">전체</option></select></div>
          <div><label>💬 댓글 정렬</label><select id="fCmtSort" class="form-select form-select-sm"><option value="">기본</option><option value="desc">댓글 많은 순</option><option value="asc">댓글 적은 순</option></select></div>
        </div>
        <div class="sp-date">
          <label class="sp-date-lbl">기간검색</label>
          <select id="fDateField" class="form-select form-select-sm">
            <option value="">기준 일자 선택</option>
            <option value="startDate">시작일</option>
            <option value="due">마감일</option>
            <option value="createdAt">최초작성일시</option>
            <option value="updatedAt">최종수정일시</option>
          </select>
          <input type="date" id="fDateFrom" class="form-control form-control-sm">
          <span class="sp-tilde">~</span>
          <input type="date" id="fDateTo" class="form-control form-control-sm">
        </div>
        <div class="sp-text">
          <label class="sp-text-lbl">통합검색</label>
          <select id="fTextField" class="form-select form-select-sm">
            <option value="">전체</option>
            <option value="title">프로젝트명</option>
            <option value="tags">태그</option>
            <option value="remark">비고</option>
            <option value="link1">참고링크1</option>
            <option value="link2">참고링크2</option>
            <option value="createdBy">최초작성자</option>
            <option value="updatedBy">최종수정자</option>
          </select>
          <input id="q" class="form-control form-control-sm" placeholder="검색어를 입력하세요">
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

  <!-- 목록 카드 -->
  <div class="card">
    <div class="card-body">
      <div class="list-head">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <h2 class="card-title">작업 목록</h2>
          <span class="count-chip" id="cnt">검색 수 0 / 총 수 0</span>
        </div>
        <div class="list-actions">
          <select id="pageSize" class="form-select form-select-sm" style="width:auto" title="페이지당 표시 개수">
            <option value="10">10개씩</option>
            <option value="20">20개씩</option>
            <option value="30">30개씩</option>
            <option value="50">50개씩</option>
            <option value="100">100개씩</option>
            <option value="200">200개씩</option>
          </select>
          <button class="btn primary sm" id="exportXlsx">⬇ Excel (.xlsx)</button>
          <a class="btn sm" id="addBtn" href="form.php">＋ 작업 추가</a>
        </div>
      </div>
      <div class="table-scroll">
        <table id="tbl" class="sch-look">
          <thead><tr>
            <th>번호</th><th>분류</th><th>차수</th><th>프로젝트명</th><th>구분</th><th>작업구간</th><th>작업자</th><th>협업자/파트/외부업체</th><th>프로젝트 참여자</th><th>우선순위</th><th>상태</th><th>시작일</th><th>마감일</th><th>작업일수(d)</th><th>진행률</th><th>태그</th><th>참고링크1</th><th>참고링크2</th><th>비고</th><th>최초작성일시</th><th>최초작성자</th><th>최종수정일시</th><th>최종수정자</th><th>💬 댓글</th><th>관리</th>
          </tr></thead>
          <tbody id="body"></tbody>
        </table>
      </div>
      <div class="empty hidden" id="empty">등록된 작업이 없습니다. 우측 상단 <b>＋ 작업 추가</b>로 등록하세요.</div>
      <div class="pager" id="pager"></div>
    </div>
  </div>
  </section>

  <!-- ===== 대시보드 (엑셀 '대시보드' 시트 기반) ===== -->
  <section id="view-dash" class="hidden">
    <div class="panel">
      <h2>핵심 지표</h2>
      <div class="kpi">
        <div class="kc"><div class="kl">전체 작업</div><div class="kn" id="kTotal">0</div></div>
        <div class="kc"><div class="kl">진행전</div><div class="kn" id="kTodo">0</div></div>
        <div class="kc"><div class="kl">진행중</div><div class="kn" id="kDoing">0</div></div>
        <div class="kc"><div class="kl">완료</div><div class="kn" id="kDone">0</div></div>
        <div class="kc"><div class="kl">보류</div><div class="kn" id="kHold">0</div></div>
        <div class="kc"><div class="kl">취소</div><div class="kn" id="kCancel">0</div></div>
        <div class="kc hi"><div class="kl">완료율</div><div class="kn" id="kRate">0%</div></div>
        <div class="kc hi"><div class="kl">지연 작업</div><div class="kn" id="kDelay">0</div></div>
        <div class="kc hi"><div class="kl">실제 소요(d)</div><div class="kn" id="kDays">0</div></div>
      </div>
    </div>
    <div class="dash-grid">
      <div class="panel"><h2>상태별 통계</h2><div id="dStatus"></div></div>
      <div class="panel"><h2>우선순위별 통계</h2><div id="dPriority"></div></div>
    </div>
    <div class="panel">
      <h2>구분별 진행 현황</h2>
      <div class="table-scroll">
        <table id="dType">
          <thead><tr><th>구분</th><th>전체</th><th>진행전</th><th>진행중</th><th>완료</th><th>보류</th><th>완료율</th><th>작업일수(d)</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </section>
  </div><!-- /.wrap -->
  </main>
</div><!-- /.app -->

<div class="toast" id="toast"></div>

<script>
const API='?api=';
const PERM = <?php echo json_encode($PERM, JSON_UNESCAPED_UNICODE); ?>;

/* ---- LNB drawer (mobile) ---- */
const lnbEl=document.querySelector('aside.lnb'), lnbOverlay=document.getElementById('lnbOverlay');
function openLnb(){ lnbEl.classList.add('open'); lnbOverlay.classList.add('show'); }
function closeLnb(){ lnbEl.classList.remove('open'); lnbOverlay.classList.remove('show'); }
document.getElementById('hambBtn').onclick=openLnb;
document.getElementById('lnbClose').onclick=closeLnb;
lnbOverlay.onclick=closeLnb;
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLnb(); });
/* 엑셀 임베드 데이터(파일 내장) — MySQL 미연결 시 읽기 전용으로 사용 */
const SEED_ROWS = <?php echo json_encode($SEED, JSON_UNESCAPED_UNICODE); ?>;
let EMBEDDED=false;
const FIELDS=['code','class','phaseRound','title','type','workPhase','worker','collaborator','participants','priority','status','startDate','due','workDays','progress','tags','remark','link1','link2'];
const OPT={ class:['운영','구축','협업'], type:['신규 시스템구축','사이트개편','백엔드','운영','리서치','기타'],
  phase:['기획','디자인','퍼블리싱','개발','QA','회의','기타'], priority:['높음','중간','낮음'], status:['진행전','진행중','완료','보류','취소'] };
let rows=[];
let pageSize=10, curPage=1;   // 페이지네이션 (기본 10개씩)

function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function fill(el,arr,blank){ el.innerHTML=''; if(blank) el.appendChild(new Option('— 선택 —','')); arr.forEach(v=>el.appendChild(new Option(v,v))); }

async function loadOptions(){
  try{ const o=await (await fetch(API+'options',{cache:'no-store'})).json();
    ['class','type','phase','priority','status'].forEach(g=>{ if(o[g]) OPT[g]=o[g].map(x=>x.label); });
  }catch(e){}
}
function fillSel(el,arr){ if(!el) return; arr.forEach(v=>el.appendChild(new Option(v,v))); }
function initSelects(){   // 코드값 셀렉트(분류/구분/작업구간/우선순위/상태) — 표준 목록
  fillSel(fClass,OPT.class); fillSel(fType,OPT.type); fillSel(fWorkPhase,OPT.phase);
  fillSel(fPriority,OPT.priority); fillSel(fStatus,OPT.status);
}
function fillDynamicFilters(){   // 자유값/수치 셀렉트(차수/작업자/협업자/참여자/작업일수/진행률) — 데이터의 distinct 값
  const distinct=g=>[...new Set(rows.map(g).map(v=>(v==null?'':String(v)).trim()).filter(Boolean))];
  fillSel(fPhaseRound,  distinct(r=>r.phaseRound).sort((a,b)=>a.localeCompare(b,'ko')));
  fillSel(fWorker,      distinct(r=>r.worker).sort((a,b)=>a.localeCompare(b,'ko')));
  fillSel(fCollab,      distinct(r=>r.collaborator).sort((a,b)=>a.localeCompare(b,'ko')));
  fillSel(fParticipants,distinct(r=>r.participants).sort((a,b)=>a.localeCompare(b,'ko')));
  fillSel(fWorkDays,    distinct(r=>r.workDays).sort((a,b)=>parseFloat(a)-parseFloat(b)));
  fillSel(fProgress,    distinct(r=>progPct(r)).sort((a,b)=>parseFloat(a)-parseFloat(b)));
}
function setDb(mode){ const el=document.getElementById('dbStatus'); if(!el) return;   // DB연결됨 라벨 제거됨 — 요소 없으면 no-op
  if(mode==='db'){ el.textContent='🟢 DB 연결됨'; el.style.background='#dcfce7'; el.style.color='#166534'; el.title=''; }
  else { el.textContent='📄 엑셀 임베드 (읽기전용)'; el.style.background='#eef2ff'; el.style.color='#4338ca'; el.title='MySQL 미연결 — 파일에 내장된 엑셀 데이터를 표시합니다. php -S 실행 + MySQL 구동 시 편집 가능'; } }
function applyReadOnly(){ const a=document.getElementById('addBtn'); if(a) a.style.display='none'; const b=document.getElementById('roBanner'); if(b) b.classList.remove('hidden'); }
async function load(){ const r=await fetch(API+'todos',{cache:'no-store'}); if(!r.ok) throw new Error('조회 실패 ('+r.status+')'); rows=await r.json(); }

/* 적용된 검색조건(검색 버튼/Enter로만 반영). 초기값=전부 빈 값(전체 노출) */
const SF_INIT={ cls:'',phaseRound:'',type:'',workPhase:'',worker:'',collab:'',participants:'',priority:'',status:'',workDays:'',progress:'',textField:'',q:'',dateField:'',dateFrom:'',dateTo:'',cmtSort:'' };   // cmtSort: ''(기본)/'desc'(많은순)/'asc'(적은순)
let SF={...SF_INIT};
const TEXT_FIELDS=['title','tags','remark','link1','link2','createdBy','updatedBy'];   // 통합검색 '전체' 대상
function progPct(r){ return (r.progress===''||r.progress==null||isNaN(parseFloat(r.progress)))?'':Math.round(parseFloat(r.progress)*100)+'%'; }
function filtered(){
  return rows.filter(r=>{
    if(SF.cls && (r.class||'')!==SF.cls) return false;
    if(SF.phaseRound && (r.phaseRound||'')!==SF.phaseRound) return false;
    if(SF.type && (r.type||'')!==SF.type) return false;
    if(SF.workPhase && (r.workPhase||'')!==SF.workPhase) return false;
    if(SF.worker && (r.worker||'')!==SF.worker) return false;
    if(SF.collab && (r.collaborator||'')!==SF.collab) return false;
    if(SF.participants && (r.participants||'')!==SF.participants) return false;
    if(SF.priority && (r.priority||'')!==SF.priority) return false;
    if(SF.status && (r.status||'')!==SF.status) return false;
    if(SF.workDays && String(r.workDays==null?'':r.workDays)!==SF.workDays) return false;
    if(SF.progress && progPct(r)!==SF.progress) return false;
    if(SF.q){
      const ql=SF.q.toLowerCase();
      const keys=SF.textField?[SF.textField]:TEXT_FIELDS;
      const hay=keys.map(k=>r[k]||'').join(' ').toLowerCase();
      if(!hay.includes(ql)) return false;
    }
    if(SF.dateField && (SF.dateFrom||SF.dateTo)){
      const v=String(r[SF.dateField]||'').slice(0,10);                 // 날짜 부분만(일시는 앞 10자리)
      if(!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;                 // 날짜가 아닌 값(상시/수시/빈값)은 기간검색서 제외
      if(SF.dateFrom && v<SF.dateFrom) return false;
      if(SF.dateTo && v>SF.dateTo) return false;
    }
    return true;
  }).slice().sort((a,b)=>{   // 댓글 수 정렬: SF.cmtSort 'desc'면 많은순, 'asc'면 적은순, 빈값이면 원순서(id) 유지
    if(SF.cmtSort==='desc') return (parseInt(b.commentCount,10)||0)-(parseInt(a.commentCount,10)||0) || (a.id-b.id);
    if(SF.cmtSort==='asc')  return (parseInt(a.commentCount,10)||0)-(parseInt(b.commentCount,10)||0) || (a.id-b.id);
    return 0;
  });
}
function rowActions(r){
  if(EMBEDDED) return '<span class="subtle">읽기전용</span>';
  const acts=[];
  if(PERM.update) acts.push(`<a class="btn ghost sm" href="form.php?id=${r.id}">수정</a>`);
  if(PERM.delete) acts.push(`<button class="btn danger sm" onclick="delItem(${r.id})">삭제</button>`);
  return acts.length?`<div class="row-actions">${acts.join('')}</div>`:'<span class="subtle">-</span>';
}
function render(){
  const list=filtered(); const total=list.length; cnt.textContent = `검색 수 ${total} / 총 수 ${rows.length}`;   // 우측 카운트칩: 필터 결과 / 전체 적재(rows)
  const b=document.getElementById('body');
  if(total===0){ b.innerHTML=''; empty.classList.remove('hidden'); renderPager(0,1); return; }
  empty.classList.add('hidden');
  const pages=Math.max(1,Math.ceil(total/pageSize));
  if(curPage>pages) curPage=pages; if(curPage<1) curPage=1;
  const start=(curPage-1)*pageSize, pageRows=list.slice(start,start+pageSize);
  const dash='<span class="subtle">-</span>';
  const tt=v=>{ v=(v==null?'':String(v)).trim(); return v?` title="${esc(v)}"`:''; };   // 말줄임된 셀 hover 시 전체 내용 표시
  /* 참고링크 — schedule.php 기획서 첨부와 동일: URL이면 '확인' 버튼(새 창), 그 외엔 텍스트 */
  const linkCell=v=>{ v=(v==null?'':String(v)).trim(); if(!v) return dash;
    return /^https?:\/\//i.test(v) ? `<a class="attach-y attach-open" href="${esc(v)}" target="_blank" rel="noopener" title="${esc(v)}">확인</a>` : esc(v); };
  const remarkCell=v=>{ v=(v==null?'':String(v)).trim(); return v?esc(v.replace(/\s*\n\s*/g,' · ')):dash; };   // 한 줄: 줄바꿈→' · '
  b.innerHTML=pageRows.map((r,i)=>{
    const pr=r.priority?`<span class="tag pr-${esc(r.priority)}">${esc(r.priority)}</span>`:dash;
    const st=r.status?`<span class="tag st-${esc(r.status)}">${esc(r.status)}</span>`:dash;
    const ty=r.type?`<span class="tag type">${esc(r.type)}</span>`:dash;
    const pg=r.progress!==''&&r.progress!=null ? Math.round(parseFloat(r.progress)*100) : null;
    const pbar = pg===null?dash:`<span class="pbar"><div style="width:${pg}%"></div></span> ${pg}%`;
    return `<tr>
      <td data-label="번호">${total-(start+i)}</td>
      <td data-label="분류">${r.class?`<span class="tag cls">${esc(r.class)}</span>`:dash}</td>
      <td data-label="차수">${esc(r.phaseRound)||dash}</td>
      <td class="title" data-label="프로젝트명"${tt(r.title)}>${esc(r.title)||'-'}</td>
      <td data-label="구분">${ty}</td>
      <td data-label="작업구간">${esc(r.workPhase)||dash}</td>
      <td data-label="작업자">${esc(r.worker)||dash}</td>
      <td class="col-wrap" data-label="협업자/파트/외부업체"${tt(r.collaborator)}>${esc(r.collaborator)||dash}</td>
      <td class="col-wrap" data-label="프로젝트 참여자"${tt(r.participants)}>${esc(r.participants)||dash}</td>
      <td data-label="우선순위">${pr}</td>
      <td data-label="상태">${st}</td>
      <td data-label="시작일">${esc(r.startDate)||dash}</td>
      <td data-label="마감일">${esc(r.due)||dash}</td>
      <td data-label="작업일수(d)">${(r.workDays===''||r.workDays==null)?dash:esc(r.workDays)}</td>
      <td data-label="진행률">${pbar}</td>
      <td class="col-tags" data-label="태그"${tt(r.tags)}>${esc(r.tags)||dash}</td>
      <td class="col-link" data-label="참고링크1"${tt(r.link1)}>${linkCell(r.link1)}</td>
      <td class="col-link" data-label="참고링크2"${tt(r.link2)}>${linkCell(r.link2)}</td>
      <td class="col-remark" data-label="비고"${tt(r.remark)}>${remarkCell(r.remark)}</td>
      <td data-label="최초작성일시">${fmtDt(r.createdAt)}</td>
      <td data-label="최초작성자">${esc(r.createdBy)||dash}</td>
      <td data-label="최종수정일시">${fmtDt(r.updatedAt)}</td>
      <td data-label="최종수정자">${esc(r.updatedBy)||dash}</td>
      <td data-label="💬 댓글">${(()=>{ const n=parseInt(r.commentCount,10)||0; return n>0?`<span class="cmt-cnt">💬 ${n}</span>`:'<span class="subtle">0</span>'; })()}</td>
      <td data-label="관리">${rowActions(r)}</td></tr>`;
  }).join('');
  applyFreeze();
  renderPager(total,pages);
}
function fmtDt(s){ if(!s) return '<span class="subtle">-</span>'; return esc(String(s).replace('T',' ').slice(0,19)); }  /* YYYY-MM-DD HH:MM:SS */
/* 검색은 [🔍 검색] 버튼 또는 통합검색 Enter로 적용, [↺ 초기화]로 전체 리셋 */
function applySearch(){
  SF={ cls:fClass.value, phaseRound:fPhaseRound.value, type:fType.value, workPhase:fWorkPhase.value,
       worker:fWorker.value, collab:fCollab.value, participants:fParticipants.value,
       priority:fPriority.value, status:fStatus.value, workDays:fWorkDays.value, progress:fProgress.value,
       textField:fTextField.value, q:(document.getElementById('q').value||'').trim(),
       dateField:fDateField.value, dateFrom:fDateFrom.value, dateTo:fDateTo.value,
       cmtSort:(document.getElementById('fCmtSort')?.value||'') };
  curPage=1; render();
}
/* 검색영역 collapse — localStorage('todoSp_collapsed') 영속 + 버튼 텍스트 토글 */
function toggleSearchPanel(){
  const c=document.getElementById('spCard'); if(!c) return;
  c.classList.toggle('collapsed');
  const cl=c.classList.contains('collapsed');
  try{ localStorage.setItem('todoSp_collapsed', cl?'1':'0'); }catch(e){}
  document.getElementById('spToggle')?.setAttribute('aria-expanded', cl?'false':'true');
  const txt=document.querySelector('#spToggle .sp-toggle-text'); if(txt) txt.textContent = cl ? '펼치기' : '접기';
}
function resetSearch(){
  ['fClass','fPhaseRound','fType','fWorkPhase','fWorker','fCollab','fParticipants','fPriority','fStatus','fWorkDays','fProgress','fTextField','fDateField','fCmtSort'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
  fDateFrom.value=''; fDateTo.value=''; document.getElementById('q').value='';
  SF={...SF_INIT};
  curPage=1; render();
}
function doSearch(){ const q=document.getElementById('q'); if(!(q.value||'').trim()){ alert('검색어를 입력해주세요'); q.focus(); return; } applySearch(); }   // 검색 버튼/Enter: 통합검색어 미입력 시 안내 팝업
document.getElementById('btnSearch').onclick=doSearch;
document.getElementById('btnReset').onclick=resetSearch;
document.getElementById('q').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });
/* 검색영역의 셀렉트박스(필터·기간기준·통합검색대상) 선택 시 + 기간 날짜 선택 시 → 즉시 검색. 통합검색어 입력은 Enter/버튼 유지 */
document.querySelectorAll('.search-panel select').forEach(sel=>sel.addEventListener('change',applySearch));
document.querySelectorAll('.search-panel input[type="date"]').forEach(inp=>inp.addEventListener('change',applySearch));

/* ===== 페이지네이션 (페이지당 개수 셀렉트 + 페이지 이동) ===== */
function renderPager(total,pages){
  const el=document.getElementById('pager'); if(!el) return;
  if(total===0){ el.innerHTML=''; return; }
  const btn=(label,p,o={})=>`<button type="button" class="pg-btn${o.active?' active':''}"${o.disabled?' disabled':''} data-page="${p}">${label}</button>`;
  let h=btn('‹',curPage-1,{disabled:curPage<=1});
  const win=2, s=Math.max(1,curPage-win), e=Math.min(pages,curPage+win);
  if(s>1){ h+=btn('1',1); if(s>2) h+='<span class="pg-ell">…</span>'; }
  for(let p=s;p<=e;p++) h+=btn(p,p,{active:p===curPage});
  if(e<pages){ if(e<pages-1) h+='<span class="pg-ell">…</span>'; h+=btn(pages,pages); }
  h+=btn('›',curPage+1,{disabled:curPage>=pages});
  el.innerHTML=h;
}
document.getElementById('pager').addEventListener('click',e=>{
  const b=e.target.closest('.pg-btn'); if(!b||b.disabled) return;
  const p=parseInt(b.dataset.page,10); if(isNaN(p)||p===curPage) return;
  curPage=p; render();
});
document.getElementById('pageSize').addEventListener('change',function(){ pageSize=parseInt(this.value,10)||10; curPage=1; render(); });

/* 리스트를 어느 위치에서도 좌우 스크롤: 휠(세로/가로)·Shift+휠·트랙패드를 가로 스크롤로 변환.
   가장자리에 닿으면 preventDefault 안 하고 페이지 세로 스크롤이 자연스럽게 이어지게 함. (목록·대시보드 표 모두) */
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

/* 좌측 컬럼 틀고정(ID·분류·차수·프로젝트명): 헤더 너비로 누적 left 계산 → 헤더·본문 셀에 sticky 적용.
   모바일 카드 레이아웃(≤640px)이거나 표가 숨김(폭 0)이면 적용 보류. */
const FREEZE_COUNT=4;
function applyFreeze(){
  if(window.matchMedia('(max-width:640px)').matches) return;
  const head=document.querySelector('#tbl thead tr');
  const ths=head?[...head.children]:[];
  if(ths.length<FREEZE_COUNT) return;
  const widths=ths.slice(0,FREEZE_COUNT).map(th=>th.getBoundingClientRect().width);
  if(widths.some(w=>w===0)) return;                       // 숨김(대시보드 탭) 등 폭 0이면 보류
  const lefts=[]; let acc=0; for(let i=0;i<FREEZE_COUNT;i++){ lefts[i]=acc; acc+=widths[i]; }
  const setRow=cells=>{ for(let i=0;i<FREEZE_COUNT && i<cells.length;i++){ const c=cells[i]; c.classList.add('frz'); if(i===FREEZE_COUNT-1) c.classList.add('frz-edge'); c.style.left=Math.round(lefts[i])+'px'; } };
  setRow(ths);
  document.querySelectorAll('#body tr').forEach(tr=>setRow([...tr.children]));
}
window.addEventListener('resize', ()=>{ clearTimeout(applyFreeze._t); applyFreeze._t=setTimeout(applyFreeze,120); });

/* ===== 탭 + 대시보드 ===== */
const INIT_TAB = <?php echo json_encode($__ttab); ?>;   // ?tab=dash 진입 시 대시보드 탭으로 자동 전환(LNB에서 대시보드 클릭 시)
document.querySelectorAll('.tabs button').forEach(b=>{
  b.onclick=()=>{
    document.querySelectorAll('.tabs button').forEach(x=>x.classList.remove('active')); b.classList.add('active');
    const v=b.dataset.v;
    document.getElementById('view-list').classList.toggle('hidden', v!=='list');
    document.getElementById('view-dash').classList.toggle('hidden', v!=='dash');
    if(v==='dash') renderDash();
    if(v==='list') requestAnimationFrame(applyFreeze);
    const pt=document.getElementById('pageTitle');   // LNB 메뉴명 동기화 — 탭 전환 시 h1도 같이 갱신
    if(pt){ pt.dataset.lnbTitle=(v==='dash'?'todolist_dash':'todolist'); const def=(v==='dash'?'대시보드':'작업 목록'); pt.dataset.lnbOrig=def;
      pt.textContent=(window.lnbResolveName?window.lnbResolveName('d3:'+pt.dataset.lnbTitle):null) || def; }
    window.scrollTo({top:0});
  };
});
/* ===== 차트 (요구사항 대시보드와 동일 스타일: 도넛 + 색상 막대) ===== */
const TCMAP={
  status:{'진행전':'#94a3b8','진행중':'#06b6d4','완료':'#10b981','보류':'#f59e0b','취소':'#ef4444'},
  priority:{'높음':'#ef4444','중간':'#f59e0b','낮음':'#22c55e'},
};
const TPAL=['#2f6bff','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#14b8a6','#94a3b8'];
const tColor=(g,k,i)=>(TCMAP[g]&&TCMAP[g][k])||TPAL[i%TPAL.length];

function bars(el, group, arr){
  const list=arr.filter(r=>r.n>0);
  if(!list.length){ el.innerHTML='<p class="chart-empty">데이터 없음</p>'; return; }
  const tot=arr.reduce((s,r)=>s+r.n,0)||1, max=Math.max(1,...list.map(r=>r.n));
  el.innerHTML='<div class="bars">'+list.map((r,i)=>{
    const c=tColor(group,r.k,i), pct=Math.round(r.n/tot*100);
    return `<div class="bar-row"><div class="bl">${esc(r.k)}</div>`+
      `<div class="bv">${r.n}건<small>${pct}%</small></div>`+
      `<div class="bar-track"><div class="bar-fill" data-w="${(r.n/max*100).toFixed(1)}" style="background:${c}"></div></div></div>`;
  }).join('')+'</div>';
  requestAnimationFrame(()=>el.querySelectorAll('.bar-fill').forEach(f=>{ f.style.width=f.dataset.w+'%'; }));
}
function donut(el, group, arr){
  const list=arr.filter(r=>r.n>0), tot=list.reduce((s,r)=>s+r.n,0);
  if(!tot){ el.innerHTML='<p class="chart-empty">데이터 없음</p>'; return; }
  let acc=0; const segs=list.map((r,i)=>{ const c=tColor(group,r.k,i), a0=acc/tot*360; acc+=r.n; return {r,c,a0,a1:acc/tot*360}; });
  const grad=segs.map(s=>`${s.c} ${s.a0.toFixed(2)}deg ${s.a1.toFixed(2)}deg`).join(',');
  const legend=segs.map(s=>{ const pct=Math.round(s.r.n/tot*100);
    return `<div class="lg"><span class="sw" style="background:${s.c}"></span><span class="ln">${esc(s.r.k)}</span><span class="lc"><b>${s.r.n}</b>건 · ${pct}%</span></div>`;
  }).join('');
  el.innerHTML=`<div class="donut-wrap"><div class="donut" style="background:conic-gradient(${grad})"><div class="donut-c"><div class="t">${tot}</div><div class="s">건</div></div></div><div class="legend">${legend}</div></div>`;
}
function renderDash(){
  const tot=rows.length, cnt=s=>rows.filter(r=>r.status===s).length;
  kTotal.textContent=tot; kTodo.textContent=cnt('진행전'); kDoing.textContent=cnt('진행중');
  kDone.textContent=cnt('완료'); kHold.textContent=cnt('보류'); kCancel.textContent=cnt('취소');
  kRate.textContent=(tot?Math.round(cnt('완료')/tot*100):0)+'%';
  const today=new Date().toISOString().slice(0,10);
  kDelay.textContent=rows.filter(r=>/^\d{4}-\d{2}-\d{2}$/.test(r.due)&&r.due<today&&r.status!=='완료'&&r.status!=='취소').length;
  kDays.textContent=rows.reduce((s,r)=>s+(parseFloat(r.workDays)||0),0).toFixed(0);
  donut(dStatus, 'status', OPT.status.map(s=>({k:s,n:cnt(s)})));
  bars(dPriority, 'priority', OPT.priority.map(p=>({k:p,n:rows.filter(r=>r.priority===p).length})));
  const tb=document.querySelector('#dType tbody');
  tb.innerHTML=OPT.type.map(ty=>{
    const sub=rows.filter(r=>r.type===ty), all=sub.length, c=s=>sub.filter(r=>r.status===s).length;
    const done=c('완료'), days=sub.reduce((s,r)=>s+(parseFloat(r.workDays)||0),0);
    return `<tr><td>${esc(ty)}</td><td>${all}</td><td>${c('진행전')}</td><td>${c('진행중')}</td><td>${done}</td><td>${c('보류')}</td><td>${all?Math.round(done/all*100):0}%</td><td>${days.toFixed(0)}</td></tr>`;
  }).join('');
}
function refreshAll(){ render(); if(!document.getElementById('view-dash').classList.contains('hidden')) renderDash(); }

/* 추가/수정은 별도 페이지 form.php 에서 처리 (＋ 작업 추가 / 행의 수정 버튼이 form.php 로 이동) */
async function delItem(id){
  if(!confirm('이 작업을 삭제하시겠습니까?')) return;
  try{ const r=await fetch(API+'todos&id='+id,{method:'DELETE'}); if(!r.ok) throw new Error('HTTP '+r.status); await load(); }
  catch(err){ alert('삭제 실패: '+err.message); return; }
  refreshAll(); toast('삭제되었습니다');
}
let toT; function toast(m){ const el=document.getElementById('toast'); el.textContent=m; el.classList.add('show'); clearTimeout(toT); toT=setTimeout(()=>el.classList.remove('show'),2200); }

/* ---- Excel(.xlsx) 내보내기 (라이브러리 없이 순수 JS로 생성 — requirements.php와 동일 방식) ---- */
function dl(blob,name){ const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=name; a.click(); URL.revokeObjectURL(a.href); }
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
const XL_LABELS={code:'ID',class:'분류',phaseRound:'차수',title:'프로젝트명',type:'구분',workPhase:'작업구간',
  worker:'작업자',collaborator:'협업자/파트/외부업체',participants:'프로젝트 참여자',priority:'우선순위',
  status:'상태',startDate:'시작일',due:'마감일',workDays:'작업일수(d)',progress:'진행률(%)',
  tags:'태그',remark:'비고',link1:'참고링크1',link2:'참고링크2'};
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
  /* sheet1: 작업 목록 (전체 19개 필드) */
  const rows1=[ FIELDS.map(k=>H(XL_LABELS[k])) ];
  rows.forEach(d=>{
    rows1.push(FIELDS.map(k=>{
      if(k==='workDays'){ const n=parseFloat(d[k]); return isNaN(n)?T(''):N(n); }
      if(k==='progress'){ const n=parseFloat(d[k]); return isNaN(n)?T(''):N(Math.round(n*100)); }
      return T(d[k]);
    }));
  });
  const cols1='<cols><col min="1" max="3" width="13"/><col min="4" max="4" width="30"/>'+
    '<col min="5" max="6" width="14"/><col min="7" max="9" width="18"/>'+
    '<col min="10" max="15" width="11"/><col min="16" max="17" width="22"/>'+
    '<col min="18" max="19" width="24"/></cols>';
  /* sheet2: 요약 */
  const tot=rows.length;
  const doing=rows.filter(r=>r.status==='진행중').length;
  const done=rows.filter(r=>r.status==='완료').length;
  const today=new Date().toISOString().slice(0,10);
  const delay=rows.filter(r=>/^\d{4}-\d{2}-\d{2}$/.test(r.due)&&r.due<today&&r.status!=='완료'&&r.status!=='취소').length;
  const days=rows.reduce((s,r)=>s+(parseFloat(r.workDays)||0),0);
  const rows2=[
    [H('📋 서비스 기획 작업(TO-DO) 요약')], [],
    [H('전체 작업'), N(tot)],
    [H('진행중'), N(doing)],
    [H('완료'), N(done)],
    [H('완료율(%)'), N(tot?Math.round(done/tot*100):0)],
    [H('지연 작업'), N(delay)],
    [H('총 작업일수(d)'), N(Math.round(days))], []
  ];
  const section=(title,key,order)=>{
    rows2.push([H(title)]);
    rows2.push([H('항목'),H('건수'),H('비율(%)')]);
    order.forEach(k=>{ const n=rows.filter(r=>(r[key]||'')===k).length; if(n>0) rows2.push([T(k),N(n),N(tot?Math.round(n/tot*100):0)]); });
    rows2.push([]);
  };
  section('상태별 현황','status',OPT.status);
  section('우선순위별 현황','priority',OPT.priority);
  section('구분별 현황','type',OPT.type);
  const cols2='<cols><col min="1" max="1" width="22"/><col min="2" max="3" width="12"/></cols>';

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
      '<sheets><sheet name="작업 목록" sheetId="1" r:id="rId1"/><sheet name="요약" sheetId="2" r:id="rId2"/></sheets></workbook>')},
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
  try{ dl(buildXlsxBlob(),'메디힘_서비스기획_작업목록.xlsx'); toast('Excel 파일을 내보냈습니다'); }
  catch(e){ alert('Excel 생성 실패: '+e.message); }
};

(async function boot(){
  if(!PERM.write){ const a=document.getElementById('addBtn'); if(a) a.style.display='none'; }   // 쓰기 권한 없으면 추가 버튼 숨김
  await loadOptions(); initSelects();
  let mode='seed';
  try{
    const h = await (await fetch(API+'health',{cache:'no-store'})).json();
    if(h && h.db){ await load(); mode='db'; }
  }catch(e){ mode='seed'; }
  if(mode!=='db'){ rows = SEED_ROWS.slice(); EMBEDDED=true; applyReadOnly(); }
  fillDynamicFilters();   // 로드된 데이터 기반 distinct 셀렉트 채움
  setDb(mode);
  render();
  if(localStorage.getItem('todoSp_collapsed')==='1'){ document.getElementById('spCard')?.classList.add('collapsed'); document.getElementById('spToggle')?.setAttribute('aria-expanded','false'); const _t=document.querySelector('#spToggle .sp-toggle-text'); if(_t) _t.textContent='펼치기'; }
  if(INIT_TAB==='dash'){ const db=document.querySelector('.tabs button[data-v="dash"]'); if(db) db.click(); }   // ?tab=dash 진입: rows 로드 완료 후 자동 탭 클릭 → renderDash가 데이터로 그려짐
  const tmsg=sessionStorage.getItem('todoToast'); if(tmsg){ sessionStorage.removeItem('todoToast'); setTimeout(()=>toast(tmsg),150); }   // form.php 저장 후 복귀 토스트
})();
</script>
</body>
</html>

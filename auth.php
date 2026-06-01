<?php
/* =====================================================================
   공용 인증 + 권한(RBAC) (세션 + 로그인 가드 + 메뉴/CRUD 권한)
   - 보호 페이지: 최상단에서  require __DIR__.'/..(경로)../auth.php'; require_login();
     · 메뉴 접근 차단까지 하려면  require_perm('<resource>','access');
   - 로그인 페이지(login/login.php): require 만 하고 require_login() 은 호출하지 않음
   - 자격증명: member 테이블의 이름 + 휴대폰번호 (검증은 login.php)
   - 권한: member.is_admin(전권) + member_perm(회원×메뉴×{access,read,write,update,delete})
     · 관리자(is_admin)는 항상 모든 권한 허용
     · 권한은 매 요청마다 DB에서 갱신 로드 → 관리자가 바꾸면 다음 페이지 로드에 즉시 반영
   - BOM 없는 UTF-8 (세션/헤더 전송 전 출력 금지 → 닫는 ?> 생략)
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

/** 권한 대상 메뉴(리소스) 정의: key => [라벨, 절대경로, 아이콘, 그룹, CRUD 사용여부] */
function auth_resources() {
  return [
    'project_manager' => ['label'=>'일정',                 'href'=>'/project_manager/schedule.php',           'ico'=>'🗓️', 'group'=>'프로젝트 매니저', 'crud'=>true],
    'requirements' => ['label'=>'요구사항 목록',          'href'=>'/requirements/requirements.php',         'ico'=>'🗂️', 'group'=>'요구사항 정의', 'crud'=>true],
    'dashboard'    => ['label'=>'요구사항 대시보드',        'href'=>'/requirements/requirements.php?tab=dash', 'ico'=>'📊', 'group'=>'요구사항 정의', 'crud'=>false, 'lnb'=>false],  // LNB 비노출(requirements 페이지의 탭으로 통합), 권한 리소스로는 유지
    'todolist'     => ['label'=>'서비스기획 TO-DO LIST',   'href'=>'/service_todolist/service_todolist.php', 'ico'=>'✅', 'group'=>'서비스 기획', 'crud'=>true],
    'member'       => ['label'=>'회원 관리',               'href'=>'/member/member.php',                     'ico'=>'👥', 'group'=>'회원',       'crud'=>true],
    'notification' => ['label'=>'공지사항',                 'href'=>'/notification/notification.php',         'ico'=>'📢', 'group'=>'공지사항',   'crud'=>true],
    'documents'    => ['label'=>'자료실',                   'href'=>'/documents/documents.php',               'ico'=>'📚', 'group'=>'자료실',     'crud'=>true],
  ];
}
function auth_actions() { return ['access','read','write','update','delete']; }

/** 로그인된 사용자 배열(id,name,dept,email) 또는 null */
function auth_user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }

/** 로그인 여부 */
function auth_check() { return isset($_SESSION['user']); }

/** 공용 PDO(요청 1회 캐시). 실패 시 false */
function auth_db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;
  $DB = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
    'name' => getenv('DB_NAME') ?: 'medihim',
  ];
  try {
    $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { $pdo = false; }
  return $pdo;
}

/** 현재 사용자의 is_admin + 권한맵을 DB에서 로드(요청 1회 캐시) */
function auth_load() {
  static $loaded = null;
  if ($loaded !== null) return $loaded;
  $loaded = ['is_admin' => false, 'perms' => [], 'db_down' => false];
  $u = auth_user();
  if (!$u) return $loaded;
  $pdo = auth_db();
  if (!$pdo) { $loaded['db_down'] = true; return $loaded; }   // DB 불가 → 권한 판정 불가(아래 can()에서 fail-open)
  try {
    $st = $pdo->prepare("SELECT is_admin FROM member WHERE id = ?");
    $st->execute([$u['id']]);
    $loaded['is_admin'] = (bool)$st->fetchColumn();
  } catch (Throwable $e) {}
  try {
    $st = $pdo->prepare("SELECT resource,can_access,can_read,can_write,can_update,can_delete FROM member_perm WHERE member_id = ?");
    $st->execute([$u['id']]);
    foreach ($st->fetchAll() as $r) {
      $loaded['perms'][$r['resource']] = [
        'access' => (int)$r['can_access'], 'read' => (int)$r['can_read'], 'write' => (int)$r['can_write'],
        'update' => (int)$r['can_update'], 'delete' => (int)$r['can_delete'],
      ];
    }
  } catch (Throwable $e) {}
  return $loaded;
}

/** 현재 사용자가 관리자인가 */
function auth_is_admin() { $l = auth_load(); return $l['is_admin']; }

/** 권한 체크: 관리자는 항상 true, 그 외는 member_perm 기준.
 *  DB 불가(오프라인)면 권한 판정이 불가하므로 fail-open(로그인은 이미 통과, 쓰기는 각 API가 별도 차단). */
function can($resource, $action = 'access') {
  $l = auth_load();
  if (!empty($l['db_down'])) return true;
  if ($l['is_admin']) return true;
  return !empty($l['perms'][$resource][$action]);
}

/** 현재 사용자의 특정 리소스 권한 5종을 bool 맵으로 (프런트 전달용) */
function perm_map($resource) {
  $a = [];
  foreach (auth_actions() as $act) $a[$act] = can($resource, $act);
  $a['admin'] = auth_is_admin();
  return $a;
}

/** 접근 가능한 첫 메뉴 경로(없으면 로그아웃) */
function auth_first_accessible() {
  foreach (auth_resources() as $res => $r) if (can($res, 'access')) return $r['href'];
  return '/login/login.php?logout=1';
}

/** 보호 가드: 미로그인 시 페이지는 로그인으로 리다이렉트, API(?api=)는 401 JSON */
function require_login() {
  if (auth_check()) return;
  if (isset($_GET['api'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '로그인이 필요합니다', 'login' => true], JSON_UNESCAPED_UNICODE);
    exit;
  }
  header('Location: /login/login.php');
  exit;
}

/** 권한 가드: 로그인 + 특정 리소스/액션 권한 필요. 없으면 페이지는 403 화면, API는 403 JSON */
function require_perm($resource, $action = 'access') {
  require_login();
  if (can($resource, $action)) return;
  if (isset($_GET['api'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '권한이 없습니다', 'forbidden' => true, 'resource' => $resource, 'action' => $action], JSON_UNESCAPED_UNICODE);
    exit;
  }
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  $back = htmlspecialchars(auth_first_accessible());
  echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>접근 권한 없음</title><link rel="stylesheet" href="/style.css"><style>'
    . 'body{display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:var(--bg,#f5f6f8);}'
    . '.f-card{max-width:430px;background:#fff;border:1px solid var(--line,#edeff3);border-radius:16px;box-shadow:0 12px 44px rgba(20,30,60,.12);padding:40px 34px;text-align:center;}'
    . '.f-ico{font-size:46px;}.f-card h1{font-size:20px;margin:14px 0 8px;}.f-card p{color:var(--sub,#8b93a3);font-size:14px;line-height:1.6;margin:0 0 22px;}'
    . '.f-card .btn{margin:0 4px;}</style></head><body><div class="f-card"><div class="f-ico">🔒</div>'
    . '<h1>접근 권한이 없습니다</h1><p>이 메뉴에 대한 접근 권한이 없습니다.<br>필요하면 관리자에게 권한을 요청하세요.</p>'
    . '<a class="btn primary" href="' . $back . '">접근 가능한 메뉴로</a>'
    . '<a class="btn ghost" href="/login/login.php?logout=1">로그아웃</a></div></body></html>';
  exit;
}

/** 관리자 전용 가드: 로그인 + is_admin 필요. 아니면 페이지는 403 화면, API는 403 JSON */
function require_admin() {
  require_login();
  if (auth_is_admin()) return;
  if (isset($_GET['api'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '관리자만 접근할 수 있습니다', 'forbidden' => true], JSON_UNESCAPED_UNICODE);
    exit;
  }
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  $back = htmlspecialchars(auth_first_accessible());
  echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>관리자 전용</title><link rel="stylesheet" href="/style.css"><style>'
    . 'body{display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:var(--bg,#f5f6f8);}'
    . '.f-card{max-width:430px;background:#fff;border:1px solid var(--line,#edeff3);border-radius:16px;box-shadow:0 12px 44px rgba(20,30,60,.12);padding:40px 34px;text-align:center;}'
    . '.f-ico{font-size:46px;}.f-card h1{font-size:20px;margin:14px 0 8px;}.f-card p{color:var(--sub,#8b93a3);font-size:14px;line-height:1.6;margin:0 0 22px;}'
    . '.f-card .btn{margin:0 4px;}</style></head><body><div class="f-card"><div class="f-ico">🔐</div>'
    . '<h1>관리자 전용 메뉴입니다</h1><p>권한 설정은 관리자만 사용할 수 있습니다.<br>필요하면 관리자에게 문의하세요.</p>'
    . '<a class="btn primary" href="' . $back . '">접근 가능한 메뉴로</a>'
    . '<a class="btn ghost" href="/login/login.php?logout=1">로그아웃</a></div></body></html>';
  exit;
}

/** 페이지 우측 상단 알림(🔔) 아이콘 — 작성일로부터 15일 이내 등록된 공지(status='게시')의 수를 빨간 뱃지로 노출.
 *  클릭 시 /notification/notification.php 이동. 각 페이지 .topbar 우측 auth_profile() 직전에 삽입. */
function auth_bell() {
  $cnt = 0;
  $pdo = auth_db();
  if ($pdo) {
    try {
      $st = $pdo->query("SELECT COUNT(*) FROM notification WHERE status='게시' AND created_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)");
      $cnt = (int)$st->fetchColumn();
    } catch (Throwable $e) {}
  }
  $badge = $cnt > 0 ? '<span class="bell-badge">' . ($cnt > 99 ? '99+' : $cnt) . '</span>' : '';
  $title = $cnt > 0 ? ('최근 15일 신규 공지 ' . $cnt . '건') : '공지사항';
  echo '<a class="bell-link" href="/notification/notification.php" title="' . htmlspecialchars($title) . '"><span class="bell-ico"><svg class="bell-svg" viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>' . $badge . '</a>';
  auth_lang();   // 알림 아이콘 뒤 언어선택
}

/** 알림 아이콘 뒤 언어선택 — 커스텀 셀렉트 + Google 웹사이트 번역(쿠키로 페이지 간 선택 유지). 페이지당 1회만 출력 */
function auth_lang() {
  static $done = false; if ($done) return; $done = true;
  // [언어코드, 표기, 국기 국가코드(flagcdn)]
  $langs = [
    ['ko','한국어','kr'], ['ja','일본어','jp'], ['en','영어','us'], ['zh-CN','중국어 간체','cn'],
    ['zh-TW','중국어 번체','tw'], ['vi','베트남어','vn'], ['id','인도네시아어','id'],
    ['ru','러시아어','ru'], ['fr','프랑스어','fr'], ['de','독일어','de'],
  ];
  $items = '';
  foreach ($langs as $L) {
    list($code, $label, $cc) = $L;
    $flag = 'https://flagcdn.com/w40/' . $cc . '.png';
    $items .= '<li class="lang-item" data-code="' . htmlspecialchars($code, ENT_QUOTES) . '" onclick="__setLang(\'' . htmlspecialchars($code, ENT_QUOTES) . '\')">'
            . '<img src="' . $flag . '" alt="" loading="lazy"><span>' . htmlspecialchars($label) . '</span></li>';
  }
  echo <<<HTML
<div class="lang-dd notranslate" id="langDD" translate="no" title="언어 선택 / Language">
  <button type="button" class="lang-btn" onclick="langToggle(event)"><span class="lang-ico">🌐</span><img class="lang-flag" id="langCurFlag" src="https://flagcdn.com/w40/kr.png" alt=""><span id="langCurLabel">한국어</span><span class="lang-caret">▾</span></button>
  <ul class="lang-menu" id="langMenu">{$items}</ul>
</div>
<div id="google_translate_element"></div>
<style>
.lang-dd{position:relative;display:inline-block;margin-left:4px;vertical-align:middle;}
.lang-btn{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 10px;border:1px solid var(--line,#e6e9f0);border-radius:8px;background:#fff;color:var(--ink,#1b2433);font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;}
.lang-btn:hover{border-color:var(--brand,#2f6bff);}
.lang-btn .lang-ico{font-size:15px;line-height:1;}
.lang-btn .lang-flag{width:22px;height:15px;object-fit:cover;border-radius:2px;display:block;box-shadow:0 0 0 1px rgba(0,0,0,.06);}
.lang-btn .lang-caret{font-size:10px;color:#888;}
.lang-menu{position:absolute;top:38px;right:0;min-width:184px;max-height:340px;overflow:auto;background:#fff;border:1px solid var(--line,#e6e9f0);border-radius:10px;box-shadow:0 12px 32px rgba(20,30,60,.16);list-style:none;margin:0;padding:6px;display:none;z-index:10001;}
.lang-menu.open{display:block;}
.lang-item{display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:7px;cursor:pointer;font-size:13px;color:var(--ink,#1b2433);white-space:nowrap;}
.lang-item:hover{background:var(--brand-soft,#e8efff);}
.lang-item.sel{background:var(--brand-soft,#e8efff);font-weight:700;}
.lang-item img{width:24px;height:16px;object-fit:cover;border-radius:2px;display:block;box-shadow:0 0 0 1px rgba(0,0,0,.06);}
#google_translate_element{display:none!important;}
.goog-te-banner-frame,iframe.goog-te-banner-frame,.goog-te-banner-frame.skiptranslate,iframe.skiptranslate{display:none!important;height:0!important;border:0!important;visibility:hidden!important;}
.goog-te-gadget{font-size:0!important;height:0!important;overflow:hidden!important;}
body{top:0!important;}
</style>
<script>
(function(){
  var m=document.cookie.match(/googtrans=\/[^\/]*\/([^;]+)/);
  var cur=m?decodeURIComponent(m[1]):'ko';
  // 언어명을 "현재 선택된 언어"로 현지화 (CODES 순서와 각 배열 순서 일치)
  var CODES=['ko','ja','en','zh-CN','zh-TW','vi','id','ru','fr','de'];
  var L10N={
    'ko':['한국어','일본어','영어','중국어 간체','중국어 번체','베트남어','인도네시아어','러시아어','프랑스어','독일어'],
    'ja':['韓国語','日本語','英語','簡体中国語','繁体中国語','ベトナム語','インドネシア語','ロシア語','フランス語','ドイツ語'],
    'en':['Korean','Japanese','English','Chinese (Simplified)','Chinese (Traditional)','Vietnamese','Indonesian','Russian','French','German'],
    'zh-CN':['韩语','日语','英语','简体中文','繁体中文','越南语','印度尼西亚语','俄语','法语','德语'],
    'zh-TW':['韓語','日語','英語','簡體中文','繁體中文','越南語','印尼語','俄語','法語','德語'],
    'vi':['Tiếng Hàn','Tiếng Nhật','Tiếng Anh','Tiếng Trung (Giản thể)','Tiếng Trung (Phồn thể)','Tiếng Việt','Tiếng Indonesia','Tiếng Nga','Tiếng Pháp','Tiếng Đức'],
    'id':['Korea','Jepang','Inggris','Tionghoa (Sederhana)','Tionghoa (Tradisional)','Vietnam','Indonesia','Rusia','Prancis','Jerman'],
    'ru':['Корейский','Японский','Английский','Китайский (упрощённый)','Китайский (традиционный)','Вьетнамский','Индонезийский','Русский','Французский','Немецкий'],
    'fr':['Coréen','Japonais','Anglais','Chinois (simplifié)','Chinois (traditionnel)','Vietnamien','Indonésien','Russe','Français','Allemand'],
    'de':['Koreanisch','Japanisch','Englisch','Chinesisch (vereinfacht)','Chinesisch (traditionell)','Vietnamesisch','Indonesisch','Russisch','Französisch','Deutsch']
  };
  (function(){ var names=L10N[cur]||L10N['ko']; document.querySelectorAll('#langMenu .lang-item').forEach(function(it){ var i=CODES.indexOf(it.dataset.code), sp=it.querySelector('span'); if(i>=0&&sp&&names[i]) sp.textContent=names[i]; }); })();
  // 현재 선택 언어로 버튼 국기/표기 동기화 + 메뉴 선택표시
  var li=document.querySelector('#langMenu .lang-item[data-code="'+cur+'"]')||document.querySelector('#langMenu .lang-item[data-code="ko"]');
  if(li){
    var cf=document.getElementById('langCurFlag'), cl=document.getElementById('langCurLabel');
    if(cf) cf.src=li.querySelector('img').src;
    if(cl) cl.textContent=li.querySelector('span').textContent;
    document.querySelectorAll('#langMenu .lang-item').forEach(function(x){x.classList.remove('sel');});
    li.classList.add('sel');
  }
  window.langToggle=function(e){ if(e){e.stopPropagation();} var mn=document.getElementById('langMenu'); if(mn) mn.classList.toggle('open'); };
  document.addEventListener('click',function(e){ var dd=document.getElementById('langDD'); var mn=document.getElementById('langMenu'); if(dd&&mn&&!dd.contains(e.target)) mn.classList.remove('open'); });
  window.__setLang=function(l){
    if(l==='ko'){ document.cookie='googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT'; }
    else { document.cookie='googtrans=/ko/'+l+';path=/'; }
    location.reload();
  };
  if(cur!=='ko' && !window.__gtLoaded){
    window.__gtLoaded=1;
    window.googleTranslateElementInit=function(){ try{ new google.translate.TranslateElement({pageLanguage:'ko', autoDisplay:false}, 'google_translate_element'); }catch(e){} };
    var s=document.createElement('script'); s.src='//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit'; s.async=true; document.body.appendChild(s);
  }
  if(cur!=='ko'){
    setInterval(function(){
      var b=document.querySelector('iframe.goog-te-banner-frame, .goog-te-banner-frame, iframe.skiptranslate');
      if(b){ b.style.display='none'; }
      if(document.body && document.body.style.top!=='0px'){ document.body.style.top='0px'; }
    },400);
  }
})();
</script>
HTML;
}

/** 세션 캐시된 사용자(name/dept/email)를 DB 최신값으로 새로고침 — 본인 정보 수정 직후 LNB 즉시 갱신용 */
function auth_refresh_session() {
  if (!isset($_SESSION['user']['id'])) return;
  $pdo = auth_db(); if (!$pdo) return;
  try {
    $st = $pdo->prepare("SELECT id, name, dept, email FROM member WHERE id = ?");
    $st->execute([(int)$_SESSION['user']['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $_SESSION['user'] = array_merge($_SESSION['user'], $row);
  } catch (Throwable $e) {}
}

/** 페이지 우측 상단 사용자 프로필 위젯(아바타·이름·부서·로그아웃 + ⚙️ 회원정보 수정 모달). 각 페이지 .topbar 우측에 삽입 */
function auth_profile() {
  auth_refresh_session();   // LNB가 항상 DB 최신 name/dept를 보여주도록 세션 리프레시
  $u = auth_user();
  $uid  = (int)($u['id']   ?? 0);
  $name = $u['name'] ?? '게스트';
  $dept = ($u['dept'] ?? '') ?: '회원';
  $ini = preg_match('/./u', (string)$name, $m) ? $m[0] : '?';
  // ⚙️ 아이콘은 로그인 상태에서만 노출(클릭 시 본인 회원정보 수정 모달)
  $gear = $uid > 0 ? '<button type="button" class="profile-edit-btn" title="내 정보 수정" onclick="openProfileEdit(' . $uid . ')">⚙️</button>' : '';
  echo '<div class="user-badge">'
     . '<span class="avatar">' . htmlspecialchars($ini) . '</span>'
     . '<div class="who"><div class="nm">' . htmlspecialchars($name) . $gear . '</div><div class="role">' . htmlspecialchars($dept) . '</div></div>'
     . '<a class="logout-btn" href="/login/login.php?logout=1" title="로그아웃">로그아웃</a>'
     . '</div>';
  // 회원정보 수정 모달(중복 출력 방지). form.php는 ?modal=1로 LNB/topbar 숨김 + 저장 시 postMessage
  if ($uid > 0) {
    echo '<div class="pe-modal" id="profileEditModal" onclick="if(event.target===this)closeProfileEdit()">'
       . '<div class="pe-modal-box">'
       . '<div class="pe-modal-head"><h3>⚙️ 내 정보 수정</h3><button type="button" class="pe-modal-close" onclick="closeProfileEdit()" title="닫기">×</button></div>'
       . '<div class="pe-modal-body"><iframe id="profileEditFrame" src="about:blank" title="회원정보 수정"></iframe></div>'
       . '</div></div>'
       . "<script>(function(){if(window.__profileBound)return;window.__profileBound=1;
            window.openProfileEdit=function(id){var m=document.getElementById('profileEditModal');var f=document.getElementById('profileEditFrame');f.src='/member/form.php?id='+id+'&modal=1';m.classList.add('show');document.body.style.overflow='hidden';};
            window.closeProfileEdit=function(){var m=document.getElementById('profileEditModal');m.classList.remove('show');document.body.style.overflow='';document.getElementById('profileEditFrame').src='about:blank';};
            window.addEventListener('message',function(e){
              if(!e.data||!e.data.type) return;
              if(e.data.type==='profile-saved'){ closeProfileEdit(); setTimeout(function(){location.reload();},150); }
              else if(e.data.type==='profile-cancel'){ closeProfileEdit(); }
            });
            document.addEventListener('keydown',function(e){if(e.key==='Escape'&&document.getElementById('profileEditModal').classList.contains('show'))closeProfileEdit();});
          })();</script>";
  }
}

/** 공용 LNB 메뉴 렌더링: 접속(access) 권한 있는 메뉴만, 그룹은 보이는 항목이 있을 때만.
 *  $active = 현재 페이지 리소스 키(requirements/dashboard/todolist/member) */
/* LNB 3-depth 트리 정의
 *   d1 = 섹션(1Depth, 헤딩) / d2 = 폴더(2Depth, collapse 토글)
 *   items[] = 리프(3Depth) — perm: 권한 자원 키, match: $active 비교용 문자열, ico/label/href
 *   같은 자원(예: todolist) 아래에 여러 리프(목록·대시보드)도 가능. */
function auth_lnb_tree() {
  return [
    ['d1'=>'프로젝트 매니저', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>', 'd2'=>'일정', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'project_manager', 'match'=>'project_manager', 'label'=>'일정', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>', 'href'=>'/project_manager/schedule.php'],
      ['perm'=>'devops', 'match'=>'devops', 'label'=>'개발일감관리', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>', 'href'=>'https://dev.ippeo.io/redmine/projects', 'target'=>'_blank'],
      ['perm'=>'planning', 'match'=>'planning', 'label'=>'기획서', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h6"/></svg>', 'href'=>'/project_manager/planning.php'],
    ]],
    ['d1'=>'요구사항 정의', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>', 'd2'=>'요구사항', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'requirements', 'match'=>'requirements', 'label'=>'요구사항 목록', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>', 'href'=>'/requirements/requirements.php'],
      ['perm'=>'dashboard',    'match'=>'dashboard',    'label'=>'대시보드',     'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>', 'href'=>'/requirements/requirements.php?tab=dash'],
    ]],
    ['d1'=>'서비스 기획', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>', 'd2'=>'TO-DO', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'todolist', 'match'=>'todolist',      'label'=>'작업 목록', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>', 'href'=>'/service_todolist/service_todolist.php'],
      ['perm'=>'todolist', 'match'=>'todolist_dash', 'label'=>'대시보드',  'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>', 'href'=>'/service_todolist/service_todolist.php?tab=dash'],
    ]],
    ['d1'=>'회원', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', 'd2'=>'회원', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'member', 'match'=>'member', 'label'=>'회원 관리', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', 'href'=>'/member/member.php'],
    ]],
    ['d1'=>'공지사항', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>', 'd2'=>'공지', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'notification', 'match'=>'notification', 'label'=>'공지사항', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>', 'href'=>'/notification/notification.php'],
    ]],
    ['d1'=>'자료실', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>', 'd2'=>'자료실', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'documents', 'match'=>'documents', 'label'=>'자료실', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>', 'href'=>'/documents/documents.php'],
      ['perm'=>'account_management', 'match'=>'account_management', 'label'=>'계정관리', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>', 'href'=>'/documents/account_management.php'],
    ]],
    ['d1'=>'관리자', 'd1_ico'=>'<svg class="d1-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', 'd2'=>'권한', 'd2_ico'=>'📁', 'items'=>[
      ['perm'=>'authz', 'match'=>'authz', 'label'=>'권한 설정', 'ico'=>'<svg class="d2-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#EC70C8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>', 'href'=>'/authorization_settings/authorization_settings.php'],
    ]],
  ];
}

/** 권한 매트릭스용 리소스 목록 — LNB 메뉴 트리(auth_lnb_tree)에서 자동 파생.
 *  메뉴를 신규 추가/삭제하면 트리만 고쳐도 권한 설정 화면·저장(member.php)에 자동 반영된다.
 *  (라벨 변경은 ✏️ 메뉴명 편집이 data-lnb-title 동기화로 즉시 반영)
 *  - authz(권한 설정)는 관리자 토글로 제어하므로 매트릭스에서 제외
 *  - 같은 perm이 여러 리프에 있으면(예: todolist 목록/대시보드) 첫 항목 기준 1행
 *  - $READONLY 자원은 조회 전용(쓰기/수정/삭제 없음)
 *  반환: [ ['key'=>, 'label'=>, 'crud'=>bool, 'ico'=>], ... ] */
function auth_perm_resources() {
  $READONLY = ['dashboard', 'account_management', 'devops'];   // 조회 전용(쓰기/수정/삭제 매트릭스 없음) — devops=외부링크, planning은 CRUD 자원
  $out = []; $seen = [];
  foreach (auth_lnb_tree() as $d1) {
    foreach ($d1['items'] as $it) {
      $key = $it['perm'];
      if ($key === 'authz' || isset($seen[$key])) continue;
      $seen[$key] = true;
      $out[] = ['key'=>$key, 'label'=>$it['label'], 'crud'=>!in_array($key, $READONLY, true), 'ico'=>$it['ico'] ?? '•'];
    }
  }
  return $out;
}

/* LNB 렌더(3-depth 트리). $active: 현재 페이지 match 키. 권한 없는 리프는 숨기고, 가시 리프 없는 d2/d1도 숨김.
 *   '권한 설정'(authz)은 모든 사용자에게 노출하되 비관리자는 클릭 시 팝업(이동 차단).
 *   d2 collapse 상태는 localStorage('lnb_open')에 키별 저장. active 포함 d2는 항상 펼침. */
function auth_lnb($active) {
  $tree = auth_lnb_tree();
  $isAdm = auth_is_admin();
  echo '<nav class="lnb-tree" data-active="' . htmlspecialchars($active) . '">';
  foreach ($tree as $d1) {
    // 권한 필터링 (authz는 비관리자도 노출 — 팝업으로 차단)
    $visible = [];
    foreach ($d1['items'] as $it) {
      if ($it['perm'] === 'authz') { $visible[] = $it; continue; }
      if (can($it['perm'], 'access')) $visible[] = $it;
    }
    if (empty($visible)) continue;

    // 2-Depth 구조: 1Depth(섹션, accordion 토글) + 2Depth(leaf 리스트). 기본 모두 닫힘 — JS가 localStorage 상태로 후처리.
    $d1Key = 'd1_' . substr(md5($d1['d1']), 0, 8);
    $d1NameKey = 'd1:' . $d1['d1'];                       // 메뉴명 편집 키(라벨 변경 시 페이지 타이틀에도 동일 키로 적용)
    // 메뉴명 편집 ✏️ 는 관리자(member.is_admin)에게만 노출
    $d1EditBtn = $isAdm ? '<span class="lnb-edit" title="메뉴명 수정" onclick="event.stopPropagation();lnbEditName(\'' . htmlspecialchars($d1NameKey, ENT_QUOTES) . '\');return false;">✏️</span>' : '';

    echo '<div class="lnb-d1" data-key="' . $d1Key . '">';   // 기본 닫힘(open 클래스 없음)
    echo '<button type="button" class="lnb-d1-lbl" onclick="lnbToggleD1(this)">';
    echo '<span class="chev1">▶</span><span class="d1-ico">' . $d1['d1_ico'] . '</span>';
    echo '<span class="d1-lbl" data-name-key="' . htmlspecialchars($d1NameKey) . '">' . htmlspecialchars($d1['d1']) . '</span>';
    echo $d1EditBtn;
    echo '</button>';
    echo '<div class="lnb-d3-list">';   // d1 직속 자식 — d2 폴더 레이어 제거
    // 5개 리프에 실제 건수 배지 — 요구사항/작업/회원/공지/권한설정(=관리 대상 회원 수)
    static $countMap = ['requirements'=>'requirement', 'todolist'=>'todo', 'member'=>'member', 'notification'=>'notification', 'documents'=>'document', 'authz'=>'member'];
    foreach ($visible as $it) {
      $isActive = ($it['match'] === $active);
      $cls = 'lnb-d3' . ($isActive ? ' active' : '');
      $d3NameKey = 'd3:' . $it['match'];
      $editBtn = $isAdm ? '<span class="lnb-edit" title="메뉴명 수정" onclick="event.preventDefault();event.stopPropagation();lnbEditName(\'' . htmlspecialchars($d3NameKey, ENT_QUOTES) . '\');return false;">✏️</span>' : '';
      $badge = '';
      if (isset($countMap[$it['match']])) {
        $tbl = $countMap[$it['match']]; $rc = 0; $pdo = auth_db();
        if ($pdo) { try { $rc = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn(); } catch (Throwable $e) {} }
        $idAttr = ($it['match'] === 'requirements') ? ' id="navCount"' : '';   // 기존 #navCount 호환 유지
        $badge = '<span class="badge"' . $idAttr . '>' . $rc . '</span>';
      }
      if ($it['perm'] === 'authz' && !$isAdm) {
        echo '<a class="' . $cls . '" href="javascript:void(0)" onclick="alert(\'접근권한이 없습니다.\');return false;">'
           . '<span class="ico">' . $it['ico'] . '</span>'
           . '<span class="lbl" data-name-key="' . htmlspecialchars($d3NameKey) . '">' . htmlspecialchars($it['label']) . '</span>'
           . $badge . $editBtn . '</a>';
        continue;
      }
      $targetAttr = !empty($it['target']) ? ' target="' . htmlspecialchars($it['target']) . '" rel="noopener"' : '';
      echo '<a class="' . $cls . '" href="' . htmlspecialchars($it['href']) . '"' . $targetAttr . '>'
         . '<span class="ico">' . $it['ico'] . '</span>'
         . '<span class="lbl" data-name-key="' . htmlspecialchars($d3NameKey) . '">' . htmlspecialchars($it['label']) . '</span>'
         . $badge . $editBtn . '</a>';
    }
    echo '</div></div>';   // d3-list, d1
  }
  echo '</nav>';
  // QR 코드 — LNB 최하단(회원명 영역 위). 클릭 시 모달로 확대 + 하단 공유 버튼
  $share = '<div class="qr-share">'
    . '<button type="button" class="sh-btn sh-x" title="X 공유" onclick="qrShare(\'x\')">X</button>'
    . '<button type="button" class="sh-btn sh-meta" title="Facebook 공유" onclick="qrShare(\'meta\')">f</button>'
    . '<button type="button" class="sh-btn sh-insta" title="Instagram 공유" onclick="qrShare(\'insta\')">IG</button>'
    . '<button type="button" class="sh-btn sh-line" title="LINE 공유" onclick="qrShare(\'line\')">L</button>'
    . '<button type="button" class="sh-btn sh-kakao" title="KakaoTalk 공유" onclick="qrShare(\'kakao\')">K</button>'
    . '<button type="button" class="sh-btn sh-google" title="Gmail 공유" onclick="qrShare(\'google\')">G</button>'
    . '</div>';
  echo <<<QR
<div class="lnb-qr"><div class="lnb-qr-title">IPPEO APP</div><img src="/qr.png" alt="QR 코드" class="lnb-qr-img" title="QR 코드 크게 보기" onclick="openQrModal()"></div>
<div class="qr-modal" id="qrModal" onclick="if(event.target===this)closeQrModal()">
  <div class="qr-modal-box"><button type="button" class="qr-modal-close" onclick="closeQrModal()" title="닫기">×</button><div class="qr-modal-title">IPPEO APP 다운로드</div><img src="/qr.png" alt="QR 코드">{$share}</div>
</div>
<style>
.lnb-qr{padding:12px 16px 0;margin-bottom:18px;display:flex;flex-direction:column;align-items:center;gap:8px;}
.lnb-qr-title{font-size:12.5px;font-weight:800;letter-spacing:.04em;color:var(--ink,#1b2433);}
.lnb-qr-img{width:104px;height:104px;border-radius:10px;border:1px solid var(--line,#e6e9f0);background:#fff;padding:6px;cursor:pointer;transition:.15s;box-sizing:border-box;}
.lnb-qr-img:hover{box-shadow:0 6px 16px rgba(20,30,60,.18);transform:translateY(-1px);}
.qr-share{display:flex;flex-wrap:wrap;justify-content:center;gap:7px;}
.sh-btn{width:30px;height:30px;border-radius:50%;border:none;cursor:pointer;font-size:13px;font-weight:800;color:#fff;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0;font-family:inherit;transition:.15s;}
.sh-btn:hover{filter:brightness(.92);transform:translateY(-1px);}
.sh-x{background:#000;}
.sh-meta{background:#1877F2;}
.sh-insta{background:radial-gradient(circle at 30% 110%,#fdf497 5%,#fd5949 45%,#d6249f 65%,#285AEB 95%);font-size:11px;}
.sh-line{background:#06C755;}
.sh-kakao{background:#FEE500;color:#3C1E1E;}
.sh-google{background:#EA4335;}
.qr-modal-box .qr-share{margin-top:18px;}
.qr-modal{position:fixed;inset:0;background:rgba(15,20,35,.62);display:none;align-items:center;justify-content:center;z-index:10000;}
.qr-modal.show{display:flex;}
.qr-modal-box{position:relative;background:#fff;padding:26px;border-radius:16px;box-shadow:0 24px 70px rgba(0,0,0,.4);}
.qr-modal-title{font-size:25px;font-weight:800;text-align:center;margin:0 0 14px;color:var(--ink,#1b2433);}
.qr-modal-box img{display:block;width:min(80vw,440px);height:auto;}
.qr-modal-close{position:absolute;top:8px;right:14px;border:none;background:transparent;font-size:28px;line-height:1;cursor:pointer;color:#888;}
.qr-modal-close:hover{color:#333;}
</style>
<script>(function(){if(window.__qrBound)return;window.__qrBound=1;
  window.openQrModal=function(){var m=document.getElementById('qrModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}};
  window.closeQrModal=function(){var m=document.getElementById('qrModal');if(m){m.classList.remove('show');document.body.style.overflow='';}};
  document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.getElementById('qrModal');if(m&&m.classList.contains('show'))closeQrModal();}});
  var QR_SHARE_URL = window.location.origin;   // 공유 링크(필요 시 앱 다운로드 URL로 변경)
  var QR_SHARE_TEXT = 'IPPEO APP';
  function qrCopy(){ var v=QR_SHARE_URL; if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(v).then(function(){alert('링크가 복사되었습니다.\\n'+v);},function(){window.prompt('아래 링크를 복사하세요',v);}); } else { window.prompt('아래 링크를 복사하세요',v); } }
  window.qrShare=function(p){
    var u=encodeURIComponent(QR_SHARE_URL), t=encodeURIComponent(QR_SHARE_TEXT), url='';
    if(p==='x') url='https://twitter.com/intent/tweet?text='+t+'&url='+u;
    else if(p==='meta') url='https://www.facebook.com/sharer/sharer.php?u='+u;
    else if(p==='line') url='https://social-plugins.line.me/lineit/share?url='+u;
    else if(p==='google') url='https://mail.google.com/mail/?view=cm&fs=1&su='+t+'&body='+u;
    else { /* Instagram·KakaoTalk: 웹 공유 URL 미지원 → 네이티브 공유 또는 링크 복사 */
      if(navigator.share){ navigator.share({title:QR_SHARE_TEXT,url:QR_SHARE_URL}).catch(function(){}); } else { qrCopy(); }
      return;
    }
    window.open(url,'_blank','noopener,noreferrer,width=600,height=640');
  };
})();</script>
QR;
  // LNB 하단 고정 프로필(아바타/이름/부서/로그아웃) — flex:1인 .lnb-tree가 위 공간을 채워 자동으로 맨 아래에 위치
  echo '<div class="lnb-bottom-profile">';
  auth_profile();
  echo '</div>';
  // d1 accordion 토글 + 상태 영속화 + LNB 메뉴명 편집(localStorage `lnb_names`) — 전 페이지 공통(중복 바인딩 방지)
  // 2-Depth 구조 + 최초 진입 시 모두 닫힘 + 클릭 시 다른 d1 자동 닫힘(accordion)
  echo "<script>(function(){if(window.__lnbBound)return;window.__lnbBound=1;
    function load(){try{return JSON.parse(localStorage.getItem('lnb_open')||'{}')}catch(e){return{}}}
    function save(o){try{localStorage.setItem('lnb_open',JSON.stringify(o))}catch(e){}}
    window.lnbToggleD1=function(btn){
      var d1=btn.closest('.lnb-d1');if(!d1)return;
      var willOpen=!d1.classList.contains('open');
      document.querySelectorAll('.lnb-d1').forEach(function(x){x.classList.remove('open');});   // 아코디언: 모두 닫고
      if(willOpen) d1.classList.add('open');                                                    // 클릭한 것만 (열림이면)
      var o={}; document.querySelectorAll('.lnb-d1').forEach(function(x){o[x.dataset.key]=x.classList.contains('open');});
      save(o);
    };
    // 상태 복원: localStorage 우선.
    var stored=load();
    document.querySelectorAll('.lnb-d1').forEach(function(d1){
      var k=d1.dataset.key; if(k && (k in stored)) d1.classList.toggle('open',!!stored[k]);
    });
    // 현재 페이지(active 메뉴)가 속한 그룹은 항상 펼침
    var __act=document.querySelector('.lnb-tree .lnb-d3.active');
    if(__act){ var __ad1=__act.closest('.lnb-d1'); if(__ad1) __ad1.classList.add('open'); }
    /* ===== LNB 메뉴명 편집(사용자별, localStorage `lnb_names`) =====
     *   - 각 라벨 span: data-name-key 보유. 같은 키의 페이지 타이틀(data-lnb-title)도 함께 적용.
     *   - 빈 입력 시 해당 키 제거(원래 라벨로 복귀). */
    function loadNames(){try{return JSON.parse(localStorage.getItem('lnb_names')||'{}')}catch(e){return{}}}
    function saveNames(o){try{localStorage.setItem('lnb_names',JSON.stringify(o))}catch(e){}}
    window.lnbResolveName=function(key){var n=loadNames(); return n[key]||null;};   // 페이지 JS가 직접 조회용
    window.lnbApplyNames=function(){
      var names=loadNames();
      document.querySelectorAll('[data-name-key]').forEach(function(el){
        var k=el.dataset.nameKey; if(!el.dataset.nameOrig) el.dataset.nameOrig=el.textContent;
        el.textContent = names[k] || el.dataset.nameOrig;
      });
      document.querySelectorAll('[data-lnb-title]').forEach(function(el){
        var k='d3:'+el.dataset.lnbTitle; if(!el.dataset.lnbOrig) el.dataset.lnbOrig=el.textContent;
        el.textContent = names[k] || el.dataset.lnbOrig;
      });
    };
    window.lnbEditName=function(key){
      var names=loadNames();
      var sample=document.querySelector('[data-name-key=\"'+key+'\"]');
      var cur=names[key] || (sample?(sample.dataset.nameOrig||sample.textContent):'');
      var v=prompt('새 메뉴명을 입력하세요 (빈 칸 = 원래대로):', cur);
      if(v===null) return;
      v=v.trim();
      if(v==='' || v===(sample?sample.dataset.nameOrig:'')){ delete names[key]; }
      else { names[key]=v; }
      saveNames(names); lnbApplyNames();
    };
    lnbApplyNames();   // 1차: LNB 라벨 즉시 적용(스크립트는 nav 직후라 nav 내부 요소만 보임)
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', lnbApplyNames);   // 2차: 페이지 본문의 [data-lnb-title](h1/breadcrumb) 적용
    // LNB 상단 브랜드 영역 세로크기/라인을 상단바(.topbar)와 동일하게 동기화
    function __syncBrand(){ var tb=document.querySelector('.topbar'); var br=document.querySelector('.lnb-brand'); if(tb&&br&&tb.offsetHeight>0){ br.style.boxSizing='border-box'; br.style.height=tb.offsetHeight+'px'; } }
    __syncBrand();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', __syncBrand);
    window.addEventListener('load', __syncBrand);
    window.addEventListener('resize', __syncBrand);
  })();</script>";
}

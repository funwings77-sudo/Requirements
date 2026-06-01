<?php
/* =====================================================================
   로그인 (member 테이블: 이름 + 휴대폰번호) + 로그아웃
   - 휴대폰번호는 숫자만 추출해 비교(하이픈/공백 무관)
   - 성공 시 세션에 user 저장 후 요구사항 목록으로 이동
   - ?logout=1 : 세션 파기 후 로그인 화면
   진입점: http://localhost:8000/login/login.php
   BOM 없는 UTF-8.
   ===================================================================== */
require __DIR__ . '/../auth.php';

$DB = [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '3306',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : '',
  'name' => getenv('DB_NAME') ?: 'medihim',
];

/* 로그아웃 */
if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
  header('Location: /login/login.php');
  exit;
}

/* 이미 로그인 → 접근 가능한 첫 메뉴로 */
if (auth_check()) { header('Location: ' . auth_first_accessible()); exit; }

$error = '';
$name_in = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name_in = trim($_POST['name'] ?? '');
  $phone_digits = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
  if ($name_in === '' || $phone_digits === '') {
    $error = '이름과 휴대폰번호를 모두 입력하세요.';
  } else {
    try {
      $dsn = "mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4";
      $pdo = new PDO($dsn, $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
      $st = $pdo->prepare("SELECT id, name, dept, email, phone, status FROM member WHERE name = ?");
      $st->execute([$name_in]);
      $matched = null;
      foreach ($st->fetchAll() as $row) {
        if (preg_replace('/\D+/', '', (string)$row['phone']) === $phone_digits) { $matched = $row; break; }
      }
      if ($matched && ($matched['status'] ?? '') === '이용중지') {
        $error = '이용이 중지된 계정입니다. 관리자에게 문의하세요.';
      } elseif ($matched) {
        session_regenerate_id(true);   // 세션 고정 공격 방지
        $_SESSION['user'] = ['id' => (int)$matched['id'], 'name' => $matched['name'], 'dept' => $matched['dept'], 'email' => $matched['email']];
        // 로그인 후 첫 화면: 요구사항 목록(접속 권한 없으면 접근 가능한 첫 메뉴)
        $dest = can('requirements', 'access') ? '/requirements/requirements.php' : auth_first_accessible();
        header('Location: ' . $dest);
        exit;
      } else {
        $error = '이름 또는 휴대폰번호가 일치하지 않습니다.';
      }
    } catch (Throwable $e) {
      $error = 'DB 연결 실패: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>메디힘 로그인</title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
  body{ display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; background:var(--bg,#f4f6fb); }
  .login-card{ width:100%; max-width:380px; background:var(--panel,#fff); border:1px solid var(--line,#e6e9f0);
    border-radius:16px; box-shadow:0 12px 44px rgba(20,30,60,.12); padding:34px 30px; }
  .login-brand{ display:flex; align-items:center; gap:12px; }
  .login-brand .logo-badge{ width:42px; height:42px; border-radius:12px; background:var(--brand,#2f6bff); color:#fff;
    font-weight:800; font-size:20px; display:flex; align-items:center; justify-content:center; }
  .login-brand .bt{ font-size:18px; font-weight:800; color:var(--ink,#1b2433); line-height:1.2; }
  .login-brand .bs{ font-size:12px; color:var(--sub,#7b8395); }
  .login-sub{ color:var(--sub,#7b8395); font-size:13px; margin:16px 0 6px; }
  .login-card label{ display:block; font-size:12.5px; font-weight:700; color:#4a5363; margin:16px 0 6px; }
  .login-card input{ width:100%; box-sizing:border-box; padding:11px 13px; border:1px solid var(--line,#e6e9f0);
    border-radius:10px; font-size:14px; }
  .login-card input:focus{ outline:none; border-color:var(--brand,#2f6bff); box-shadow:0 0 0 3px var(--brand-soft,#e8efff); }
  .login-btn{ width:100%; margin-top:22px; padding:12px; border:none; border-radius:10px; background:var(--brand,#2f6bff);
    color:#fff; font-size:15px; font-weight:700; cursor:pointer; }
  .login-btn:hover{ filter:brightness(.96); }
  .login-err{ margin-top:16px; background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; border-radius:9px;
    padding:10px 12px; font-size:13px; font-weight:600; }
  .login-hint{ margin-top:18px; text-align:center; color:var(--sub,#9aa3b2); font-size:12px; }
</style>
</head>
<body>
  <form class="login-card" method="post" action="login.php">
    <div class="login-brand">
      <span class="logo-badge">M</span>
      <div><div class="bt">메디힘</div><div class="bs">Requirements</div></div>
    </div>
    <div class="login-sub">등록된 회원만 로그인할 수 있습니다.</div>
    <?php if ($error !== ''): ?><div class="login-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label for="name">이름</label>
    <input id="name" name="name" type="text" autocomplete="username" placeholder="이름을 입력해주세요." value="<?= htmlspecialchars($name_in) ?>" autofocus required>
    <label for="phone">휴대폰번호</label>
    <input id="phone" name="phone" type="text" autocomplete="current-password" inputmode="numeric" placeholder="숫자만 입력하세요. (예: 01012345678)" required>
    <button class="login-btn" type="submit">로그인</button>
    <div class="login-hint">이름과 등록된 휴대폰번호로 로그인하세요</div>
  </form>
</body>
</html>

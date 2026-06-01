<?php
/* 루트 진입점 — 로그인 상태면 첫 접근 가능 메뉴로, 아니면 로그인 페이지로 이동 */
require __DIR__ . '/auth.php';
if (function_exists('auth_check') && auth_check()) {
  // 로그인 상태: 요구사항 목록 우선(접속 권한 없으면 접근 가능한 첫 메뉴)
  header('Location: ' . (can('requirements','access') ? '/requirements/requirements.php' : auth_first_accessible()));
} else {
  header('Location: /login/login.php');
}
exit;

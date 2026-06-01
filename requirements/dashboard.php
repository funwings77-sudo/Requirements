<?php
/* 요구사항 대시보드는 requirements.php 안의 '대시보드' 탭으로 통합됨.
   기존 링크/북마크 호환을 위해 통합 페이지의 대시보드 탭으로 리다이렉트. */
require __DIR__ . '/../auth.php';
require_login();
require_perm('dashboard', 'access');
header('Location: /requirements/requirements.php?tab=dash');
exit;

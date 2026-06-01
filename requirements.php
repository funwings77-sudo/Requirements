<?php
/* 구 주소 호환 리다이렉트: /requirements.php  ->  /requirements/requirements.php
   (2026-05-27 파일이 requirements/ 하위로 이동됨. 기존 북마크 호환용) */
$qs = (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /requirements/requirements.php' . $qs, true, 301);
exit
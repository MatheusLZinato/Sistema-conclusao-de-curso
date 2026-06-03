<?php
// Entry point: dispara auto-setup (via bootstrap) e serve o SPA
define('SKIP_JSON_HEADER', true);
require_once dirname(__DIR__) . '/backend/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');

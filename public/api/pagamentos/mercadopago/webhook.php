<?php
require_once dirname(__DIR__,4)."/backend/bootstrap.php";
$raw = file_get_contents("php://input");
$sig = $_SERVER["HTTP_X_SIGNATURE"] ?? "";
(new App\Controllers\PagamentoController())->webhook($raw, $sig);

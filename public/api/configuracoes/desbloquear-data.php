<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\ConfiguracaoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="DELETE") Response::error("Método inválido.",405);
AdminOnly::check();
$d = $_GET["data"] ?? "";
if(!$d) Response::error("data obrigatória.");
(new ConfiguracaoController())->desbloquearData($d);

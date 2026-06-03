<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\PedidoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="GET") Response::error("Método inválido.",405);
AdminOnly::check();
$f = [];
if(!empty($_GET["status"])) $f["status"] = $_GET["status"];
if(!empty($_GET["mes"]))    $f["mes"]    = $_GET["mes"];
(new PedidoController())->listar($f);

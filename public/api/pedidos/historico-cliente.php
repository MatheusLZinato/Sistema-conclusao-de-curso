<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\PedidoController;
if($_SERVER["REQUEST_METHOD"]!=="GET") App\Helpers\Response::error("Método inválido.",405);
$p = Auth::check();
$f = ["usuario_id" => (int)$p["sub"]];
if(!empty($_GET["mes"])) $f["mes"] = $_GET["mes"];
(new PedidoController())->listar($f);

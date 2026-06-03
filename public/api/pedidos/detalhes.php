<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\PedidoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="GET") Response::error("Método inválido.",405);
$payload = Auth::check();
$id = $_GET["id"] ?? "";
if(!$id) Response::error("ID obrigatório.");
$ctrl = new PedidoController();
$ped = $ctrl->detalheRaw($id);
if(!$ped) Response::notFound();
if($payload["perfil"] === "cliente" && (int)($ped["usuario_id"]??-1) !== (int)$payload["sub"]) Response::forbidden();
$ctrl->detalhe($id);

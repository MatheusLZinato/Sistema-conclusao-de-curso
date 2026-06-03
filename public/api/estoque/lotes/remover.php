<?php
require_once dirname(__DIR__,4)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\EstoqueController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="DELETE") Response::error("Método inválido.",405);
AdminOnly::check();
$id = $_GET["id"] ?? "";
if(!$id) Response::error("ID obrigatório.");
(new EstoqueController())->removerLote($id);

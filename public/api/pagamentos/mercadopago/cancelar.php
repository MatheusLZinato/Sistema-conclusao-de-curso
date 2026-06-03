<?php
require_once dirname(__DIR__,4)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\PagamentoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="DELETE") Response::error("Método inválido.",405);
AdminOnly::check();
$id = $_GET["mp_payment_id"] ?? "";
if(!$id) Response::error("mp_payment_id obrigatório.");
(new PagamentoController())->cancelar($id);

<?php
require_once dirname(__DIR__,4)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\PagamentoController;
use App\Helpers\Response;
Auth::check();
$id = $_GET["mp_payment_id"] ?? "";
if(!$id) Response::error("mp_payment_id obrigatório.");
(new PagamentoController())->consultarStatus($id);

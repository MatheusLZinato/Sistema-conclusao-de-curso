<?php
require_once dirname(__DIR__,4)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\PagamentoController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="POST") Response::error("Método inválido.",405);
Auth::check();
(new PagamentoController())->criarPix(Validator::body());

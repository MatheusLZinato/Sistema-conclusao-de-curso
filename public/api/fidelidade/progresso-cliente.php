<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\FidelidadeController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="GET") Response::error("Método inválido.",405);
$p = Auth::check();
(new FidelidadeController())->progressoCliente((int)$p["sub"]);

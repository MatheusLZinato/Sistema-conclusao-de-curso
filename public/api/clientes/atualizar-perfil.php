<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\ClienteController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="PUT") Response::error("Método inválido.",405);
$p = Auth::check();
(new ClienteController())->atualizar((int)$p["sub"], Validator::body());

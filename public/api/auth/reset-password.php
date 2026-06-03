<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Controllers\AuthController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="POST") Response::error("Método inválido.",405);
(new AuthController())->resetPassword(Validator::body());

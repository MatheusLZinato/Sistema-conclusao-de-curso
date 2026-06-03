<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\RateLimit;
use App\Controllers\AuthController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="POST") Response::error("Método inválido.",405);
RateLimit::login();
(new AuthController())->login(Validator::body());

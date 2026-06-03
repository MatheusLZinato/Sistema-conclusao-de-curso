<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\CategoriaController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="POST") Response::error("Método inválido.",405);
AdminOnly::check();
(new CategoriaController())->criar(Validator::body());

<?php
define("SKIP_JSON_HEADER", true);
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
header("Content-Type: application/json; charset=utf-8");
use App\Middleware\AdminOnly;
use App\Controllers\UploadController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="POST") Response::error("Método inválido.",405);
AdminOnly::check();
(new UploadController())->imagem($_GET["prefix"]??"prod");

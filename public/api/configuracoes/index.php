<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\ConfiguracaoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="GET") Response::error("Método inválido.",405);
AdminOnly::check();
(new ConfiguracaoController())->listar();

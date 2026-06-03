<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\ProdutoController;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="PUT") Response::error("Método inválido.",405);
AdminOnly::check();
$id = (int)($_GET["id"] ?? 0);
if(!$id) Response::error("ID obrigatório.");
(new ProdutoController())->editar($id, Validator::body());

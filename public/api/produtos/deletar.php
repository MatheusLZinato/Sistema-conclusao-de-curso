<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\ProdutoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="DELETE") Response::error("Método inválido.",405);
AdminOnly::strict();
$id = (int)($_GET["id"] ?? 0);
if(!$id) Response::error("ID obrigatório.");
(new ProdutoController())->deletar($id);

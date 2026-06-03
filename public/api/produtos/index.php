<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\ProdutoController;
use App\Helpers\Response;
if($_SERVER["REQUEST_METHOD"]!=="GET") Response::error("Método inválido.",405);
$p = Auth::optional();
$isAdmin = in_array($p["perfil"]??"",["admin","cozinha"]);
(new ProdutoController())->listar($_GET["categoria"]??null, $isAdmin);

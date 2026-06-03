<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\ProdutoController;
use App\Helpers\{Response,Validator};
AdminOnly::check();
$b = Validator::body();
(new ProdutoController())->reordenar((int)($b["id"]??0), $b["dir"]??"up");

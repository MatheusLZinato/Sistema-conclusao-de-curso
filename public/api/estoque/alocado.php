<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\EstoqueController;
AdminOnly::check();
(new EstoqueController())->alocado();

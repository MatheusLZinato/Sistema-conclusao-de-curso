<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Controllers\AuthController;
$p = Auth::check();
(new AuthController())->refresh($p);

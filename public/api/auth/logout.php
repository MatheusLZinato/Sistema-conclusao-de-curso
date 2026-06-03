<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\Auth;
use App\Helpers\Response;
Auth::check();
Response::ok(null,"Logout realizado.");

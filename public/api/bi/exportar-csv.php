<?php
define("SKIP_JSON_HEADER", true);
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\BIController;
AdminOnly::check();
(new BIController())->exportarCSV($_GET["mes"]??null);

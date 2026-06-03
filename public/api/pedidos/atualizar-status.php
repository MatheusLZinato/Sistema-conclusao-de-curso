<?php
require_once dirname(__DIR__,3)."/backend/bootstrap.php";
use App\Middleware\AdminOnly;
use App\Controllers\PedidoController;
use App\Models\Pedido;
use App\Helpers\{Response,Validator};
if($_SERVER["REQUEST_METHOD"]!=="PUT") Response::error("Método inválido.",405);
AdminOnly::check();
$b = Validator::body();
$id = $b["pedido_id"] ?? $_GET["id"] ?? "";
if(!$id) Response::error("pedido_id obrigatório.");
if(isset($b["resposta_admin"])) { (new Pedido())->salvarResposta($id, $b["resposta_admin"]); Response::ok(null,"Mensagem enviada."); }
if(!empty($b["receber_saldo"])) { (new PedidoController())->receberSaldo($id); }
$status = $b["status"] ?? "";
if(!$status) Response::error("status obrigatório.");
(new PedidoController())->atualizarStatus($id, $status);

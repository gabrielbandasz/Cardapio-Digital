<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
header('Content-Type: application/json; charset=utf-8');
$numero = trim($_GET['numero'] ?? '');
if (!$numero) { http_response_code(400); echo json_encode(['erro'=>'Número inválido']); exit; }
$stmt = $pdo->prepare("SELECT numero, nome_cliente, status, total, created_at, tipo_entrega FROM pedidos WHERE numero = ?");
$stmt->execute([$numero]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); echo json_encode(['erro'=>'Pedido não encontrado']); exit; }
echo json_encode([
    'numero' => $p['numero'],
    'status' => $p['status'],
    'label'  => status_label($p['status']),
    'emoji'  => status_emoji($p['status']),
    'nome'   => $p['nome_cliente'],
    'total'  => formatar_dinheiro((float)$p['total']),
    'criado' => date('d/m H:i', strtotime($p['created_at'])),
    'entrega'=> $p['tipo_entrega'],
]);

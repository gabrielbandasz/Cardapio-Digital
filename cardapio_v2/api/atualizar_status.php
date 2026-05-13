<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!admin_logado()) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$body = json_decode(file_get_contents('php://input'), true);
$id = (int)($body['id'] ?? 0);
$status = $body['status'] ?? '';
$validos = ['novo','confirmado','preparo','pronto','entregue','cancelado'];
if (!$id || !in_array($status, $validos)) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$pdo->prepare("UPDATE pedidos SET status=? WHERE id=?")->execute([$status, $id]);
echo json_encode(['ok'=>true]);

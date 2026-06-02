<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!admin_logado()) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$body = json_decode(file_get_contents('php://input'), true);
$campo = $body['campo'] ?? '';
$valor = (int)($body['valor'] ?? 0);
$camposPermitidos = ['aberto','modo_pico','promo_ativa'];
if (!in_array($campo, $camposPermitidos)) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$pdo->prepare("UPDATE config SET $campo = ? WHERE id = 1")->execute([$valor]);
echo json_encode(['ok'=>true, 'campo'=>$campo, 'valor'=>$valor]);

<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$body = json_decode(file_get_contents('php://input'), true);
$codigo = strtoupper(trim($body['codigo'] ?? ''));
$subtotal = (float)($body['subtotal'] ?? 0);

if (!$codigo) { echo json_encode(['ok'=>false,'erro'=>'Código inválido']); exit; }

$stmt = $pdo->prepare("SELECT * FROM cupons WHERE codigo=? AND ativo=1");
$stmt->execute([$codigo]); $cupom = $stmt->fetch();

if (!$cupom) { echo json_encode(['ok'=>false,'erro'=>'Cupom não encontrado ou inativo']); exit; }
if ($cupom['valido_ate'] && strtotime($cupom['valido_ate']) < time()) { echo json_encode(['ok'=>false,'erro'=>'Cupom expirado']); exit; }
if ($cupom['uso_maximo'] && $cupom['uso_atual'] >= $cupom['uso_maximo']) { echo json_encode(['ok'=>false,'erro'=>'Cupom esgotado']); exit; }

$desconto = $cupom['tipo'] === 'percentual' ? round($subtotal * $cupom['valor'] / 100, 2) : min((float)$cupom['valor'], $subtotal);

echo json_encode(['ok'=>true,'desconto'=>$desconto,'tipo'=>$cupom['tipo'],'valor'=>$cupom['valor'],'descricao'=>$cupom['descricao']]);

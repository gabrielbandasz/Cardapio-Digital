<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
$produtoId = (int)($_GET['produto_id'] ?? 0);
if (!$produtoId) { echo json_encode([]); exit; }
// Produtos que aparecem junto com este nas mesmas ordens
$stmt = $pdo->prepare("
    SELECT pi2.produto_id, p.nome, p.emoji, p.preco, COUNT(*) AS freq
    FROM pedido_itens pi1
    JOIN pedido_itens pi2 ON pi2.pedido_id = pi1.pedido_id AND pi2.produto_id != pi1.produto_id
    JOIN produtos p ON p.id = pi2.produto_id AND p.disponivel = 1
    WHERE pi1.produto_id = ?
    GROUP BY pi2.produto_id ORDER BY freq DESC LIMIT 3
");
$stmt->execute([$produtoId]);
$rows = $stmt->fetchAll();
if (empty($rows)) {
    // fallback: mais vendidos
    $stmt2 = $pdo->prepare("SELECT id AS produto_id, nome, emoji, preco FROM produtos WHERE disponivel=1 AND id != ? ORDER BY total_vendido DESC LIMIT 3");
    $stmt2->execute([$produtoId]);
    $rows = $stmt2->fetchAll();
}
echo json_encode($rows);

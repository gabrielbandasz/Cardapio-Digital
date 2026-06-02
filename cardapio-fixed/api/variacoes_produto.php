<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$produtoId = (int)($_GET['produto_id'] ?? 0);
if (!$produtoId) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT * FROM produto_variacoes WHERE produto_id=? AND disponivel=1 ORDER BY grupo,id");
$stmt->execute([$produtoId]);
$rows = $stmt->fetchAll();

$grupos = [];
foreach ($rows as $r) {
    $grupos[$r['grupo']][] = $r;
}
echo json_encode($grupos);

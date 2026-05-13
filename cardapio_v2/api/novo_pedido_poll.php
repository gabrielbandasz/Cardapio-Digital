<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!admin_logado()) { http_response_code(403); echo json_encode(['count'=>0]); exit; }
$desde = $_GET['desde'] ?? date('Y-m-d H:i:s', time() - 30);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE status='novo' AND created_at > ?");
$stmt->execute([$desde]);
echo json_encode(['count' => (int)$stmt->fetchColumn(), 'ts' => date('Y-m-d H:i:s')]);

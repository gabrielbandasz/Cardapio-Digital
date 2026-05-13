<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$config = $pdo->query("SELECT tempo_preparo_base, tempo_preparo_por_pedido, modo_pico, pico_tempo FROM config WHERE id=1")->fetch();
$pedidosAtivos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo') AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)")->fetchColumn();

$base = (int)$config['tempo_preparo_base'];
$extra = $pedidosAtivos * (int)$config['tempo_preparo_por_pedido'];
$total = $base + $extra;

if ($config['modo_pico']) {
    echo json_encode(['minutos'=>$total,'pico'=>true,'label'=>$config['pico_tempo'],'pedidos_ativos'=>$pedidosAtivos]);
} else {
    $min = $total; $max = $total + 15;
    echo json_encode(['minutos'=>$total,'pico'=>false,'label'=>"{$min}–{$max} min",'pedidos_ativos'=>$pedidosAtivos]);
}

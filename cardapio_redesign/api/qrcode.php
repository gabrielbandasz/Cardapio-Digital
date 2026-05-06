<?php
require_once __DIR__ . '/../config/db.php';
$config = $pdo->query("SELECT loja_slug, nome_restaurante FROM config WHERE id=1")->fetch();
$slug = $config['loja_slug'] ?: 'cardapio';
$proto = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$base = $proto . '://' . $_SERVER['HTTP_HOST'];
$url = $base . '/' . $slug;
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($url);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
$img = file_get_contents($qrUrl);
if ($img) { echo $img; } else {
    // fallback: redirect
    header('Location: '.$qrUrl); exit;
}

<?php
/**
 * API Mercado Pago — Gera preferência de pagamento e verifica status
 * Endpoint: POST /api/mercadopago.php?action=criar
 *           GET  /api/mercadopago.php?action=status&pedido_id=X
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$mpToken = $config['mp_access_token'] ?? '';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Criar preferência ──────────────────────────────────────
if ($action === 'criar') {
    if (!$mpToken) {
        echo json_encode(['ok'=>false,'erro'=>'Mercado Pago não configurado.']); exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $pedidoId = (int)($body['pedido_id'] ?? 0);
    $total    = (float)($body['total'] ?? 0);
    $nome     = trim($body['nome'] ?? 'Cliente');
    $numero   = trim($body['numero'] ?? $pedidoId);

    if (!$pedidoId || $total <= 0) {
        echo json_encode(['ok'=>false,'erro'=>'Dados inválidos']); exit;
    }

    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
             . rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');

    $payload = [
        'external_reference' => (string)$pedidoId,
        'items' => [[
            'title'      => 'Pedido #' . $numero . ' — ' . ($config['nome_restaurante'] ?? 'Restaurante'),
            'quantity'   => 1,
            'unit_price' => round($total, 2),
            'currency_id'=> 'BRL',
        ]],
        'payer' => ['name' => $nome],
        'back_urls' => [
            'success' => $baseUrl . '/pedido-confirmado.php?numero=' . $numero . '&mp=ok',
            'failure' => $baseUrl . '/carrinho.php?mp=fail',
            'pending' => $baseUrl . '/status.php?numero=' . $numero,
        ],
        'auto_return'         => 'approved',
        'notification_url'    => $baseUrl . '/api/mercadopago.php?action=webhook',
        'statement_descriptor'=> substr($config['nome_restaurante'] ?? 'Restaurante', 0, 22),
        'expires'             => true,
        'expiration_date_from'=> date('c'),
        'expiration_date_to'  => date('c', strtotime('+2 hours')),
    ];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $mpToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($httpCode !== 201 || empty($data['id'])) {
        error_log('MP erro: ' . $resp);
        echo json_encode(['ok'=>false,'erro'=>'Erro ao criar pagamento. Tente outro método.']); exit;
    }

    // Salvar preference_id no pedido
    $pdo->prepare("UPDATE pedidos SET mp_preference_id=? WHERE id=?")
        ->execute([$data['id'], $pedidoId]);

    echo json_encode([
        'ok'           => true,
        'preference_id'=> $data['id'],
        'init_point'   => $data['init_point'],      // redirect completo
        'sandbox_url'  => $data['sandbox_init_point'] ?? '',
    ]);
    exit;
}

// ── Webhook (notificação MP) ───────────────────────────────
if ($action === 'webhook') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $body['type'] ?? $_GET['type'] ?? '';

    if ($type === 'payment') {
        $paymentId = $body['data']['id'] ?? $_GET['data_id'] ?? null;
        if ($paymentId && $mpToken) {
            $ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mpToken],
                CURLOPT_TIMEOUT        => 10,
            ]);
            $resp = curl_exec($ch); curl_close($ch);
            $pay  = json_decode($resp, true);

            if (!empty($pay['external_reference']) && !empty($pay['status'])) {
                $pedidoId = (int)$pay['external_reference'];
                if ($pay['status'] === 'approved') {
                    $pdo->prepare("UPDATE pedidos SET pagamento_online='aprovado', status=CASE WHEN status='novo' THEN 'confirmado' ELSE status END WHERE id=?")
                        ->execute([$pedidoId]);
                } elseif (in_array($pay['status'], ['rejected','cancelled'])) {
                    $pdo->prepare("UPDATE pedidos SET pagamento_online='recusado' WHERE id=?")
                        ->execute([$pedidoId]);
                }
            }
        }
    }
    http_response_code(200); echo 'OK'; exit;
}

echo json_encode(['ok'=>false,'erro'=>'Ação inválida']);

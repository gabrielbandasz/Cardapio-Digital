<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate_limit.php';
rate_limit('auth_cliente', 10, 60);

$action = $_POST['action'] ?? '';

// ── LOGIN ─────────────────────────────────────────────────
if ($action === 'login') {
    csrf_verify();
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $senha    = $_POST['senha'] ?? '';

    if (strlen($whatsapp) < 10 || !$senha) {
        echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$whatsapp]);
    $cliente = $stmt->fetch();

    if (!$cliente || !password_verify($senha, $cliente['senha_hash'] ?? '')) {
        echo json_encode(['ok' => false, 'erro' => 'WhatsApp ou senha incorretos.']);
        exit;
    }

    $_SESSION['cliente_id']       = $cliente['id'];
    $_SESSION['cliente_nome']     = $cliente['nome'];
    $_SESSION['cliente_whatsapp'] = $cliente['whatsapp'];

    echo json_encode(['ok' => true, 'nome' => $cliente['nome']]);
    exit;
}

// ── CADASTRO ──────────────────────────────────────────────
if ($action === 'cadastro') {
    csrf_verify();
    $nome     = trim($_POST['nome'] ?? '');
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $senha    = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if (!$nome || strlen($whatsapp) < 10 || !$senha) {
        echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos.']);
        exit;
    }
    if (strlen($senha) < 6) {
        echo json_encode(['ok' => false, 'erro' => 'A senha deve ter pelo menos 6 caracteres.']);
        exit;
    }
    if ($senha !== $confirma) {
        echo json_encode(['ok' => false, 'erro' => 'As senhas não coincidem.']);
        exit;
    }

    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$whatsapp]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'erro' => 'Este WhatsApp já tem uma conta. Faça login.']);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO clientes (nome, whatsapp, senha_hash) VALUES (?, ?, ?)")
        ->execute([$nome, $whatsapp, $hash]);

    $id = $pdo->lastInsertId();
    $_SESSION['cliente_id']       = $id;
    $_SESSION['cliente_nome']     = $nome;
    $_SESSION['cliente_whatsapp'] = $whatsapp;

    echo json_encode(['ok' => true, 'nome' => $nome]);
    exit;
}

// ── LOGOUT ────────────────────────────────────────────────
if ($action === 'logout') {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_whatsapp']);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Ação inválida.']);

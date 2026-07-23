<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate_limit.php';
rate_limit('auth_cliente', 15, 60);

// Cadastro de cliente é direto, sem verificação por e-mail (login é por WhatsApp + senha).

$action = $_POST['action'] ?? '';

// ── LOGIN ────────────────────────────────────────────────────────
if ($action === 'login') {
    csrf_verify();
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $senha    = $_POST['senha'] ?? '';

    if (strlen($whatsapp) < 10 || !$senha) {
        echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos.']); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$whatsapp]);
    $cliente = $stmt->fetch();

    if (!$cliente || !password_verify($senha, $cliente['senha_hash'] ?? '')) {
        echo json_encode(['ok' => false, 'erro' => 'WhatsApp ou senha incorretos.']); exit;
    }
    $_SESSION['cliente_id']       = $cliente['id'];
    $_SESSION['cliente_nome']     = $cliente['nome'];
    $_SESSION['cliente_whatsapp'] = $cliente['whatsapp'];
    $_SESSION['cliente_email']    = $cliente['email'] ?? '';
    echo json_encode(['ok' => true, 'nome' => $cliente['nome']]); exit;
}

// ── CADASTRO (direto, sem verificação por e-mail) ─────────────────
if ($action === 'cadastro') {
    csrf_verify();
    $nome     = trim($_POST['nome'] ?? '');
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $endereco = trim($_POST['endereco'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if (!$nome || strlen($whatsapp) < 10 || !$senha)
        { echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos obrigatórios.']); exit; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        { echo json_encode(['ok' => false, 'erro' => 'E-mail inválido.']); exit; }
    if (strlen($senha) < 6)
        { echo json_encode(['ok' => false, 'erro' => 'Senha mínima de 6 caracteres.']); exit; }
    if ($senha !== $confirma)
        { echo json_encode(['ok' => false, 'erro' => 'As senhas não coincidem.']); exit; }

    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$whatsapp]);
    if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'Este WhatsApp já tem uma conta. Faça login.']); exit; }

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'Este e-mail já está em uso. Faça login.']); exit; }
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO clientes (nome, whatsapp, senha_hash, email, email_verificado, endereco) VALUES (?, ?, ?, ?, 0, ?)")
        ->execute([$nome, $whatsapp, $hash, $email ?: null, $endereco]);
    $id = $pdo->lastInsertId();

    $_SESSION['cliente_id']       = $id;
    $_SESSION['cliente_nome']     = $nome;
    $_SESSION['cliente_whatsapp'] = $whatsapp;
    $_SESSION['cliente_email']    = $email;
    echo json_encode(['ok' => true, 'nome' => $nome]); exit;
}

// ── ATUALIZAR PERFIL ─────────────────────────────────────────────
if ($action === 'update_profile') {
    csrf_verify();
    if (empty($_SESSION['cliente_id'])) { echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']); exit; }

    $id       = (int)$_SESSION['cliente_id'];
    $nome     = trim($_POST['nome'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $endereco = trim($_POST['endereco'] ?? '');
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $senha    = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if (!$nome) { echo json_encode(['ok' => false, 'erro' => 'Informe seu nome.']); exit; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'erro' => 'E-mail inválido.']); exit;
    }
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND id != ? LIMIT 1");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'E-mail já em uso.']); exit; }
    }

    $wppFinal = strlen($whatsapp) >= 10 ? $whatsapp : $_SESSION['cliente_whatsapp'];

    if ($senha) {
        if (strlen($senha) < 6) { echo json_encode(['ok' => false, 'erro' => 'Senha muito curta.']); exit; }
        if ($senha !== $confirma) { echo json_encode(['ok' => false, 'erro' => 'As senhas não coincidem.']); exit; }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE clientes SET nome=?, email=?, endereco=?, whatsapp=?, senha_hash=? WHERE id=?")
            ->execute([$nome, $email ?: null, $endereco ?: null, $wppFinal, $hash, $id]);
    } else {
        $pdo->prepare("UPDATE clientes SET nome=?, email=?, endereco=?, whatsapp=? WHERE id=?")
            ->execute([$nome, $email ?: null, $endereco ?: null, $wppFinal, $id]);
    }

    $_SESSION['cliente_nome']  = $nome;
    $_SESSION['cliente_email'] = $email;
    if (strlen($whatsapp) >= 10) $_SESSION['cliente_whatsapp'] = $whatsapp;
    echo json_encode(['ok' => true, 'nome' => $nome]); exit;
}

// ── EXCLUIR CONTA ────────────────────────────────────────────────
if ($action === 'delete_account') {
    csrf_verify();
    if (empty($_SESSION['cliente_id'])) { echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']); exit; }

    $id    = (int)$_SESSION['cliente_id'];
    $senha = $_POST['senha'] ?? '';
    $stmt  = $pdo->prepare("SELECT senha_hash FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $c = $stmt->fetch();

    if (!$c || !password_verify($senha, $c['senha_hash'] ?? '')) {
        echo json_encode(['ok' => false, 'erro' => 'Senha incorreta.']); exit;
    }
    $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
    session_destroy();
    echo json_encode(['ok' => true]); exit;
}

// ── LOGOUT ───────────────────────────────────────────────────────
if ($action === 'logout') {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_whatsapp'], $_SESSION['cliente_email']);
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['ok' => false, 'erro' => 'Ação inválida.']);
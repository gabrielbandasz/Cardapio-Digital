<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate_limit.php';
rate_limit('auth_cliente', 15, 60);

// ══════════════════════════════════════════════════════════════
// CONFIGURAÇÕES DE E-MAIL
// ══════════════════════════════════════════════════════════════
// MODO_DEBUG = true  → o código aparece na tela (para testes)
// MODO_DEBUG = false → envia o e-mail de verdade
define('MODO_DEBUG', true);

// Para usar SMTP (Gmail, etc) em vez do mail() padrão:
// 1. Instale PHPMailer: composer require phpmailer/phpmailer
// 2. Mude USAR_SMTP para true e preencha as configs abaixo
define('USAR_SMTP', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu@gmail.com');
define('SMTP_PASS', 'sua_senha_app');
define('SMTP_FROM', 'seu@gmail.com');
define('SMTP_NAME', 'Seu Restaurante');
// ══════════════════════════════════════════════════════════════

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

// ── ENVIAR CÓDIGO (passo 1 do cadastro) ─────────────────────────
if ($action === 'enviar_codigo') {
    csrf_verify();
    $nome     = trim($_POST['nome'] ?? '');
    $whatsapp = preg_replace('/\D/', '', trim($_POST['whatsapp'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $endereco = trim($_POST['endereco'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if (!$nome || strlen($whatsapp) < 10 || !$email || !$senha)
        { echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos.']); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        { echo json_encode(['ok' => false, 'erro' => 'E-mail inválido.']); exit; }
    if (strlen($senha) < 6)
        { echo json_encode(['ok' => false, 'erro' => 'Senha mínima de 6 caracteres.']); exit; }
    if ($senha !== $confirma)
        { echo json_encode(['ok' => false, 'erro' => 'As senhas não coincidem.']); exit; }

    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$whatsapp]);
    if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'Este WhatsApp já tem uma conta. Faça login.']); exit; }

    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'Este e-mail já está em uso. Faça login.']); exit; }

    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['cad_pendente'] = [
        'nome'     => $nome,
        'whatsapp' => $whatsapp,
        'email'    => $email,
        'endereco' => $endereco,
        'senha'    => password_hash($senha, PASSWORD_BCRYPT),
        'codigo'   => $codigo,
        'expira'   => time() + 600,
    ];

    if (MODO_DEBUG) {
        echo json_encode(['ok' => true, 'debug_codigo' => $codigo]); exit;
    }

    $enviado = enviarCodigoEmail($email, $nome, $codigo);
    if (!$enviado) {
        error_log("Falha ao enviar email de verificacao para: {$email}");
        echo json_encode(['ok' => false, 'erro' => 'Não foi possível enviar o e-mail. Ative MODO_DEBUG para testar.']); exit;
    }
    echo json_encode(['ok' => true]); exit;
}

// ── REENVIAR CÓDIGO ──────────────────────────────────────────────
if ($action === 'reenviar_codigo') {
    csrf_verify();
    $pend = $_SESSION['cad_pendente'] ?? null;
    if (!$pend) { echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Preencha o formulário novamente.']); exit; }

    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['cad_pendente']['codigo'] = $codigo;
    $_SESSION['cad_pendente']['expira'] = time() + 600;

    if (MODO_DEBUG) {
        echo json_encode(['ok' => true, 'debug_codigo' => $codigo]); exit;
    }
    $enviado = enviarCodigoEmail($pend['email'], $pend['nome'], $codigo);
    echo json_encode(['ok' => $enviado, 'erro' => $enviado ? null : 'Erro ao reenviar.']); exit;
}

// ── CADASTRO (confirmar código e criar conta) ────────────────────
if ($action === 'cadastro') {
    csrf_verify();
    $codigo = trim($_POST['codigo'] ?? '');
    $pend   = $_SESSION['cad_pendente'] ?? null;

    if (!$pend) { echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Preencha o formulário novamente.']); exit; }
    if (time() > $pend['expira']) {
        unset($_SESSION['cad_pendente']);
        echo json_encode(['ok' => false, 'erro' => 'Código expirado. Solicite um novo.']); exit;
    }
    if (!hash_equals($pend['codigo'], $codigo)) {
        echo json_encode(['ok' => false, 'erro' => 'Código incorreto. Verifique e tente novamente.']); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->execute([$pend['whatsapp']]);
    if ($stmt->fetch()) { echo json_encode(['ok' => false, 'erro' => 'Este WhatsApp já tem uma conta.']); exit; }

    $pdo->prepare("INSERT INTO clientes (nome, whatsapp, senha_hash, email, email_verificado, endereco) VALUES (?, ?, ?, ?, 1, ?)")
        ->execute([$pend['nome'], $pend['whatsapp'], $pend['senha'], $pend['email'], $pend['endereco']]);
    $id = $pdo->lastInsertId();
    unset($_SESSION['cad_pendente']);

    $_SESSION['cliente_id']       = $id;
    $_SESSION['cliente_nome']     = $pend['nome'];
    $_SESSION['cliente_whatsapp'] = $pend['whatsapp'];
    $_SESSION['cliente_email']    = $pend['email'];
    echo json_encode(['ok' => true, 'nome' => $pend['nome']]); exit;
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

// ── Helper: enviar e-mail ────────────────────────────────────────
function enviarCodigoEmail(string $email, string $nome, string $codigo): bool {
    if (USAR_SMTP) {
        // Descomente abaixo se tiver PHPMailer instalado (composer require phpmailer/phpmailer)
        /*
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->addAddress($email, $nome);
            $mail->isHTML(true);
            $mail->Subject = 'Seu código de verificação';
            $mail->Body    = gerarHtmlCodigo($nome, $codigo);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
            return false;
        }
        */
        return false;
    }

    // mail() padrão do PHP
    $host     = $_SERVER['HTTP_HOST'] ?? 'seusite.com';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@{$host}\r\n";
    return @mail($email, 'Seu código de verificação', gerarHtmlCodigo($nome, $codigo), $headers);
}

function gerarHtmlCodigo(string $nome, string $codigo): string {
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:20px;">
<div style="max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;">
  <h2 style="margin:0 0 8px;color:#111;">Verificação de e-mail</h2>
  <p style="color:#666;margin:0 0 24px;">Olá, <strong>' . htmlspecialchars($nome) . '</strong>! Use o código abaixo para criar sua conta:</p>
  <div style="text-align:center;background:#f9f9f9;border:2px dashed #ddd;border-radius:10px;padding:24px;margin-bottom:24px;">
    <span style="font-size:40px;font-weight:900;letter-spacing:12px;color:#111;">' . $codigo . '</span>
  </div>
  <p style="color:#999;font-size:13px;">Este código expira em <strong>10 minutos</strong>.</p>
</div></body></html>';
}
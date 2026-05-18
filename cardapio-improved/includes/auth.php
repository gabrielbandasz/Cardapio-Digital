<?php
// ── Security Headers ──────────────────────────────────────────
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob:; connect-src 'self'");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Regenerar session ID após login para prevenir fixação
function admin_regenerar_sessao(): void {
    session_regenerate_id(true);
    $_SESSION['_regenerated'] = time();
}

function admin_logado(): bool {
    if (!isset($_SESSION['admin_id'])) return false;
    // Verificar IP de sessão (proteção básica contra hijacking)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($_SESSION['_ip']) && $_SESSION['_ip'] !== $ip) {
        session_destroy();
        return false;
    }
    return true;
}

function exigir_login(): void {
    if (!admin_logado()) {
        $url = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . BASE_URL . 'admin/login.php?redir=' . $url);
        exit;
    }
    // Renovar sessão a cada 30 minutos
    if (!isset($_SESSION['_last_activity'])) $_SESSION['_last_activity'] = time();
    if (time() - $_SESSION['_last_activity'] > 1800) {
        admin_regenerar_sessao();
    }
    $_SESSION['_last_activity'] = time();
}

// ── CSRF ──────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Ação inválida. Recarregue a página e tente novamente.');
    }
}

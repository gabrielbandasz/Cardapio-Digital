<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_logado(): bool {
    return isset($_SESSION['admin_id']);
}

function exigir_login(): void {
    if (!admin_logado()) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
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

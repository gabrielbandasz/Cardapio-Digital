<?php
/**
 * Cardápio Digital — Configuração do Banco de Dados
 * Usa variáveis de ambiente (.env) para segurança
 */

// Carrega .env se existir
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (!array_key_exists($key, $_ENV)) { $_ENV[$key] = $value; putenv("$key=$value"); }
    }
}

define('DB_HOST',  $_ENV['DB_HOST']  ?? 'localhost');
define('DB_USER',  $_ENV['DB_USER']  ?? 'root');
define('DB_PASS',  $_ENV['DB_PASS']  ?? '');
define('DB_NAME',  $_ENV['DB_NAME']  ?? 'cardapio_digital');
define('APP_ENV',  $_ENV['APP_ENV']  ?? 'production');
define('APP_DEBUG',filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));

// Esconder erros em produção
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ADMIN_SALT — mantido para compatibilidade com admins existentes
define('ADMIN_SALT', $_ENV['ADMIN_SALT'] ?? 'cef8002e3dbd3c63acd250b28b580e249601bf462f6139576');

// ══════════════════════════════════════════════════════════════
// E-MAIL (verificação de cadastro de clientes)
// ══════════════════════════════════════════════════════════════
// MODO_DEBUG_EMAIL: só ative como true em ambiente LOCAL para testar sem
// enviar e-mail de verdade (o código volta na resposta da API).
// Em produção isso é sempre 'false' por padrão, mesmo que o .env não exista.
define('MODO_DEBUG_EMAIL', filter_var($_ENV['MODO_DEBUG_EMAIL'] ?? 'false', FILTER_VALIDATE_BOOLEAN));

// USAR_SMTP: true envia via SMTP (Gmail, SendGrid, etc) usando PHPMailer (já incluso em /vendor).
// false usa a função mail() nativa do PHP (funciona em pouquíssimos hosts sem configuração extra).
define('USAR_SMTP', filter_var($_ENV['USAR_SMTP'] ?? 'true', FILTER_VALIDATE_BOOLEAN));
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? ($_ENV['SMTP_USER'] ?? ''));
define('SMTP_NAME', $_ENV['SMTP_NAME'] ?? 'Cardápio Digital');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]
    );
} catch (PDOException $e) {
    error_log('[DB ERROR] ' . $e->getMessage());
    http_response_code(503);
    die('Serviço temporariamente indisponível. Tente novamente em instantes.');
}

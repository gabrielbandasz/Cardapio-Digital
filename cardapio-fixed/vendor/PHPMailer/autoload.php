<?php
/**
 * Autoload simples para o PHPMailer, sem precisar de Composer.
 * Basta incluir este arquivo antes de usar PHPMailer\PHPMailer\PHPMailer.
 */
require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

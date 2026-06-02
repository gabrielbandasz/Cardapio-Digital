<?php
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL', '../');

session_destroy();
header('Location: login.php');
exit;

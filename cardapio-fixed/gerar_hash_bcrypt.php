<?php
/**
 * FERRAMENTA TEMPORÁRIA — Gerar hash bcrypt para admin
 * APAGUE ESTE ARQUIVO após usar!
 */

// Proteção básica: só funciona em localhost
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('Acesso negado. Este arquivo só pode ser usado localmente.');
}

$hash = '';
$senha_input = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['senha'])) {
    $senha_input = $_POST['senha'];
    if (strlen($senha_input) < 8) {
        $erro = 'Senha deve ter ao menos 8 caracteres.';
    } else {
        $hash = password_hash($senha_input, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Gerar Hash bcrypt</title>
<style>
  body{font-family:monospace;max-width:600px;margin:60px auto;padding:20px;background:#111;color:#eee}
  input{width:100%;padding:10px;background:#222;border:1px solid #444;color:#eee;border-radius:6px;font-size:14px;margin-bottom:10px}
  button{background:#e85d04;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px}
  .hash{background:#1a2a1a;border:1px solid #2a5c2a;padding:14px;border-radius:8px;word-break:break-all;margin-top:16px;color:#4ade80}
  .warn{background:#3d1a00;border:1px solid #7c3510;padding:12px;border-radius:8px;color:#fb923c;margin-top:16px}
</style>
</head>
<body>
<h2>🔐 Gerador de Hash bcrypt</h2>
<p>Use para migrar a senha do admin para o formato seguro (bcrypt).</p>
<div class="warn">⚠️ <strong>APAGUE este arquivo após usar!</strong></div>
<form method="POST" style="margin-top:20px">
  <label>Nova senha do admin:<br>
    <input type="password" name="senha" minlength="8" required placeholder="Mínimo 8 caracteres">
  </label>
  <button type="submit">Gerar Hash</button>
</form>
<?php if ($hash): ?>
  <div class="hash">
    <strong>Hash gerado:</strong><br><br>
    <?= htmlspecialchars($hash) ?>
  </div>
  <p style="color:#aaa;font-size:13px;margin-top:12px">
    Execute no MySQL:<br>
    <code>UPDATE admins SET senha_hash='<?= htmlspecialchars($hash) ?>' WHERE email='SEU_EMAIL';</code>
  </p>
<?php elseif (!empty($erro)): ?>
  <p style="color:#f87171"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
</body>
</html>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL', '../');
if (admin_logado()) { header('Location: dashboard.php'); exit; }
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $hash  = hash('sha256', $senha . ADMIN_SALT);
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && hash_equals($admin['senha_hash'], $hash)) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_nome'] = $admin['nome'];
        header('Location: dashboard.php'); exit;
    } else { $erro = 'Email ou senha incorretos.'; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;">

<div style="width:100%;max-width:380px;">
  <div style="text-align:center;margin-bottom:36px;">
    <div style="font-size:48px;margin-bottom:14px;">🍽️</div>
    <h1 style="font-family:var(--font-display);font-size:26px;font-weight:700;margin-bottom:6px;">Painel Admin</h1>
    <p style="color:var(--muted);font-size:14px;">Acesse para gerenciar seu restaurante</p>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger mb-4">⚠️ <?= h($erro) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" autocomplete="on">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" placeholder="admin@restaurante.com" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Senha</label>
        <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">Entrar no painel</button>
    </form>
  </div>

  <div style="text-align:center;margin-top:20px;">
    <a href="../index.php" style="font-size:13px;color:var(--muted);">← Voltar ao início</a>
    <br><button id="darkToggle" class="btn btn-outline btn-sm" aria-label="Alternar tema" style="margin-top:10px">🌙 Tema</button>
  </div>
</div>

<script>
if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");
(function(){
  const root = document.documentElement;
  const btn  = document.getElementById('darkToggle');
  if (!btn) return;
  const isDark = () => root.getAttribute('data-theme') !== 'light';
  btn.setAttribute('aria-label','Alternar tema');
  btn.textContent = isDark() ? '☀️ Tema' : '🌙 Tema';
  btn.onclick = () => {
    const dark = isDark();
    root.setAttribute('data-theme', dark ? 'light' : 'dark');
    localStorage.setItem('darkMode', dark ? '0' : '1');
    btn.textContent = dark ? '🌙 Tema' : '☀️ Tema';
  };
})();
</script>
</body>
</html>

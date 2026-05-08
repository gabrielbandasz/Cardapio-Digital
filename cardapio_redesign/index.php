<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
header('Location: menu.php');
exit;
$config = $pdo->query("SELECT nome_restaurante, descricao, aberto, cor_primaria, whatsapp FROM config WHERE id=1")->fetch();
$config['logo'] = null;
$cor = h($config['cor_primaria'] ?? '#e85d04');
$jaLogado = admin_logado();
$slug = $_GET['slug'] ?? null;
if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM config WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $config = $stmt->fetch();
    if (!$config) { http_response_code(404); die('Restaurante não encontrado.'); }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($config['nome_restaurante']) ?> — Cardápio Digital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/style.css">
<style>:root{--primary:<?= $cor ?>;}</style>
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;">

<header class="lp-header">
  <div class="lp-brand">
    <?php if (!empty($config['logo'])): ?>
      <img src="<?= h($config['logo']) ?>" alt="Logo" style="width:40px;height:40px;border-radius:10px;object-fit:cover;">
    <?php else: ?>
      <span style="font-size:28px">🍽️</span>
    <?php endif; ?>
    <span class="lp-name"><?= h($config['nome_restaurante']) ?></span>
  </div>
  <button class="btn btn-outline btn-sm" id="darkToggle" aria-label="Alternar tema" style="gap:6px;">🌙 Tema</button>
</header>

<main class="lp-hero">
  <h1>Peça online<br><em><?= h($config['nome_restaurante']) ?></em></h1>
  <p><?= h($config['descricao'] ?: 'Comida deliciosa, direto para você. Rápido e fácil pelo WhatsApp.') ?></p>

  <div class="lp-cards">
    <a href="menu.php" class="lp-card">
      <span style="position:absolute;top:14px;right:14px;" class="<?= $config['aberto'] ? 'badge-aberto' : 'badge-fechado' ?>">
        <?= $config['aberto'] ? '● Aberto' : '● Fechado' ?>
      </span>
      <div class="lp-card-icon">🛒</div>
      <div class="lp-card-title">Sou cliente</div>
      <div class="lp-card-desc">Ver cardápio e fazer pedido pelo WhatsApp</div>
    </a>

    <a href="<?= $jaLogado ? 'admin/dashboard.php' : 'admin/login.php' ?>" class="lp-card">
      <span style="position:absolute;top:14px;right:14px;font-size:11px;font-weight:700;color:var(--muted);">🔐 Admin</span>
      <div class="lp-card-icon">🏪</div>
      <div class="lp-card-title">Restaurante</div>
      <div class="lp-card-desc"><?= $jaLogado ? 'Ir para o painel de controle' : 'Acessar painel de gerenciamento' ?></div>
    </a>
  </div>

  <?php if ($jaLogado): ?>
  <div style="margin-top:20px;padding:14px 20px;background:rgba(232,93,4,.1);border:1px solid rgba(232,93,4,.2);border-radius:var(--radius);display:flex;align-items:center;gap:12px;max-width:600px;width:100%;font-size:14px;">
    <span>🟢 Você está logado como administrador</span>
    <a href="admin/dashboard.php" class="btn btn-primary btn-sm" style="margin-left:auto;">Ir ao painel →</a>
  </div>
  <?php endif; ?>
</main>

<footer style="text-align:center;padding:20px;font-size:12px;color:var(--muted);border-top:1px solid var(--border);">
  <?= h($config['nome_restaurante']) ?> · Cardápio Digital
</footer>

<script>
// ── Dark mode ─────────────────────────────────────────────
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

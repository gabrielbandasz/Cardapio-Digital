<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
define('BASE_URL','./');
$config = $pdo->query('SELECT * FROM config WHERE id=1')->fetch();
$numero = trim($_GET['n'] ?? '');
$pedido = null;
$itens = [];
if ($numero) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE numero=?");
    $stmt->execute([$numero]);
    $pedido = $stmt->fetch();
    if ($pedido) {
        $si = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id=?");
        $si->execute([$pedido['id']]);
        $itens = $si->fetchAll();
    }
}
$statusPassos = ['novo','confirmado','preparo','pronto','entregue'];
$statusAtual = $pedido ? $pedido['status'] : '';
$statusIdx = array_search($statusAtual, $statusPassos);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Status do Pedido — <?= h($config['nome_restaurante']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
<div class="container" style="display:flex;align-items:center;justify-content:space-between">
  <div>
    <a href="index.php" class="back-link">← Voltar ao cardápio</a>
    <h1 style="margin-top:4px">📦 Status do Pedido</h1>
  </div>
  <button onclick="toggleDark()" class="btn btn-outline" style="padding:8px 12px;font-size:20px;background:transparent;border:none;color:#fff">🌙</button>
</div>
</header>
<div class="container" style="padding-top:24px;padding-bottom:40px;max-width:500px">

<?php if (!$numero): ?>
<div class="card">
  <h2 style="margin-bottom:16px;font-size:18px">Rastrear pedido</h2>
  <form method="GET" action="status.php">
    <div class="form-group">
      <label class="form-label">Número do pedido</label>
      <input type="text" name="n" class="form-control" placeholder="Ex: PEDA3F8B2" autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Buscar pedido</button>
  </form>
</div>
<?php elseif (!$pedido): ?>
<div class="card" style="text-align:center;padding:40px 20px">
  <div style="font-size:48px;margin-bottom:16px">🔍</div>
  <h2>Pedido não encontrado</h2>
  <p class="text-muted" style="margin:8px 0 20px">Verifique o número e tente novamente.</p>
  <a href="status.php" class="btn btn-primary">Tentar novamente</a>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <div style="font-size:13px;color:var(--muted);font-weight:600;text-transform:uppercase">Pedido</div>
      <div style="font-size:20px;font-weight:800"><?= h($pedido['numero']) ?></div>
    </div>
    <span class="badge <?= status_class($pedido['status']) ?>" style="font-size:14px;padding:6px 14px">
      <?= status_emoji($pedido['status']) ?> <?= status_label($pedido['status']) ?>
    </span>
  </div>
  <?php if ($statusAtual !== 'cancelado'): ?>
  <div class="status-steps">
    <?php foreach ($statusPassos as $i => $s): ?>
    <div class="status-step <?= $statusIdx >= $i ? 'done' : '' ?> <?= $statusIdx === $i ? 'active' : '' ?>">
      <div class="step-dot"><?= $statusIdx >= $i ? '✓' : ($i+1) ?></div>
      <div class="step-label"><?= status_label($s) ?></div>
    </div>
    <?php if ($i < count($statusPassos)-1): ?><div class="step-line <?= $statusIdx > $i ? 'done' : '' ?>"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="alerta alerta-erro">❌ Pedido cancelado. Entre em contato com o restaurante.</div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="section-title" style="margin-bottom:12px">Itens do pedido</div>
  <?php foreach ($itens as $it): ?>
  <div style="display:flex;justify-content:space-between;font-size:14px;padding:6px 0;border-bottom:1px solid var(--border)">
    <span><?= $it['quantidade'] ?>x <?= h($it['nome_produto']) ?></span>
    <span class="fw-bold"><?= formatar_dinheiro((float)$it['subtotal']) ?></span>
  </div>
  <?php endforeach; ?>
  <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;padding-top:10px;margin-top:4px;color:var(--primary)">
    <span>Total</span><span><?= formatar_dinheiro((float)$pedido['total']) ?></span>
  </div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap">
  <button onclick="atualizarStatus()" class="btn btn-outline" style="flex:1">🔄 Atualizar</button>
  <a href="index.php" class="btn btn-primary" style="flex:1">🛒 Novo pedido</a>
</div>
<p style="text-align:center;font-size:13px;color:var(--muted);margin-top:12px">Atualiza automaticamente a cada 30s</p>
<?php endif; ?>
</div>
<script src="assets/js/cart.js"></script>
<script>
<?php if ($pedido && $pedido['status'] !== 'entregue' && $pedido['status'] !== 'cancelado'): ?>
setInterval(() => {
  fetch('api/status_pedido.php?numero=<?= urlencode($pedido['numero']) ?>')
    .then(r=>r.json()).then(d=>{
      if(d.status && d.status !== '<?= $pedido['status'] ?>') location.reload();
    });
}, 30000);
<?php endif; ?>
function atualizarStatus(){ location.reload(); }
</script>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
<script>
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
</body></html>

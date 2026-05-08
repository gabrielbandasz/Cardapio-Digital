<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();

// Toggles rápidos via POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle'])) {
    $campo = in_array($_POST['toggle'],['aberto','modo_pico','promo_ativa','fidelidade_ativo']) ? $_POST['toggle'] : '';
    if ($campo) { $pdo->prepare("UPDATE config SET $campo=1-$campo WHERE id=1")->execute(); header('Location: dashboard.php'); exit; }
}

$pedidosHoje   = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$faturHoje     = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE DATE(created_at)=CURDATE() AND status!='cancelado'")->fetchColumn();
$pedidosAtivos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo')")->fetchColumn();
$clientesTotal = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$pedidosNovos  = $pdo->query("SELECT * FROM pedidos WHERE status='novo' ORDER BY created_at DESC LIMIT 10")->fetchAll();
$tempoBase     = (int)($config['tempo_preparo_base'] ?? 30);
$tempoPorPedido= (int)($config['tempo_preparo_por_pedido'] ?? 5);
$tempoEst      = $tempoBase + ($pedidosAtivos * $tempoPorPedido);
$modoPico      = !empty($config['modo_pico']);
$promoAtiva    = !empty($config['promo_ativa']);
$fidelidadeAtivo = !empty($config['fidelidade_ativo']);
$lojaAberta    = !empty($config['aberto']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Painel — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
    <div class="section-title" style="margin:0">📊 Painel</div>
    <!-- Botões rápidos -->
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="POST" style="display:inline">
      <?= csrf_field() ?>
        <input type="hidden" name="toggle" value="aberto">
        <button type="submit" class="btn <?= $lojaAberta?'btn-primary':'btn-danger' ?>" style="font-size:13px;padding:8px 14px">
          <?= $lojaAberta?'🟢 Loja aberta':'🔴 Loja fechada' ?>
        </button>
      </form>
      <form method="POST" style="display:inline">
      <?= csrf_field() ?>
        <input type="hidden" name="toggle" value="modo_pico">
        <button type="submit" class="btn <?= $modoPico?'btn-primary':'btn-outline' ?>" style="font-size:13px;padding:8px 14px">
          🔥 Pico <?= $modoPico?'ON':'OFF' ?>
        </button>
      </form>
      <form method="POST" style="display:inline">
      <?= csrf_field() ?>
        <input type="hidden" name="toggle" value="promo_ativa">
        <button type="submit" class="btn <?= $promoAtiva?'btn-primary':'btn-outline' ?>" style="font-size:13px;padding:8px 14px">
          ⚡ Promo <?= $promoAtiva?'ON':'OFF' ?>
        </button>
      </form>
      <form method="POST" style="display:inline">
      <?= csrf_field() ?>
        <input type="hidden" name="toggle" value="fidelidade_ativo">
        <button type="submit" class="btn <?= $fidelidadeAtivo?'btn-primary':'btn-outline' ?>" style="font-size:13px;padding:8px 14px">
          ⭐ Fidelidade <?= $fidelidadeAtivo?'ON':'OFF' ?>
        </button>
      </form>
    </div>
  </div>

  <!-- KPIs -->
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Pedidos hoje</div><div class="stat-value blue"><?= $pedidosHoje ?></div></div>
    <div class="stat-card"><div class="stat-label">Faturamento hoje</div><div class="stat-value orange"><?= formatar_dinheiro($faturHoje) ?></div></div>
    <div class="stat-card"><div class="stat-label">Pedidos em aberto</div><div class="stat-value" style="color:var(--danger)"><?= $pedidosAtivos ?></div></div>
    <div class="stat-card"><div class="stat-label">Tempo estimado</div><div class="stat-value green"><?= $modoPico?($config['pico_tempo']??'60 min'):$tempoEst.'–'.($tempoEst+15).' min' ?></div></div>
    <div class="stat-card"><div class="stat-label">Clientes cadastrados</div><div class="stat-value blue"><?= $clientesTotal ?></div></div>
  </div>

  <!-- Novos pedidos com som -->
  <div class="card" style="margin-top:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div class="section-title" style="margin:0">🔔 Novos pedidos</div>
      <div style="display:flex;gap:8px;align-items:center">
        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer">
          <input type="checkbox" id="somAtivado" checked> Som de alerta
        </label>
        <a href="pedidos.php" class="btn btn-outline" style="padding:6px 12px;font-size:13px">Ver todos</a>
      </div>
    </div>
    <?php if (empty($pedidosNovos)): ?>
      <div class="empty-state" style="padding:20px"><p>Nenhum pedido novo no momento. 😴</p></div>
    <?php else: ?>
    <div class="pedidos-wrap">
      <table class="pedidos-table">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Hora</th><th>Ação rápida</th></tr></thead>
        <tbody>
          <?php foreach ($pedidosNovos as $p): ?>
          <tr id="pedido-<?= $p['id'] ?>">
            <td><a href="pedido.php?id=<?= $p['id'] ?>" class="text-primary fw-bold">#<?= h($p['numero']) ?></a></td>
            <td><?= h($p['nome_cliente']?:'—') ?></td>
            <td class="text-primary fw-bold"><?= formatar_dinheiro((float)$p['total']) ?></td>
            <td style="color:var(--muted);font-size:13px"><?= date('H:i',strtotime($p['created_at'])) ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap">
              <form method="POST" action="pedido.php?id=<?= $p['id'] ?>
      <?= csrf_field() ?>" style="display:inline">
                <input type="hidden" name="status" value="confirmado"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-primary" style="font-size:12px;padding:5px 10px">✅ Confirmar</button>
              </form>
              <a href="pedido.php?id=<?= $p['id'] ?>" class="btn btn-outline" style="font-size:12px;padding:5px 10px">👁 Ver</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Som de notificação -->
<script>
let _ultimoId = <?= $pedidosNovos ? max(array_column($pedidosNovos,'id')) : 0 ?>;
const somCheck = document.getElementById('somAtivado');

function tocarSom() {
  if (!somCheck?.checked) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [880, 1100, 880].forEach((f, i) => {
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.frequency.value = f; o.type = 'sine';
      g.gain.setValueAtTime(0.3, ctx.currentTime + i * 0.18);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.18 + 0.3);
      o.start(ctx.currentTime + i * 0.18);
      o.stop(ctx.currentTime + i * 0.18 + 0.3);
    });
  } catch(e){}
}

function verificarNovos() {
  fetch('../api/novo_pedido_poll.php?desde=' + _ultimoId)
    .then(r => r.json()).then(d => {
      if (d.novos > 0) {
        tocarSom();
        if (Notification.permission === 'granted') {
          new Notification('🍔 Novo pedido!', { body: `${d.novos} pedido(s) novo(s)!`, icon: '🍔' });
        }
        _ultimoId = d.ultimo_id;
        location.reload();
      }
    }).catch(()=>{});
}

// Pedir permissão para notificações
if ('Notification' in window && Notification.permission === 'default') {
  Notification.requestPermission();
}

setInterval(verificarNovos, 15000);
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

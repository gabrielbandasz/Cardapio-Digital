<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();

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
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px}
.stat-card.orange::after{background:var(--primary)}
.stat-card.gold::after{background:#d4a843}
.stat-card.green::after{background:#22c55e}
.stat-card.blue::after{background:#3b82f6}
.stat-card.red::after{background:#ef4444}
.stat-label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.stat-value{font-family:var(--font-display);font-size:26px;font-weight:700;color:var(--text);line-height:1}

/* Toggles */
.toggles-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
.toggle-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text-soft);font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:all .15s}
.toggle-btn.on{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 2px 12px rgba(232,93,4,.3)}
.toggle-btn.on-green{background:#16a34a;color:#fff;border-color:#16a34a}

/* Pedidos novos */
.novos-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.novos-title{font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.novos-badge{background:var(--primary);color:#fff;border-radius:99px;font-size:11px;font-weight:800;padding:2px 9px}

/* Cards de pedido mobile-first */
.pedido-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:10px;border-left:3px solid var(--primary)}
.pedido-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.pedido-num{font-weight:800;font-size:15px;color:var(--text)}
.pedido-hora{font-size:12px;color:var(--muted)}
.pedido-cliente{font-size:14px;color:var(--text-soft);margin-bottom:10px}
.pedido-total{font-size:16px;font-weight:800;color:var(--primary);font-family:var(--font-display)}
.pedido-actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.pedido-actions .btn{flex:1;min-width:80px;text-align:center;font-size:13px;padding:8px 10px}

/* Section title */
.dash-section{margin-bottom:8px}

@media(max-width:480px){
  .stats-grid{grid-template-columns:1fr 1fr}
  .stat-value{font-size:22px}
  .toggle-btn{padding:8px 12px;font-size:12px}
}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">

  <!-- Toggles rápidos -->
  <div class="toggles-row">
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="aberto">
      <button type="submit" class="toggle-btn <?= $lojaAberta?'on-green':'' ?>">
        <?= $lojaAberta?'🟢 Aberto':'🔴 Fechado' ?>
      </button>
    </form>
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="modo_pico">
      <button type="submit" class="toggle-btn <?= $modoPico?'on':'' ?>">🔥 Pico <?= $modoPico?'ON':'OFF' ?></button>
    </form>
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="promo_ativa">
      <button type="submit" class="toggle-btn <?= $promoAtiva?'on':'' ?>">⚡ Promo <?= $promoAtiva?'ON':'OFF' ?></button>
    </form>
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="fidelidade_ativo">
      <button type="submit" class="toggle-btn <?= $fidelidadeAtivo?'on':'' ?>">⭐ Fidelidade <?= $fidelidadeAtivo?'ON':'OFF' ?></button>
    </form>
  </div>

  <!-- KPIs -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-label">Pedidos hoje</div>
      <div class="stat-value"><?= $pedidosHoje ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Faturamento</div>
      <div class="stat-value"><?= formatar_dinheiro($faturHoje) ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Em aberto</div>
      <div class="stat-value"><?= $pedidosAtivos ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Tempo estimado</div>
      <div class="stat-value" style="font-size:18px"><?= $modoPico?($config['pico_tempo']??'60 min'):$tempoEst.'–'.($tempoEst+15).' min' ?></div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Clientes</div>
      <div class="stat-value"><?= $clientesTotal ?></div>
    </div>
  </div>

  <!-- Novos pedidos -->
  <div class="dash-section">
    <div class="novos-header">
      <div class="novos-title">
        🔔 Novos pedidos
        <?php if(!empty($pedidosNovos)): ?>
          <span class="novos-badge"><?= count($pedidosNovos) ?></span>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--text-soft)">
          <input type="checkbox" id="somAtivado" checked> Som
        </label>
        <a href="pedidos.php" class="btn btn-outline btn-sm">Ver todos →</a>
      </div>
    </div>

    <?php if (empty($pedidosNovos)): ?>
      <div class="card" style="text-align:center;padding:40px 20px;color:var(--muted)">
        <div style="font-size:40px;margin-bottom:10px;opacity:.4">😴</div>
        <p style="font-size:15px">Nenhum pedido novo no momento</p>
      </div>
    <?php else: ?>
      <?php foreach ($pedidosNovos as $p): ?>
      <div class="pedido-card" id="pedido-<?= $p['id'] ?>">
        <div class="pedido-card-top">
          <a href="pedido.php?id=<?= $p['id'] ?>" class="pedido-num">#<?= h($p['numero']) ?></a>
          <span class="pedido-hora">⏱ <?= date('H:i', strtotime($p['created_at'])) ?></span>
        </div>
        <div class="pedido-cliente">👤 <?= h($p['nome_cliente'] ?: '—') ?></div>
        <div style="display:flex;align-items:center;justify-content:space-between">
          <span class="pedido-total"><?= formatar_dinheiro((float)$p['total']) ?></span>
          <span style="font-size:12px;color:var(--muted)"><?= $p['tipo_entrega']==='retirada'?'🏃 Retirada':'🛵 Entrega' ?></span>
        </div>
        <div class="pedido-actions">
          <form method="POST" action="pedido.php?id=<?= $p['id'] ?>" style="display:contents">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="confirmado">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-primary">✅ Confirmar</button>
          </form>
          <a href="pedido.php?id=<?= $p['id'] ?>" class="btn btn-outline">👁 Ver</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<?php include __DIR__ . '/nav_end.php'; ?>

<script>
let _ultimoId = <?= $pedidosNovos ? max(array_column($pedidosNovos,'id')) : 0 ?>;
function tocarSom(){
  if (!document.getElementById('somAtivado')?.checked) return;
  try {
    const ctx = new (window.AudioContext||window.webkitAudioContext)();
    [880,1100,880].forEach((f,i)=>{
      const o=ctx.createOscillator(),g=ctx.createGain();
      o.connect(g);g.connect(ctx.destination);
      o.frequency.value=f;o.type='sine';
      g.gain.setValueAtTime(.3,ctx.currentTime+i*.18);
      g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.18+.3);
      o.start(ctx.currentTime+i*.18);o.stop(ctx.currentTime+i*.18+.3);
    });
  } catch(e){}
}
function verificarNovos(){
  fetch('../api/novo_pedido_poll.php?desde='+_ultimoId)
    .then(r=>r.json()).then(d=>{
      if(d.novos>0){ tocarSom(); if(Notification.permission==='granted') new Notification('🍔 Novo pedido!',{body:`${d.novos} pedido(s) novo(s)!`}); _ultimoId=d.ultimo_id; location.reload(); }
    }).catch(()=>{});
}
if('Notification' in window && Notification.permission==='default') Notification.requestPermission();
setInterval(verificarNovos,15000);
</script>
</body>
</html>

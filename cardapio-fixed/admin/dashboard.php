<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();

// Toggle rápido de status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle'])) {
    $campo = in_array($_POST['toggle'],['aberto','modo_pico','promo_ativa','fidelidade_ativo']) ? $_POST['toggle'] : '';
    if ($campo) { $pdo->prepare("UPDATE config SET $campo=1-$campo WHERE id=1")->execute(); header('Location: dashboard.php'); exit; }
}

// ── KPIs principais ──
$pedidosHoje   = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$faturHoje     = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE DATE(created_at)=CURDATE() AND status!='cancelado'")->fetchColumn();
$pedidosAtivos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo')")->fetchColumn();
$clientesTotal = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$pedidosNovos  = $pdo->query("SELECT * FROM pedidos WHERE status='novo' ORDER BY created_at DESC LIMIT 10")->fetchAll();

// ── KPIs avançados ──
$ticketMedio = $pedidosHoje > 0 ? round($faturHoje / $pedidosHoje, 2) : 0;
$pedidosMes  = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelado'")->fetchColumn();
$faturMes    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelado'")->fetchColumn();
$cancelados  = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at)=CURDATE() AND status='cancelado'")->fetchColumn();

// Produto mais vendido hoje
$maisPedidoHoje = $pdo->query("
    SELECT p.nome, SUM(pi.quantidade) as qtd
    FROM pedido_itens pi
    JOIN produtos p ON p.id = pi.produto_id
    JOIN pedidos pe ON pe.id = pi.pedido_id
    WHERE DATE(pe.created_at) = CURDATE()
    GROUP BY pi.produto_id ORDER BY qtd DESC LIMIT 1
")->fetch();

// ── Gráfico de pedidos 7 dias ──
$grafPedidos7d = $pdo->query("
    SELECT DATE(created_at) as dia, COUNT(*) as total,
           COALESCE(SUM(CASE WHEN status!='cancelado' THEN total ELSE 0 END),0) as faturamento
    FROM pedidos WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at) ORDER BY dia ASC
")->fetchAll();

// Preencher dias sem pedidos
$labels7d = []; $dados7d = []; $fatur7d = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $labels7d[] = date('d/m', strtotime($dia));
    $found = array_filter($grafPedidos7d, fn($r) => $r['dia'] === $dia);
    $row = $found ? reset($found) : ['total'=>0,'faturamento'=>0];
    $dados7d[] = (int)$row['total'];
    $fatur7d[] = round((float)$row['faturamento'], 2);
}

// ── Gráfico pedidos por status hoje ──
$statusHoje = $pdo->query("
    SELECT status, COUNT(*) as total FROM pedidos
    WHERE DATE(created_at)=CURDATE() GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Pedidos por hora hoje ──
$porHora = $pdo->query("
    SELECT HOUR(created_at) as hora, COUNT(*) as total
    FROM pedidos WHERE DATE(created_at)=CURDATE()
    GROUP BY HOUR(created_at) ORDER BY hora
")->fetchAll();
$labelsHoras = []; $dadosHoras = [];
for ($h = 8; $h <= 23; $h++) {
    $labelsHoras[] = $h . 'h';
    $found = array_filter($porHora, fn($r) => (int)$r['hora'] === $h);
    $dadosHoras[] = $found ? (int)reset($found)['total'] : 0;
}

// Variáveis de status
$tempoBase = (int)($config['tempo_preparo_base'] ?? 30);
$tempoPorPedido = (int)($config['tempo_preparo_por_pedido'] ?? 5);
$tempoEst = $tempoBase + ($pedidosAtivos * $tempoPorPedido);
$modoPico = !empty($config['modo_pico']);
$promoAtiva = !empty($config['promo_ativa']);
$fidelidadeAtivo = !empty($config['fidelidade_ativo']);
$lojaAberta = !empty($config['aberto']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — Admin</title>
<script>if(localStorage.getItem('darkMode')==='0')document.documentElement.setAttribute('data-theme','light');</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── KPIs Grid ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:28px}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s}
.kpi-card:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.3)}
.kpi-accent{position:absolute;bottom:0;left:0;right:0;height:3px}
.kpi-accent.orange{background:linear-gradient(90deg,var(--primary),var(--gold))}
.kpi-accent.blue{background:linear-gradient(90deg,#3b82f6,#06b6d4)}
.kpi-accent.green{background:linear-gradient(90deg,#22c55e,#10b981)}
.kpi-accent.gold{background:linear-gradient(90deg,var(--gold),#f59e0b)}
.kpi-accent.red{background:linear-gradient(90deg,#ef4444,#f97316)}
.kpi-accent.purple{background:linear-gradient(90deg,#8b5cf6,#6366f1)}
.kpi-icon{font-size:22px;margin-bottom:10px;display:block}
.kpi-label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:5px}
.kpi-value{font-family:var(--font-display);font-size:26px;font-weight:700;color:var(--text);line-height:1.1}
.kpi-sub{font-size:12px;color:var(--muted);margin-top:4px}
.kpi-trend{font-size:11px;margin-top:4px;font-weight:700}
.kpi-trend.up{color:var(--success)}
.kpi-trend.down{color:var(--danger)}

/* ── Toggles ── */
.toggles-section{margin-bottom:24px}
.toggles-title{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}
.toggles-row{display:flex;gap:8px;flex-wrap:wrap}
.toggle-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text-soft);font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:all .18s}
.toggle-btn:hover{border-color:var(--primary);color:var(--primary)}
.toggle-btn.on-green{background:rgba(34,197,94,.12);color:#22c55e;border-color:rgba(34,197,94,.3)}
.toggle-btn.on{background:rgba(232,93,4,.12);color:var(--primary);border-color:rgba(232,93,4,.3)}
.toggle-btn .dot{width:8px;height:8px;border-radius:99px;background:currentColor;display:inline-block}

/* ── Charts Grid ── */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px}
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px}
.chart-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;display:flex;align-items:center;gap:8px}
.chart-sub{font-size:12px;color:var(--muted);margin-bottom:16px}
.chart-wrap{position:relative;height:180px}

/* ── Novos pedidos ── */
.section-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.section-title-dash{font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.badge-count{background:var(--primary);color:#fff;border-radius:99px;font-size:11px;font-weight:800;padding:2px 9px;min-width:22px;text-align:center}
.badge-count.pulse{animation:badge-pulse 1.5s ease-in-out infinite}
@keyframes badge-pulse{0%,100%{box-shadow:0 0 0 0 rgba(232,93,4,.6)}50%{box-shadow:0 0 0 6px rgba(232,93,4,0)}}

/* ── Pedido card ── */
.pedido-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;border-left:3px solid var(--primary);transition:transform .15s}
.pedido-card:hover{transform:translateX(3px)}
.pedido-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.pedido-num{font-weight:800;font-size:15px;color:var(--text);text-decoration:none}
.pedido-num:hover{color:var(--primary)}
.pedido-hora{font-size:12px;color:var(--muted)}
.pedido-info{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:6px}
.pedido-cliente{font-size:14px;color:var(--text-soft)}
.pedido-total{font-size:17px;font-weight:800;color:var(--primary);font-family:var(--font-display)}
.pedido-tipo{font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:99px;padding:3px 9px}
.pedido-actions{display:flex;gap:8px;flex-wrap:wrap}
.pedido-actions .btn{flex:1;min-width:90px;font-size:13px;padding:8px 12px;text-align:center}
.empty-pedidos{background:var(--surface2);border:1px solid var(--border);border-radius:12px;text-align:center;padding:48px 20px;color:var(--muted)}
.empty-pedidos .empty-icon{font-size:48px;margin-bottom:12px;opacity:.4}

/* ── Status pie legend ── */
.status-legend{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.legend-row{display:flex;align-items:center;justify-content:space-between;font-size:13px}
.legend-dot{width:10px;height:10px;border-radius:99px;flex-shrink:0}
.legend-label{color:var(--text-soft);flex:1;margin-left:8px}
.legend-val{font-weight:700;color:var(--text)}

/* Produto mais vendido */
.mais-vendido-card{background:linear-gradient(135deg,rgba(232,93,4,.08),rgba(212,168,67,.05));border:1px solid rgba(232,93,4,.2);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:14px;margin-bottom:24px}
.mv-icon{font-size:36px}
.mv-info{}
.mv-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px}
.mv-nome{font-size:17px;font-weight:800;color:var(--text)}
.mv-qtd{font-size:13px;color:var(--primary);font-weight:600}

@media(max-width:700px){
  .kpi-grid{grid-template-columns:1fr 1fr}
  .kpi-value{font-size:22px}
  .charts-grid{grid-template-columns:1fr}
  .chart-wrap{height:160px}
}
@media(max-width:400px){
  .kpi-grid{grid-template-columns:1fr 1fr}
  .kpi-value{font-size:18px}
  .toggle-btn{font-size:12px;padding:8px 12px}
}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">

<!-- ── Toggles Rápidos ── -->
<div class="toggles-section">
  <div class="toggles-title">Controles rápidos</div>
  <div class="toggles-row">
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="aberto">
      <button type="submit" class="toggle-btn <?= $lojaAberta?'on-green':'' ?>">
        <span class="dot"></span>
        <?= $lojaAberta?'Loja Aberta':'Loja Fechada' ?>
      </button>
    </form>
    <form method="POST" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle" value="modo_pico">
      <button type="submit" class="toggle-btn <?= $modoPico?'on':'' ?>">🔥 Modo Pico <?= $modoPico?'ON':'OFF' ?></button>
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
</div>

<!-- ── KPIs ── -->
<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-icon">📦</span>
    <div class="kpi-label">Pedidos hoje</div>
    <div class="kpi-value"><?= $pedidosHoje ?></div>
    <div class="kpi-sub"><?= $pedidosAtivos ?> em aberto</div>
    <div class="kpi-accent blue"></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">💰</span>
    <div class="kpi-label">Faturamento hoje</div>
    <div class="kpi-value" style="font-size:20px"><?= formatar_dinheiro($faturHoje) ?></div>
    <div class="kpi-sub">Mês: <?= formatar_dinheiro($faturMes) ?></div>
    <div class="kpi-accent orange"></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">🎯</span>
    <div class="kpi-label">Ticket médio</div>
    <div class="kpi-value" style="font-size:20px"><?= formatar_dinheiro($ticketMedio) ?></div>
    <div class="kpi-sub">Por pedido hoje</div>
    <div class="kpi-accent gold"></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">⏱️</span>
    <div class="kpi-label">Tempo estimado</div>
    <div class="kpi-value" style="font-size:18px"><?= $modoPico?($config['pico_tempo']??'60 min'):$tempoEst.'–'.($tempoEst+15).' min' ?></div>
    <div class="kpi-sub"><?= $modoPico?'Modo pico ativo':'Baseado na fila' ?></div>
    <div class="kpi-accent green"></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">👥</span>
    <div class="kpi-label">Clientes</div>
    <div class="kpi-value"><?= $clientesTotal ?></div>
    <div class="kpi-sub">Total cadastrados</div>
    <div class="kpi-accent purple"></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-icon">❌</span>
    <div class="kpi-label">Cancelados hoje</div>
    <div class="kpi-value"><?= $cancelados ?></div>
    <div class="kpi-sub">De <?= $pedidosHoje ?> pedidos</div>
    <div class="kpi-accent red"></div>
  </div>
</div>

<!-- ── Mais Pedido ── -->
<?php if ($maisPedidoHoje): ?>
<div class="mais-vendido-card">
  <div class="mv-icon">🏆</div>
  <div class="mv-info">
    <div class="mv-label">Mais pedido hoje</div>
    <div class="mv-nome"><?= h($maisPedidoHoje['nome']) ?></div>
    <div class="mv-qtd"><?= $maisPedidoHoje['qtd'] ?> unidade<?= $maisPedidoHoje['qtd']!=1?'s':'' ?> vendida<?= $maisPedidoHoje['qtd']!=1?'s':'' ?></div>
  </div>
</div>
<?php endif; ?>

<!-- ── Gráficos ── -->
<div class="charts-grid">
  <div class="chart-card">
    <div class="chart-title">📈 Pedidos — últimos 7 dias</div>
    <div class="chart-sub">Volume de pedidos diário</div>
    <div class="chart-wrap"><canvas id="chartPedidos"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">💵 Faturamento — 7 dias</div>
    <div class="chart-sub">Receita diária (excl. cancelados)</div>
    <div class="chart-wrap"><canvas id="chartFatur"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">🕐 Pedidos por hora hoje</div>
    <div class="chart-sub">Pico de movimento do dia</div>
    <div class="chart-wrap"><canvas id="chartHoras"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">📊 Status dos pedidos hoje</div>
    <div class="chart-sub">Distribuição por status</div>
    <div class="chart-wrap" style="height:140px"><canvas id="chartStatus"></canvas></div>
    <div class="status-legend" id="statusLegend"></div>
  </div>
</div>

<!-- ── Novos Pedidos ── -->
<div class="section-header">
  <div class="section-title-dash">
    🔔 Novos pedidos
    <?php if(!empty($pedidosNovos)): ?>
      <span class="badge-count pulse"><?= count($pedidosNovos) ?></span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--text-soft)">
      <input type="checkbox" id="somAtivado" checked style="accent-color:var(--primary)"> Som
    </label>
    <a href="pedidos.php" class="btn btn-outline btn-sm">Ver todos →</a>
  </div>
</div>

<?php if (empty($pedidosNovos)): ?>
  <div class="empty-pedidos">
    <div class="empty-icon">😴</div>
    <p style="font-size:15px;font-weight:600;color:var(--text-soft)">Nenhum pedido novo</p>
    <p style="font-size:13px;margin-top:4px">O sistema verifica automaticamente a cada 15 segundos</p>
  </div>
<?php else: ?>
  <?php foreach ($pedidosNovos as $p): ?>
  <div class="pedido-card" id="pedido-<?= $p['id'] ?>">
    <div class="pedido-card-top">
      <a href="pedido.php?id=<?= $p['id'] ?>" class="pedido-num">#<?= h($p['numero']) ?></a>
      <span class="pedido-hora">⏱ <?= date('H:i', strtotime($p['created_at'])) ?></span>
    </div>
    <div class="pedido-info">
      <span class="pedido-cliente">👤 <?= h($p['nome_cliente'] ?: '—') ?></span>
      <span class="pedido-tipo"><?= $p['tipo_entrega']==='retirada'?'🏃 Retirada':'🛵 Entrega' ?></span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <span class="pedido-total"><?= formatar_dinheiro((float)$p['total']) ?></span>
      <span style="font-size:12px;color:var(--muted)"><?= $p['pagamento']??'' ?></span>
    </div>
    <div class="pedido-actions">
      <form method="POST" action="pedido.php?id=<?= $p['id'] ?>" style="display:contents">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="confirmado">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-primary">✅ Confirmar</button>
      </form>
      <a href="pedido.php?id=<?= $p['id'] ?>" class="btn btn-outline">👁 Detalhes</a>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

</div><!-- /admin-wrap -->
<?php include __DIR__ . '/nav_end.php'; ?>

<script>
// ── Dados PHP → JS ──────────────────────────────────────
const LABELS_7D  = <?= json_encode($labels7d) ?>;
const PEDIDOS_7D = <?= json_encode($dados7d) ?>;
const FATUR_7D   = <?= json_encode($fatur7d) ?>;
const LABELS_H   = <?= json_encode($labelsHoras) ?>;
const DADOS_H    = <?= json_encode($dadosHoras) ?>;
const STATUS_HOJE= <?= json_encode((object)$statusFiltro ?? '{}') ?>;
const STATUS_DATA = <?= json_encode($statusHoje) ?>;

// ── Tema aware: pegar cor do CSS ────────────────────────
function cssVar(name){ return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }

const BASE = {
  color: cssVar('--text'),
  grid:  'rgba(128,128,128,.1)',
  font: 'DM Sans, system-ui, sans-serif',
  fontSize: 11
};

Chart.defaults.color = BASE.color;
Chart.defaults.font.family = BASE.font;
Chart.defaults.font.size = BASE.fontSize;

// ── Pedidos 7d (Bar) ────────────────────────────────────
new Chart(document.getElementById('chartPedidos'), {
  type: 'bar',
  data: {
    labels: LABELS_7D,
    datasets: [{
      label: 'Pedidos',
      data: PEDIDOS_7D,
      backgroundColor: 'rgba(232,93,4,.5)',
      borderColor: '#e85d04',
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: BASE.grid } },
      y: { grid: { color: BASE.grid }, beginAtZero: true, ticks: { precision: 0 } }
    }
  }
});

// ── Faturamento 7d (Line) ───────────────────────────────
new Chart(document.getElementById('chartFatur'), {
  type: 'line',
  data: {
    labels: LABELS_7D,
    datasets: [{
      label: 'Faturamento',
      data: FATUR_7D,
      borderColor: '#d4a843',
      backgroundColor: 'rgba(212,168,67,.1)',
      borderWidth: 2.5,
      pointRadius: 4,
      pointBackgroundColor: '#d4a843',
      fill: true,
      tension: 0.4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: ctx => 'R$ ' + ctx.raw.toFixed(2).replace('.',',') } }
    },
    scales: {
      x: { grid: { color: BASE.grid } },
      y: { grid: { color: BASE.grid }, beginAtZero: true,
        ticks: { callback: v => 'R$' + v } }
    }
  }
});

// ── Por hora (Bar) ──────────────────────────────────────
new Chart(document.getElementById('chartHoras'), {
  type: 'bar',
  data: {
    labels: LABELS_H,
    datasets: [{
      label: 'Pedidos',
      data: DADOS_H,
      backgroundColor: ctx => ctx.raw > 0 ? 'rgba(99,102,241,.6)' : 'rgba(99,102,241,.15)',
      borderColor: '#6366f1',
      borderWidth: 1.5,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: BASE.grid } },
      y: { grid: { color: BASE.grid }, beginAtZero: true, ticks: { precision: 0 } }
    }
  }
});

// ── Status donut ────────────────────────────────────────
const STATUS_COLORS = {
  novo: '#3b82f6', confirmado: '#8b5cf6', preparo: '#f97316',
  pronto: '#22c55e', entregue: '#6b7280', cancelado: '#ef4444'
};
const STATUS_LABELS_PT = {
  novo:'Novo', confirmado:'Confirmado', preparo:'Em Preparo',
  pronto:'Pronto', entregue:'Entregue', cancelado:'Cancelado'
};

const statusKeys   = Object.keys(STATUS_DATA);
const statusVals   = Object.values(STATUS_DATA).map(Number);
const statusColors = statusKeys.map(k => STATUS_COLORS[k] || '#888');

if (statusKeys.length > 0) {
  new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
      labels: statusKeys.map(k => STATUS_LABELS_PT[k] || k),
      datasets: [{ data: statusVals, backgroundColor: statusColors, borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: { legend: { display: false } }
    }
  });

  const legend = document.getElementById('statusLegend');
  statusKeys.forEach((k, i) => {
    const row = document.createElement('div');
    row.className = 'legend-row';
    row.innerHTML = `<span class="legend-dot" style="background:${statusColors[i]}"></span>
      <span class="legend-label">${STATUS_LABELS_PT[k]||k}</span>
      <span class="legend-val">${statusVals[i]}</span>`;
    legend.appendChild(row);
  });
} else {
  document.getElementById('chartStatus').parentElement.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:140px;color:var(--muted);font-size:13px">Sem pedidos hoje</div>';
}

// ── Poll novos pedidos ──────────────────────────────────
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
      if(d.novos>0){
        tocarSom();
        if(Notification.permission==='granted') new Notification('🍔 Novo pedido!',{body:d.novos+' pedido(s) novo(s)!'});
        _ultimoId=d.ultimo_id;
        location.reload();
      }
    }).catch(()=>{});
}
if('Notification' in window && Notification.permission==='default') Notification.requestPermission();
setInterval(verificarNovos, 15000);
</script>
</body>
</html>

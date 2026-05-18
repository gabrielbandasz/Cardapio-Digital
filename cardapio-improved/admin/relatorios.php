<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$periodo = $_GET['periodo'] ?? 'hoje';
$whereMap = [
  'hoje'    => "DATE(created_at) = CURDATE()",
  'semana'  => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
  'mes'     => "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
  'total'   => "1=1",
];
$where = $whereMap[$periodo] ?? $whereMap['hoje'];

$stats = $pdo->query("SELECT COUNT(*) AS total_pedidos, COALESCE(SUM(total),0) AS faturamento, COALESCE(AVG(total),0) AS ticket_medio FROM pedidos WHERE $where AND status != 'cancelado'")->fetch();
$porStatus = $pdo->query("SELECT status, COUNT(*) AS cnt FROM pedidos WHERE $where GROUP BY status")->fetchAll();
$porPagamento = $pdo->query("SELECT pagamento, COUNT(*) AS cnt, SUM(total) AS total FROM pedidos WHERE $where AND status != 'cancelado' GROUP BY pagamento ORDER BY total DESC")->fetchAll();
$topProdutos = $pdo->query("SELECT pi.nome_produto, SUM(pi.quantidade) AS qtd, SUM(pi.subtotal) AS receita FROM pedido_itens pi JOIN pedidos p ON p.id=pi.pedido_id WHERE {$where} AND p.status != 'cancelado' GROUP BY pi.nome_produto ORDER BY qtd DESC LIMIT 10")->fetchAll();
$porHora = $pdo->query("SELECT HOUR(created_at) AS hora, COUNT(*) AS cnt FROM pedidos WHERE $where GROUP BY hora ORDER BY hora")->fetchAll();
$inativos = $pdo->query("SELECT nome, whatsapp, total_pedidos, total_gasto, ultimo_pedido FROM clientes WHERE ultimo_pedido < DATE_SUB(NOW(), INTERVAL 30 DAY) OR ultimo_pedido IS NULL ORDER BY ultimo_pedido ASC LIMIT 15")->fetchAll();
$topClientes = $pdo->query("SELECT nome, whatsapp, total_pedidos, total_gasto FROM clientes ORDER BY total_gasto DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Relatórios — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.chart-bar-wrap { display:flex; flex-direction:column; gap:6px; }
.chart-bar-row { display:flex; align-items:center; gap:8px; font-size:13px; }
.chart-bar-label { width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:right; color:var(--muted); }
.chart-bar { height:22px; background:var(--primary); border-radius:4px; min-width:4px; transition:width .5s; }
.chart-bar-val { white-space:nowrap; font-weight:600; color:var(--primary); font-size:12px; }
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
    <div class="section-title" style="margin:0">📈 Relatórios</div>
    <?php foreach(['hoje'=>'Hoje','semana'=>'7 dias','mes'=>'Este mês','total'=>'Tudo'] as $k=>$l): ?>
      <a href="relatorios.php?periodo=<?=$k?>" class="btn <?=$periodo===$k?'btn-primary':'btn-outline'?>" style="padding:6px 14px;font-size:13px"><?=$l?></a>
    <?php endforeach; ?>
  </div>

  <!-- KPIs -->
  <div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card"><div class="stat-label">Pedidos</div><div class="stat-value blue"><?= (int)$stats['total_pedidos'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Faturamento</div><div class="stat-value orange"><?= formatar_dinheiro((float)$stats['faturamento']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Ticket médio</div><div class="stat-value green"><?= formatar_dinheiro((float)$stats['ticket_medio']) ?></div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Produtos mais vendidos -->
    <div class="card">
      <div class="section-title" style="margin-bottom:16px">🏆 Produtos mais vendidos</div>
      <?php if (empty($topProdutos)): ?><p style="color:var(--muted);font-size:13px">Sem dados.</p><?php else: ?>
      <?php $maxQtd = max(array_column($topProdutos,'qtd')); ?>
      <div class="chart-bar-wrap">
        <?php foreach($topProdutos as $i=>$p): ?>
        <div class="chart-bar-row">
          <span class="chart-bar-label" title="<?= h($p['nome_produto']) ?>"><?= $i+1 ?>. <?= h(substr($p['nome_produto'],0,16)) ?></span>
          <div class="chart-bar" style="width:<?= round((int)$p['qtd']/$maxQtd*160) ?>px"></div>
          <span class="chart-bar-val"><?= (int)$p['qtd'] ?>x — <?= formatar_dinheiro((float)$p['receita']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Pagamentos -->
    <div class="card">
      <div class="section-title" style="margin-bottom:16px">💳 Por forma de pagamento</div>
      <?php if (empty($porPagamento)): ?><p style="color:var(--muted);font-size:13px">Sem dados.</p><?php else: ?>
      <?php $maxPag = max(array_column($porPagamento,'total')); ?>
      <div class="chart-bar-wrap">
        <?php $icons = ['dinheiro'=>'💵','pix'=>'💸','cartao'=>'💳']; ?>
        <?php foreach($porPagamento as $p): ?>
        <div class="chart-bar-row">
          <span class="chart-bar-label"><?= $icons[$p['pagamento']]??'' ?> <?= h(ucfirst($p['pagamento'])) ?></span>
          <div class="chart-bar" style="width:<?= round((float)$p['total']/$maxPag*160) ?>px;background:#3b82f6"></div>
          <span class="chart-bar-val"><?= (int)$p['cnt'] ?>x — <?= formatar_dinheiro((float)$p['total']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Pedidos por hora -->
    <div class="card">
      <div class="section-title" style="margin-bottom:16px">⏰ Pedidos por hora</div>
      <?php if (empty($porHora)): ?><p style="color:var(--muted);font-size:13px">Sem dados.</p><?php else: ?>
      <?php $maxH = max(array_column($porHora,'cnt')); ?>
      <div class="chart-bar-wrap">
        <?php foreach($porHora as $h): ?>
        <div class="chart-bar-row">
          <span class="chart-bar-label"><?= str_pad($h['hora'],2,'0',STR_PAD_LEFT) ?>:00</span>
          <div class="chart-bar" style="width:<?= round((int)$h['cnt']/$maxH*140) ?>px;background:#8b5cf6"></div>
          <span class="chart-bar-val"><?= (int)$h['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Status dos pedidos -->
    <div class="card">
      <div class="section-title" style="margin-bottom:16px">📊 Por status</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($porStatus as $s): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px">
          <span><?= status_emoji($s['status']) ?> <?= status_label($s['status']) ?></span>
          <span class="badge <?= status_class($s['status']) ?>"><?= (int)$s['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($porStatus)): ?><p style="color:var(--muted);font-size:13px">Sem dados.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top clientes -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px">👑 Top clientes (histórico completo)</div>
    <table class="pedidos-table">
      <thead><tr><th>#</th><th>Cliente</th><th>WhatsApp</th><th>Pedidos</th><th>Total gasto</th></tr></thead>
      <tbody>
        <?php foreach ($topClientes as $i=>$c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= h($c['nome']?:'—') ?></td>
          <td><a href="https://wa.me/<?= preg_replace('/\D/','',$c['whatsapp']) ?>" target="_blank" class="text-primary"><?= h($c['whatsapp']) ?></a></td>
          <td><?= (int)$c['total_pedidos'] ?></td>
          <td class="text-primary fw-bold"><?= formatar_dinheiro((float)$c['total_gasto']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topClientes)): ?><tr><td colspan="5"><p style="text-align:center;color:var(--muted);padding:16px">Sem clientes ainda.</p></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Clientes inativos -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px;color:var(--danger)">⚠️ Clientes que sumiram (+30 dias sem pedir)</div>
    <?php if (empty($inativos)): ?>
      <p style="color:var(--muted);font-size:13px">Nenhum cliente inativo. 🎉</p>
    <?php else: ?>
    <table class="pedidos-table">
      <thead><tr><th>Cliente</th><th>Último pedido</th><th>Total gasto</th><th>Ação</th></tr></thead>
      <tbody>
        <?php foreach ($inativos as $c): ?>
        <?php $dias = $c['ultimo_pedido'] ? floor((time()-strtotime($c['ultimo_pedido']))/86400) : '?'; ?>
        <tr>
          <td><?= h($c['nome']?:'—') ?></td>
          <td style="color:var(--danger)"><?= $c['ultimo_pedido'] ? date('d/m/Y',strtotime($c['ultimo_pedido']))." ($dias dias)" : 'Nunca' ?></td>
          <td><?= formatar_dinheiro((float)$c['total_gasto']) ?></td>
          <td>
            <?php $wa = preg_replace('/\D/','',$c['whatsapp']); $msg = 'Oi '.($c['nome']?:'').'! Sentimos sua falta 😊 Temos novidades especiais pra você. Que tal pedir hoje?'; ?>
            <a href="https://wa.me/<?=$wa?>?text=<?=urlencode($msg)?>" target="_blank" class="btn btn-whatsapp" style="padding:5px 10px;font-size:12px">📲 Chamar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/nav_end.php'; ?>
</body></html>

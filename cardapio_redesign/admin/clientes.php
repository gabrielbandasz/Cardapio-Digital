<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$busca = trim($_GET['busca'] ?? '');
$ordenar = $_GET['ord'] ?? 'total_gasto';
$ordsValidos = ['total_gasto','total_pedidos','ultimo_pedido','created_at'];
if (!in_array($ordenar, $ordsValidos)) $ordenar = 'total_gasto';

$where = ''; $params = [];
if ($busca) { $where = "WHERE nome LIKE ? OR whatsapp LIKE ?"; $params = ["%$busca%","%$busca%"]; }

$stmt = $pdo->prepare("SELECT * FROM clientes $where ORDER BY $ordenar DESC");
$stmt->execute($params); $clientes = $stmt->fetchAll();

// Inativos há mais de 30 dias
$inativos = $pdo->query("SELECT * FROM clientes WHERE ultimo_pedido < DATE_SUB(NOW(), INTERVAL 30 DAY) OR ultimo_pedido IS NULL ORDER BY ultimo_pedido ASC LIMIT 20")->fetchAll();

$statsC = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(total_gasto),0) AS gasto_total, COALESCE(AVG(total_gasto),0) AS gasto_medio FROM clientes")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Clientes — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">
  <div class="section-title">👥 Clientes</div>

  <div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-label">Total de clientes</div><div class="stat-value blue"><?= (int)$statsC['total'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Gasto total (todos)</div><div class="stat-value orange"><?= formatar_dinheiro((float)$statsC['gasto_total']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Ticket médio / cliente</div><div class="stat-value green"><?= formatar_dinheiro((float)$statsC['gasto_medio']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Inativos +30 dias</div><div class="stat-value" style="color:var(--danger)"><?= count($inativos) ?></div></div>
  </div>

  <!-- Busca -->
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input name="busca" class="form-control" value="<?= h($busca) ?>" placeholder="Buscar por nome ou WhatsApp..." style="flex:1">
    <select name="ord" class="form-control" style="width:200px">
      <option value="total_gasto" <?= $ordenar==='total_gasto'?'selected':'' ?>>Maior gasto</option>
      <option value="total_pedidos" <?= $ordenar==='total_pedidos'?'selected':'' ?>>Mais pedidos</option>
      <option value="ultimo_pedido" <?= $ordenar==='ultimo_pedido'?'selected':'' ?>>Último pedido</option>
    </select>
    <button type="submit" class="btn btn-primary">Buscar</button>
  </form>

  <!-- Inativos -->
  <?php if (!empty($inativos) && !$busca): ?>
  <div class="card mb-4" style="border-left:4px solid var(--danger)">
    <div class="section-title" style="margin-bottom:10px;color:var(--danger)">⚠️ Clientes inativos há +30 dias — Chame no WhatsApp!</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach (array_slice($inativos,0,10) as $c): ?>
        <?php $wa = preg_replace('/\D/','',$c['whatsapp']); $dias = $c['ultimo_pedido'] ? floor((time()-strtotime($c['ultimo_pedido']))/86400) : '?'; ?>
        <a href="https://wa.me/<?=$wa?>?text=<?=urlencode('Oi '.($c['nome']?:'').'! Sentimos sua falta 😊 Temos novidades e uma oferta especial pra você. Vem nos visitar!')?>" target="_blank" class="btn btn-outline" style="font-size:13px;padding:6px 12px">
          <?= h($c['nome'] ?: $c['whatsapp']) ?> <span style="color:var(--muted)">(<?=$dias?> dias)</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Lista de clientes -->
  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead><tr><th>Cliente</th><th>WhatsApp</th><th>Pedidos</th><th>Total gasto</th><th>Pontos</th><th>Último pedido</th></tr></thead>
      <tbody>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="6"><div class="empty-state" style="padding:20px"><p>Nenhum cliente cadastrado ainda.<br>Eles aparecem automaticamente quando fazem pedidos.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($clientes as $c): ?>
        <tr>
          <td><strong><?= h($c['nome'] ?: '—') ?></strong></td>
          <td>
            <?php $wa=preg_replace('/\D/','',$c['whatsapp']); ?>
            <a href="https://wa.me/<?=$wa?>" target="_blank" class="text-primary"><?= h($c['whatsapp']) ?></a>
          </td>
          <td><strong><?= (int)$c['total_pedidos'] ?></strong></td>
          <td class="text-primary fw-bold"><?= formatar_dinheiro((float)$c['total_gasto']) ?></td>
          <td>
            <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:99px;font-size:12px;font-weight:700">
              ⭐ <?= (int)$c['pontos'] ?> pts
            </span>
          </td>
          <td style="color:var(--muted);font-size:13px">
            <?= $c['ultimo_pedido'] ? date('d/m/Y',strtotime($c['ultimo_pedido'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
</body></html>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$statusFiltro = $_GET['status'] ?? '';
$statusValidos = ['novo','confirmado','preparo','pronto','entregue','cancelado'];
$where = ''; $params = [];
if ($statusFiltro && in_array($statusFiltro, $statusValidos)) { $where='WHERE p.status=?'; $params=[$statusFiltro]; }
$stmt = $pdo->prepare("SELECT p.id,p.numero,p.nome_cliente,p.tipo_entrega,p.total,p.status,p.pagamento,p.created_at,COUNT(pi.id) AS qtd_itens FROM pedidos p LEFT JOIN pedido_itens pi ON pi.pedido_id=p.id $where GROUP BY p.id ORDER BY p.created_at DESC");
$stmt->execute($params); $pedidos=$stmt->fetchAll();
$filtros=[''=> 'Todos','novo'=>'Novos','confirmado'=>'Confirmados','preparo'=>'Em Preparo','pronto'=>'Prontos','entregue'=>'Entregues','cancelado'=>'Cancelados'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pedidos — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">
  <div class="section-title">Pedidos <span class="text-muted" style="font-size:14px"><?= count($pedidos) ?> resultado<?= count($pedidos)!==1?'s':'' ?></span></div>
  <div class="filtros">
    <?php foreach ($filtros as $val=>$label): ?>
      <a href="pedidos.php<?= $val?'?status='.$val:'' ?>" class="filtro-btn <?= $statusFiltro===$val?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($pedidos)): ?>
    <div class="pedidos-wrap"><div class="empty-state"><div class="icon">📋</div><p>Nenhum pedido encontrado</p></div></div>
  <?php else: ?>
  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead><tr><th>Número</th><th>Cliente</th><th>Entrega</th><th>Pagamento</th><th>Total</th><th>Status</th><th>Data</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
          <td><strong><?= h($p['numero']) ?></strong></td>
          <td><?= h($p['nome_cliente']?:'—') ?></td>
          <td><?= $p['tipo_entrega']==='retirada'?'Retirada':'Entrega' ?></td>
          <td style="font-size:13px"><?= ['dinheiro'=>'💵','pix'=>'💸','cartao'=>'💳'][$p['pagamento']]??'' ?> <?= ucfirst($p['pagamento']) ?></td>
          <td class="text-primary fw-bold"><?= formatar_dinheiro((float)$p['total']) ?></td>
          <td><span class="badge <?= status_class($p['status']) ?>"><?= status_label($p['status']) ?></span></td>
          <td style="color:var(--muted);font-size:13px"><?= date('d/m H:i',strtotime($p['created_at'])) ?></td>
          <td><a href="pedido.php?id=<?= $p['id'] ?>" class="btn btn-outline" style="padding:6px 12px;font-size:13px">Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
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

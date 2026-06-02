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

// Contadores por status
$contadores = $pdo->query("SELECT status, COUNT(*) as total FROM pedidos GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$filtros=[''=> 'Todos','novo'=>'Novos','confirmado'=>'Confirmados','preparo'=>'Em Preparo','pronto'=>'Prontos','entregue'=>'Entregues','cancelado'=>'Cancelados'];
$pagtIcons = ['dinheiro'=>'💵','pix'=>'💸','cartao'=>'💳'];
$entregaIcon = ['retirada'=>'🏃','entrega'=>'🛵'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pedidos — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Filtros ── */
.filtros-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
.filtro-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text-soft);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap}
.filtro-btn:hover{border-color:var(--primary);color:var(--primary)}
.filtro-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 2px 12px rgba(232,93,4,.3)}
.filtro-count{background:rgba(255,255,255,.2);border-radius:99px;padding:1px 7px;font-size:11px;font-weight:800}
.filtro-btn:not(.active) .filtro-count{background:var(--border);color:var(--muted)}

/* ── Tabela ── */
.table-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.pedidos-table{width:100%;border-collapse:collapse;font-size:14px}
.pedidos-table thead th{background:var(--surface2);color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
.pedidos-table tbody tr{border-bottom:1px solid var(--border);transition:background .1s}
.pedidos-table tbody tr:last-child{border-bottom:none}
.pedidos-table tbody tr:hover{background:var(--surface2)}
.pedidos-table td{padding:13px 16px;vertical-align:middle}
.pedido-num{font-weight:800;color:var(--text);font-size:15px}
.pedido-num small{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:2px}
.cliente-cell{font-weight:600;color:var(--text)}
.entrega-pill{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-soft);background:var(--surface2);border:1px solid var(--border);padding:3px 10px;border-radius:99px}
.pagto-cell{font-size:13px;color:var(--text-soft)}
.total-cell{font-weight:800;color:var(--primary);font-size:15px;font-family:var(--font-display)}
.data-cell{font-size:12px;color:var(--muted);white-space:nowrap}
.btn-ver{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);background:transparent;color:var(--text-soft);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s}
.btn-ver:hover{border-color:var(--primary);color:var(--primary);background:rgba(232,93,4,.06)}

/* ── Header da seção ── */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.page-title{font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px}
.result-count{font-size:13px;color:var(--muted);font-family:var(--font-body);font-weight:400}
.btn-novo-pedido{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:var(--primary);color:#fff;border-radius:var(--radius-sm);font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 2px 12px rgba(232,93,4,.3);transition:all .15s}
.btn-novo-pedido:hover{background:var(--primary-dark);transform:translateY(-1px)}

/* ── Empty state ── */
.empty-pedidos{text-align:center;padding:64px 20px;color:var(--muted)}
.empty-pedidos .icon{font-size:52px;margin-bottom:16px;opacity:.5}
.empty-pedidos p{font-size:15px}

/* ── Status urgente pulse ── */
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}
.badge-novo .dot,.badge-pronto .dot{display:inline-block;width:7px;height:7px;border-radius:99px;margin-right:4px;animation:pulse-dot 1.4s ease-in-out infinite}
.badge-novo .dot{background:#60a5fa}
.badge-pronto .dot{background:#4ade80}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">

  <div class="page-header">
    <div class="page-title">
      🧾 Pedidos
      <span class="result-count"><?= count($pedidos) ?> resultado<?= count($pedidos)!==1?'s':'' ?></span>
    </div>
    <a href="novo_pedido.php" class="btn-novo-pedido">➕ Novo pedido</a>
  </div>

  <!-- Filtros -->
  <div class="filtros-bar">
    <?php foreach ($filtros as $val=>$label):
      $cnt = $val === '' ? array_sum($contadores) : ($contadores[$val] ?? 0);
    ?>
      <a href="pedidos.php<?= $val?'?status='.$val:'' ?>" class="filtro-btn <?= $statusFiltro===$val?'active':'' ?>">
        <?= $label ?>
        <?php if($cnt > 0): ?><span class="filtro-count"><?= $cnt ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($pedidos)): ?>
    <div class="table-card">
      <div class="empty-pedidos">
        <div class="icon">📋</div>
        <p>Nenhum pedido <?= $statusFiltro ? 'com status <strong>'.h($filtros[$statusFiltro]).'</strong>' : 'encontrado' ?></p>
      </div>
    </div>
  <?php else: ?>
  <div class="table-card">
    <table class="pedidos-table">
      <thead>
        <tr>
          <th>Pedido</th>
          <th>Cliente</th>
          <th>Entrega</th>
          <th>Pagamento</th>
          <th>Total</th>
          <th>Status</th>
          <th>Data</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
          <td>
            <div class="pedido-num">
              #<?= h($p['numero']) ?>
              <small><?= $p['qtd_itens'] ?> ite<?= $p['qtd_itens']==1?'m':'ns' ?></small>
            </div>
          </td>
          <td class="cliente-cell"><?= h($p['nome_cliente'] ?: '—') ?></td>
          <td>
            <span class="entrega-pill">
              <?= $entregaIcon[$p['tipo_entrega']] ?? '📦' ?>
              <?= $p['tipo_entrega']==='retirada' ? 'Retirada' : 'Entrega' ?>
            </span>
          </td>
          <td class="pagto-cell">
            <?= $pagtIcons[$p['pagamento']] ?? '' ?> <?= ucfirst(h($p['pagamento'])) ?>
          </td>
          <td class="total-cell"><?= formatar_dinheiro((float)$p['total']) ?></td>
          <td>
            <span class="badge <?= status_class($p['status']) ?>">
              <?php if(in_array($p['status'],['novo','pronto'])): ?><span class="dot"></span><?php endif; ?>
              <?= status_label($p['status']) ?>
            </span>
          </td>
          <td class="data-cell"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
          <td><a href="pedido.php?id=<?= $p['id'] ?>" class="btn-ver">Ver →</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/nav_end.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: pedidos.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id=?");
$stmt->execute([$id]); $pedido=$stmt->fetch();
if (!$pedido) { header('Location: pedidos.php'); exit; }

$itens = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id=?");
$itens->execute([$id]); $itens=$itens->fetchAll();

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['status'])) {
    $novoStatus = $_POST['status'];
    $validos = ['novo','confirmado','preparo','pronto','entregue','cancelado'];
    if (in_array($novoStatus, $validos)) {
        $pdo->prepare("UPDATE pedidos SET status=? WHERE id=?")->execute([$novoStatus,$id]);
        $pedido['status'] = $novoStatus; $msg='✅ Status atualizado!';
    }
}
$config = $pdo->query('SELECT * FROM config WHERE id=1')->fetch();
$waNum = preg_replace('/\D/','',$config['whatsapp']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pedido <?= h($pedido['numero']) ?> — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:700px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="pedidos.php" class="btn btn-outline" style="padding:8px 14px;font-size:13px">← Voltar</a>
    <h2>Pedido <?= h($pedido['numero']) ?></h2>
    <span class="badge <?= status_class($pedido['status']) ?>" style="font-size:14px;padding:6px 14px"><?= status_emoji($pedido['status']) ?> <?= status_label($pedido['status']) ?></span>
  </div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <!-- Alterar status -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px">Atualizar status</div>
    <form method="POST" style="display:flex;flex-wrap:wrap;gap:8px">
      <?= csrf_field() ?>
      <?php foreach(['novo'=>'📋 Novo','confirmado'=>'✅ Confirmado','preparo'=>'🍳 Em Preparo','pronto'=>'🔔 Pronto','entregue'=>'🛵 Entregue','cancelado'=>'❌ Cancelado'] as $s=>$l): ?>
        <button type="submit" name="status" value="<?=$s?>" class="btn <?=$pedido['status']===$s?'btn-primary':'btn-outline'?>" style="font-size:13px;padding:8px 14px"><?=$l?></button>
      <?php endforeach; ?>
    </form>
  </div>

  <!-- Dados do cliente -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px">👤 Dados do cliente</div>
    <div class="detail-grid">
      <div class="detail-field"><p>Cliente</p><p><strong><?= h($pedido['nome_cliente']?:'—') ?></strong></p></div>
      <div class="detail-field"><p>WhatsApp</p><p><?= $pedido['whatsapp_cliente']?('<a href="https://wa.me/'.preg_replace('/\D/','',$pedido['whatsapp_cliente']).'" target="_blank" class="text-primary">'.h($pedido['whatsapp_cliente']).'</a>'):'—' ?></p></div>
      <div class="detail-field"><p>Entrega</p><p><?= $pedido['tipo_entrega']==='retirada'?'🏠 Retirada':'🛵 Entrega' ?></p></div>
      <div class="detail-field"><p>Pagamento</p><p><?= ['dinheiro'=>'💵 Dinheiro','pix'=>'💸 PIX','cartao'=>'💳 Cartão'][$pedido['pagamento']]??$pedido['pagamento'] ?></p></div>
      <?php if ($pedido['endereco_entrega']): ?>
      <div class="detail-field" style="grid-column:span 2"><p>Endereço</p><p><?= h($pedido['endereco_entrega']) ?></p></div>
      <?php endif; ?>
      <?php if ($pedido['observacoes']): ?>
      <div class="detail-field" style="grid-column:span 2"><p>Observações</p><p><?= h($pedido['observacoes']) ?></p></div>
      <?php endif; ?>
      <div class="detail-field"><p>Data/hora</p><p><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p></div>
    </div>
  </div>

  <!-- Itens -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px">🧾 Itens do pedido</div>
    <?php foreach ($itens as $it): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span><?= $it['quantidade'] ?>x <strong><?= h($it['nome_produto']) ?></strong><?= $it['obs']?' <em style="color:var(--muted)">('.h($it['obs']).')</em>':'' ?></span>
      <span class="text-primary fw-bold"><?= formatar_dinheiro((float)$it['subtotal']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:10px">
      <div class="resumo-linha"><span>Subtotal</span><span><?= formatar_dinheiro((float)$pedido['subtotal']) ?></span></div>
      <?php if ($pedido['taxa_entrega']>0): ?>
      <div class="resumo-linha"><span>Taxa de entrega</span><span><?= formatar_dinheiro((float)$pedido['taxa_entrega']) ?></span></div>
      <?php endif; ?>
      <div class="resumo-total"><span>Total</span><span class="text-primary"><?= formatar_dinheiro((float)$pedido['total']) ?></span></div>
    </div>
  </div>

  <!-- Mensagem WhatsApp -->
  <?php if ($pedido['mensagem_whatsapp']): ?>
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:12px">💬 Enviar status via WhatsApp</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php
      $statusMsgs = [
        'confirmado' => 'Seu pedido '.$pedido['numero'].' foi *confirmado*! ✅ Estamos preparando com amor. 🍳',
        'preparo'    => 'Seu pedido '.$pedido['numero'].' está *em preparo*! 🍳 Em breve fica pronto.',
        'pronto'     => 'Seu pedido '.$pedido['numero'].' está *pronto*! 🔔 '.($pedido['tipo_entrega']==='retirada'?'Pode vir buscar!':'Saiu para entrega agora! 🛵'),
        'entregue'   => 'Pedido '.$pedido['numero'].' *entregue*! ✅ Obrigado pela preferência! 😊',
      ];
      foreach ($statusMsgs as $s => $m):
        if ($pedido['whatsapp_cliente']):
          $waLink = 'https://wa.me/'.preg_replace('/\D/','',$pedido['whatsapp_cliente']).'?text='.rawurlencode($m);
      ?>
        <a href="<?=h($waLink)?>" target="_blank" class="btn btn-outline" style="font-size:12px;padding:7px 12px">
          <?= status_emoji($s) ?> Avisar: <?= status_label($s) ?>
        </a>
      <?php endif; endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
</body></html>

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

$stmtIt = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id=? ORDER BY id");
$stmtIt->execute([$id]); $itens=$stmtIt->fetchAll();

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

/**
 * Decodifica o campo customizacoes (JSON ou string legada).
 * Retorna ['extras'=>[], 'removidos'=>[], 'variacoes'=>[]]
 */
function decodificar_custom(string $raw): array {
    $vazio = ['extras'=>[], 'removidos'=>[], 'variacoes'=>[]];
    if ($raw === '') return $vazio;

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_merge($vazio, $decoded);
    }

    // Formato legado: string simples separada por vírgula
    return array_merge($vazio, ['variacoes' => array_filter(array_map('trim', explode(',', $raw)))]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pedido <?= h($pedido['numero']) ?> — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.item-card {
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 10px;
  background: var(--surface2, var(--surface));
}
.item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  flex-wrap: wrap;
}
.item-nome {
  font-weight: 700;
  font-size: 15px;
}
.item-preco {
  font-weight: 700;
  color: var(--primary);
  white-space: nowrap;
  font-size: 15px;
}
.item-detalhe {
  margin-top: 6px;
  font-size: 13px;
  color: var(--muted);
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.item-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 600;
}
.tag-extra   { background: rgba(34,197,94,.15); color: #22c55e; }
.tag-remover { background: rgba(239,68,68,.15);  color: #ef4444; }
.tag-obs     { background: rgba(148,163,184,.12); color: var(--muted); }
.tag-var     { background: rgba(99,102,241,.15); color: #818cf8; }
.item-tags   { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
.item-sub-row{
  display: flex; justify-content: space-between;
  font-size: 13px; color: var(--muted); margin-top: 4px;
}
</style>
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
      <div class="detail-field" style="grid-column:span 2"><p>Observações gerais</p><p><?= h($pedido['observacoes']) ?></p></div>
      <?php endif; ?>
      <div class="detail-field"><p>Data/hora</p><p><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p></div>
    </div>
  </div>

  <!-- Itens do pedido -->
  <div class="card mb-4">
    <div class="section-title" style="margin-bottom:14px">🧾 Itens do pedido</div>

    <?php foreach ($itens as $it):
      $custom = decodificar_custom($it['customizacoes'] ?? '');
      $extras   = $custom['extras'];
      $removidos= $custom['removidos'];
      $variacoes= $custom['variacoes'];
    ?>
    <div class="item-card">
      <div class="item-header">
        <div>
          <div class="item-nome"><?= h($it['quantidade']) ?>x <?= h($it['nome_produto']) ?></div>
          <div class="item-sub-row">
            <span>Preço unit.: <?= formatar_dinheiro((float)$it['preco_unitario']) ?></span>
          </div>
        </div>
        <div class="item-preco"><?= formatar_dinheiro((float)$it['subtotal']) ?></div>
      </div>

      <?php
        $temDetalhe = !empty($extras) || !empty($removidos) || !empty($variacoes) || !empty($it['obs']);
      ?>
      <?php if ($temDetalhe): ?>
      <div class="item-tags">
        <?php foreach ($extras as $ex): ?>
          <span class="item-tag tag-extra">
            ➕ <?= h(($ex['emoji'] ? $ex['emoji'].' ' : '') . $ex['nome']) ?>
            <?php if (($ex['preco'] ?? 0) > 0): ?>
              <small>(+<?= formatar_dinheiro((float)$ex['preco']) ?>)</small>
            <?php endif; ?>
          </span>
        <?php endforeach; ?>

        <?php foreach ($removidos as $rem): ?>
          <span class="item-tag tag-remover">🚫 Sem <?= h($rem) ?></span>
        <?php endforeach; ?>

        <?php foreach ($variacoes as $var): ?>
          <span class="item-tag tag-var">⚙️ <?= h($var) ?></span>
        <?php endforeach; ?>

        <?php if (!empty($it['obs'])): ?>
          <span class="item-tag tag-obs">📝 <?= h($it['obs']) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Totais -->
    <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
      <div class="resumo-linha"><span>Subtotal</span><span><?= formatar_dinheiro((float)$pedido['subtotal']) ?></span></div>
      <?php if ($pedido['taxa_entrega']>0): ?>
      <div class="resumo-linha"><span>Taxa de entrega</span><span><?= formatar_dinheiro((float)$pedido['taxa_entrega']) ?></span></div>
      <?php endif; ?>
      <?php if ((float)($pedido['cupom_desconto']??0) > 0): ?>
      <div class="resumo-linha" style="color:#22c55e"><span>Desconto <?= $pedido['cupom_codigo'] ? '('.h($pedido['cupom_codigo']).')' : '' ?></span><span>-<?= formatar_dinheiro((float)$pedido['cupom_desconto']) ?></span></div>
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

<?php include __DIR__ . '/nav_end.php'; ?>
</body></html>

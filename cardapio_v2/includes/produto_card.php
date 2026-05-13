<?php /** @var array $p */
$imgSrc     = !empty($p['imagem']) ? $p['imagem'] : '';
// O banco salva como "assets/uploads/arquivo.jpg" — usar direto da raiz
$precoFinal = (float)$p['preco'];
?>
<div class="prod-card"
     data-nome="<?= strtolower(h($p['nome'])) ?>"
     data-produto='<?= htmlspecialchars(json_encode([
       'id'    => (int)$p['id'],
       'nome'  => $p['nome'],
       'preco' => $precoFinal,
       'desc'  => $p['descricao'] ?? '',
       'img'   => $imgSrc,
     ]), ENT_QUOTES) ?>'>
  <?php if ($imgSrc): ?>
    <img class="prod-img" src="<?= h($imgSrc) ?>" alt="<?= h($p['nome']) ?>" loading="lazy"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div class="prod-emoji" style="display:none"><?= h($p['emoji'] ?? '🍽️') ?></div>
  <?php else: ?>
    <div class="prod-emoji"><?= h($p['emoji'] ?? '🍽️') ?></div>
  <?php endif; ?>
  <div class="prod-body">
    <?php if (!empty($p['mais_vendido'])): ?><span class="prod-badge">🔥 Mais pedido</span><?php endif; ?>
    <div class="prod-nome"><?= h($p['nome']) ?></div>
    <?php if (!empty($p['descricao'])): ?><div class="prod-desc"><?= h($p['descricao']) ?></div><?php endif; ?>
    <div class="prod-footer">
      <div>
        <?php if (!empty($p['preco_original']) && (float)$p['preco_original'] > $precoFinal): ?>
          <span class="prod-preco-old"><?= formatar_dinheiro((float)$p['preco_original']) ?></span>
        <?php endif; ?>
        <span class="prod-preco"><?= formatar_dinheiro($precoFinal) ?></span>
      </div>
      <button class="btn-add">+ Adicionar</button>
    </div>
  </div>
</div>

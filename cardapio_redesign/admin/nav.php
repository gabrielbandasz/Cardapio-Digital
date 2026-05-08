<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
$config = isset($pdo) ? $pdo->query("SELECT nome_restaurante, aberto FROM config WHERE id=1")->fetch() : ['nome_restaurante'=>'Cardápio','aberto'=>1];
function navLink($href,$label,$pagina){
    $ativo = basename($href)===$pagina ? ' active' : '';
    return "<a href=\"$href\" class=\"nav-link$ativo\">$label</a>";
}
?>
<nav class="admin-nav">
  <div class="admin-nav-inner">
    <a href="dashboard.php" class="admin-nav-brand">
      🍽️ <span><?= htmlspecialchars($config['nome_restaurante'] ?: 'Cardápio') ?></span>
    </a>
    <?= navLink('dashboard.php','📊 Painel',$paginaAtual) ?>
    <?= navLink('pedidos.php','🧾 Pedidos',$paginaAtual) ?>
    <?= navLink('novo_pedido.php','➕ Novo',$paginaAtual) ?>
    <?= navLink('produtos.php','🍽 Produtos',$paginaAtual) ?>
    <?= navLink('clientes.php','👥 Clientes',$paginaAtual) ?>
    <?= navLink('cupons.php','🎟 Cupons',$paginaAtual) ?>
    <?= navLink('zonas_entrega.php','🗺 Frete',$paginaAtual) ?>
    <?= navLink('relatorios.php','📈 Relatórios',$paginaAtual) ?>
    <?= navLink('configuracoes.php','⚙️ Config',$paginaAtual) ?>
    <div class="nav-spacer"></div>
    <span class="<?= $config['aberto'] ? 'badge-aberto' : 'badge-fechado' ?>" style="flex-shrink:0">
      <?= $config['aberto'] ? '● Aberto' : '● Fechado' ?>
    </span>
    <a href="../index.php" target="_blank" class="btn btn-outline btn-sm" style="flex-shrink:0">👁 Loja</a>
    <button id="darkToggle" class="btn btn-outline btn-sm" aria-label="Alternar tema" style="flex-shrink:0">🌙 Tema</button>
    <a href="logout.php" class="btn btn-danger btn-sm" style="flex-shrink:0">Sair</a>
  </div>
</nav>

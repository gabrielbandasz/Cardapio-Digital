<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
$config = isset($pdo) ? $pdo->query("SELECT nome_restaurante, aberto FROM config WHERE id=1")->fetch() : ['nome_restaurante'=>'Cardápio','aberto'=>1];
$links = [
  ['dashboard.php',  '📊', 'Painel'],
  ['pedidos.php',    '🧾', 'Pedidos'],
  ['kanban.php',     '📋', 'Kanban'],
  ['novo_pedido.php','➕', 'Novo pedido'],
  ['produtos.php',   '🍽', 'Produtos'],
  ['ingredientes.php','🥬','Ingredientes'],
  ['clientes.php',   '👥', 'Clientes'],
  ['cupons.php',     '🎟', 'Cupons'],
  ['zonas_entrega.php','🗺','Frete'],
  ['relatorios.php', '📈', 'Relatórios'],
  ['configuracoes.php','⚙️','Config'],
];
?>
<style>
/* ── Sidebar ── */
:root{--sidebar:220px}
.sidebar{
  position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
  background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;z-index:200;
  transition:transform .25s cubic-bezier(.4,0,.2,1);
}
.sidebar-brand{
  display:flex;align-items:center;gap:10px;padding:20px 16px 16px;
  font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--text);
  border-bottom:1px solid var(--border);text-decoration:none;flex-shrink:0;
}
.sidebar-brand span{color:var(--primary)}
.sidebar-brand small{display:block;font-size:11px;font-weight:400;color:var(--muted);font-family:var(--font-body)}
.sidebar-nav{flex:1;overflow-y:auto;padding:10px 8px;}
.sidebar-nav::-webkit-scrollbar{width:0}
.s-link{
  display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;
  font-size:13px;font-weight:600;color:var(--text-soft);text-decoration:none;
  transition:all .15s;margin-bottom:2px;white-space:nowrap;
}
.s-link:hover{background:var(--surface2);color:var(--text)}
.s-link.active{background:rgba(232,93,4,.12);color:var(--primary);border:1px solid rgba(232,93,4,.2)}
.s-link .s-icon{font-size:18px;width:24px;text-align:center;flex-shrink:0}
.sidebar-footer{padding:12px 8px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px}
.sidebar-status{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--surface2);border-radius:10px;font-size:12px;font-weight:700}
.sidebar-footer-btns{display:flex;gap:6px}
.sidebar-footer-btns a,.sidebar-footer-btns button{flex:1;text-align:center;padding:8px 6px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--font-body);text-decoration:none;border:1.5px solid var(--border);background:transparent;color:var(--text-soft);transition:all .15s}
.sidebar-footer-btns a:hover,.sidebar-footer-btns button:hover{border-color:var(--primary);color:var(--primary)}
.sidebar-footer-btns .btn-sair{border-color:rgba(239,68,68,.3);color:var(--danger)}
.sidebar-footer-btns .btn-sair:hover{background:rgba(239,68,68,.1)}

/* ── Layout com sidebar ── */
.admin-layout{margin-left:var(--sidebar);min-height:100vh}

/* ── Topbar mobile ── */
.topbar{
  display:none;position:sticky;top:0;z-index:150;
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:12px 16px;align-items:center;justify-content:space-between;
}
.topbar-brand{font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--text)}
.topbar-brand span{color:var(--primary)}
.hamburger{background:var(--surface2);border:1px solid var(--border);border-radius:8px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;color:var(--text)}

/* ── Overlay mobile ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:190;backdrop-filter:blur(2px)}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0);box-shadow:8px 0 32px rgba(0,0,0,.4)}
  .admin-layout{margin-left:0}
  .topbar{display:flex}
  .sidebar-overlay.open{display:block}
}
</style>

<!-- Sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <a href="dashboard.php" class="sidebar-brand">
    🍽️ <div><div><span><?= htmlspecialchars($config['nome_restaurante'] ?: 'Cardápio') ?></span></div><small>Painel Admin</small></div>
  </a>
  <nav class="sidebar-nav">
    <?php foreach($links as [$href,$icon,$label]): ?>
      <a href="<?= $href ?>" class="s-link <?= basename($href)===$paginaAtual?'active':'' ?>">
        <span class="s-icon"><?= $icon ?></span><?= $label ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-status">
      <span><?= $config['aberto'] ? '🟢 Loja aberta' : '🔴 Loja fechada' ?></span>
    </div>
    <div class="sidebar-footer-btns">
      <a href="../index.php" target="_blank">👁 Loja</a>
      <button id="darkToggle" aria-label="Alternar tema">🌙</button>
      <a href="logout.php" class="btn-sair">Sair</a>
    </div>
  </div>
</aside>

<!-- Topbar mobile -->
<div class="topbar">
  <button class="hamburger" onclick="openSidebar()">☰</button>
  <div class="topbar-brand">🍽️ <span><?= htmlspecialchars($config['nome_restaurante'] ?: 'Cardápio') ?></span></div>
  <button id="darkToggleMobile" aria-label="Alternar tema" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;width:38px;height:38px;cursor:pointer;font-size:16px;color:var(--text)">🌙</button>
</div>

<!-- Wrapper que empurra conteúdo -->
<div class="admin-layout">
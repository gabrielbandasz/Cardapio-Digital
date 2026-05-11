<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$lojaAberta = (bool)$config['aberto'];
$promoAtiva = (bool)($config['promo_ativa'] ?? 0) && (!$config['promo_fim'] || strtotime($config['promo_fim']) > time());
$cor = $config['cor_primaria'] ?? '#e85d04';
$slug = $_GET['slug'] ?? null;

if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM config WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $config = $stmt->fetch();
    if (!$config) { http_response_code(404); die('Restaurante não encontrado.'); }
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome")->fetchAll();
$produtosRaw = $pdo->query("SELECT p.*, c.nome AS cat_nome FROM produtos p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.disponivel=1 ORDER BY c.nome, p.nome")->fetchAll();

$porCategoria = [];
foreach ($produtosRaw as $p) { $porCategoria[$p['categoria_id'] ?? 0][] = $p; }
$maisVendidos = array_filter($produtosRaw, fn($p) => (int)$p['mais_vendido']);

$pedidosAtivos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo') AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)")->fetchColumn();
$tempoBase = (int)($config['tempo_preparo_base'] ?? 30);
$tempoLabel = $config['modo_pico'] ? ($config['pico_tempo'] ?? '60 min') : ($tempoBase + $pedidosAtivos * (int)($config['tempo_preparo_por_pedido'] ?? 5)) . '–' . ($tempoBase + $pedidosAtivos * (int)($config['tempo_preparo_por_pedido'] ?? 5) + 15) . ' min';

$zonas = (int)($config['frete_por_zona'] ?? 0) ? $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1 ORDER BY taxa")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($config['nome_restaurante']) ?></title>
<script>if(localStorage.getItem('darkMode')==='0')document.documentElement.setAttribute('data-theme','light');</script>  <!-- ← AQUI -->
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --p:<?= $cor ?>;--pd:#c44d02;--bg:#f5f5f5;--card:#fff;
  --text:#1a1a1a;--muted:#666;--border:#e0e0e0;--r:14px;
}
[data-theme=dark]{--bg:#111;--card:#1e1e1e;--text:#f0f0f0;--muted:#999;--border:#333}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{color:inherit;text-decoration:none}

/* HEADER */
.hd{background:var(--p);color:#fff;padding:18px 0}
.hd-inner{max-width:720px;margin:0 auto;padding:0 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.hd-title{font-size:24px;font-weight:800;line-height:1.2}
.hd-sub{opacity:.85;font-size:14px;margin-top:4px}
.hd-meta{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:13px;flex-wrap:wrap}
.hd-right{display:flex;align-items:flex-start;gap:8px;flex-shrink:0}
.pill{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:12px;font-weight:700;white-space:nowrap}
.pill-tempo{background:rgba(0,0,0,.2);color:#fff}
.pill-aberto{background:#16a34a;color:#fff}
.pill-fechado{background:#374151;color:#fff}
.icon-btn{background:rgba(0,0,0,.35);border:none;border-radius:99px;padding:7px 14px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;transition:background .15s;white-space:nowrap}
.icon-btn:hover{background:rgba(0,0,0,.55)}

/* AVISO FECHADO */
.closed-bar{background:#374151;color:#fff;text-align:center;padding:10px 16px;font-size:13px;font-weight:600}
.promo-bar{background:linear-gradient(135deg,#ff4500,#ff8c00);color:#fff;padding:10px 16px;font-size:14px;font-weight:700;display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap}
.promo-bar button{background:none;border:none;color:#fff;cursor:pointer;font-size:18px;line-height:1}

/* BUSCA + FILTROS */
.sticky-nav{position:sticky;top:0;z-index:100;background:var(--bg);border-bottom:1px solid var(--border);padding:10px 0}
.wrap{max-width:720px;margin:0 auto;padding:0 16px}
.search-input{width:100%;padding:10px 16px;border:1px solid var(--border);border-radius:99px;font-size:15px;background:var(--card);color:var(--text);outline:none;transition:border-color .15s}
.search-input:focus{border-color:var(--p)}
.cats{display:flex;gap:8px;overflow-x:auto;padding:8px 0 4px;scrollbar-width:none}
.cats::-webkit-scrollbar{display:none}
.cat-btn{flex-shrink:0;padding:7px 16px;border-radius:99px;border:2px solid var(--border);background:var(--card);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap}
.cat-btn.ativo,.cat-btn:hover{background:var(--p);color:#fff;border-color:var(--p)}

/* SEÇÕES */
.main{max-width:720px;margin:0 auto;padding:16px}
.sec-title{font-size:17px;font-weight:800;margin:20px 0 10px;display:flex;align-items:center;gap:6px}

/* CARDS DE PRODUTO */
.prod-list{display:flex;flex-direction:column;gap:10px}
.prod-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);display:flex;gap:12px;overflow:hidden;cursor:pointer;transition:box-shadow .15s,transform .1s}
.prod-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-1px)}
.prod-card:active{transform:scale(.99)}
.prod-img{width:100px;height:100px;object-fit:cover;flex-shrink:0}
.prod-emoji{width:100px;height:100px;display:flex;align-items:center;justify-content:center;font-size:40px;flex-shrink:0;background:var(--bg)}
.prod-body{flex:1;padding:12px 12px 12px 0;display:flex;flex-direction:column;justify-content:space-between;min-width:0}
.prod-badge{display:inline-block;background:linear-gradient(135deg,#ff4500,#ff8c00);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;margin-bottom:4px}
.prod-nome{font-size:15px;font-weight:700;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.6em}
.prod-desc{font-size:12px;color:var(--muted);margin-top:3px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.8em}
.prod-footer{display:flex;align-items:center;justify-content:space-between;margin-top:8px;gap:8px}
.prod-preco{font-size:17px;font-weight:800;color:var(--p)}
.prod-preco-old{font-size:12px;color:var(--muted);text-decoration:line-through;margin-right:4px}
.btn-add{background:var(--p);color:#fff;border:none;border-radius:99px;padding:7px 18px;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0;transition:background .15s,transform .1s}
.btn-add:hover{background:var(--pd)}
.btn-add:active{transform:scale(.95)}

/* CARRINHO FAB */
.cart-fab{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--p);color:#fff;padding:14px 28px;border-radius:99px;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 24px rgba(0,0,0,.25);z-index:200;display:none;align-items:center;gap:8px;white-space:nowrap;border:none;transition:transform .15s,box-shadow .15s}
.cart-fab:hover{transform:translateX(-50%) translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.3)}

/* SKELETON LOADING */
.skeleton{background:linear-gradient(90deg,#2a2a2a 25%,#333 50%,#2a2a2a 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;border-radius:8px}
[data-theme=light] .skeleton{background:linear-gradient(90deg,#eee 25%,#ddd 50%,#eee 75%);background-size:200% 100%}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.skel-card{display:flex;gap:12px;padding:12px;margin-bottom:10px;background:var(--card);border-radius:14px;border:1px solid var(--border)}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:300;display:flex;align-items:flex-end;justify-content:center}
@media(min-width:560px){.modal-overlay{align-items:center}}
.modal-box{background:var(--card);border-radius:20px 20px 0 0;width:100%;max-width:480px;padding:24px 20px 32px;max-height:90vh;overflow-y:auto;position:relative}
@media(min-width:560px){.modal-box{border-radius:20px}}
.modal-close{position:absolute;top:14px;right:14px;background:var(--bg);border:none;border-radius:50%;width:34px;height:34px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;color:var(--text)}
.modal-img{width:100%;max-height:180px;object-fit:cover;border-radius:12px;margin-bottom:12px}
.modal-nome{font-size:20px;font-weight:800;margin-bottom:4px}
.modal-desc{font-size:13px;color:var(--muted);margin-bottom:12px}
.modal-preco{font-size:24px;font-weight:800;color:var(--p);margin-bottom:16px}
.qtd-row{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.qtd-btn{width:40px;height:40px;border-radius:99px;border:2px solid var(--border);background:var(--card);color:var(--text);font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;transition:border-color .15s}
.qtd-btn:hover{border-color:var(--p)}
.qtd-num{font-size:22px;font-weight:800;min-width:36px;text-align:center}
.obs-input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:10px;font-size:14px;background:var(--card);color:var(--text);margin-bottom:14px;outline:none}
.obs-input:focus{border-color:var(--p)}
.btn-conf{width:100%;padding:16px;background:var(--p);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;transition:background .15s}
.btn-conf:hover{background:var(--pd)}

/* TOAST */
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(10px);background:#1a1a1a;color:#fff;padding:10px 22px;border-radius:99px;font-size:14px;font-weight:600;z-index:400;opacity:0;transition:all .25s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

/* BOAS VINDAS */
.bv-toast{position:fixed;top:80px;right:16px;background:var(--p);color:#fff;padding:10px 18px;border-radius:12px;font-size:14px;font-weight:600;z-index:400;box-shadow:0 4px 16px rgba(0,0,0,.2);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(20px);opacity:0}to{transform:none;opacity:1}}

/* DARK */
[data-theme=dark] .prod-emoji{background:#2a2a2a}
</style>
</head>
<body>

<?php if ($promoAtiva): ?>
<div class="promo-bar" id="promoBanner">
  ⚡ <?= h($config['promo_titulo'] ?? 'Promoção') ?> — <strong><?= (int)$config['promo_desconto'] ?>% OFF</strong>!
  <?php if ($config['promo_fim']): ?>
    ⏳ <span id="promoTimer" data-fim="<?= strtotime($config['promo_fim']) ?>"></span>
  <?php endif; ?>
  <button onclick="this.parentElement.style.display='none'">✕</button>
</div>
<?php endif; ?>

<header class="hd">
  <div class="hd-inner">
    <div>
      <div class="hd-title"><?= h($config['nome_restaurante']) ?></div>
      <?php if ($config['descricao']): ?><div class="hd-sub"><?= h($config['descricao']) ?></div><?php endif; ?>
      <div class="hd-meta">
        <span class="pill pill-tempo">⏱ <?= h($tempoLabel) ?></span>
        <span class="pill <?= $lojaAberta ? 'pill-aberto' : 'pill-fechado' ?>">
          <?= $lojaAberta ? '🟢 Aberto' : '🔴 Fechado' ?>
        </span>
      </div>
    </div>
    <div class="hd-right">
      <button class="icon-btn" id="darkToggle" aria-label="Alternar tema">🌙 Tema</button>
      <button class="icon-btn" id="shareBtn">🔗 Compartilhar</button>
    </div>
  </div>
</header>

<?php if (!$lojaAberta): ?>
<div class="closed-bar">😴 Estamos fechados no momento. <?= $lojaAberta ? '' : ($config['horario_abre'] ?? null ? 'Abrimos às '.$config['horario_abre'] : '') ?></div>
<?php endif; ?>

<div class="sticky-nav">
  <div class="wrap">
    <input type="text" class="search-input" id="searchInput" placeholder="🔍 Buscar pratos...">
    <div class="cats" id="catsNav">
      <button class="cat-btn ativo" data-cat="todos">Todos</button>
      <?php foreach ($categorias as $cat): ?>
        <?php if (!empty($porCategoria[$cat['id']])): ?>
        <button class="cat-btn" data-cat="<?= $cat['id'] ?>"><?= h($cat['nome']) ?></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="main" id="boasVindasArea"></div>
<div id="skeletons" class="main">
  <?php for($i=0;$i<4;$i++): ?>
  <div class="skel-card">
    <div class="skeleton" style="width:100px;height:100px;flex-shrink:0"></div>
    <div style="flex:1;display:flex;flex-direction:column;gap:8px;padding:4px 0">
      <div class="skeleton" style="height:16px;width:60%"></div>
      <div class="skeleton" style="height:12px;width:90%"></div>
      <div class="skeleton" style="height:12px;width:75%"></div>
      <div class="skeleton" style="height:18px;width:30%;margin-top:4px"></div>
    </div>
  </div>
  <?php endfor; ?>
</div>
<div class="main" id="menuMain">

  <?php if (!empty($maisVendidos)): ?>
  <div class="sec-title">🔥 Mais pedidos hoje</div>
  <div class="prod-list" style="margin-bottom:8px">
    <?php foreach (array_slice($maisVendidos, 0, 3) as $p): ?>
    <?php include __DIR__ . '/includes/produto_card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($categorias as $cat): ?>
    <?php if (empty($porCategoria[$cat['id']])) continue; ?>
    <div class="cat-sec" data-cat="<?= $cat['id'] ?>">
      <div class="sec-title"><?= h($cat['nome']) ?></div>
      <div class="prod-list">
        <?php foreach ($porCategoria[$cat['id']] as $p): ?>
        <?php include __DIR__ . '/includes/produto_card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($porCategoria[0])): ?>
  <div class="cat-sec" data-cat="0">
    <div class="sec-title">Outros</div>
    <div class="prod-list">
      <?php foreach ($porCategoria[0] as $p): ?>
      <?php include __DIR__ . '/includes/produto_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- FAB Carrinho -->
<button class="cart-fab" id="cartFab" onclick="location.href='carrinho.php'">
  🛒 Ver carrinho (<span id="cartCount">0</span>) — <span id="cartTotal">R$ 0,00</span>
</button>

<!-- Modal -->
<div class="modal-overlay" id="modalProduto" style="display:none" onclick="if(event.target===this)fecharModal()">
  <div class="modal-box">
    <button class="modal-close" onclick="fecharModal()">✕</button>
    <img id="modalImg" class="modal-img" src="" alt="" style="display:none">
    <div class="modal-nome" id="modalNome"></div>
    <div class="modal-desc" id="modalDesc"></div>
    <div class="modal-preco" id="modalPreco"></div>
    <div id="variacoesWrap"></div>
    <div class="qtd-row">
      <button class="qtd-btn" onclick="adjQtd(-1)">−</button>
      <div class="qtd-num" id="modalQtd">1</div>
      <button class="qtd-btn" onclick="adjQtd(1)">+</button>
    </div>
    <input type="text" id="modalObs" class="obs-input" placeholder="Alguma observação? (sem cebola, etc.)">
    <button class="btn-conf" id="modalConfBtn" onclick="confirmarModal()">
      🛒 Adicionar — <span id="modalBtnPreco"></span>
    </button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const LOJA = {
  aberta: <?= $lojaAberta ? 'true' : 'false' ?>,
  minPedido: <?= (float)($config['pedido_minimo'] ?? 0) ?>,
  taxaEntrega: <?= (float)($config['taxa_entrega'] ?? 0) ?>,
  fretePorZona: <?= (int)($config['frete_por_zona'] ?? 0) ?>,
  zonas: <?= json_encode(array_map(fn($z) => ['nome'=>$z['nome'],'bairros'=>$z['bairros'],'taxa'=>(float)$z['taxa']], $zonas)) ?>,
  promoAtiva: <?= $promoAtiva ? 'true' : 'false' ?>,
  promoDesconto: <?= (float)($config['promo_desconto'] ?? 0) ?>,
  pixChave: <?= json_encode($config['pix_chave'] ?? '') ?>,
  pixTipo: <?= json_encode($config['pix_tipo'] ?? '') ?>,
  pixNome: <?= json_encode($config['pix_nome'] ?? '') ?>,
};
// aliases de compatibilidade
const addToCart = (...args) => addItem(...args);
const updateCartBtn = () => atualizarBotaoCarrinho();
</script>
<script src="assets/js/cart.js"></script>
<script>

// ── Clique nos cards de produto (event delegation) ────────
document.getElementById('menuMain').addEventListener('click', e => {
  const card = e.target.closest('.prod-card');
  if (!card) return;
  try {
    const d = JSON.parse(card.dataset.produto);
    abrirProduto(d.id, d.nome, d.preco, d.desc, d.img);
  } catch(err) { console.error('prod-card parse error', err); }
});

// ── Filtros ───────────────────────────────────────────────
document.getElementById('catsNav').addEventListener('click', e => {
  const btn = e.target.closest('.cat-btn'); if (!btn) return;
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('ativo'));
  btn.classList.add('ativo');
  const cat = btn.dataset.cat;
  document.querySelectorAll('.cat-sec').forEach(s => {
    s.style.display = cat === 'todos' || s.dataset.cat === cat ? '' : 'none';
  });
});

let _debounce;
document.getElementById('searchInput').addEventListener('input', e => {
  clearTimeout(_debounce);
  _debounce = setTimeout(() => {
    const q = e.target.value.toLowerCase();
  document.querySelectorAll('.prod-card').forEach(c => {
    const txt = c.dataset.nome || '';
    c.style.display = !q || txt.includes(q) ? '' : 'none';
  });
  document.querySelectorAll('.cat-sec').forEach(s => {
    const vis = [...s.querySelectorAll('.prod-card')].some(c => c.style.display !== 'none');
    s.style.display = vis ? '' : 'none';
  });
  // Empty state
  const totalVis = [...document.querySelectorAll('.prod-card')].filter(c => c.style.display !== 'none').length;
  let emptyEl = document.getElementById('searchEmpty');
  if (!emptyEl) {
    emptyEl = document.createElement('div');
    emptyEl.id = 'searchEmpty';
    emptyEl.style.cssText = 'text-align:center;padding:40px 20px;color:var(--muted)';
    emptyEl.innerHTML = '<div style="font-size:48px;margin-bottom:12px">🔍</div><div style="font-size:16px;font-weight:700;margin-bottom:8px">Nenhum resultado</div><div style="font-size:14px;margin-bottom:16px">Tente outro termo ou veja todas as categorias.</div><button onclick="document.getElementById(\'searchInput\').value=\'\';document.getElementById(\'searchInput\').dispatchEvent(new Event(\'input\'))" style="background:var(--p);color:#fff;border:none;border-radius:99px;padding:9px 22px;font-size:14px;font-weight:700;cursor:pointer">Ver tudo</button>';
    document.getElementById('menuMain').appendChild(emptyEl);
  }
  emptyEl.style.display = q && totalVis === 0 ? 'block' : 'none';
  }, 250);
});

// ── Compartilhar ──────────────────────────────────────────
document.getElementById('shareBtn').onclick = () => {
  if (navigator.share) navigator.share({ title: document.title, url: location.href });
  else { navigator.clipboard.writeText(location.href).then(() => toast('🔗 Link copiado!')); }
};

// ── Boas-vindas ───────────────────────────────────────────
(function() {
  const nome = localStorage.getItem('clienteNome');
  if (!nome) return;
  const el = document.createElement('div');
  el.className = 'bv-toast';
  el.textContent = '👋 Bem-vindo de volta, ' + nome + '!';
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
})();

// ── Timer promoção ────────────────────────────────────────
(function() {
  const el = document.getElementById('promoTimer');
  if (!el) return;
  const fim = parseInt(el.dataset.fim) * 1000;
  function tick() {
    const r = fim - Date.now();
    if (r <= 0) { el.textContent = 'Encerrada'; return; }
    const h = Math.floor(r/3600000), m = Math.floor((r%3600000)/60000), s = Math.floor((r%60000)/1000);
    el.textContent = h + 'h ' + m + 'm ' + s + 's';
    setTimeout(tick, 1000);
  }
  tick();
})();
const sk = document.getElementById('skeletons');
if (sk) sk.remove();
updateCartBtn();
</script>
<footer style="text-align:center;padding:20px;font-size:12px;color:var(--muted);border-top:1px solid var(--border);">
  <?= h($config['nome_restaurante']) ?> · Cardápio Digital
  <a href="admin/login.php" style="margin-left:16px;color:var(--border);font-size:11px;" title="Acesso restrito">⚙</a>
</footer>
</body>
</html>
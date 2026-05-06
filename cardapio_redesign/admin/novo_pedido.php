<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$config  = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$produtos = $pdo->query("SELECT p.*, c.nome AS cat_nome FROM produtos p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.disponivel=1 ORDER BY c.nome, p.nome")->fetchAll();
$categorias = [];
foreach ($produtos as $p) {
    $categorias[$p['cat_nome'] ?? 'Outros'][] = $p;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar') {
    $nomeCli   = trim($_POST['nome_cliente'] ?? '');
    $waCliente = preg_replace('/\D/', '', $_POST['whatsapp_cliente'] ?? '');
    $tipoEnt   = in_array($_POST['tipo_entrega'] ?? '', ['entrega','retirada']) ? $_POST['tipo_entrega'] : 'retirada';
    $endereco  = trim($_POST['endereco'] ?? '');
    $pagamento = in_array($_POST['pagamento'] ?? '', ['dinheiro','pix','cartao']) ? $_POST['pagamento'] : 'dinheiro';
    $obs       = trim($_POST['observacoes'] ?? '');
    $itensPost = $_POST['itens'] ?? [];

    $itensSalvos = [];
    $subtotal = 0;
    foreach ($itensPost as $prodId => $dados) {
        $qtd = (int)($dados['quantidade'] ?? 0);
        if ($qtd <= 0) continue;
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id=?");
        $stmt->execute([$prodId]); $prod = $stmt->fetch();
        if (!$prod) continue;
        $sub = round((float)$prod['preco'] * $qtd, 2);
        $subtotal += $sub;
        $itensSalvos[] = [
            'produto_id' => $prod['id'],
            'nome'       => $prod['nome'],
            'preco_unit' => (float)$prod['preco'],
            'quantidade' => $qtd,
            'subtotal'   => $sub,
            'obs'        => trim($dados['obs'] ?? ''),
        ];
    }

    if (empty($itensSalvos)) {
        $msg = '⚠️ Adicione pelo menos um item ao pedido.';
    } else {
        $taxaEntrega = $tipoEnt === 'entrega' ? (float)$config['taxa_entrega'] : 0;
        $total = $subtotal + $taxaEntrega;
        $numero = gerar_numero_pedido();

        $pdo->prepare("INSERT INTO pedidos (numero,nome_cliente,whatsapp_cliente,tipo_entrega,endereco_entrega,observacoes,subtotal,taxa_entrega,total,status,pagamento,mensagem_whatsapp,created_at) VALUES (?,?,?,?,?,?,?,?,?,'confirmado',?,?,NOW())")
            ->execute([$numero,$nomeCli,$waCliente,$tipoEnt,$endereco,$obs,$subtotal,$taxaEntrega,$total,$pagamento,$waCliente]);
        $pedidoId = $pdo->lastInsertId();

        foreach ($itensSalvos as $item) {
            $pdo->prepare("INSERT INTO pedido_itens (pedido_id,produto_id,nome_produto,quantidade,preco_unit,subtotal,obs) VALUES (?,?,?,?,?,?,?)")
                ->execute([$pedidoId,$item['produto_id'],$item['nome'],$item['quantidade'],$item['preco_unit'],$item['subtotal'],$item['obs']]);
            $pdo->prepare("UPDATE produtos SET total_vendido=total_vendido+? WHERE id=?")->execute([$item['quantidade'],$item['produto_id']]);
        }

        // CRM
        if ($waCliente) upsert_cliente($pdo, $waCliente, $nomeCli, $total);

        // Montar link WhatsApp para notificar cliente
        $waMsg = "🍔 *Pedido #{$numero}* confirmado!\n\n";
        foreach ($itensSalvos as $it) {
            $waMsg .= "• {$it['quantidade']}x {$it['nome']} — " . formatar_dinheiro($it['subtotal']) . "\n";
            if ($it['obs']) $waMsg .= "  _{$it['obs']}_\n";
        }
        $waMsg .= "\n💰 *Total:* " . formatar_dinheiro($total);
        if ($taxaEntrega > 0) $waMsg .= "\n🛵 *Entrega:* " . formatar_dinheiro($taxaEntrega);
        $waMsg .= "\n💳 *Pagamento:* " . ['dinheiro'=>'Dinheiro','pix'=>'PIX','cartao'=>'Cartão'][$pagamento];
        $waLink = $waCliente ? 'https://wa.me/' . $waCliente . '?text=' . rawurlencode($waMsg) : null;

        header("Location: pedido.php?id={$pedidoId}&novo=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Novo Pedido — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.prod-item {
  display:flex; align-items:center; gap:10px;
  padding:10px 12px; border-radius:10px; border:1px solid var(--border);
  margin-bottom:8px; background:var(--bg); transition:border-color .15s;
}
.prod-item.selecionado { border-color:var(--primary); background:var(--primary-light); }
.prod-item-img { width:44px; height:44px; object-fit:cover; border-radius:8px; flex-shrink:0; }
.prod-item-emoji { width:44px; height:44px; display:flex; align-items:center; justify-content:center; font-size:24px; background:var(--bg-alt); border-radius:8px; flex-shrink:0; }
.prod-item-info { flex:1; min-width:0; }
.prod-item-nome { font-weight:600; font-size:14px; }
.prod-item-preco { color:var(--primary); font-weight:700; font-size:13px; }
.qtd-wrap { display:flex; align-items:center; gap:6px; }
.qtd-btn { width:32px; height:32px; border-radius:8px; border:1px solid var(--border); background:var(--bg-alt); cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; font-weight:700; }
.qtd-input { width:40px; text-align:center; border:1px solid var(--border); border-radius:8px; padding:4px; font-size:14px; font-weight:700; background:var(--bg); color:var(--text); }
.resumo-sticky {
  position:sticky; top:20px; background:var(--bg);
  border:2px solid var(--border); border-radius:16px; padding:20px;
}
.grid-pedido { display:grid; grid-template-columns:1fr 340px; gap:24px; }
@media (max-width:800px) { .grid-pedido { grid-template-columns:1fr; } }
.cat-section { margin-bottom:20px; }
.cat-title { font-size:13px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:1100px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="pedidos.php" class="btn btn-outline" style="padding:8px 14px;font-size:13px">← Pedidos</a>
    <div class="section-title" style="margin:0">➕ Novo pedido manual</div>
  </div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso" style="color:#991b1b;background:#fee2e2"><?= h($msg) ?></div><?php endif; ?>

  <form method="POST" id="formPedido">
      <?= csrf_field() ?>
    <input type="hidden" name="action" value="criar">
    <div class="grid-pedido">

      <!-- ── ESQUERDA: Produtos ── -->
      <div>
        <!-- Busca de produto -->
        <div class="card mb-3">
          <input type="text" id="buscaProd" class="form-control" placeholder="🔍 Buscar produto..." oninput="filtrarProdutos(this.value)">
        </div>

        <?php foreach ($categorias as $catNome => $prods): ?>
        <div class="cat-section" data-cat="<?= h($catNome) ?>">
          <div class="cat-title"><?= h($catNome) ?></div>
          <?php foreach ($prods as $p): ?>
          <div class="prod-item" id="item-<?= $p['id'] ?>" data-nome="<?= strtolower(h($p['nome'])) ?>">
            <?php if (!empty($p['imagem'])): ?>
              <img src="../<?= h($p['imagem']) ?>" alt="" class="prod-item-img">
            <?php else: ?>
              <div class="prod-item-emoji"><?= $p['emoji'] ?? '🍽' ?></div>
            <?php endif; ?>
            <div class="prod-item-info">
              <div class="prod-item-nome"><?= h($p['nome']) ?></div>
              <div class="prod-item-preco"><?= formatar_dinheiro((float)$p['preco']) ?></div>
              <?php if ($p['descricao']): ?><div style="font-size:11px;color:var(--muted)"><?= h(substr($p['descricao'],0,50)) ?></div><?php endif; ?>
            </div>
            <div class="qtd-wrap">
              <button type="button" class="qtd-btn" onclick="mudarQtd(<?= $p['id'] ?>,<?= $p['preco'] ?>,<?= json_encode($p['nome']) ?>,-1)">−</button>
              <input type="number" name="itens[<?= $p['id'] ?>][quantidade]" id="qtd-<?= $p['id'] ?>" class="qtd-input" value="0" min="0" onchange="atualizarDoProduto(<?= $p['id'] ?>,<?= $p['preco'] ?>,<?= json_encode($p['nome']) ?>)">
              <button type="button" class="qtd-btn" onclick="mudarQtd(<?= $p['id'] ?>,<?= $p['preco'] ?>,<?= json_encode($p['nome']) ?>,1)">+</button>
            </div>
          </div>
          <!-- Obs por item (mostrada quando qty > 0) -->
          <div id="obs-<?= $p['id'] ?>" style="display:none;padding:0 0 8px 54px">
            <input type="text" name="itens[<?= $p['id'] ?>][obs]" class="form-control" placeholder="Obs: sem cebola, etc." style="font-size:13px;padding:8px">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ── DIREITA: Resumo + dados cliente ── -->
      <div>
        <div class="resumo-sticky">
          <div class="section-title" style="margin-bottom:14px">🧾 Resumo do pedido</div>

          <!-- Itens selecionados -->
          <div id="resumoItens" style="margin-bottom:12px;min-height:60px">
            <p style="color:var(--muted);font-size:13px;text-align:center">Nenhum item selecionado</p>
          </div>
          <div class="resumo-linha" id="linhaSubtotal" style="display:none"><span>Subtotal</span><span id="vlSubtotal">—</span></div>
          <div class="resumo-linha" id="linhaEntrega" style="display:none"><span>Taxa de entrega</span><span id="vlEntrega">—</span></div>
          <div class="resumo-total" id="linhaTotal" style="display:none"><span>Total</span><span class="text-primary" id="vlTotal">—</span></div>

          <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">

          <!-- Dados do cliente -->
          <div class="form-group">
            <label class="form-label">👤 Nome do cliente</label>
            <input name="nome_cliente" class="form-control" placeholder="Nome do cliente">
          </div>
          <div class="form-group">
            <label class="form-label">📱 WhatsApp</label>
            <input type="tel" name="whatsapp_cliente" class="form-control" placeholder="(11) 99999-9999">
          </div>
          <div class="form-group">
            <label class="form-label">Tipo de entrega</label>
            <div style="display:flex;gap:8px">
              <label style="flex:1;display:flex;align-items:center;gap:6px;padding:10px;border:1px solid var(--border);border-radius:10px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo_entrega" value="retirada" checked onchange="toggleEntrega()"> 🏃 Retirada
              </label>
              <label style="flex:1;display:flex;align-items:center;gap:6px;padding:10px;border:1px solid var(--border);border-radius:10px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo_entrega" value="entrega" onchange="toggleEntrega()"> 🛵 Entrega
              </label>
            </div>
          </div>
          <div id="campoEndereco" style="display:none">
            <div class="form-group">
              <label class="form-label">Endereço</label>
              <textarea name="endereco" class="form-control" rows="2" placeholder="Rua, número, bairro..."></textarea>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">💳 Pagamento</label>
            <select name="pagamento" class="form-control">
              <option value="dinheiro">💵 Dinheiro</option>
              <option value="pix">💸 PIX</option>
              <option value="cartao">💳 Cartão</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">📝 Observações gerais</label>
            <textarea name="observacoes" class="form-control" rows="2" placeholder="Observações gerais..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px" id="btnCriar" disabled>
            ✅ Criar pedido
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
const TAXA = <?= (float)$config['taxa_entrega'] ?>;
let _itens = {}; // { id: { nome, preco, qtd } }
let _tipoEnt = 'retirada';

function mudarQtd(id, preco, nome, delta) {
  const inp = document.getElementById('qtd-' + id);
  const atual = parseInt(inp.value) || 0;
  const novo = Math.max(0, atual + delta);
  inp.value = novo;
  atualizarItem(id, preco, nome, novo);
}

function atualizarDoProduto(id, preco, nome) {
  const inp = document.getElementById('qtd-' + id);
  atualizarItem(id, preco, nome, parseInt(inp.value) || 0);
}

function atualizarItem(id, preco, nome, qtd) {
  const card = document.getElementById('item-' + id);
  const obsDiv = document.getElementById('obs-' + id);
  if (qtd > 0) {
    _itens[id] = { nome, preco, qtd };
    card.classList.add('selecionado');
    if (obsDiv) obsDiv.style.display = 'block';
  } else {
    delete _itens[id];
    card.classList.remove('selecionado');
    if (obsDiv) obsDiv.style.display = 'none';
  }
  renderResumo();
}

function renderResumo() {
  const cont = document.getElementById('resumoItens');
  const keys = Object.keys(_itens);
  const btn = document.getElementById('btnCriar');
  if (!keys.length) {
    cont.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center">Nenhum item selecionado</p>';
    document.getElementById('linhaSubtotal').style.display = 'none';
    document.getElementById('linhaEntrega').style.display = 'none';
    document.getElementById('linhaTotal').style.display = 'none';
    if (btn) btn.disabled = true;
    return;
  }
  let sub = 0;
  cont.innerHTML = keys.map(id => {
    const it = _itens[id]; const st = it.preco * it.qtd; sub += st;
    return `<div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border)">
      <span>${it.qtd}x ${it.nome}</span><span style="color:var(--primary);font-weight:700">${fmt(st)}</span></div>`;
  }).join('');
  const taxa = _tipoEnt === 'entrega' ? TAXA : 0;
  document.getElementById('vlSubtotal').textContent = fmt(sub);
  document.getElementById('vlEntrega').textContent = fmt(taxa);
  document.getElementById('vlTotal').textContent = fmt(sub + taxa);
  document.getElementById('linhaSubtotal').style.display = 'flex';
  document.getElementById('linhaEntrega').style.display = taxa > 0 ? 'flex' : 'none';
  document.getElementById('linhaTotal').style.display = 'flex';
  if (btn) btn.disabled = false;
}

function toggleEntrega() {
  _tipoEnt = document.querySelector('input[name="tipo_entrega"]:checked')?.value || 'retirada';
  document.getElementById('campoEndereco').style.display = _tipoEnt === 'entrega' ? 'block' : 'none';
  renderResumo();
}

function filtrarProdutos(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.prod-item').forEach(el => {
    el.style.display = (!q || el.dataset.nome.includes(q)) ? '' : 'none';
  });
  document.querySelectorAll('.cat-section').forEach(sec => {
    const visivel = [...sec.querySelectorAll('.prod-item')].some(e => e.style.display !== 'none');
    sec.style.display = visivel ? '' : 'none';
  });
}

const fmt = v => 'R$ ' + Number(v).toFixed(2).replace('.', ',');
</script>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
</body></html>

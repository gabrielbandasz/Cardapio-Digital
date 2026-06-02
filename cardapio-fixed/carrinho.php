<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
$config     = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$lojaAberta = loja_aberta($config);
$zonas      = (int)$config['frete_por_zona'] ? $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1 ORDER BY taxa")->fetchAll() : [];
$cor        = h($config['cor_primaria'] ?? '#e85d04');
$tempoEnt   = h($config['tempo_entrega'] ?? '30-50 min');
$promoAtiva = $lojaAberta && $config['promo_ativa'] && (!$config['promo_fim'] || strtotime($config['promo_fim']) > time());
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Carrinho — <?= h($config['nome_restaurante']) ?></title>
<style>
:root {
  --primary: <?= $cor ?>;
  --primary-dark: color-mix(in srgb, <?= $cor ?> 80%, black);
}
</style>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ── Carrinho extra ─────────────────────────────────────── */
.cart-header { background: var(--primary); padding: 0; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,.2); }
.cart-header-inner { max-width: 640px; margin: 0 auto; padding: 0 16px; display: flex; align-items: center; justify-content: space-between; min-height: 52px; gap: 12px; }
.cart-header a, .cart-header button { color: #fff; text-decoration: none; background: rgba(255,255,255,.18); border: none; border-radius: 99px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.cart-header h2 { font-size: 16px; font-weight: 700; color: #fff; flex: 1; text-align: center; }
.wrap { max-width: 640px; margin: 0 auto; padding: 16px 16px 120px; }

/* Itens */
.item-row { display: flex; align-items: center; gap: 10px; padding: 14px 0; border-bottom: 1px solid var(--border, #2a2a2a); }
.item-row:last-child { border-bottom: none; }
.item-info { flex: 1; min-width: 0; }
.item-nome { font-weight: 700; font-size: 15px; }
.item-var  { font-size: 12px; color: var(--muted); }
.item-obs  { font-size: 12px; color: var(--muted); font-style: italic; }
.item-preco { color: var(--primary); font-weight: 800; font-size: 15px; margin-top: 2px; }
.item-ctrl { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.qtd-btn-sm { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--border, #2a2a2a); background: var(--surface2, #1e1e1e); color: var(--text); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.qtd-val { font-weight: 700; min-width: 22px; text-align: center; font-size: 15px; }
.btn-rem  { background: none; border: none; color: var(--muted); font-size: 16px; cursor: pointer; padding: 4px; transition: color .15s; }
.btn-rem:hover { color: var(--danger, #ef4444); }

/* Seção card */
.sec { background: var(--surface, #161616); border: 1px solid var(--border, #2a2a2a); border-radius: 16px; padding: 18px; margin-bottom: 12px; }
.sec-title { font-weight: 700; font-size: 15px; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }

/* Tipo entrega */
.tipo-group { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.tipo-opt { border: 2px solid var(--border, #2a2a2a); border-radius: 12px; padding: 12px; cursor: pointer; text-align: center; transition: border-color .15s, background .15s; }
.tipo-opt.active { border-color: var(--primary); background: var(--primary)18; }
.tipo-opt .tipo-icon { font-size: 24px; margin-bottom: 4px; }
.tipo-opt .tipo-label { font-weight: 700; font-size: 14px; }
.tipo-opt .tipo-sub { font-size: 12px; color: var(--muted); }

/* Pagamento */
.pag-group { display: flex; flex-wrap: wrap; gap: 8px; }
.pag-opt { border: 2px solid var(--border, #2a2a2a); border-radius: 12px; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 600; transition: border-color .15s, background .15s; background: none; color: var(--text); }
.pag-opt.active { border-color: var(--primary); background: var(--primary)18; color: var(--primary); }
.pag-opt.pag-mp { background: #009ee3; color: #fff; border-color: #009ee3; width: 100%; text-align: center; padding: 12px; font-size: 15px; }
.pag-opt.pag-mp.active { background: #0077b6; border-color: #0077b6; }

/* PIX box */
.pix-box { background: var(--surface2, #1e1e1e); border-radius: 10px; padding: 14px; margin-top: 12px; }
.pix-key { display: flex; align-items: center; gap: 8px; background: var(--surface, #161616); border-radius: 8px; padding: 10px 12px; margin-top: 8px; font-family: monospace; font-size: 14px; word-break: break-all; }
.pix-key button { flex-shrink: 0; background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; cursor: pointer; white-space: nowrap; }

/* Resumo */
.resumo-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border, #2a2a2a); font-size: 14px; color: var(--text-soft, #b8b0a8); }
.resumo-row.total { border-bottom: none; border-top: 2px solid var(--border, #2a2a2a); padding-top: 14px; margin-top: 4px; font-weight: 800; font-size: 18px; color: var(--text); }
.resumo-row.total span:last-child { color: var(--primary); }
.resumo-row.desconto { color: var(--primary); }

/* Alerta min */
.min-alerta { background: #fef3c7; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; margin-top: 10px; display: none; }
[data-theme="dark"] .min-alerta { background: #3b2a0a; color: #fbbf24; }

/* Fidelidade */
.fid-box { background: #fef3c7; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; text-align: center; display: none; margin-top: 10px; }
[data-theme="dark"] .fid-box { background: #3b2a0a; color: #fbbf24; }

/* Botão finalizar */
.btn-finalizar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 200; padding: 12px 16px; background: var(--bg, #0d0d0d); border-top: 1px solid var(--border, #2a2a2a); }
.btn-finalizar-inner { max-width: 640px; margin: 0 auto; }
.btn-finalizar button { width: 100%; padding: 16px; font-size: 16px; font-weight: 700; border: none; border-radius: 14px; cursor: pointer; font-family: inherit; background: #25d366; color: #fff; transition: background .15s, transform .15s; }
.btn-finalizar button:hover { background: #1ebe5d; transform: translateY(-1px); }
.btn-finalizar button:disabled { background: var(--muted, #6b6560); cursor: not-allowed; transform: none; }
.btn-finalizar button.mp { background: #009ee3; }
.btn-finalizar button.mp:hover { background: #0077b6; }

/* Vazio */
.empty { text-align: center; padding: 80px 20px; }
.empty .icon { font-size: 64px; margin-bottom: 16px; }
.empty h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
.empty p { color: var(--muted); margin-bottom: 20px; }

/* Fechado */
.closed-bar { background: #374151; color: #fff; text-align: center; padding: 10px 16px; font-size: 13px; font-weight: 600; }

/* Form */
.form-group { margin-bottom: 12px; }
.form-label { font-size: 13px; font-weight: 600; color: var(--text-soft, #b8b0a8); margin-bottom: 6px; display: block; }
.form-control { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border, #2a2a2a); border-radius: 10px; font-size: 15px; background: var(--surface2, #1e1e1e); color: var(--text); font-family: inherit; transition: border-color .15s; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent); }
textarea.form-control { resize: vertical; min-height: 70px; }
[data-theme="light"] .form-control { background: #fff; border-color: #e0ddd8; }

/* Cupom */
.cupom-row { display: flex; gap: 8px; }
.cupom-row input { flex: 1; text-transform: uppercase; font-family: monospace; letter-spacing: 1px; }
.cupom-row button { background: var(--primary); color: #fff; border: none; border-radius: 10px; padding: 0 18px; font-weight: 700; cursor: pointer; font-size: 14px; white-space: nowrap; }

/* Promoção badge */
.promo-badge { background: var(--primary); color: #fff; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
</style>
</head>
<body>

<!-- Header -->
<div class="cart-header">
  <div class="cart-header-inner">
    <a href="menu.php">← Cardápio</a>
    <h2>🛒 Carrinho</h2>
    <button id="darkToggle">🌙 Tema</button>
  </div>
</div>

<?php if (!$lojaAberta): ?>
<div class="closed-bar">🔴 Estamos fechados no momento. <?= $config['horario_abre'] ? 'Abrimos às '.$config['horario_abre'] : '' ?></div>
<?php endif; ?>

<div class="wrap">

  <!-- Vazio -->
  <div id="emptyCart" style="display:none">
    <div class="empty">
      <div class="icon">🛒</div>
      <h3>Carrinho vazio</h3>
      <p>Adicione itens do cardápio para continuar.</p>
      <a href="menu.php" style="display:inline-block;background:var(--primary);color:#fff;padding:12px 28px;border-radius:99px;font-weight:700;text-decoration:none;font-size:15px">Ver cardápio</a>
    </div>
  </div>

  <div id="cartContent">

    <!-- Itens -->
    <div class="sec">
      <div class="sec-title">📋 Itens do pedido</div>
      <div id="cartItems"></div>
    </div>

    <?php if ($promoAtiva): ?>
    <div class="promo-badge">⚡ <?= h($config['promo_titulo']) ?> — <?= (int)$config['promo_desconto'] ?>% OFF aplicado!</div>
    <?php endif; ?>

    <!-- Dados do cliente -->
    <div class="sec">
      <div class="sec-title">👤 Seus dados</div>
      <div class="form-group">
        <label class="form-label">Seu nome *</label>
        <input type="text" id="nomeCliente" class="form-control" placeholder="Como te chamamos?" autocomplete="name">
      </div>
      <div class="form-group">
        <label class="form-label">WhatsApp *</label>
        <input type="tel" id="whatsappCliente" class="form-control" placeholder="(51) 99999-9999" autocomplete="tel">
      </div>
    </div>

    <!-- Entrega -->
    <div class="sec">
      <div class="sec-title">🏠 Como vai receber?</div>
      <div class="tipo-group">
        <div class="tipo-opt active" data-tipo="retirada" onclick="setTipoEntrega('retirada')">
          <div class="tipo-icon">🏃</div>
          <div class="tipo-label">Retirada</div>
          <div class="tipo-sub">No local • Grátis</div>
        </div>
        <div class="tipo-opt" data-tipo="entrega" onclick="setTipoEntrega('entrega')">
          <div class="tipo-icon">🛵</div>
          <div class="tipo-label">Entrega</div>
          <div class="tipo-sub"><?= $tempoEnt ?></div>
        </div>
      </div>

      <div id="camposEntrega" style="display:none;margin-top:14px">
        <?php if (!empty($zonas)): ?>
        <div class="form-group">
          <label class="form-label">Seu bairro</label>
          <select id="bairroSelect" class="form-control" onchange="selecionarZona(this.value)">
            <option value="">— Selecione seu bairro —</option>
            <?php foreach ($zonas as $z): ?>
              <?php foreach (array_filter(array_map('trim', explode(',', $z['bairros']))) as $b): ?>
                <option value="<?= h($b) ?>" data-taxa="<?= $z['taxa'] ?>"><?= h($b) ?> — <?= formatar_dinheiro((float)$z['taxa']) ?></option>
              <?php endforeach; ?>
              <option value="outro_<?= $z['id'] ?>" data-taxa="<?= $z['taxa'] ?>">Outro bairro (<?= h($z['nome']) ?>) — <?= formatar_dinheiro((float)$z['taxa']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Endereço completo</label>
          <textarea id="enderecoEntrega" class="form-control" placeholder="Rua, número, complemento, ponto de referência..." rows="2"></textarea>
        </div>
      </div>
    </div>

    <!-- Pagamento -->
    <div class="sec">
      <div class="sec-title">💳 Pagamento</div>
      <div class="pag-group" id="pagamentoOpcoes">
        <button class="pag-opt active" data-pag="dinheiro" onclick="setPagamento('dinheiro')">💵 Dinheiro</button>
        <?php if ($config['pix_chave']): ?>
        <button class="pag-opt" data-pag="pix" onclick="setPagamento('pix')">💸 PIX</button>
        <?php endif; ?>
        <button class="pag-opt" data-pag="cartao" onclick="setPagamento('cartao')">💳 Cartão na entrega</button>
      </div>

      <?php if ($config['pix_chave']): ?>
      <div id="pixInfo" style="display:none">
        <div class="pix-box">
          <div style="font-weight:700;margin-bottom:4px">Chave PIX <span style="font-size:12px;color:var(--muted);font-weight:400">(<?= h($config['pix_tipo']) ?>)</span></div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px">Favorecido: <?= h($config['pix_nome']) ?></div>
          <div class="pix-key">
            <span style="flex:1"><?= h($config['pix_chave']) ?></span>
            <button onclick="navigator.clipboard.writeText('<?= h($config['pix_chave']) ?>').then(()=>mostrarToast('✅ Chave PIX copiada!'))">📋 Copiar</button>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($config['mp_access_token'])): ?>
      <div style="margin-top:10px">
        <button class="pag-opt pag-mp" data-pag="mercadopago" onclick="setPagamento('mercadopago')">
          💳 Pagar Online — Cartão / PIX / Boleto (Mercado Pago)
        </button>
      </div>
      <div id="mpInfo" style="display:none;margin-top:10px;background:#e8f4fd;color:#0c4a6e;border-radius:10px;padding:12px;font-size:14px">
        🔒 Você será redirecionado para o <strong>Mercado Pago</strong> para concluir o pagamento com segurança.
      </div>
      <?php endif; ?>
    </div>

    <!-- Cupom -->
    <div class="sec">
      <div class="sec-title">🎟 Cupom de desconto</div>
      <div class="cupom-row">
        <input type="text" id="cupomInput" class="form-control" placeholder="CÓDIGO DO CUPOM">
        <button onclick="aplicarCupom()">Aplicar</button>
      </div>
      <div id="cupomFeedback" style="margin-top:8px;font-size:13px"></div>
    </div>

    <!-- Observações -->
    <div class="sec">
      <div class="sec-title">📝 Observações</div>
      <textarea id="observacoes" class="form-control" placeholder="Algum recado para a cozinha? (sem cebola, molho à parte...)" rows="2"></textarea>
    </div>

    <!-- Resumo -->
    <div class="sec">
      <div class="sec-title">💰 Resumo</div>
      <div class="resumo-row"><span>Subtotal</span><span id="resumoSubtotal">—</span></div>
      <div class="resumo-row" id="linhaEntrega" style="display:none"><span>Entrega</span><span id="resumoEntrega">—</span></div>
      <div class="resumo-row desconto" id="linhaPromo" style="display:none"><span>⚡ Promoção</span><span id="resumoPromo">—</span></div>
      <div class="resumo-row desconto" id="linhaCupom" style="display:none"><span>🎟 Cupom</span><span id="resumoCupom">—</span></div>
      <div class="resumo-row total"><span>Total</span><span id="resumoTotal">—</span></div>
      <div class="min-alerta" id="minPedidoAlerta"></div>
      <div class="fid-box" id="fidelidadeMsg"></div>
    </div>

    <!-- Repetir pedido -->
    <div id="repetirPedidoArea" style="display:none;margin-bottom:12px">
      <button onclick="repetirUltimoPedido()" style="width:100%;padding:12px;background:none;border:1.5px solid var(--border);border-radius:12px;color:var(--text);cursor:pointer;font-size:14px;font-weight:600">🔄 Repetir último pedido</button>
    </div>

    <!-- Extras -->
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button onclick="lerPedidoEmVoz()" style="flex:1;padding:10px;background:none;border:1.5px solid var(--border);border-radius:10px;color:var(--text);cursor:pointer;font-size:13px">🔊 Ouvir resumo</button>
      <button onclick="compartilharPedido()" style="flex:1;padding:10px;background:none;border:1.5px solid var(--border);border-radius:10px;color:var(--text);cursor:pointer;font-size:13px">🔗 Compartilhar</button>
    </div>

  </div><!-- /cartContent -->
</div><!-- /wrap -->

<!-- Botão fixo finalizar -->
<div class="btn-finalizar">
  <div class="btn-finalizar-inner">
    <button id="btnFinalizar" onclick="finalizarPedido()">📲 Enviar pedido via WhatsApp</button>
  </div>
</div>

<script>
const LOJA_CONFIG = {
  aberta:              <?= $lojaAberta ? 'true' : 'false' ?>,
  offlineMsg:          <?= json_encode($config['whatsapp_offline_msg'] ?? 'Estamos fechados no momento.') ?>,
  minPedido:           <?= (float)($config['pedido_minimo'] ?? 0) ?>,
  taxaEntrega:         <?= (float)($config['taxa_entrega'] ?? 0) ?>,
  fretePorZona:        <?= (int)($config['frete_por_zona'] ?? 0) ?>,
  zonas:               <?= json_encode(array_map(fn($z) => ['nome'=>$z['nome'],'bairros'=>$z['bairros'],'taxa'=>(float)$z['taxa']], $zonas)) ?>,
  promoAtiva:          <?= $promoAtiva ? 'true' : 'false' ?>,
  promoDesconto:       <?= (float)($config['promo_desconto'] ?? 0) ?>,
  pixChave:            <?= json_encode($config['pix_chave'] ?? '') ?>,
  pixTipo:             <?= json_encode($config['pix_tipo'] ?? '') ?>,
  pixNome:             <?= json_encode($config['pix_nome'] ?? '') ?>,
  fidelidadeAtivo:     <?= (int)($config['fidelidade_ativo'] ?? 0) ?>,
  fidelidadePedidos:   <?= (int)($config['fidelidade_pedidos'] ?? 5) ?>,
  fidelidadeDesconto:  <?= (int)($config['fidelidade_desconto'] ?? 10) ?>,
};
</script>
<script src="assets/js/cart.js"></script>
<script>
// Máscara WhatsApp
const waInput = document.getElementById('whatsappCliente');
if (waInput) {
  waInput.addEventListener('input', e => {
    let v = e.target.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
    else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
    e.target.value = v;
  });
}

// Override tipo-btn para novos botões
function setTipoEntrega(tipo) {
  window._tipoEntrega = tipo;
  document.querySelectorAll('.tipo-opt').forEach(b => {
    b.classList.toggle('active', b.dataset.tipo === tipo);
  });
  const ce = document.getElementById('camposEntrega');
  if (ce) ce.style.display = tipo === 'entrega' ? 'block' : 'none';
  const le = document.getElementById('linhaEntrega');
  if (le) le.style.display = tipo === 'entrega' ? 'flex' : 'none';
  if (typeof atualizarResumo === 'function') atualizarResumo();
}

// Override setPagamento para novos botões
const _setPagOriginal = window.setPagamento;
window.setPagamento = function(pag) {
  window._pagamento = pag;
  document.querySelectorAll('.pag-opt').forEach(b => {
    b.classList.toggle('active', b.dataset.pag === pag);
  });
  const pi = document.getElementById('pixInfo');
  if (pi) pi.style.display = pag === 'pix' ? 'block' : 'none';
  const mi = document.getElementById('mpInfo');
  if (mi) mi.style.display = pag === 'mercadopago' ? 'block' : 'none';
  const btn = document.getElementById('btnFinalizar');
  if (btn) {
    btn.textContent = pag === 'mercadopago' ? '💳 Ir para pagamento' : '📲 Enviar pedido via WhatsApp';
    btn.className = pag === 'mercadopago' ? 'mp' : '';
  }
};
</script>
</body>
</html>

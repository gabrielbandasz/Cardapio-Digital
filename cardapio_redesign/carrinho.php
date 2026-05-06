<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$lojaAberta = loja_aberta($config);
$zonas = (int)$config['frete_por_zona'] ? $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1 ORDER BY taxa")->fetchAll() : [];
$cor = h($config['cor_primaria'] ?? '#e85d04');
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Carrinho — <?= h($config['nome_restaurante']) ?></title>
<style>:root{ --primary:<?= $cor ?>; --primary-light:<?= $cor ?>22; }</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header">
  <div class="header-inner">
    <a href="index.php" class="btn btn-outline" style="padding:8px 16px">← Cardápio</a>
    <h2>🛒 Meu Carrinho</h2>
    <button class="btn-icon" id="darkToggle" title="Dark mode">🌙</button>
  </div>
</header>

<div class="container" style="max-width:600px;padding-top:20px">
  <div id="emptyCart" style="display:none">
    <div class="empty-state">
      <div style="font-size:60px;margin-bottom:12px">🛒</div>
      <h3>Carrinho vazio</h3>
      <p>Adicione itens do cardápio para continuar.</p>
      <a href="index.php" class="btn btn-primary" style="margin-top:12px">Ver cardápio</a>
    </div>
  </div>

  <div id="cartContent">
    <!-- Itens -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">📋 Itens do pedido</div>
      <div id="cartItems"></div>
    </div>

    <!-- Dados do cliente -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">👤 Seus dados</div>
      <div class="form-group">
        <label class="form-label">Seu nome *</label>
        <input type="text" id="nomeCliente" class="form-control" placeholder="Como te chamamos?" required>
      </div>
      <div class="form-group">
        <label class="form-label">WhatsApp (com DDD) *</label>
        <input type="tel" id="whatsappCliente" class="form-control" placeholder="(11) 99999-9999" required>
      </div>
    </div>

    <!-- Tipo de entrega -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">🏠 Entrega ou retirada?</div>
      <div style="display:flex;gap:10px;margin-bottom:12px">
        <button class="btn btn-primary tipo-btn" data-tipo="retirada" id="btnRetirada" onclick="setTipoEntrega('retirada')">🏃 Retirada no local</button>
        <button class="btn btn-outline tipo-btn" data-tipo="entrega" id="btnEntrega" onclick="setTipoEntrega('entrega')">🛵 Entrega</button>
      </div>
      <div id="camposEntrega" style="display:none">
        <?php if (!empty($zonas)): ?>
        <div class="form-group">
          <label class="form-label">Selecione seu bairro</label>
          <select id="bairroSelect" class="form-control" onchange="selecionarZona(this.value)">
            <option value="">-- Escolha seu bairro --</option>
            <?php foreach ($zonas as $z): ?>
              <?php foreach (array_filter(array_map('trim',explode(',',$z['bairros']))) as $b): ?>
                <option value="<?= h($b) ?>" data-taxa="<?= $z['taxa'] ?>"><?= h($b) ?> — <?= formatar_dinheiro((float)$z['taxa']) ?></option>
              <?php endforeach; ?>
              <option value="outro_<?= $z['id'] ?>" data-taxa="<?= $z['taxa'] ?>">Outro bairro da <?= h($z['nome']) ?> — <?= formatar_dinheiro((float)$z['taxa']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Endereço completo</label>
          <textarea id="enderecoEntrega" class="form-control" placeholder="Rua, número, complemento..." rows="2"></textarea>
        </div>
      </div>
    </div>

    <!-- Pagamento -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">💳 Forma de pagamento</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap" id="pagamentoOpcoes">
        <button class="btn btn-primary pag-btn active" data-pag="dinheiro" onclick="setPagamento('dinheiro')">💵 Dinheiro</button>
        <?php if ($config['pix_chave']): ?>
        <button class="btn btn-outline pag-btn" data-pag="pix" onclick="setPagamento('pix')">💸 PIX</button>
        <?php endif; ?>
        <button class="btn btn-outline pag-btn" data-pag="cartao" onclick="setPagamento('cartao')">💳 Cartão</button>
      </div>
      <!-- PIX info -->
      <?php if ($config['pix_chave']): ?>
      <div id="pixInfo" style="display:none;margin-top:14px;background:var(--bg-alt);border-radius:12px;padding:14px">
        <div style="font-weight:700;margin-bottom:8px">💸 Pagamento via PIX</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:8px">Tipo: <?= h($config['pix_tipo']) ?> | Favorecido: <strong><?= h($config['pix_nome']) ?></strong></div>
        <div style="display:flex;align-items:center;gap:8px">
          <code style="flex:1;background:var(--bg);padding:10px;border-radius:8px;font-size:14px;word-break:break-all"><?= h($config['pix_chave']) ?></code>
          <button class="btn btn-primary" onclick="navigator.clipboard.writeText('<?= h($config['pix_chave']) ?>').then(()=>alert('✅ Chave PIX copiada!'))" style="padding:10px 14px">📋 Copiar</button>
        </div>
      </div>
      <?php endif; ?>
      <!-- MP botão -->
      <?php if (!empty($config['mp_access_token'])): ?>
      <div style="margin-top:8px">
        <button class="btn pag-btn" data-pag="mercadopago" onclick="setPagamento('mercadopago')"
                style="background:#009ee3;color:#fff;border:none;width:100%;padding:12px;font-size:15px">
          💳 Pagar Online (Cartão / PIX / Boleto via Mercado Pago)
        </button>
      </div>
      <div id="mpInfo" style="display:none;margin-top:10px;background:#e8f4fd;border-radius:10px;padding:12px;font-size:14px">
        Você será redirecionado para o <strong>Mercado Pago</strong> para concluir o pagamento com segurança.
      </div>
      <?php endif; ?>
    </div>

    <!-- Cupom de desconto -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">🎟 Cupom de desconto</div>
      <div style="display:flex;gap:8px">
        <input type="text" id="cupomInput" class="form-control" placeholder="CÓDIGO DO CUPOM" style="text-transform:uppercase;font-family:monospace">
        <button class="btn btn-primary" onclick="aplicarCupom()" style="white-space:nowrap">Aplicar</button>
      </div>
      <div id="cupomFeedback" style="margin-top:8px;font-size:13px"></div>
    </div>

    <!-- Observações -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">📝 Observações gerais</div>
      <textarea id="observacoes" class="form-control" placeholder="Algum recado para a cozinha?" rows="2"></textarea>
    </div>

    <!-- Resumo + totais -->
    <div class="card mb-4">
      <div class="section-title" style="margin-bottom:12px">💰 Resumo do pedido</div>
      <div class="resumo-linha"><span>Subtotal</span><span id="resumoSubtotal">—</span></div>
      <div class="resumo-linha" id="linhaEntrega" style="display:none"><span>Taxa de entrega</span><span id="resumoEntrega">—</span></div>
      <div class="resumo-linha" id="linhaPromo" style="display:none;color:var(--primary)"><span>⚡ Promoção</span><span id="resumoPromo">—</span></div>
      <div class="resumo-linha" id="linhaCupom" style="display:none;color:var(--primary)"><span>🎟 Cupom</span><span id="resumoCupom">—</span></div>
      <div class="resumo-total"><span>Total</span><span class="text-primary" id="resumoTotal">—</span></div>
      <div id="minPedidoAlerta" style="display:none;margin-top:8px;color:var(--danger);font-size:13px;font-weight:600"></div>
      <!-- Fidelidade -->
      <div id="fidelidadeMsg" style="display:none;margin-top:10px;background:#fef3c7;color:#92400e;padding:10px;border-radius:8px;font-size:13px;font-weight:600;text-align:center"></div>
    </div>

    <!-- Botão enviar -->
    <button class="btn btn-whatsapp" id="btnFinalizar" style="width:100%;padding:18px;font-size:16px" onclick="finalizarPedido()">
      📲 Enviar pedido via WhatsApp
    </button>

    <!-- Áudio do pedido -->
    <div style="margin-top:12px;text-align:center">
      <button class="btn btn-outline" onclick="lerPedidoEmVoz()" style="padding:8px 16px;font-size:13px">🔊 Ouvir resumo do pedido</button>
      <button class="btn btn-outline" onclick="compartilharPedido()" style="padding:8px 16px;font-size:13px;margin-left:8px">🔗 Compartilhar</button>
    </div>

    <!-- Repetir último pedido -->
    <div id="repetirPedidoArea" style="display:none;margin-top:16px">
      <button class="btn btn-outline" style="width:100%;padding:12px" onclick="repetirUltimoPedido()">
        🔄 Repetir último pedido
      </button>
    </div>
  </div>
</div>

<script>
const LOJA_CONFIG = {
  aberta: <?= $lojaAberta ? 'true' : 'false' ?>,
  minPedido: <?= (float)$config['pedido_minimo'] ?>,
  taxaEntrega: <?= (float)$config['taxa_entrega'] ?>,
  fretePorZona: <?= (int)$config['frete_por_zona'] ?>,
  zonas: <?= json_encode(array_map(fn($z)=>['nome'=>$z['nome'],'bairros'=>$z['bairros'],'taxa'=>(float)$z['taxa']],$zonas)) ?>,
  promoAtiva: <?= (loja_aberta($config) && $config['promo_ativa'] && (!$config['promo_fim'] || strtotime($config['promo_fim'])>time())) ? 'true' : 'false' ?>,
  promoDesconto: <?= (float)$config['promo_desconto'] ?>,
  pixChave: <?= json_encode($config['pix_chave']??'') ?>,
  pixTipo: <?= json_encode($config['pix_tipo']??'') ?>,
  pixNome: <?= json_encode($config['pix_nome']??'') ?>,
  fidelidadeAtivo: <?= (int)($config['fidelidade_ativo']??0) ?>,
  fidelidadePedidos: <?= (int)($config['fidelidade_pedidos']??5) ?>,
  fidelidadeDesconto: <?= (int)($config['fidelidade_desconto']??10) ?>,
};
</script>
<script src="assets/js/cart.js"></script>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
</body>
</html>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
define('BASE_URL','./');
$config   = $pdo->query('SELECT * FROM config WHERE id=1')->fetch();
$numero   = $_GET['numero'] ?? '';
$total    = (float)($_GET['total'] ?? 0);
$taxa     = (float)($_GET['taxa'] ?? 0);
$whatsapp = $_GET['whatsapp'] ?? $config['whatsapp'];
$mensagem = $_GET['mensagem'] ?? '';
$waUrl    = 'https://wa.me/'.preg_replace('/\D/','',  $whatsapp).'?text='.rawurlencode($mensagem);
$statusUrl= 'status.php?n='.urlencode($numero);
$shareUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/'.$statusUrl;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pedido Realizado — <?= h($config['nome_restaurante']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display:flex;align-items:center;min-height:100vh;padding:20px 0">
<div class="confirmado-wrap">
  <div class="check-icon">✅</div>
  <h1 style="font-size:24px;margin-bottom:8px">Pedido realizado!</h1>
  <p class="text-muted" style="margin-bottom:4px">Envie pelo WhatsApp para confirmar</p>
  <?php if ($numero): ?>
    <p style="margin-bottom:24px">Pedido: <strong><?= h($numero) ?></strong></p>
  <?php endif; ?>

  <div class="card mb-4" style="text-align:left">
    <div class="resumo-linha"><span>Subtotal</span><span><?= formatar_dinheiro($total - $taxa) ?></span></div>
    <?php if ($taxa > 0): ?>
    <div class="resumo-linha"><span>Taxa de entrega</span><span><?= formatar_dinheiro($taxa) ?></span></div>
    <?php endif; ?>
    <div class="resumo-total"><span>Total</span><span class="text-primary"><?= formatar_dinheiro($total) ?></span></div>
  </div>

  <!-- Status do pedido -->
  <a href="<?= h($statusUrl) ?>" class="btn btn-outline btn-full btn-lg" style="margin-bottom:12px">📦 Acompanhar status do pedido</a>

  <!-- WhatsApp -->
  <?php if ($mensagem && $whatsapp): ?>
  <a href="<?= h($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-full btn-lg" style="margin-bottom:12px" onclick="salvarPedidoLocal()">
    💬 Enviar pelo WhatsApp
  </a>
  <?php endif; ?>

  <!-- Áudio do pedido -->
  <button onclick="ouvirResumo()" class="btn btn-outline btn-full" style="margin-bottom:12px">🔊 Ouvir resumo do pedido</button>

  <!-- Compartilhar -->
  <button onclick="compartilharLink()" class="btn btn-outline btn-full" style="margin-bottom:20px">🔗 Compartilhar status</button>

  <a href="index.php" class="text-muted" style="font-size:14px">← Fazer outro pedido</a>
</div>

<script>
localStorage.removeItem('cardapio_cart');
const NUMERO = '<?= h($numero) ?>';
const TOTAL  = '<?= formatar_dinheiro($total) ?>';
const TAXA   = '<?= formatar_dinheiro($taxa) ?>';
const MENS   = <?= json_encode($mensagem) ?>;
const SHARE_URL = <?= json_encode($shareUrl) ?>;

function salvarPedidoLocal() {
  try {
    const hist = JSON.parse(localStorage.getItem('cardapio_historico') || '[]');
    hist.unshift({ numero: NUMERO, total: '<?= $total ?>', mensagem: MENS, data: new Date().toISOString() });
    localStorage.setItem('cardapio_historico', JSON.stringify(hist.slice(0,5)));
  } catch(e){}
}

function ouvirResumo() {
  if (!window.speechSynthesis) { alert('Seu navegador não suporta síntese de voz.'); return; }
  const texto = MENS.replace(/[*_]/g,'');
  const utter = new SpeechSynthesisUtterance(texto);
  utter.lang = 'pt-BR'; utter.rate = 0.9;
  window.speechSynthesis.speak(utter);
}

function compartilharLink() {
  if (navigator.share) {
    navigator.share({ title: 'Meu pedido', text: 'Acompanhe meu pedido: ' + NUMERO, url: SHARE_URL });
  } else {
    navigator.clipboard.writeText(SHARE_URL).then(()=>alert('Link copiado: ' + SHARE_URL));
  }
}

salvarPedidoLocal();
</script>
<script>if(localStorage.getItem("darkMode")==="0")document.documentElement.setAttribute("data-theme","light");</script>
<script>
(function(){
  const root = document.documentElement;
  const btn  = document.getElementById('darkToggle');
  if (!btn) return;
  const isDark = () => root.getAttribute('data-theme') !== 'light';
  btn.setAttribute('aria-label','Alternar tema');
  btn.textContent = isDark() ? '☀️ Tema' : '🌙 Tema';
  btn.onclick = () => {
    const dark = isDark();
    root.setAttribute('data-theme', dark ? 'light' : 'dark');
    localStorage.setItem('darkMode', dark ? '0' : '1');
    btn.textContent = dark ? '🌙 Tema' : '☀️ Tema';
  };
})();
</script>
</body></html>

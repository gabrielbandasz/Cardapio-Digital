<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$msg = '';
$action = $_POST['action'] ?? '';
$c = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();

if ($action === 'save_geral') {
    $fields = ['nome_restaurante','whatsapp','endereco','descricao','taxa_entrega','pedido_minimo'];
    $vals = array_map(fn($f) => trim($_POST[$f] ?? ''), $fields);
    $pdo->prepare("UPDATE config SET nome_restaurante=?,whatsapp=?,endereco=?,descricao=?,taxa_entrega=?,pedido_minimo=? WHERE id=1")->execute($vals);
    $msg = '✅ Configurações salvas!';
} elseif ($action === 'save_visual') {
    $cor = preg_match('/^#[0-9a-f]{6}$/i', $_POST['cor_primaria']??'') ? $_POST['cor_primaria'] : '#e85d04';
    $pdo->prepare("UPDATE config SET cor_primaria=?,nome_restaurante=? WHERE id=1")->execute([$cor,trim($_POST['nome_restaurante']??$c['nome_restaurante'])]);
    $msg = '✅ Visual atualizado!';
} elseif ($action === 'save_pix') {
    $pdo->prepare("UPDATE config SET pix_chave=?,pix_tipo=?,pix_nome=? WHERE id=1")->execute([trim($_POST['pix_chave']),trim($_POST['pix_tipo']),trim($_POST['pix_nome'])]);
    $msg = '✅ PIX salvo!';
} elseif ($action === 'save_mp') {
    $pdo->prepare("UPDATE config SET mp_access_token=? WHERE id=1")->execute([trim($_POST['mp_access_token'])]);
    $msg = '✅ Mercado Pago salvo!';
} elseif ($action === 'save_promo') {
    $fim = !empty($_POST['promo_fim']) ? date('Y-m-d H:i:s', strtotime($_POST['promo_fim'])) : null;
    $pdo->prepare("UPDATE config SET promo_ativa=?,promo_titulo=?,promo_desconto=?,promo_fim=? WHERE id=1")->execute([(int)($_POST['promo_ativa']??0),trim($_POST['promo_titulo']),(float)$_POST['promo_desconto'],$fim]);
    $msg = '✅ Promoção relâmpago salva!';
} elseif ($action === 'save_horario') {
    $dias = isset($_POST['dias']) && is_array($_POST['dias']) ? implode(',',array_map('intval',$_POST['dias'])) : '1,2,3,4,5,6';
    $pdo->prepare("UPDATE config SET horario_auto=?,horario_abre=?,horario_fecha=?,dias_funcionamento=?,tempo_preparo_base=?,tempo_preparo_por_pedido=? WHERE id=1")
        ->execute([(int)($_POST['horario_auto']??0),$_POST['horario_abre'],$_POST['horario_fecha'],$dias,(int)$_POST['tempo_preparo_base'],(int)$_POST['tempo_preparo_por_pedido']]);
    $msg = '✅ Horário salvo!';
} elseif ($action === 'save_fidelidade') {
    $pdo->prepare("UPDATE config SET fidelidade_ativo=?,fidelidade_pedidos=?,fidelidade_desconto=? WHERE id=1")->execute([(int)($_POST['fidelidade_ativo']??0),(int)$_POST['fidelidade_pedidos'],(int)$_POST['fidelidade_desconto']]);
    $msg = '✅ Fidelidade salva!';
} elseif ($action === 'save_frete') {
    $pdo->prepare("UPDATE config SET frete_por_zona=?,taxa_entrega=?,pedido_minimo=? WHERE id=1")->execute([(int)($_POST['frete_por_zona']??0),(float)$_POST['taxa_entrega'],(float)$_POST['pedido_minimo']]);
    $msg = '✅ Frete salvo!';
} elseif ($action === 'save_slug') {
    $slug = preg_replace('/[^a-z0-9-]/','',$_POST['slug']);
    $pdo->prepare("UPDATE config SET loja_slug=? WHERE id=1")->execute([$slug]);
    $msg = '✅ Link personalizado salvo!';
} elseif ($action === 'toggle') {
    $campo = in_array($_POST['campo'],['aberto','modo_pico']) ? $_POST['campo'] : 'aberto';
    $pdo->prepare("UPDATE config SET $campo = 1 - $campo WHERE id=1")->execute();
    header("Location: configuracoes.php?msg=".urlencode('✅ Atualizado!')); exit;
}

$c = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'];
$diasArr = array_map('intval', explode(',', $c['dias_funcionamento'] ?? '1,2,3,4,5,6'));
$diasNomes = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];
if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Configurações — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:750px">
  <div class="section-title">⚙️ Configurações</div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <!-- Toggles rápidos -->
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="campo" value="aberto">
      <button type="submit" class="btn <?= $c['aberto']?'btn-primary':'btn-danger' ?>" style="font-size:14px">
        <?= $c['aberto']?'🟢 Loja ABERTA — Fechar':'🔴 Loja FECHADA — Abrir' ?>
      </button>
    </form>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="campo" value="modo_pico">
      <button type="submit" class="btn <?= $c['modo_pico']?'btn-primary btn-outline':'btn-outline' ?>" style="font-size:14px">
        🔥 Modo Pico: <?= $c['modo_pico']?'ON':'OFF' ?>
      </button>
    </form>
  </div>

  <!-- Geral -->
  <details class="card mb-4" open>
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">🏪 Dados da loja</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_geral">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Nome do restaurante</label><input name="nome_restaurante" class="form-control" value="<?= h($c['nome_restaurante']) ?>"></div>
        <div class="form-group"><label class="form-label">WhatsApp (com DDD)</label><input name="whatsapp" class="form-control" value="<?= h($c['whatsapp']) ?>" placeholder="5511999999999"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Endereço</label><input name="endereco" class="form-control" value="<?= h($c['endereco']??'') ?>"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Descrição / tagline</label><input name="descricao" class="form-control" value="<?= h($c['descricao']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Taxa de entrega fixa (R$)</label><input type="number" step="0.01" name="taxa_entrega" class="form-control" value="<?= $c['taxa_entrega'] ?>"></div>
        <div class="form-group"><label class="form-label">Pedido mínimo (R$)</label><input type="number" step="0.01" name="pedido_minimo" class="form-control" value="<?= $c['pedido_minimo'] ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary">Salvar dados</button>
    </form>
  </details>

  <!-- Personalização visual -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">🎨 Personalização visual</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_visual">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Nome que aparece no topo</label><input name="nome_restaurante" class="form-control" value="<?= h($c['nome_restaurante']) ?>"></div>
        <div class="form-group">
          <label class="form-label">Cor principal</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="color" name="cor_primaria" class="form-control" value="<?= h($c['cor_primaria'] ?? '#e85d04') ?>" style="width:60px;height:40px;padding:2px;cursor:pointer">
            <input type="text" id="corHex" class="form-control" value="<?= h($c['cor_primaria'] ?? '#e85d04') ?>" placeholder="#e85d04" style="flex:1;font-family:monospace">
          </div>
        </div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">URL do logo (link ou caminho)</label><input name="logo" class="form-control" value="<?= h($c['logo']??'') ?>" placeholder="https://..."></div>
      </div>
      <div class="form-group">
        <div style="background:var(--primary);color:#fff;padding:12px 20px;border-radius:12px;font-weight:700;display:inline-block;font-size:14px" id="corPreview">Prévia da cor — <?= h($c['nome_restaurante']) ?></div>
      </div>
      <button type="submit" class="btn btn-primary">Salvar visual</button>
    </form>
  </details>

  <!-- Link personalizado + QR Code -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">🔗 Link personalizado & QR Code</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_slug">
      <div class="form-group">
        <label class="form-label">Slug da loja</label>
        <div style="display:flex;align-items:center;gap:0">
          <span style="background:var(--bg-alt);border:1px solid var(--border);border-right:0;padding:10px 12px;border-radius:8px 0 0 8px;font-size:13px;color:var(--muted)"><?= $baseUrl ?>/</span>
          <input name="slug" class="form-control" value="<?= h($c['loja_slug']??'meu-cardapio') ?>" style="border-radius:0 8px 8px 0;flex:1" placeholder="ze-burguer">
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-bottom:16px">Salvar link</button>
    </form>
    <div style="margin-top:8px;text-align:center">
      <p style="margin-bottom:8px;font-size:13px;color:var(--muted)">QR Code para imprimir ou colocar na mesa:</p>
      <img src="../api/qrcode.php" alt="QR Code" style="width:180px;height:180px;border:4px solid var(--border);border-radius:12px">
      <br><a href="../api/qrcode.php" download="qrcode-cardapio.png" class="btn btn-outline" style="margin-top:10px;padding:6px 14px;font-size:13px">⬇ Baixar QR Code</a>
    </div>
  </details>

  <!-- PIX -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">💸 PIX</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_pix">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Chave PIX</label><input name="pix_chave" class="form-control" value="<?= h($c['pix_chave']??'') ?>" placeholder="email, CPF, telefone ou chave aleatória"></div>
        <div class="form-group"><label class="form-label">Tipo de chave</label>
          <select name="pix_tipo" class="form-control">
            <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'Email','telefone'=>'Telefone','aleatoria'=>'Chave aleatória'] as $v=>$l): ?>
              <option value="<?=$v?>" <?= ($c['pix_tipo']??'')===$v?'selected':'' ?>><?=$l?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Nome no PIX</label><input name="pix_nome" class="form-control" value="<?= h($c['pix_nome']??'') ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary">Salvar PIX</button>
    </form>
  </details>

  <!-- Mercado Pago -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">💳 Mercado Pago (pagamento online)</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_mp">
      <div class="form-group">
        <label class="form-label">Access Token (Production)</label>
        <input name="mp_access_token" class="form-control" type="password"
               value="<?= h($c['mp_access_token']??'') ?>"
               placeholder="APP_USR-...">
        <small style="color:var(--muted);font-size:12px">
          Obtenha em <a href="https://www.mercadopago.com.br/developers/panel" target="_blank">mercadopago.com.br/developers</a> → Suas aplicações → Credenciais de produção
        </small>
      </div>
      <?php if (!empty($c['mp_access_token'])): ?>
        <div class="alerta alerta-sucesso" style="font-size:13px">✅ Mercado Pago configurado. A opção "Pagar Online" aparecerá no carrinho.</div>
      <?php else: ?>
        <div class="alerta" style="background:#fef9c3;color:#854d0e;font-size:13px">⚠️ Sem token configurado — pagamento online desativado.</div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Salvar Mercado Pago</button>
    </form>
  </details>

  <!-- Horário automático -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">🕐 Horário automático de funcionamento</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_horario">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="horario_auto" value="1" <?= ($c['horario_auto']??0)?'checked':'' ?>> Abrir/fechar automaticamente por horário</label>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Abre às</label><input type="time" name="horario_abre" class="form-control" value="<?= h($c['horario_abre']??'11:00') ?>"></div>
        <div class="form-group"><label class="form-label">Fecha às</label><input type="time" name="horario_fecha" class="form-control" value="<?= h($c['horario_fecha']??'23:00') ?>"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Dias de funcionamento</label>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach ($diasNomes as $num=>$nome): ?>
            <label style="display:flex;align-items:center;gap:4px;background:var(--bg-alt);padding:6px 12px;border-radius:8px;cursor:pointer;font-size:14px">
              <input type="checkbox" name="dias[]" value="<?=$num?>" <?= in_array($num,$diasArr)?'checked':'' ?>> <?=$nome?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Tempo de preparo base (min)</label><input type="number" name="tempo_preparo_base" class="form-control" value="<?= (int)($c['tempo_preparo_base']??30) ?>"></div>
        <div class="form-group"><label class="form-label">+ minutos por pedido ativo</label><input type="number" name="tempo_preparo_por_pedido" class="form-control" value="<?= (int)($c['tempo_preparo_por_pedido']??5) ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary">Salvar horário</button>
    </form>
  </details>

  <!-- Promoção relâmpago -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">⚡ Promoção relâmpago</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_promo">
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="promo_ativa" value="1" <?= ($c['promo_ativa']??0)?'checked':'' ?>> Promoção ativa</label></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Título da promoção</label><input name="promo_titulo" class="form-control" value="<?= h($c['promo_titulo']??'') ?>" placeholder="🔥 Happy Hour!"></div>
        <div class="form-group"><label class="form-label">Desconto (%)</label><input type="number" name="promo_desconto" class="form-control" value="<?= $c['promo_desconto']??10 ?>"></div>
        <div class="form-group"><label class="form-label">Válida até</label><input type="datetime-local" name="promo_fim" class="form-control" value="<?= $c['promo_fim'] ? date('Y-m-d\TH:i',strtotime($c['promo_fim'])) : '' ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary">Salvar promoção</button>
    </form>
  </details>

  <!-- Fidelidade -->
  <details class="card mb-4">
    <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:4px 0">⭐ Programa de fidelidade</summary>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_fidelidade">
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="fidelidade_ativo" value="1" <?= ($c['fidelidade_ativo']??0)?'checked':'' ?>> Programa de fidelidade ativo</label></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">A cada quantos pedidos</label><input type="number" name="fidelidade_pedidos" class="form-control" value="<?= (int)($c['fidelidade_pedidos']??5) ?>"></div>
        <div class="form-group"><label class="form-label">Desconto ganho (%)</label><input type="number" name="fidelidade_desconto" class="form-control" value="<?= (int)($c['fidelidade_desconto']??10) ?>"></div>
      </div>
      <p style="font-size:13px;color:var(--muted)">💡 Cliente recebe aviso "Faltam X pedidos para seu desconto!" automaticamente.</p>
      <button type="submit" class="btn btn-primary">Salvar fidelidade</button>
    </form>
  </details>

  <!-- Backup -->
  <div class="card mb-4" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-weight:700;margin-bottom:4px">💾 Backup do banco de dados</div>
      <div style="font-size:13px;color:var(--muted)">Baixa um arquivo SQL com todos os dados da sua loja.</div>
    </div>
    <a href="../api/backup.php" class="btn btn-outline" style="padding:10px 18px">⬇ Baixar backup agora</a>
  </div>

</div>
<script>
const colorInput = document.querySelector('input[name="cor_primaria"]');
const hexInput = document.getElementById('corHex');
const preview = document.getElementById('corPreview');
if (colorInput && hexInput && preview) {
  colorInput.addEventListener('input', e => { hexInput.value = e.target.value; preview.style.background = e.target.value; });
  hexInput.addEventListener('input', e => { if (/^#[0-9a-f]{6}$/i.test(e.target.value)) { colorInput.value = e.target.value; preview.style.background = e.target.value; } });
}
</script>

<?php include __DIR__ . '/nav_end.php'; ?>
</body></html>

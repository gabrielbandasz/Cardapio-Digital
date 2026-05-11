<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$msg = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'remover_imagem' && isset($_GET['id'])) $_POST['id'] = (int)$_GET['id'];
$uploadDir = __DIR__ . '/../assets/uploads/';

// Upload de imagem
function processarImagem(array $file, string $dir): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    if (!in_array($realMime, $allowed)) return null;
    if ($file['size'] > 4 * 1024 * 1024) return null;
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'img_' . uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . $name);
    return 'assets/uploads/' . $name;
}

if ($action === 'toggle') {
    $pdo->prepare("UPDATE produtos SET disponivel=? WHERE id=?")->execute([(int)$_POST['disponivel'],(int)$_POST['id']]);
    $msg = 'Produto atualizado!';
} elseif ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $nome       = trim($_POST['nome']);
    $preco      = (float)$_POST['preco'];
    $preco_original = $_POST['preco_original'] !== '' ? (float)$_POST['preco_original'] : null;
    $descricao  = trim($_POST['descricao'] ?? '');
    $emoji      = trim($_POST['emoji'] ?? '🍽️');
    $catId      = (int)$_POST['categoria_id'];
    $destaque   = (int)($_POST['destaque'] ?? 0);
    $maisVend   = (int)($_POST['mais_vendido'] ?? 0);

    // Imagem
    $imagem = null;
    if (!empty($_FILES['imagem']['name'])) {
        $imagem = processarImagem($_FILES['imagem'], $uploadDir);
    }
    // URL manual
    if (!$imagem && !empty($_POST['imagem_url'])) {
        $imagem = trim($_POST['imagem_url']);
    }

    if ($id > 0) {
        // Remover imagem antiga se nova foi enviada
        if ($imagem) {
            $old = $pdo->prepare("SELECT imagem FROM produtos WHERE id=?"); $old->execute([$id]); $oldR = $old->fetch();
            if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'],'assets/uploads/') === 0) {
                @unlink(__DIR__ . '/../' . $oldR['imagem']);
            }
            $pdo->prepare("UPDATE produtos SET nome=?,preco=?,preco_original=?,descricao=?,emoji=?,categoria_id=?,destaque=?,mais_vendido=?,imagem=? WHERE id=?")->execute([$nome,$preco,$preco_original,$descricao,$emoji,$catId,$destaque,$maisVend,$imagem,$id]);
        } else {
            $pdo->prepare("UPDATE produtos SET nome=?,preco=?,preco_original=?,descricao=?,emoji=?,categoria_id=?,destaque=?,mais_vendido=? WHERE id=?")->execute([$nome,$preco,$preco_original,$descricao,$emoji,$catId,$destaque,$maisVend,$id]);
        }
        $msg = '✅ Produto atualizado!';
    } else {
        $pdo->prepare("INSERT INTO produtos (categoria_id,nome,descricao,preco,preco_original,emoji,destaque,mais_vendido,disponivel,imagem) VALUES (?,?,?,?,?,?,?,?,1,?)")->execute([$catId,$nome,$descricao,$preco,$preco_original,$emoji,$destaque,$maisVend,$imagem]);
        $msg = '✅ Produto adicionado!';
    }
} elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    $old = $pdo->prepare("SELECT imagem FROM produtos WHERE id=?"); $old->execute([$id]); $oldR=$old->fetch();
    if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'],'assets/uploads/')===0) @unlink(__DIR__.'/../'.$oldR['imagem']);
    $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$id]);
    $msg = '🗑 Produto removido.';
} elseif ($action === 'remover_imagem') {
    $id = (int)$_POST['id'];
    $old = $pdo->prepare("SELECT imagem FROM produtos WHERE id=?"); $old->execute([$id]); $oldR=$old->fetch();
    if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'],'assets/uploads/')===0) @unlink(__DIR__.'/../'.$oldR['imagem']);
    $pdo->prepare("UPDATE produtos SET imagem=NULL WHERE id=?")->execute([$id]);
    header("Location: produtos.php?edit={$id}&msg=imagem_removida"); exit;
}

$produtos = $pdo->query("SELECT p.*, c.nome AS cat_nome FROM produtos p LEFT JOIN categorias c ON c.id=p.categoria_id ORDER BY c.nome, p.nome")->fetchAll();
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editProd = null;
if ($editId) { foreach ($produtos as $p) { if ($p['id']==$editId) { $editProd=$p; break; } } }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Produtos — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.img-preview-wrap { position:relative; display:inline-block; }
.img-preview { width:100%; max-height:180px; object-fit:cover; border-radius:12px; border:2px solid var(--border); margin-bottom:8px; }
.img-upload-area {
  border:2px dashed var(--border); border-radius:12px; padding:32px 20px;
  text-align:center; cursor:pointer; transition:border-color .2s; position:relative;
}
.img-upload-area:hover { border-color:var(--primary); }
.img-upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
.img-upload-area .icon { font-size:32px; margin-bottom:8px; }
.img-upload-area p { font-size:13px; color:var(--muted); }
.img-tab-btn { padding:8px 16px; border:1px solid var(--border); background:none; cursor:pointer; font-size:13px; color:var(--text); }
.img-tab-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.img-tab-btn:first-child { border-radius:8px 0 0 8px; }
.img-tab-btn:last-child { border-radius:0 8px 8px 0; }
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">
  <div class="section-title">🍽 Produtos <a href="produtos.php?edit=0" class="btn btn-primary" style="padding:6px 14px;font-size:13px">+ Novo produto</a></div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <?php if (isset($_GET['edit'])): ?>
  <div class="card mb-4">
    <h3 style="margin-bottom:20px"><?= $editProd ? '✏️ Editar produto' : '➕ Novo produto' ?></h3>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editProd ? $editProd['id'] : 0 ?>">

      <!-- IMAGEM -->
      <div class="form-group">
        <label class="form-label">📸 Imagem do produto</label>
        <?php if ($editProd && !empty($editProd['imagem'])): ?>
        <div class="img-preview-wrap" style="display:block;margin-bottom:10px">
          <img src="../<?= h($editProd['imagem']) ?>" alt="Imagem atual" class="img-preview">
          <a href="produtos.php?action=remover_imagem&id=<?= $editProd['id'] ?>"
             class="btn btn-danger" style="font-size:12px;padding:4px 10px;display:inline-flex;margin-top:6px"
             onclick="return confirm('Remover imagem?')">🗑 Remover imagem</a>
        </div>
        <?php endif; ?>
        <!-- Abas upload/URL -->
        <div style="margin-bottom:10px">
          <button type="button" class="img-tab-btn active" id="tabUpload" onclick="switchTab('upload')">⬆ Upload</button>
          <button type="button" class="img-tab-btn" id="tabUrl" onclick="switchTab('url')">🔗 URL</button>
        </div>
        <div id="areaUpload">
          <div class="img-upload-area" id="dropArea">
            <input type="file" name="imagem" accept="image/*" id="fileInput" onchange="previewFile(this)">
            <div class="icon">🖼</div>
            <p><strong>Clique ou arraste uma imagem aqui</strong><br>JPG, PNG, WEBP até 4MB</p>
          </div>
          <img id="imgPreviewNew" src="" alt="" style="display:none;width:100%;max-height:160px;object-fit:cover;border-radius:12px;margin-top:8px">
        </div>
        <div id="areaUrl" style="display:none">
          <input name="imagem_url" id="imgUrlInput" class="form-control" placeholder="https://exemplo.com/imagem.jpg" value="<?= (!empty($editProd['imagem']) && strpos($editProd['imagem'],'http')===0) ? h($editProd['imagem']) : '' ?>">
          <img id="imgUrlPreview" src="" alt="" style="display:none;width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-top:8px">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Emoji</label>
          <input name="emoji" class="form-control" value="<?= h($editProd['emoji'] ?? '🍔') ?>" style="font-size:24px;text-align:center;padding:8px">
        </div>
        <div class="form-group">
          <label class="form-label">Categoria *</label>
          <select name="categoria_id" class="form-control">
            <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($editProd && $editProd['categoria_id']==$c['id']) ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
            <?php endforeach; ?>
            <?php if (empty($categorias)): ?><option value="1">Geral</option><?php endif; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nome do produto *</label>
        <input name="nome" class="form-control" required value="<?= h($editProd['nome'] ?? '') ?>" placeholder="Ex: X-Burguer Clássico">
      </div>
      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" class="form-control" rows="2" placeholder="Ingredientes, tamanho, informações..."><?= h($editProd['descricao'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Preço (R$) *</label>
          <input type="number" step="0.01" name="preco" class="form-control" required value="<?= $editProd['preco'] ?? '' ?>" placeholder="0,00">
        </div>
        <div class="form-group">
          <label class="form-label">Preço original (riscado)</label>
          <input type="number" step="0.01" name="preco_original" class="form-control" value="<?= $editProd['preco_original'] ?? '' ?>" placeholder="0,00">
        </div>
      </div>
      <div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600">
          <input type="checkbox" name="destaque" value="1" <?= ($editProd['destaque'] ?? 0) ? 'checked' : '' ?>> ⭐ Destaque
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600">
          <input type="checkbox" name="mais_vendido" value="1" <?= ($editProd['mais_vendido'] ?? 0) ? 'checked' : '' ?>> 🔥 Mais vendido
        </label>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1;padding:14px">💾 Salvar produto</button>
        <a href="produtos.php" class="btn btn-outline" style="flex:1;padding:14px;text-align:center">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Lista de produtos -->
  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead><tr><th style="width:60px">Img</th><th>Produto</th><th>Preço</th><th>Categoria</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($produtos as $p): ?>
        <tr>
          <td>
            <?php if (!empty($p['imagem'])): ?>
              <img src="../<?= h($p['imagem']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
            <?php else: ?>
              <span style="font-size:32px;display:block;text-align:center"><?= $p['emoji'] ?? '🍽' ?></span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h($p['nome']) ?></strong>
            <?php if ($p['destaque']): ?> <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">⭐</span><?php endif; ?>
            <?php if ($p['mais_vendido']): ?> <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:10px">🔥</span><?php endif; ?>
            <?php if ($p['descricao']): ?><div style="font-size:12px;color:var(--muted);margin-top:2px"><?= h(substr($p['descricao'],0,60)) ?>...</div><?php endif; ?>
          </td>
          <td class="text-primary fw-bold">
            <?= formatar_dinheiro((float)$p['preco']) ?>
            <?php if ($p['preco_original'] ?? null): ?><br><span style="text-decoration:line-through;font-size:11px;color:var(--muted)"><?= formatar_dinheiro((float)$p['preco_original']) ?></span><?php endif; ?>
          </td>
          <td style="font-size:13px;color:var(--muted)"><?= h($p['cat_nome'] ?? '—') ?></td>
          <td>
            <form method="POST" style="display:inline">
      <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="disponivel" value="<?= $p['disponivel'] ? 0 : 1 ?>">
              <button type="submit" class="btn <?= $p['disponivel'] ? 'btn-success' : 'btn-danger' ?>" style="padding:4px 12px;font-size:12px">
                <?= $p['disponivel'] ? '✅ Ativo' : '❌ Pausado' ?>
              </button>
            </form>
          </td>
          <td style="white-space:nowrap">
            <a href="produtos.php?edit=<?= $p['id'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:12px">✏️ Editar</a>
            <form method="POST" style="display:inline;margin-left:4px" onsubmit="return confirm('Remover produto?')">
      <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:12px">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($produtos)): ?>
        <tr><td colspan="6"><div class="empty-state" style="padding:30px"><p>Nenhum produto cadastrado ainda.<br><a href="produtos.php?edit=0" class="text-primary">Adicionar primeiro produto</a></p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('areaUpload').style.display = tab==='upload'?'block':'none';
  document.getElementById('areaUrl').style.display   = tab==='url'?'block':'none';
  document.getElementById('tabUpload').className = 'img-tab-btn' + (tab==='upload'?' active':'');
  document.getElementById('tabUrl').className    = 'img-tab-btn' + (tab==='url'?' active':'');
}
function previewFile(input) {
  const file = input.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('imgPreviewNew');
    img.src = e.target.result; img.style.display = 'block';
  };
  reader.readAsDataURL(file);
}
// Preview URL
const urlInput = document.getElementById('imgUrlInput');
const urlPreview = document.getElementById('imgUrlPreview');
if (urlInput && urlPreview) {
  urlInput.addEventListener('input', () => {
    const v = urlInput.value.trim();
    if (v.startsWith('http')) { urlPreview.src = v; urlPreview.style.display = 'block'; }
    else urlPreview.style.display = 'none';
  });
  if (urlInput.value.trim().startsWith('http')) { urlPreview.src = urlInput.value; urlPreview.style.display='block'; }
}
// Drag & Drop
const drop = document.getElementById('dropArea');
if (drop) {
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor='var(--primary)'; });
  drop.addEventListener('dragleave', () => drop.style.borderColor='');
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.style.borderColor='';
    const file = e.dataTransfer.files[0];
    if (file) { document.getElementById('fileInput').files = e.dataTransfer.files; previewFile({files:[file]}); }
  });
}
// Dark mode
(function(){
  const root=document.documentElement, btn=document.getElementById('darkToggle');
  if(localStorage.getItem('darkMode')==='1') root.setAttribute('data-theme','dark');
  if(btn){ btn.textContent=root.getAttribute('data-theme')==='dark'?'☀️':'🌙';
    btn.addEventListener('click',()=>{const d=root.getAttribute('data-theme')==='dark';root.setAttribute('data-theme',d?'light':'dark');localStorage.setItem('darkMode',d?'0':'1');btn.textContent=d?'🌙':'☀️';});}
})();
</script>
<?php include __DIR__ . '/nav_end.php'; ?>
</body></html>
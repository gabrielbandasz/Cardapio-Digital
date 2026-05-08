<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$msg = '';
$action = $_POST['action'] ?? '';
$produtoId = (int)($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $grupo = trim($_POST['grupo']); $nome = trim($_POST['nome']); $preco = (float)$_POST['preco_extra'];
    if ($id > 0) { $pdo->prepare("UPDATE produto_variacoes SET grupo=?,nome=?,preco_extra=? WHERE id=?")->execute([$grupo,$nome,$preco,$id]); }
    else { $pdo->prepare("INSERT INTO produto_variacoes (produto_id,grupo,nome,preco_extra) VALUES (?,?,?,?)")->execute([$produtoId,$grupo,$nome,$preco]); }
    $msg = '✅ Variação salva!';
} elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM produto_variacoes WHERE id=?")->execute([(int)$_POST['vid']]);
    $msg = '🗑 Variação removida.';
}

$produto = $produtoId ? $pdo->prepare("SELECT * FROM produtos WHERE id=?") : null;
if ($produto) { $produto->execute([$produtoId]); $produto = $produto->fetch(); }
$variacoes = $produtoId ? $pdo->prepare("SELECT * FROM produto_variacoes WHERE produto_id=? ORDER BY grupo,id") : null;
if ($variacoes) { $variacoes->execute([$produtoId]); $variacoes = $variacoes->fetchAll(); }

$grupos_existentes = $produtoId ? array_unique(array_column($variacoes??[],'grupo')) : [];
$editId = (int)($_GET['edit'] ?? 0);
$editVar = null;
if ($editId && $variacoes) { foreach ($variacoes as $v) { if ($v['id']==$editId) { $editVar=$v; break; } } }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Variações — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:700px">
  <?php if (!$produto): ?>
  <div class="card"><div class="section-title">Variações de produtos</div>
    <p style="margin-bottom:16px">Selecione um produto para gerenciar suas variações (tamanhos, adicionais, etc).</p>
    <form method="GET" style="display:flex;gap:10px">
      <select name="produto_id" class="form-control">
        <option value="">-- Escolha um produto --</option>
        <?php foreach ($pdo->query("SELECT id,nome FROM produtos ORDER BY nome") as $p): ?>
          <option value="<?=$p['id']?>"><?= h($p['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Ver variações</button>
    </form>
  </div>
  <?php else: ?>
  <a href="variacoes.php" style="font-size:13px;color:var(--muted)">← Voltar</a>
  <div class="section-title" style="margin-top:8px">
    🔀 Variações de: <strong><?= h($produto['nome']) ?></strong>
    <a href="variacoes.php?produto_id=<?=$produtoId?>&edit=0" class="btn btn-primary" style="padding:6px 14px;font-size:13px;margin-left:10px">+ Adicionar</a>
  </div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <?php if (isset($_GET['edit'])): ?>
  <div class="card mb-4">
    <h3 style="margin-bottom:16px"><?= $editVar ? '✏️ Editar variação' : '➕ Nova variação' ?></h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="produto_id" value="<?= $produtoId ?>">
      <input type="hidden" name="id" value="<?= $editVar ? $editVar['id'] : 0 ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Grupo (ex: Tamanho, Adicional)</label>
          <input name="grupo" class="form-control" required list="grupos" value="<?= h($editVar['grupo']??'') ?>" placeholder="Tamanho">
          <datalist id="grupos"><?php foreach ($grupos_existentes as $g): ?><option value="<?= h($g) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="form-group">
          <label class="form-label">Nome da opção</label>
          <input name="nome" class="form-control" required value="<?= h($editVar['nome']??'') ?>" placeholder="Ex: G — 240g">
        </div>
        <div class="form-group">
          <label class="form-label">Preço extra (R$, pode ser negativo)</label>
          <input type="number" step="0.01" name="preco_extra" class="form-control" value="<?= $editVar['preco_extra']??0 ?>">
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Salvar</button>
        <a href="variacoes.php?produto_id=<?= $produtoId ?>" class="btn btn-outline" style="flex:1">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php $grupoAtual = null; foreach ($variacoes as $v): if ($v['grupo'] !== $grupoAtual): ?>
    <?php if ($grupoAtual !== null) echo '</div>'; $grupoAtual = $v['grupo']; ?>
    <div class="card mb-3">
    <div style="font-weight:700;font-size:14px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">📦 <?= h($v['grupo']) ?></div>
  <?php endif; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border)">
      <span><?= h($v['nome']) ?> <?= $v['preco_extra'] != 0 ? '<span style="color:'.($v['preco_extra']>0?'var(--primary)':'var(--muted').'">('.($v['preco_extra']>0?'+':'').formatar_dinheiro((float)$v['preco_extra']).')</span>' : '' ?></span>
      <span style="display:flex;gap:6px">
        <a href="variacoes.php?produto_id=<?=$produtoId?>&edit=<?=$v['id']?>" class="btn btn-outline" style="padding:3px 8px;font-size:12px">✏️</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remover?')">
      <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="produto_id" value="<?=$produtoId?>"><input type="hidden" name="vid" value="<?=$v['id']?>">
          <button type="submit" class="btn btn-danger" style="padding:3px 8px;font-size:12px">🗑</button>
        </form>
      </span>
    </div>
  <?php endforeach; if ($grupoAtual !== null) echo '</div>'; ?>
  <?php if (empty($variacoes)): ?><div class="empty-state"><p>Nenhuma variação cadastrada.<br>Adicione tamanhos, adicionais ou personalizações.</p></div><?php endif; ?>
  <?php endif; ?>
</div>
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

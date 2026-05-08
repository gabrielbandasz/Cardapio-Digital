<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

$msg = '';
$action = $_POST['action'] ?? '';
if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome']); $bairros = trim($_POST['bairros']); $taxa = (float)$_POST['taxa']; $ativo = (int)($_POST['ativo']??1);
    if ($id > 0) { $pdo->prepare("UPDATE zonas_entrega SET nome=?,bairros=?,taxa=?,ativo=? WHERE id=?")->execute([$nome,$bairros,$taxa,$ativo,$id]); }
    else { $pdo->prepare("INSERT INTO zonas_entrega (nome,bairros,taxa,ativo) VALUES (?,?,?,?)")->execute([$nome,$bairros,$taxa,$ativo]); }
    $msg = '✅ Zona salva!';
} elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM zonas_entrega WHERE id=?")->execute([(int)$_POST['id']]);
    $msg = '🗑 Zona removida.';
}
$zonas = $pdo->query("SELECT * FROM zonas_entrega ORDER BY taxa")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editZona = null;
if ($editId) { foreach ($zonas as $z) { if ($z['id']==$editId) { $editZona=$z; break; } } }
$fretePorZona = (int)$pdo->query("SELECT frete_por_zona FROM config WHERE id=1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Zonas de Entrega — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:700px">
  <div class="section-title">🗺 Frete Inteligente por Zona <a href="zonas_entrega.php?edit=0" class="btn btn-primary" style="padding:6px 14px;font-size:13px">+ Nova zona</a></div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <div class="card mb-4">
    <form method="POST" action="configuracoes.php" style="display:flex;align-items:center;gap:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="frete_zona">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
        <input type="checkbox" name="frete_por_zona" value="1" <?= $fretePorZona?'checked':'' ?>>
        Usar frete por bairro/zona (ao invés de taxa fixa)
      </label>
      <button type="submit" class="btn btn-primary" style="padding:8px 16px;font-size:13px">Salvar</button>
    </form>
  </div>

  <?php if (isset($_GET['edit'])): ?>
  <div class="card mb-4">
    <h3 style="margin-bottom:16px"><?= $editZona ? '✏️ Editar zona' : '➕ Nova zona' ?></h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editZona ? $editZona['id'] : 0 ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Nome da zona</label><input name="nome" class="form-control" required value="<?= h($editZona['nome']??'') ?>" placeholder="Ex: Centro"></div>
        <div class="form-group"><label class="form-label">Taxa (R$)</label><input type="number" step="0.01" name="taxa" class="form-control" required value="<?= $editZona['taxa']??5 ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Bairros (separados por vírgula)</label><textarea name="bairros" class="form-control" placeholder="Centro, República, Sé..."><?= h($editZona['bairros']??'') ?></textarea></div>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="ativo" value="1" <?= ($editZona['ativo']??1)?'checked':'' ?>> Ativo</label></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Salvar</button>
        <a href="zonas_entrega.php" class="btn btn-outline" style="flex:1">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead><tr><th>Zona</th><th>Taxa</th><th>Bairros</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($zonas as $z): ?>
        <tr>
          <td><strong><?= h($z['nome']) ?></strong></td>
          <td class="text-primary fw-bold"><?= formatar_dinheiro((float)$z['taxa']) ?></td>
          <td style="font-size:13px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($z['bairros']) ?></td>
          <td><?= $z['ativo']?'<span class="badge badge-pronto">Ativo</span>':'<span class="badge badge-cancelado">Inativo</span>' ?></td>
          <td style="white-space:nowrap">
            <a href="zonas_entrega.php?edit=<?= $z['id'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:12px">✏️</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remover?')">
      <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $z['id'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:12px">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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

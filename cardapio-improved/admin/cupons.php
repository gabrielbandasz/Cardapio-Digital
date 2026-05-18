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
    $codigo = strtoupper(preg_replace('/\s+/','',$_POST['codigo']));
    $tipo = $_POST['tipo']; $valor = (float)$_POST['valor'];
    $uso_maximo = !empty($_POST['uso_maximo']) ? (int)$_POST['uso_maximo'] : null;
    $valido_ate = !empty($_POST['valido_ate']) ? date('Y-m-d H:i:s', strtotime($_POST['valido_ate'])) : null;
    $descricao = trim($_POST['descricao'] ?? '');
    $ativo = (int)($_POST['ativo'] ?? 1);
    if ($id > 0) {
        $pdo->prepare("UPDATE cupons SET codigo=?,tipo=?,valor=?,uso_maximo=?,valido_ate=?,descricao=?,ativo=? WHERE id=?")->execute([$codigo,$tipo,$valor,$uso_maximo,$valido_ate,$descricao,$ativo,$id]);
        $msg = '✅ Cupom atualizado!';
    } else {
        $pdo->prepare("INSERT INTO cupons (codigo,tipo,valor,uso_maximo,valido_ate,descricao,ativo) VALUES (?,?,?,?,?,?,?)")->execute([$codigo,$tipo,$valor,$uso_maximo,$valido_ate,$descricao,$ativo]);
        $msg = '✅ Cupom criado!';
    }
} elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM cupons WHERE id=?")->execute([(int)$_POST['id']]);
    $msg = '🗑 Cupom removido.';
}

$cupons = $pdo->query("SELECT * FROM cupons ORDER BY created_at DESC")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editCupom = null;
if ($editId) { foreach ($cupons as $c) { if ($c['id']==$editId) { $editCupom=$c; break; } } }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cupons — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap" style="max-width:700px">
  <div class="section-title">🎟 Cupons de Desconto <a href="cupons.php?edit=0" class="btn btn-primary" style="padding:6px 14px;font-size:13px">+ Novo cupom</a></div>
  <?php if ($msg): ?><div class="alerta alerta-sucesso"><?= h($msg) ?></div><?php endif; ?>

  <?php if (isset($_GET['edit'])): ?>
  <div class="card mb-4">
    <h3 style="margin-bottom:16px"><?= $editCupom ? '✏️ Editar cupom' : '➕ Novo cupom' ?></h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editCupom ? $editCupom['id'] : 0 ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Código do cupom *</label>
          <input name="codigo" class="form-control" required value="<?= h($editCupom['codigo']??'') ?>" placeholder="PRIMEIRACOMPRA" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="ativo" class="form-control">
            <option value="1" <?= ($editCupom['ativo']??1)?'selected':'' ?>>✅ Ativo</option>
            <option value="0" <?= isset($editCupom) && !$editCupom['ativo']?'selected':'' ?>>❌ Inativo</option>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-control">
            <option value="percentual" <?= ($editCupom['tipo']??'')==='percentual'?'selected':'' ?>>% Percentual</option>
            <option value="fixo" <?= ($editCupom['tipo']??'')==='fixo'?'selected':'' ?>>R$ Valor fixo</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Valor do desconto</label>
          <input type="number" step="0.01" name="valor" class="form-control" value="<?= $editCupom['valor']??10 ?>" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Uso máximo (vazio = ilimitado)</label>
          <input type="number" name="uso_maximo" class="form-control" value="<?= $editCupom['uso_maximo']??'' ?>" placeholder="Ilimitado">
        </div>
        <div class="form-group">
          <label class="form-label">Válido até (vazio = sem prazo)</label>
          <input type="datetime-local" name="valido_ate" class="form-control" value="<?= $editCupom && $editCupom['valido_ate'] ? date('Y-m-d\TH:i',strtotime($editCupom['valido_ate'])) : '' ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Descrição interna</label>
        <input name="descricao" class="form-control" value="<?= h($editCupom['descricao']??'') ?>" placeholder="Para uso interno">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Salvar</button>
        <a href="cupons.php" class="btn btn-outline" style="flex:1">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead><tr><th>Código</th><th>Desconto</th><th>Usos</th><th>Válido até</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($cupons as $c): ?>
        <tr>
          <td><strong style="font-family:monospace;font-size:15px"><?= h($c['codigo']) ?></strong><br><span style="font-size:12px;color:var(--muted)"><?= h($c['descricao']) ?></span></td>
          <td class="text-primary fw-bold"><?= $c['tipo']==='percentual' ? (int)$c['valor'].'%' : formatar_dinheiro((float)$c['valor']) ?></td>
          <td><?= (int)$c['uso_atual'] ?><?= $c['uso_maximo'] ? '/'.$c['uso_maximo'] : '' ?></td>
          <td style="font-size:13px;color:var(--muted)"><?= $c['valido_ate'] ? date('d/m/y H:i',strtotime($c['valido_ate'])) : 'Sem prazo' ?></td>
          <td><?= $c['ativo'] ? '<span class="badge badge-pronto">Ativo</span>' : '<span class="badge badge-cancelado">Inativo</span>' ?></td>
          <td style="white-space:nowrap">
            <a href="cupons.php?edit=<?= $c['id'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:12px">✏️</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remover?')">
      <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:12px">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/nav_end.php'; ?>
</body></html>

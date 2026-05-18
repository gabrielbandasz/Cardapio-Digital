<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';

define('BASE_URL','../');
exigir_login();
csrf_verify();

$msg = '';
$tipo = 'sucesso';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$confirmDeleteId = 0;

// Garante tabela existe
$pdo->exec("
CREATE TABLE IF NOT EXISTS ingredientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    emoji VARCHAR(20) DEFAULT '🍴',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if ($action === 'save') {
    $id    = (int)($_POST['id'] ?? 0);
    $nome  = trim($_POST['nome'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '🍴');

    if ($nome === '') {
        $msg = 'Nome obrigatório.';
        $tipo = 'erro';
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE ingredientes SET nome=?, emoji=? WHERE id=?")
                ->execute([$nome, $emoji, $id]);
            $msg = '✅ Ingrediente atualizado!';
        } else {
            try {
                $pdo->prepare("INSERT INTO ingredientes (nome, emoji, ativo) VALUES (?, ?, 1)")
                    ->execute([$nome, $emoji]);
                $msg = '✅ Ingrediente cadastrado!';
            } catch (PDOException $e) {
                $msg = '⚠️ Já existe um ingrediente com esse nome.';
                $tipo = 'erro';
            }
        }
    }

} elseif ($action === 'toggle') {
    $id    = (int)$_POST['id'];
    $ativo = (int)$_POST['ativo'];
    $pdo->prepare("UPDATE ingredientes SET ativo=? WHERE id=?")->execute([$ativo, $id]);
    $msg = $ativo ? '✅ Ingrediente ativado.' : '⛔ Ingrediente desativado.';

} elseif ($action === 'delete') {
    $id    = (int)$_POST['id'];
    $force = (int)($_POST['force'] ?? 0);

    // Verifica se está em uso em produto_opcoes (por nome)
    $uso = $pdo->prepare("SELECT COUNT(*) FROM produto_opcoes WHERE nome=(SELECT nome FROM ingredientes WHERE id=?)");
    $uso->execute([$id]);
    $emUso = (int)$uso->fetchColumn();

    if ($emUso > 0 && !$force) {
        $msg  = "⚠️ Esse ingrediente está salvo em {$emUso} opção(ões) de produto. Deseja excluir mesmo assim e remover essas opções?";
        $tipo = 'aviso';
        $confirmDeleteId = $id;
    } else {
        // Remove das produto_opcoes e exclui o ingrediente
        $nome = $pdo->prepare("SELECT nome FROM ingredientes WHERE id=?");
        $nome->execute([$id]);
        $nomeIng = $nome->fetchColumn();
        if ($nomeIng) {
            $pdo->prepare("DELETE FROM produto_opcoes WHERE nome=?")->execute([$nomeIng]);
        }
        $pdo->prepare("DELETE FROM ingredientes WHERE id=?")->execute([$id]);
        $msg = '🗑 Ingrediente excluído.';
    }
}

$ingredientes = $pdo->query("SELECT * FROM ingredientes ORDER BY ativo DESC, nome")->fetchAll();

// Para edição inline
$editId = (int)($_GET['edit'] ?? 0);
$editIng = null;
if ($editId) {
    foreach ($ingredientes as $ing) {
        if ((int)$ing['id'] === $editId) { $editIng = $ing; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ingredientes — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.ing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
  margin-top: 16px;
}
.ing-card {
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px 16px;
  background: var(--surface);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: border-color .2s;
}
.ing-card:hover { border-color: var(--primary); }
.ing-card.inativo { opacity: .5; }
.ing-emoji { font-size: 26px; width: 36px; text-align: center; flex-shrink: 0; }
.ing-info { flex: 1; min-width: 0; }
.ing-nome { font-weight: 700; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ing-status { font-size: 11px; color: var(--muted); margin-top: 2px; }
.ing-actions { display: flex; gap: 6px; flex-shrink: 0; }
.ing-actions a, .ing-actions button {
  padding: 5px 9px;
  border-radius: 8px;
  font-size: 12px;
  border: 1px solid var(--border);
  background: none;
  color: var(--text-soft);
  cursor: pointer;
  text-decoration: none;
  line-height: 1;
  transition: all .15s;
}
.ing-actions a:hover, .ing-actions button:hover { border-color: var(--primary); color: var(--primary); }
.ing-actions .btn-del { border-color: rgba(239,68,68,.3); color: var(--danger); }
.ing-actions .btn-del:hover { background: rgba(239,68,68,.1); }

.search-bar {
  position: relative;
  margin-bottom: 4px;
}
.search-bar input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface2);
  color: var(--text);
  font-size: 14px;
  box-sizing: border-box;
}
.search-bar .search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 16px;
  pointer-events: none;
}

.alerta-aviso { background: rgba(234,179,8,.12); border: 1px solid rgba(234,179,8,.3); color: #ca8a04; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.form-add {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 24px;
}
.form-add h3 { margin: 0 0 14px; font-size: 15px; }
.form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 120px; margin-bottom: 0; }
.emoji-input { font-size: 22px; text-align: center; width: 70px !important; flex: 0 0 70px !important; }

.filtro-tabs { display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
.filtro-tab {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border);
  background: none;
  color: var(--text-soft);
  cursor: pointer;
  transition: all .15s;
}
.filtro-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

.empty-ing { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; }
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<div class="admin-wrap">
  <div class="section-title">🥬 Ingredientes</div>

  <?php if ($msg): ?>
    <div class="alerta alerta-<?= $tipo === 'erro' ? 'erro' : ($tipo === 'aviso' ? 'aviso' : 'sucesso') ?>">
      <?= h($msg) ?>
      <?php if ($confirmDeleteId): ?>
        <form method="POST" style="display:inline;margin-left:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $confirmDeleteId ?>">
          <input type="hidden" name="force" value="1">
          <button type="submit" class="btn btn-danger" style="padding:4px 14px;font-size:13px">
            🗑 Confirmar exclusão
          </button>
        </form>
        <a href="ingredientes.php" style="margin-left:8px;font-size:13px;color:var(--muted)">Cancelar</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Formulário cadastro/edição -->
  <div class="form-add">
    <h3><?= $editIng ? '✏️ Editar ingrediente' : '➕ Novo ingrediente' ?></h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editIng ? (int)$editIng['id'] : 0 ?>">

      <div class="form-row">
        <div class="form-group" style="flex:0 0 70px">
          <label class="form-label">Emoji</label>
          <input name="emoji" class="form-control emoji-input"
                 value="<?= h($editIng['emoji'] ?? '🍴') ?>"
                 maxlength="5" placeholder="🍴">
        </div>
        <div class="form-group">
          <label class="form-label">Nome do ingrediente *</label>
          <input name="nome" class="form-control" required
                 value="<?= h($editIng['nome'] ?? '') ?>"
                 placeholder="Ex: Bacon, Queijo, Cebola...">
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;align-items:flex-end">
          <button type="submit" class="btn btn-primary" style="padding:10px 20px">
            💾 Salvar
          </button>
          <?php if ($editIng): ?>
            <a href="ingredientes.php" class="btn btn-outline" style="padding:10px 16px">Cancelar</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- Filtros e busca -->
  <div style="margin-bottom:12px">
    <div class="filtro-tabs">
      <button class="filtro-tab active" onclick="filtrar('todos',this)">Todos (<?= count($ingredientes) ?>)</button>
      <button class="filtro-tab" onclick="filtrar('ativos',this)">
        Ativos (<?= count(array_filter($ingredientes, fn($i) => $i['ativo'])) ?>)
      </button>
      <button class="filtro-tab" onclick="filtrar('inativos',this)">
        Inativos (<?= count(array_filter($ingredientes, fn($i) => !$i['ativo'])) ?>)
      </button>
    </div>
    <div class="search-bar">
      <span class="search-icon">🔍</span>
      <input type="text" id="buscaIng" placeholder="Buscar ingrediente..." oninput="buscar(this.value)">
    </div>
  </div>

  <!-- Grid de ingredientes -->
  <div class="ing-grid" id="ingGrid">
    <?php foreach ($ingredientes as $ing): ?>
    <div class="ing-card <?= $ing['ativo'] ? '' : 'inativo' ?>"
         data-nome="<?= strtolower(h($ing['nome'])) ?>"
         data-status="<?= $ing['ativo'] ? 'ativos' : 'inativos' ?>">
      <span class="ing-emoji"><?= h($ing['emoji'] ?? '🍴') ?></span>
      <div class="ing-info">
        <div class="ing-nome"><?= h($ing['nome']) ?></div>
        <div class="ing-status"><?= $ing['ativo'] ? '✅ Ativo' : '⛔ Inativo' ?></div>
      </div>
      <div class="ing-actions">
        <a href="ingredientes.php?edit=<?= (int)$ing['id'] ?>" title="Editar">✏️</a>

        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$ing['id'] ?>">
          <input type="hidden" name="ativo" value="<?= $ing['ativo'] ? 0 : 1 ?>">
          <button type="submit" title="<?= $ing['ativo'] ? 'Desativar' : 'Ativar' ?>">
            <?= $ing['ativo'] ? '⛔' : '✅' ?>
          </button>
        </form>

        <form method="POST" style="display:inline" onsubmit="return confirm('Excluir <?= h(addslashes($ing['nome'])) ?>?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$ing['id'] ?>">
          <button type="submit" class="btn-del" title="Excluir">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($ingredientes)): ?>
      <div class="empty-ing" style="grid-column:1/-1">
        <div style="font-size:40px;margin-bottom:8px">🥬</div>
        Nenhum ingrediente cadastrado ainda.<br>
        Use o formulário acima para adicionar o primeiro.
      </div>
    <?php endif; ?>
  </div>

  <div id="emptySearch" class="empty-ing" style="display:none">
    <div style="font-size:32px;margin-bottom:8px">🔍</div>
    Nenhum ingrediente encontrado.
  </div>
</div>

<script>
let filtroAtual = 'todos';

function filtrar(tipo, btn) {
  filtroAtual = tipo;
  document.querySelectorAll('.filtro-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  aplicarFiltro(document.getElementById('buscaIng').value.toLowerCase());
}

function buscar(q) {
  aplicarFiltro(q.toLowerCase());
}

function aplicarFiltro(q) {
  const cards = document.querySelectorAll('.ing-card');
  let visivel = 0;
  cards.forEach(card => {
    const nome = card.dataset.nome;
    const status = card.dataset.status;
    const matchQ = !q || nome.includes(q);
    const matchF = filtroAtual === 'todos' || status === filtroAtual;
    const show = matchQ && matchF;
    card.style.display = show ? '' : 'none';
    if (show) visivel++;
  });
  document.getElementById('emptySearch').style.display = visivel === 0 ? 'block' : 'none';
}

(function(){
  const root = document.documentElement;
  if (localStorage.getItem('darkMode') === '1') root.setAttribute('data-theme','dark');
  const btn = document.getElementById('darkToggle');
  if (btn) {
    btn.textContent = root.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
    btn.addEventListener('click', () => {
      const d = root.getAttribute('data-theme') === 'dark';
      root.setAttribute('data-theme', d ? 'light' : 'dark');
      localStorage.setItem('darkMode', d ? '0' : '1');
      btn.textContent = d ? '🌙' : '☀️';
    });
  }
})();
</script>

<?php include __DIR__ . '/nav_end.php'; ?>
</body>
</html>
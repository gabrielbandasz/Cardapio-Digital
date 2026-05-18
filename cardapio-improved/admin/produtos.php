<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';

define('BASE_URL','../');

exigir_login();
csrf_verify();

$msg = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'remover_imagem' && isset($_GET['id'])) {
    $_POST['id'] = (int)$_GET['id'];
}

$uploadDir = __DIR__ . '/../assets/uploads/';

$pdo->exec("
CREATE TABLE IF NOT EXISTS ingredientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    emoji VARCHAR(20) DEFAULT '🍴',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS produto_opcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo ENUM('remover','extra') NOT NULL DEFAULT 'remover',
    nome VARCHAR(120) NOT NULL,
    preco DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    emoji VARCHAR(20) DEFAULT '🍴',
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_produto_tipo (produto_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

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

function salvarIngredientesProduto(PDO $pdo, int $produtoId): void {
    $novoIngrediente = trim($_POST['novo_ingrediente'] ?? '');

    if ($novoIngrediente !== '') {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO ingredientes (nome, emoji, ativo)
            VALUES (?, '🍴', 1)
        ");
        $stmt->execute([$novoIngrediente]);
    }

    $removerIds = $_POST['ingredientes_remover'] ?? [];
    $extraIds   = $_POST['ingredientes_extra'] ?? [];
    $precos     = $_POST['extra_preco'] ?? [];

    $pdo->prepare("
        DELETE FROM produto_opcoes 
        WHERE produto_id=?
    ")->execute([$produtoId]);

    $ids = array_unique(array_merge($removerIds, $extraIds));

    if (empty($ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT * FROM ingredientes 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);

    $ingredientes = [];

    foreach ($stmt->fetchAll() as $ing) {
        $ingredientes[(int)$ing['id']] = $ing;
    }

    $insert = $pdo->prepare("
        INSERT INTO produto_opcoes
        (produto_id, tipo, nome, preco, emoji, ativo, ordem)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");

    $ordem = 1;

    foreach ($removerIds as $ingId) {
        $ingId = (int)$ingId;

        if (!isset($ingredientes[$ingId])) continue;

        $insert->execute([
            $produtoId,
            'remover',
            $ingredientes[$ingId]['nome'],
            0,
            $ingredientes[$ingId]['emoji'] ?: '🍴',
            $ordem++
        ]);
    }

    foreach ($extraIds as $ingId) {
        $ingId = (int)$ingId;

        if (!isset($ingredientes[$ingId])) continue;

        $preco = isset($precos[$ingId]) && $precos[$ingId] !== ''
            ? (float)str_replace(',', '.', $precos[$ingId])
            : 0;

        $insert->execute([
            $produtoId,
            'extra',
            $ingredientes[$ingId]['nome'],
            $preco,
            $ingredientes[$ingId]['emoji'] ?: '🍴',
            $ordem++
        ]);
    }
}

function buscarSelecionadosProduto(PDO $pdo, int $produtoId): array {
    $remover = [];
    $extras = [];
    $precos = [];

    if ($produtoId <= 0) {
        return [$remover, $extras, $precos];
    }

    $stmt = $pdo->prepare("
        SELECT po.*, i.id AS ingrediente_id
        FROM produto_opcoes po
        LEFT JOIN ingredientes i ON i.nome = po.nome
        WHERE po.produto_id=? AND po.ativo=1
    ");
    $stmt->execute([$produtoId]);

    foreach ($stmt->fetchAll() as $opcao) {
        if (!$opcao['ingrediente_id']) continue;

        $ingId = (int)$opcao['ingrediente_id'];

        if ($opcao['tipo'] === 'remover') {
            $remover[] = $ingId;
        }

        if ($opcao['tipo'] === 'extra') {
            $extras[] = $ingId;
            $precos[$ingId] = $opcao['preco'];
        }
    }

    return [$remover, $extras, $precos];
}

if ($action === 'toggle') {
    $pdo->prepare("
        UPDATE produtos 
        SET disponivel=? 
        WHERE id=?
    ")->execute([
        (int)$_POST['disponivel'],
        (int)$_POST['id']
    ]);

    $msg = 'Produto atualizado!';

} elseif ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);

    $nome       = trim($_POST['nome']);
    $preco      = (float)str_replace(',', '.', $_POST['preco']);
    $preco_original = $_POST['preco_original'] !== '' 
        ? (float)str_replace(',', '.', $_POST['preco_original']) 
        : null;

    $descricao  = trim($_POST['descricao'] ?? '');
    $emoji      = trim($_POST['emoji'] ?? '🍽️');
    $catId      = (int)$_POST['categoria_id'];
    $destaque   = (int)($_POST['destaque'] ?? 0);
    $maisVend   = (int)($_POST['mais_vendido'] ?? 0);

    $imagem = null;

    if (!empty($_FILES['imagem']['name'])) {
        $imagem = processarImagem($_FILES['imagem'], $uploadDir);
    }

    if (!$imagem && !empty($_POST['imagem_url'])) {
        $imagem = trim($_POST['imagem_url']);
    }

    if ($id > 0) {
        if ($imagem) {
            $old = $pdo->prepare("
                SELECT imagem 
                FROM produtos 
                WHERE id=?
            ");
            $old->execute([$id]);
            $oldR = $old->fetch();

            if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'], 'assets/uploads/') === 0) {
                @unlink(__DIR__ . '/../' . $oldR['imagem']);
            }

            $pdo->prepare("
                UPDATE produtos 
                SET nome=?, preco=?, preco_original=?, descricao=?, emoji=?, categoria_id=?, destaque=?, mais_vendido=?, imagem=? 
                WHERE id=?
            ")->execute([
                $nome,
                $preco,
                $preco_original,
                $descricao,
                $emoji,
                $catId,
                $destaque,
                $maisVend,
                $imagem,
                $id
            ]);
        } else {
            $pdo->prepare("
                UPDATE produtos 
                SET nome=?, preco=?, preco_original=?, descricao=?, emoji=?, categoria_id=?, destaque=?, mais_vendido=? 
                WHERE id=?
            ")->execute([
                $nome,
                $preco,
                $preco_original,
                $descricao,
                $emoji,
                $catId,
                $destaque,
                $maisVend,
                $id
            ]);
        }

        salvarIngredientesProduto($pdo, $id);

        $msg = '✅ Produto atualizado!';

    } else {
        $pdo->prepare("
            INSERT INTO produtos 
            (categoria_id, nome, descricao, preco, preco_original, emoji, destaque, mais_vendido, disponivel, imagem) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ")->execute([
            $catId,
            $nome,
            $descricao,
            $preco,
            $preco_original,
            $emoji,
            $destaque,
            $maisVend,
            $imagem
        ]);

        $novoId = (int)$pdo->lastInsertId();

        salvarIngredientesProduto($pdo, $novoId);

        $msg = '✅ Produto adicionado!';
    }

} elseif ($action === 'delete') {
    $id = (int)$_POST['id'];

    $old = $pdo->prepare("
        SELECT imagem 
        FROM produtos 
        WHERE id=?
    ");
    $old->execute([$id]);
    $oldR = $old->fetch();

    if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'], 'assets/uploads/') === 0) {
        @unlink(__DIR__ . '/../' . $oldR['imagem']);
    }

    $pdo->prepare("
        DELETE FROM produto_opcoes 
        WHERE produto_id=?
    ")->execute([$id]);

    $pdo->prepare("
        DELETE FROM produtos 
        WHERE id=?
    ")->execute([$id]);

    $msg = '🗑 Produto removido.';

} elseif ($action === 'remover_imagem') {
    $id = (int)$_POST['id'];

    $old = $pdo->prepare("
        SELECT imagem 
        FROM produtos 
        WHERE id=?
    ");
    $old->execute([$id]);
    $oldR = $old->fetch();

    if ($oldR && $oldR['imagem'] && strpos($oldR['imagem'], 'assets/uploads/') === 0) {
        @unlink(__DIR__ . '/../' . $oldR['imagem']);
    }

    $pdo->prepare("
        UPDATE produtos 
        SET imagem=NULL 
        WHERE id=?
    ")->execute([$id]);

    header("Location: produtos.php?edit={$id}&msg=imagem_removida");
    exit;
}

$ingredientes = $pdo->query("
    SELECT * 
    FROM ingredientes 
    WHERE ativo=1 
    ORDER BY nome
")->fetchAll();

$produtos = $pdo->query("
    SELECT p.*, c.nome AS cat_nome 
    FROM produtos p 
    LEFT JOIN categorias c ON c.id=p.categoria_id 
    ORDER BY c.nome, p.nome
")->fetchAll();

$categorias = $pdo->query("
    SELECT * 
    FROM categorias 
    ORDER BY nome
")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editProd = null;

if ($editId) {
    foreach ($produtos as $p) {
        if ((int)$p['id'] === $editId) {
            $editProd = $p;
            break;
        }
    }
}

[$ingredientesRemoverSelecionados, $ingredientesExtraSelecionados, $precosExtrasSelecionados] = buscarSelecionadosProduto($pdo, $editId);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Produtos — Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
.img-preview-wrap { position:relative; display:inline-block; }
.img-preview { width:100%; max-height:180px; object-fit:cover; border-radius:12px; border:2px solid var(--border); margin-bottom:8px; }

.img-upload-area {
  border:2px dashed var(--border);
  border-radius:12px;
  padding:32px 20px;
  text-align:center;
  cursor:pointer;
  transition:border-color .2s;
  position:relative;
}

.img-upload-area:hover { border-color:var(--primary); }
.img-upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
.img-upload-area .icon { font-size:32px; margin-bottom:8px; }
.img-upload-area p { font-size:13px; color:var(--muted); }

.img-tab-btn {
  padding:8px 16px;
  border:1px solid var(--border);
  background:none;
  cursor:pointer;
  font-size:13px;
  color:var(--text);
}

.img-tab-btn.active {
  background:var(--primary);
  color:#fff;
  border-color:var(--primary);
}

.img-tab-btn:first-child { border-radius:8px 0 0 8px; }
.img-tab-btn:last-child { border-radius:0 8px 8px 0; }

.help-text {
  font-size:12px;
  color:var(--muted);
  margin-top:6px;
  line-height:1.5;
}

.ingredientes-box {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}

.ingrediente-card {
  border:1px solid var(--border);
  padding:10px;
  border-radius:10px;
  display:flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
  background:rgba(255,255,255,0.02);
}

.extra-card {
  display:grid;
  grid-template-columns:1fr 120px;
  gap:10px;
  margin-bottom:10px;
  align-items:center;
  border:1px solid var(--border);
  padding:10px;
  border-radius:10px;
  background:rgba(255,255,255,0.02);
}

@media (max-width:700px) {
  .ingredientes-box {
    grid-template-columns:1fr;
  }

  .extra-card {
    grid-template-columns:1fr;
  }
}
</style>
</head>

<body>

<?php include __DIR__ . '/nav.php'; ?>

<div class="admin-wrap">

  <div class="section-title">
    🍽 Produtos 
    <a href="produtos.php?edit=0" class="btn btn-primary" style="padding:6px 14px;font-size:13px">
      + Novo produto
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="alerta alerta-sucesso"><?= h($msg) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['edit'])): ?>
  <div class="card mb-4">

    <h3 style="margin-bottom:20px">
      <?= $editProd ? '✏️ Editar produto' : '➕ Novo produto' ?>
    </h3>

    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editProd ? (int)$editProd['id'] : 0 ?>">

      <div class="form-group">
        <label class="form-label">📸 Imagem do produto</label>

        <?php if ($editProd && !empty($editProd['imagem'])): ?>
        <div class="img-preview-wrap" style="display:block;margin-bottom:10px">
          <img src="../<?= h($editProd['imagem']) ?>" alt="Imagem atual" class="img-preview">

          <a href="produtos.php?action=remover_imagem&id=<?= (int)$editProd['id'] ?>"
             class="btn btn-danger"
             style="font-size:12px;padding:4px 10px;display:inline-flex;margin-top:6px"
             onclick="return confirm('Remover imagem?')">
             🗑 Remover imagem
          </a>
        </div>
        <?php endif; ?>

        <div style="margin-bottom:10px">
          <button type="button" class="img-tab-btn active" id="tabUpload" onclick="switchTab('upload')">⬆ Upload</button>
          <button type="button" class="img-tab-btn" id="tabUrl" onclick="switchTab('url')">🔗 URL</button>
        </div>

        <div id="areaUpload">
          <div class="img-upload-area" id="dropArea">
            <input type="file" name="imagem" accept="image/*" id="fileInput" onchange="previewFile(this)">
            <div class="icon">🖼</div>
            <p>
              <strong>Clique ou arraste uma imagem aqui</strong><br>
              JPG, PNG, WEBP até 4MB
            </p>
          </div>

          <img id="imgPreviewNew" src="" alt="" style="display:none;width:100%;max-height:160px;object-fit:cover;border-radius:12px;margin-top:8px">
        </div>

        <div id="areaUrl" style="display:none">
          <input 
            name="imagem_url" 
            id="imgUrlInput" 
            class="form-control" 
            placeholder="https://exemplo.com/imagem.jpg" 
            value="<?= (!empty($editProd['imagem']) && strpos($editProd['imagem'], 'http') === 0) ? h($editProd['imagem']) : '' ?>"
          >

          <img id="imgUrlPreview" src="" alt="" style="display:none;width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-top:8px">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Emoji</label>
          <input 
            name="emoji" 
            class="form-control" 
            value="<?= h($editProd['emoji'] ?? '🍔') ?>" 
            style="font-size:24px;text-align:center;padding:8px"
          >
        </div>

        <div class="form-group">
          <label class="form-label">Categoria *</label>

          <select name="categoria_id" class="form-control">
            <?php foreach ($categorias as $c): ?>
              <option 
                value="<?= (int)$c['id'] ?>" 
                <?= ($editProd && (int)$editProd['categoria_id'] === (int)$c['id']) ? 'selected' : '' ?>
              >
                <?= h($c['nome']) ?>
              </option>
            <?php endforeach; ?>

            <?php if (empty($categorias)): ?>
              <option value="1">Geral</option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nome do produto *</label>
        <input 
          name="nome" 
          class="form-control" 
          required 
          value="<?= h($editProd['nome'] ?? '') ?>" 
          placeholder="Ex: X-Burguer Clássico"
        >
      </div>

      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea 
          name="descricao" 
          class="form-control" 
          rows="2" 
          placeholder="Descrição curta que aparece no cardápio"
        ><?= h($editProd['descricao'] ?? '') ?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Preço (R$) *</label>
          <input 
            type="number" 
            step="0.01" 
            name="preco" 
            class="form-control" 
            required 
            value="<?= h($editProd['preco'] ?? '') ?>" 
            placeholder="0,00"
          >
        </div>

        <div class="form-group">
          <label class="form-label">Preço original (riscado)</label>
          <input 
            type="number" 
            step="0.01" 
            name="preco_original" 
            class="form-control" 
            value="<?= h($editProd['preco_original'] ?? '') ?>" 
            placeholder="0,00"
          >
        </div>
      </div>

      <div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600">
          <input 
            type="checkbox" 
            name="destaque" 
            value="1" 
            <?= ($editProd['destaque'] ?? 0) ? 'checked' : '' ?>
          > 
          ⭐ Destaque
        </label>

        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600">
          <input 
            type="checkbox" 
            name="mais_vendido" 
            value="1" 
            <?= ($editProd['mais_vendido'] ?? 0) ? 'checked' : '' ?>
          > 
          🔥 Mais vendido
        </label>
      </div>

      <hr>

      <h3 style="margin-bottom:10px">🥬 Ingredientes do produto</h3>

      <div class="form-group">
        <label class="form-label">Selecione os ingredientes que o cliente pode remover</label>

        <?php if (empty($ingredientes)): ?>
          <div class="help-text">
            Nenhum ingrediente cadastrado ainda. Escreva um novo ingrediente abaixo e salve o produto.
          </div>
        <?php endif; ?>

        <div class="ingredientes-box">
          <?php foreach ($ingredientes as $ing): ?>
            <label class="ingrediente-card">
              <input 
                type="checkbox" 
                name="ingredientes_remover[]" 
                value="<?= (int)$ing['id'] ?>"
                <?= in_array((int)$ing['id'], $ingredientesRemoverSelecionados) ? 'checked' : '' ?>
              >

              <span><?= h($ing['emoji'] ?? '🍴') ?> <?= h($ing['nome']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="help-text">
          Marque aqui os ingredientes que aparecem no produto e que o cliente poderá remover.
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adicionar novo ingrediente na lista</label>
        <input 
          type="text"
          name="novo_ingrediente"
          class="form-control"
          placeholder="Ex: Bacon, Ovo, Cebola, Tomate..."
        >
        <div class="help-text">
          Ao salvar, esse ingrediente entra na lista para poder selecionar depois em qualquer produto.
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">➕ Ingredientes extras pagos</label>

        <?php if (empty($ingredientes)): ?>
          <div class="help-text">
            Nenhum extra disponível ainda. Primeiro cadastre ingredientes na lista.
          </div>
        <?php endif; ?>

        <?php foreach ($ingredientes as $ing): ?>
          <div class="extra-card">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input 
                type="checkbox" 
                name="ingredientes_extra[]" 
                value="<?= (int)$ing['id'] ?>"
                <?= in_array((int)$ing['id'], $ingredientesExtraSelecionados) ? 'checked' : '' ?>
              >

              <span><?= h($ing['emoji'] ?? '🍴') ?> <?= h($ing['nome']) ?></span>
            </label>

            <input 
              type="number"
              step="0.01"
              name="extra_preco[<?= (int)$ing['id'] ?>]"
              placeholder="Preço"
              class="form-control"
              value="<?= h($precosExtrasSelecionados[(int)$ing['id']] ?? '') ?>"
            >
          </div>
        <?php endforeach; ?>

        <div class="help-text">
          Marque os ingredientes que o cliente pode adicionar como extra e coloque o preço.
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1;padding:14px">
          💾 Salvar produto
        </button>

        <a href="produtos.php" class="btn btn-outline" style="flex:1;padding:14px;text-align:center">
          Cancelar
        </a>
      </div>

    </form>
  </div>
  <?php endif; ?>

  <div class="pedidos-wrap">
    <table class="pedidos-table">
      <thead>
        <tr>
          <th style="width:60px">Img</th>
          <th>Produto</th>
          <th>Preço</th>
          <th>Categoria</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($produtos as $p): ?>
        <tr>
          <td>
            <?php if (!empty($p['imagem'])): ?>
              <img 
                src="../<?= h($p['imagem']) ?>" 
                alt="" 
                style="width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid var(--border)"
              >
            <?php else: ?>
              <span style="font-size:32px;display:block;text-align:center">
                <?= h($p['emoji'] ?? '🍽') ?>
              </span>
            <?php endif; ?>
          </td>

          <td>
            <strong><?= h($p['nome']) ?></strong>

            <?php if ($p['destaque']): ?>
              <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">⭐</span>
            <?php endif; ?>

            <?php if ($p['mais_vendido']): ?>
              <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:10px">🔥</span>
            <?php endif; ?>

            <?php if ($p['descricao']): ?>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">
                <?= h(substr($p['descricao'], 0, 60)) ?>...
              </div>
            <?php endif; ?>
          </td>

          <td class="text-primary fw-bold">
            <?= formatar_dinheiro((float)$p['preco']) ?>

            <?php if ($p['preco_original'] ?? null): ?>
              <br>
              <span style="text-decoration:line-through;font-size:11px;color:var(--muted)">
                <?= formatar_dinheiro((float)$p['preco_original']) ?>
              </span>
            <?php endif; ?>
          </td>

          <td style="font-size:13px;color:var(--muted)">
            <?= h($p['cat_nome'] ?? '—') ?>
          </td>

          <td>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>

              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="disponivel" value="<?= $p['disponivel'] ? 0 : 1 ?>">

              <button 
                type="submit" 
                class="btn <?= $p['disponivel'] ? 'btn-success' : 'btn-danger' ?>" 
                style="padding:4px 12px;font-size:12px"
              >
                <?= $p['disponivel'] ? '✅ Ativo' : '❌ Pausado' ?>
              </button>
            </form>
          </td>

          <td style="white-space:nowrap">
            <a 
              href="produtos.php?edit=<?= (int)$p['id'] ?>" 
              class="btn btn-outline" 
              style="padding:4px 10px;font-size:12px"
            >
              ✏️ Editar
            </a>

            <form 
              method="POST" 
              style="display:inline;margin-left:4px" 
              onsubmit="return confirm('Remover produto?')"
            >
              <?= csrf_field() ?>

              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

              <button 
                type="submit" 
                class="btn btn-danger" 
                style="padding:4px 10px;font-size:12px"
              >
                🗑
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($produtos)): ?>
        <tr>
          <td colspan="6">
            <div class="empty-state" style="padding:30px">
              <p>
                Nenhum produto cadastrado ainda.<br>
                <a href="produtos.php?edit=0" class="text-primary">
                  Adicionar primeiro produto
                </a>
              </p>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function switchTab(tab) {
  document.getElementById('areaUpload').style.display = tab === 'upload' ? 'block' : 'none';
  document.getElementById('areaUrl').style.display = tab === 'url' ? 'block' : 'none';

  document.getElementById('tabUpload').className = 'img-tab-btn' + (tab === 'upload' ? ' active' : '');
  document.getElementById('tabUrl').className = 'img-tab-btn' + (tab === 'url' ? ' active' : '');
}

function previewFile(input) {
  const file = input.files[0];

  if (!file) return;

  const reader = new FileReader();

  reader.onload = e => {
    const img = document.getElementById('imgPreviewNew');
    img.src = e.target.result;
    img.style.display = 'block';
  };

  reader.readAsDataURL(file);
}

const urlInput = document.getElementById('imgUrlInput');
const urlPreview = document.getElementById('imgUrlPreview');

if (urlInput && urlPreview) {
  urlInput.addEventListener('input', () => {
    const v = urlInput.value.trim();

    if (v.startsWith('http')) {
      urlPreview.src = v;
      urlPreview.style.display = 'block';
    } else {
      urlPreview.style.display = 'none';
    }
  });

  if (urlInput.value.trim().startsWith('http')) {
    urlPreview.src = urlInput.value;
    urlPreview.style.display = 'block';
  }
}

const drop = document.getElementById('dropArea');

if (drop) {
  drop.addEventListener('dragover', e => {
    e.preventDefault();
    drop.style.borderColor = 'var(--primary)';
  });

  drop.addEventListener('dragleave', () => {
    drop.style.borderColor = '';
  });

  drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.style.borderColor = '';

    const file = e.dataTransfer.files[0];

    if (file) {
      document.getElementById('fileInput').files = e.dataTransfer.files;
      previewFile({files:[file]});
    }
  });
}

(function(){
  const root = document.documentElement;
  const btn = document.getElementById('darkToggle');

  if (localStorage.getItem('darkMode') === '1') {
    root.setAttribute('data-theme', 'dark');
  }

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
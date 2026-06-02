<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
define('BASE_URL','../');
exigir_login();
csrf_verify();

// Atualizar status via POST (AJAX ou form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['novo_status'])) {
    $statusValidos = ['novo','confirmado','preparo','pronto','entregue','cancelado'];
    $id = (int)$_POST['pedido_id'];
    $status = $_POST['novo_status'];
    if ($id > 0 && in_array($status, $statusValidos)) {
        $pdo->prepare("UPDATE pedidos SET status=? WHERE id=?")->execute([$status, $id]);
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'erro'=>'Dados inválidos']);
    }
    exit;
}

// Buscar pedidos agrupados por status (máx. 3 dias)
$colunas = [
    'novo'       => ['label'=>'Novo',        'icon'=>'📋', 'cor'=>'#3b82f6'],
    'confirmado' => ['label'=>'Confirmado',   'icon'=>'✅', 'cor'=>'#8b5cf6'],
    'preparo'    => ['label'=>'Em Preparo',   'icon'=>'🍳', 'cor'=>'#f97316'],
    'pronto'     => ['label'=>'Pronto',       'icon'=>'🔔', 'cor'=>'#22c55e'],
    'entregue'   => ['label'=>'Entregue',     'icon'=>'🛵', 'cor'=>'#6b7280'],
];

$pedidosPorStatus = [];
foreach (array_keys($colunas) as $st) {
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(pi.id) as qtd_itens
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE p.status = ? AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY p.id ORDER BY p.created_at DESC LIMIT 20
    ");
    $stmt->execute([$st]);
    $pedidosPorStatus[$st] = $stmt->fetchAll();
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kanban — Pedidos</title>
<script>if(localStorage.getItem('darkMode')==='0')document.documentElement.setAttribute('data-theme','light');</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Kanban Layout ── */
.kanban-wrap{overflow-x:auto;padding-bottom:24px;min-height:60vh}
.kanban-board{display:flex;gap:14px;min-width:900px;align-items:flex-start}
.kanban-col{flex:1;min-width:200px;max-width:260px}
.kanban-col-header{border-radius:10px 10px 0 0;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
.kanban-col-title{font-size:13px;font-weight:800;display:flex;align-items:center;gap:7px}
.kanban-col-count{background:rgba(255,255,255,.2);color:#fff;border-radius:99px;font-size:11px;font-weight:800;padding:2px 8px;min-width:20px;text-align:center}
.kanban-col-body{min-height:200px;border:1px solid var(--border);border-top:none;border-radius:0 0 10px 10px;padding:10px;background:var(--surface);display:flex;flex-direction:column;gap:8px;transition:background .15s}
.kanban-col-body.drag-over{background:rgba(232,93,4,.05);border-color:rgba(232,93,4,.3)}
.kanban-empty{text-align:center;padding:30px 10px;color:var(--muted);font-size:12px;opacity:.6}
.kanban-empty-icon{font-size:28px;margin-bottom:6px;display:block}

/* ── Kanban Card ── */
.kcard{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px;cursor:grab;transition:all .18s;position:relative;user-select:none}
.kcard:hover{border-color:rgba(232,93,4,.3);box-shadow:0 4px 16px rgba(0,0,0,.3);transform:translateY(-1px)}
.kcard:active,.kcard.dragging{cursor:grabbing;opacity:.6;transform:scale(.97)}
.kcard-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.kcard-num{font-weight:800;font-size:14px;color:var(--text)}
.kcard-hora{font-size:11px;color:var(--muted)}
.kcard-cliente{font-size:13px;color:var(--text-soft);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kcard-footer{display:flex;align-items:center;justify-content:space-between;margin-top:8px;flex-wrap:wrap;gap:4px}
.kcard-total{font-size:15px;font-weight:800;color:var(--primary)}
.kcard-tipo{font-size:11px;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:99px;padding:2px 8px}
.kcard-itens{font-size:11px;color:var(--muted)}
.kcard-actions{margin-top:8px;display:flex;gap:6px}
.kcard-btn{flex:1;font-size:11px;padding:5px 8px;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:var(--text-soft);cursor:pointer;font-family:var(--font-body);font-weight:600;transition:all .15s;text-decoration:none;text-align:center}
.kcard-btn:hover{border-color:var(--primary);color:var(--primary)}
.kcard-btn.confirm{background:var(--primary);color:#fff;border-color:var(--primary)}
.kcard-btn.confirm:hover{background:var(--primary-dark)}
.kcard-urgente{position:absolute;top:0;right:0;left:0;height:3px;border-radius:10px 10px 0 0}

/* ── Toast ── */
.ktoa{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--surface);border:1px solid var(--border);border-radius:99px;padding:10px 20px;font-size:13px;font-weight:600;z-index:999;opacity:0;transition:all .25s;pointer-events:none;white-space:nowrap}
.ktoa.show{opacity:1;transform:translateX(-50%) translateY(0)}
.ktoa.ok{border-color:rgba(34,197,94,.4);color:var(--success)}
.ktoa.err{border-color:rgba(239,68,68,.4);color:var(--danger)}

/* ── Poll indicator ── */
.poll-indicator{position:fixed;bottom:24px;right:24px;background:var(--surface);border:1px solid var(--border);border-radius:99px;padding:8px 14px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:7px;z-index:100}
.poll-dot{width:8px;height:8px;border-radius:99px;background:var(--success);animation:poll-pulse 2s ease-in-out infinite}
@keyframes poll-pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── Page header ── */
.kb-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.kb-title{font-family:var(--font-display);font-size:22px;font-weight:700;display:flex;align-items:center;gap:8px}
.kb-sub{font-size:13px;color:var(--muted);margin-top:2px}

@media(max-width:600px){
  .kanban-board{min-width:700px}
  .kanban-col{min-width:165px}
}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="admin-wrap">

<div class="kb-page-header">
  <div>
    <div class="kb-title">📋 Kanban de Pedidos</div>
    <div class="kb-sub">Arraste os cards para mudar o status | Últimas 24h</div>
  </div>
  <div style="display:flex;gap:10px">
    <button onclick="location.reload()" class="btn btn-outline btn-sm">🔄 Atualizar</button>
    <a href="pedidos.php" class="btn btn-outline btn-sm">Ver lista →</a>
  </div>
</div>

<div class="kanban-wrap">
<div class="kanban-board" id="kanbanBoard">
<?php foreach ($colunas as $status => $col): ?>
  <div class="kanban-col" id="col-<?= $status ?>">
    <div class="kanban-col-header" style="background:<?= $col['cor'] ?>">
      <span class="kanban-col-title">
        <?= $col['icon'] ?> <?= $col['label'] ?>
      </span>
      <span class="kanban-col-count"><?= count($pedidosPorStatus[$status]) ?></span>
    </div>
    <div class="kanban-col-body" data-status="<?= $status ?>">
      <?php if (empty($pedidosPorStatus[$status])): ?>
        <div class="kanban-empty">
          <span class="kanban-empty-icon">✨</span>
          Vazio
        </div>
      <?php else: ?>
        <?php foreach ($pedidosPorStatus[$status] as $p): ?>
          <?php
            $minutos = round((time() - strtotime($p['created_at'])) / 60);
            $urgente = $status === 'novo' && $minutos > 10;
          ?>
          <div class="kcard" draggable="true" data-id="<?= $p['id'] ?>" data-status="<?= $status ?>">
            <?php if ($urgente): ?>
              <div class="kcard-urgente" style="background:#ef4444"></div>
            <?php endif; ?>
            <div class="kcard-top">
              <span class="kcard-num">#<?= h($p['numero']) ?></span>
              <span class="kcard-hora" title="<?= $urgente ? "⚠️ Aguarda $minutos min" : '' ?>">
                <?= $urgente ? "⚠️ {$minutos}m" : date('H:i', strtotime($p['created_at'])) ?>
              </span>
            </div>
            <div class="kcard-cliente">👤 <?= h($p['nome_cliente'] ?: '—') ?></div>
            <div class="kcard-footer">
              <span class="kcard-total"><?= formatar_dinheiro((float)$p['total']) ?></span>
              <span class="kcard-tipo"><?= $p['tipo_entrega']==='retirada'?'🏃':'🛵' ?></span>
            </div>
            <div class="kcard-actions">
              <a href="pedido.php?id=<?= $p['id'] ?>" class="kcard-btn">👁 Ver</a>
              <?php
              $proxStatus = ['novo'=>'confirmado','confirmado'=>'preparo','preparo'=>'pronto','pronto'=>'entregue'];
              $proxLabel  = ['novo'=>'Confirmar','confirmado'=>'Preparar','preparo'=>'Pronto','pronto'=>'Entregue'];
              if (isset($proxStatus[$status])): ?>
                <button class="kcard-btn confirm" onclick="moverCard(<?= $p['id'] ?>,'<?= $proxStatus[$status] ?>')">
                  <?= $proxLabel[$status] ?>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
</div>

</div><!-- /admin-wrap -->
<?php include __DIR__ . '/nav_end.php'; ?>

<div class="poll-indicator">
  <span class="poll-dot"></span> Ao vivo
</div>
<div class="ktoa" id="ktoa"></div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

// ── Toast ──────────────────────────────────────────────
function kToast(msg, type='ok'){
  const t=document.getElementById('ktoa');
  t.textContent=msg; t.className='ktoa show '+type;
  clearTimeout(t._t); t._t=setTimeout(()=>t.className='ktoa',2800);
}

// ── Mover card via botão ───────────────────────────────
async function moverCard(id, novoStatus){
  try {
    const fd = new FormData();
    fd.append('pedido_id', id);
    fd.append('novo_status', novoStatus);
    fd.append('csrf_token', CSRF);
    const r = await fetch('kanban.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) { kToast('✅ Status atualizado!', 'ok'); location.reload(); }
    else kToast('❌ Erro: ' + (d.erro||''), 'err');
  } catch(e){ kToast('❌ Falha na conexão', 'err'); }
}

// ── Drag and Drop ──────────────────────────────────────
let dragged = null;

document.querySelectorAll('.kcard').forEach(card => {
  card.addEventListener('dragstart', e => {
    dragged = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.id);
  });
  card.addEventListener('dragend', () => {
    card.classList.remove('dragging');
    dragged = null;
    document.querySelectorAll('.kanban-col-body').forEach(c => c.classList.remove('drag-over'));
  });
});

document.querySelectorAll('.kanban-col-body').forEach(col => {
  col.addEventListener('dragover', e => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    col.classList.add('drag-over');
  });
  col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
  col.addEventListener('drop', async e => {
    e.preventDefault();
    col.classList.remove('drag-over');
    const id = e.dataTransfer.getData('text/plain');
    const novoStatus = col.dataset.status;
    if (!dragged || dragged.dataset.status === novoStatus) return;

    // Mover visualmente primeiro (otimistic UI)
    col.appendChild(dragged);
    dragged.dataset.status = novoStatus;
    const emptyEl = col.querySelector('.kanban-empty');
    if (emptyEl) emptyEl.remove();

    // Atualizar contadores
    atualizarContadores();

    // Persistir no servidor
    await moverCard(id, novoStatus);
  });
});

function atualizarContadores(){
  document.querySelectorAll('.kanban-col').forEach(col => {
    const status = col.id.replace('col-','');
    const count = col.querySelectorAll('.kcard').length;
    const badge = col.querySelector('.kanban-col-count');
    if (badge) badge.textContent = count;
  });
}

// ── Poll automático a cada 30s ─────────────────────────
let _ultimoId = 0;
setInterval(async () => {
  try {
    const r = await fetch('../api/novo_pedido_poll.php?desde='+_ultimoId);
    const d = await r.json();
    if (d.novos > 0) {
      _ultimoId = d.ultimo_id;
      kToast('🔔 ' + d.novos + ' novo(s) pedido(s)!', 'ok');
      if (Notification.permission==='granted')
        new Notification('🍔 Novo pedido!',{body:d.novos+' pedido(s) aguardando confirmação.'});
      setTimeout(()=>location.reload(), 2000);
    }
  } catch(e){}
}, 30000);

if('Notification' in window && Notification.permission==='default') Notification.requestPermission();
</script>
</body>
</html>

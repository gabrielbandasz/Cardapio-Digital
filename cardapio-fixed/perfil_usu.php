<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['cliente_id'])) {
    header('Location: login_cliente.php?redir=' . urlencode('perfil_usu.php'));
    exit;
}

$id     = (int)$_SESSION['cliente_id'];
$config = $pdo->query("SELECT nome_restaurante, cor_primaria, logo, logo_emoji FROM config WHERE id=1")->fetch();
$cor    = h($config['cor_primaria'] ?? '#e85d04');

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    session_destroy();
    header('Location: login_cliente.php');
    exit;
}

// Estatísticas de pedidos (seguro mesmo que a tabela/coluna não exista)
$stats = ['total' => 0, 'gasto' => 0];
try {
    $pedidos = $pdo->prepare(
        "SELECT COUNT(*) as total, COALESCE(SUM(total),0) as gasto FROM pedidos WHERE cliente_id = ?"
    );
    $pedidos->execute([$id]);
    $row = $pedidos->fetch();
    if ($row) $stats = $row;
} catch (Exception $e) {
    // tabela pedidos sem cliente_id ou inexistente — ignora
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Meu Perfil — <?= h($config['nome_restaurante']) ?></title>
<style>:root{--primary:<?= $cor ?>;--primary-dark:color-mix(in srgb,<?= $cor ?> 80%,black);}</style>
<link rel="stylesheet" href="assets/css/style.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family: inherit; }

.perfil-wrap {
  min-height: 100vh;
  padding: 0 16px 48px;
  max-width: 560px;
  margin: 0 auto;
}

/* Header */
.perfil-header {
  display: flex; align-items: center; gap: 12px;
  padding: 20px 0 24px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 24px;
}
.perfil-header a {
  display: flex; align-items: center; gap: 6px;
  color: var(--muted); font-size: 13px; font-weight: 600;
  text-decoration: none;
  padding: 8px 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface);
  transition: all .15s;
}
.perfil-header a:hover { color: var(--text); border-color: var(--primary); }
.perfil-header a svg { width:15px; height:15px; }
.perfil-header-title { font-size: 20px; font-weight: 800; color: var(--text); }

/* Avatar e info do topo */
.perfil-top {
  display: flex; align-items: center; gap: 16px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 20px;
  margin-bottom: 20px;
}
.perfil-avatar {
  width: 60px; height: 60px; border-radius: 50%;
  background: color-mix(in srgb, var(--primary) 15%, transparent);
  border: 2px solid color-mix(in srgb, var(--primary) 30%, transparent);
  display: flex; align-items: center; justify-content: center;
  color: var(--primary); flex-shrink: 0;
}
.perfil-avatar svg { width: 28px; height: 28px; }
.perfil-top-info { flex: 1; min-width: 0; }
.perfil-top-nome  { font-size: 18px; font-weight: 800; color: var(--text); }
.perfil-top-email { font-size: 13px; color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.perfil-top-membro { font-size: 12px; color: var(--muted); margin-top: 4px; }

/* Stats */
.perfil-stats {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 12px; margin-bottom: 20px;
}
.stat-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 14px; text-align: center;
}
.stat-val { font-size: 22px; font-weight: 900; color: var(--primary); }
.stat-lbl { font-size: 11px; color: var(--muted); margin-top: 2px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }

/* Seção de formulário */
.perfil-section {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 24px;
  margin-bottom: 16px;
}
.perfil-section-title {
  font-size: 15px; font-weight: 800; color: var(--text);
  margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}
.perfil-section-title svg { width: 17px; height: 17px; color: var(--primary); }

.field { margin-bottom: 16px; }
.field:last-child { margin-bottom: 0; }
.field label {
  display: flex; align-items: center; gap: 7px;
  font-size: 13px; font-weight: 600;
  color: var(--text-soft); margin-bottom: 6px;
}
.field label svg { width: 14px; height: 14px; color: var(--muted); flex-shrink: 0; }
.field input {
  width: 100%; padding: 12px 14px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  background: var(--bg); color: var(--text);
  font-size: 15px; font-family: inherit;
  transition: border-color .15s;
}
.field input:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent);
}
.field input::placeholder { color: var(--muted); }
.hint { font-size: 11.5px; color: var(--muted); margin-top: 4px; }
.hint.ok  { color: #16a34a; }
.hint.err { color: #dc2626; }

.show-pw { position: relative; }
.show-pw input { padding-right: 42px; }
.show-pw button {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--muted);
  padding: 0; display: flex; align-items: center;
}
.show-pw button svg { width: 18px; height: 18px; }

.pw-separator {
  font-size: 12px; color: var(--muted); font-weight: 600;
  margin: 20px 0 16px; display: flex; align-items: center; gap: 8px;
}
.pw-separator::before, .pw-separator::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* Botões */
.btn-salvar {
  width: 100%; padding: 14px;
  background: var(--primary); color: #fff;
  border: none; border-radius: var(--radius-sm);
  font-size: 15px; font-weight: 700; cursor: pointer;
  transition: background .15s, transform .1s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 20px;
}
.btn-salvar:hover { background: var(--primary-dark); }
.btn-salvar:active { transform: scale(.98); }
.btn-salvar:disabled { opacity: .6; cursor: not-allowed; }

.btn-logout {
  width: 100%; padding: 13px;
  background: transparent; color: var(--muted);
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 14px; font-weight: 700; cursor: pointer;
  transition: all .15s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-logout:hover { color: var(--text); border-color: var(--text); }

.btn-excluir {
  width: 100%; padding: 13px;
  background: transparent; color: #dc2626;
  border: 1.5px solid #dc2626; border-radius: var(--radius-sm);
  font-size: 14px; font-weight: 700; cursor: pointer;
  transition: all .15s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 8px;
}
.btn-excluir:hover { background: rgba(220,38,38,.08); }

/* Alertas */
.msg-ok  { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; font-weight: 500; margin-bottom: 16px; display: none; }
.msg-err { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; font-weight: 500; margin-bottom: 16px; display: none; }

/* Modal exclusão */
.modal-del {
  position: fixed; inset: 0; z-index: 999;
  background: rgba(0,0,0,.7); display: none;
  align-items: center; justify-content: center; padding: 24px;
}
.modal-del.open { display: flex; }
.modal-del-box {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 32px;
  max-width: 380px; width: 100%;
}
.modal-del-title { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.modal-del-sub   { font-size: 14px; color: var(--muted); margin-bottom: 20px; line-height: 1.5; }
.modal-del-err   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; margin-bottom: 12px; display: none; }
.modal-del-btns  { display: flex; gap: 10px; margin-top: 20px; }
.modal-del-btns button { flex: 1; padding: 13px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 700; cursor: pointer; border: none; }
.modal-del-cancel  { background: var(--surface2); color: var(--text); border: 1px solid var(--border) !important; }
.modal-del-confirm { background: #dc2626; color: #fff; }
.modal-del-confirm:hover { background: #b91c1c; }

.spinner { width:18px; height:18px; border:2.5px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>
<div class="perfil-wrap">

  <!-- Header -->
  <div class="perfil-header">
    <a href="menu.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Voltar ao cardápio
    </a>
    <div class="perfil-header-title">Meu Perfil</div>
  </div>

  <!-- Avatar e nome -->
  <div class="perfil-top">
    <div class="perfil-avatar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div class="perfil-top-info">
      <div class="perfil-top-nome"><?= h($cliente['nome'] ?? 'Usuário') ?></div>
      <div class="perfil-top-email"><?= !empty($cliente['email']) ? h($cliente['email']) : 'E-mail não cadastrado' ?></div>
      <div class="perfil-top-membro">Membro desde <?= isset($cliente['created_at']) ? date('M/Y', strtotime($cliente['created_at'])) : '—' ?></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="perfil-stats">
    <div class="stat-card">
      <div class="stat-val"><?= (int)$stats['total'] ?></div>
      <div class="stat-lbl">Pedidos</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= (int)($cliente['pontos'] ?? 0) ?></div>
      <div class="stat-lbl">Pontos</div>
    </div>
    <div class="stat-card">
      <div class="stat-val">R$&nbsp;<?= number_format((float)$stats['gasto'], 0, ',', '.') ?></div>
      <div class="stat-lbl">Total gasto</div>
    </div>
  </div>

  <!-- Formulário de edição -->
  <div class="perfil-section">
    <div class="perfil-section-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Meus dados
    </div>

    <div class="msg-ok"  id="msgOk"></div>
    <div class="msg-err" id="msgErr"></div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Nome
      </label>
      <input type="text" id="nome" value="<?= h($cliente['nome'] ?? '') ?>" autocomplete="name">
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        WhatsApp
      </label>
      <input type="tel" id="whatsapp" value="<?= h($cliente['whatsapp'] ?? '') ?>" maxlength="16" inputmode="numeric" autocomplete="tel">
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        E-mail
      </label>
      <input type="email" id="email" value="<?= h($cliente['email'] ?? '') ?>" autocomplete="email">
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Endereço de entrega
      </label>
      <input type="text" id="endereco" value="<?= h($cliente['endereco'] ?? '') ?>" placeholder="Rua, número, bairro" autocomplete="street-address">
    </div>

    <div class="pw-separator">Alterar senha (opcional)</div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Nova senha
      </label>
      <div class="show-pw">
        <input type="password" id="senha" placeholder="Deixe em branco para não alterar">
        <button type="button" onclick="togglePw('senha',this)" tabindex="-1">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Confirmar nova senha
      </label>
      <div class="show-pw">
        <input type="password" id="confirma" placeholder="Repita a nova senha">
        <button type="button" onclick="togglePw('confirma',this)" tabindex="-1">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button class="btn-salvar" id="btnSalvar" onclick="salvar()">
      <div class="spinner" id="spin"></div>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <span id="btnTxt">Salvar alterações</span>
    </button>
  </div>

  <!-- Ações da conta -->
  <div class="perfil-section">
    <div class="perfil-section-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
      Conta
    </div>

    <button class="btn-logout" onclick="logout()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sair da conta
    </button>

    <button class="btn-excluir" onclick="abrirModalExcluir()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      Excluir minha conta
    </button>
  </div>

</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal-del" id="modalExcluir">
  <div class="modal-del-box">
    <div class="modal-del-title">Excluir conta</div>
    <div class="modal-del-sub">Esta ação é irreversível. Todos os seus dados serão removidos permanentemente. Para confirmar, digite sua senha atual.</div>
    <input type="password" id="senhaExcluir" placeholder="Sua senha atual"
      style="width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:var(--bg);color:var(--text);font-size:15px;font-family:inherit;margin-bottom:12px;box-sizing:border-box;">
    <div class="modal-del-err" id="erroExcluir"></div>
    <div class="modal-del-btns">
      <button class="modal-del-cancel" onclick="fecharModalExcluir()">Cancelar</button>
      <button class="modal-del-confirm" id="btnConfirmarExclusao" onclick="excluirConta()">Excluir</button>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

document.getElementById('whatsapp').addEventListener('input', e => {
  let v = e.target.value.replace(/\D/g,'').slice(0,11);
  if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
  e.target.value = v;
});

function togglePw(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
  btn.querySelector('svg').innerHTML = i.type === 'password'
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
}

function showMsg(id, msg) {
  ['msgOk','msgErr'].forEach(x => document.getElementById(x).style.display = 'none');
  const el = document.getElementById(id);
  el.textContent = msg; el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function salvar() {
  const nome     = document.getElementById('nome').value.trim();
  const whatsapp = document.getElementById('whatsapp').value;
  const email    = document.getElementById('email').value.trim();
  const endereco = document.getElementById('endereco').value.trim();
  const senha    = document.getElementById('senha').value;
  const confirma = document.getElementById('confirma').value;

  if (!nome) { showMsg('msgErr', 'Informe seu nome.'); return; }
  if (senha && senha !== confirma) { showMsg('msgErr', 'As senhas não coincidem.'); return; }

  const btn  = document.getElementById('btnSalvar');
  const spin = document.getElementById('spin');
  const txt  = document.getElementById('btnTxt');
  btn.disabled = true; spin.style.display = 'block'; txt.textContent = 'Salvando...';

  const fd = new FormData();
  fd.append('action','update_profile'); fd.append('csrf_token',CSRF);
  fd.append('nome',nome); fd.append('whatsapp',whatsapp);
  fd.append('email',email); fd.append('endereco',endereco);
  if (senha) { fd.append('senha',senha); fd.append('confirma',confirma); }

  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      showMsg('msgOk', 'Dados salvos com sucesso!');
      document.getElementById('senha').value    = '';
      document.getElementById('confirma').value = '';
    } else {
      showMsg('msgErr', d.erro || 'Erro ao salvar.');
    }
  } catch { showMsg('msgErr', 'Erro de conexão. Tente novamente.'); }

  btn.disabled = false; spin.style.display = 'none'; txt.textContent = 'Salvar alterações';
}

async function logout() {
  const fd = new FormData();
  fd.append('action','logout'); fd.append('csrf_token',CSRF);
  await fetch('api/auth_cliente.php', { method:'POST', body:fd });
  window.location.href = 'menu.php';
}

function abrirModalExcluir() {
  document.getElementById('senhaExcluir').value = '';
  document.getElementById('erroExcluir').style.display = 'none';
  document.getElementById('modalExcluir').classList.add('open');
}

function fecharModalExcluir() {
  document.getElementById('modalExcluir').classList.remove('open');
}

async function excluirConta() {
  const senha = document.getElementById('senhaExcluir').value;
  document.getElementById('erroExcluir').style.display = 'none';
  if (!senha) {
    const el = document.getElementById('erroExcluir');
    el.textContent = 'Digite sua senha para confirmar.'; el.style.display = 'block'; return;
  }

  const btn = document.getElementById('btnConfirmarExclusao');
  btn.textContent = 'Excluindo...'; btn.disabled = true;

  const fd = new FormData();
  fd.append('action','delete_account'); fd.append('csrf_token',CSRF); fd.append('senha',senha);

  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) { window.location.href = 'menu.php'; }
    else {
      const el = document.getElementById('erroExcluir');
      el.textContent = d.erro || 'Erro ao excluir.'; el.style.display = 'block';
      btn.textContent = 'Excluir'; btn.disabled = false;
    }
  } catch {
    const el = document.getElementById('erroExcluir');
    el.textContent = 'Erro de conexão.'; el.style.display = 'block';
    btn.textContent = 'Excluir'; btn.disabled = false;
  }
}

document.getElementById('modalExcluir').addEventListener('click', e => {
  if (e.target === e.currentTarget) fecharModalExcluir();
});

const saved = localStorage.getItem('darkMode') ?? '1';
document.documentElement.setAttribute('data-theme', saved === '1' ? 'dark' : 'light');
</script>
</body>
</html>
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['cliente_id'])) {
    header('Location: menu.php');
    exit;
}

$config = $pdo->query("SELECT nome_restaurante, cor_primaria, logo, logo_emoji FROM config WHERE id=1")->fetch();
$cor    = h($config['cor_primaria'] ?? '#e85d04');
$redir  = $_GET['redir'] ?? 'menu.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Entrar — <?= h($config['nome_restaurante']) ?></title>
<style>:root{--primary:<?= $cor ?>;--primary-dark:color-mix(in srgb,<?= $cor ?> 80%,black);}</style>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; }
.auth-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:36px 32px 32px; width:100%; max-width:420px; box-shadow:0 8px 40px rgba(0,0,0,.12); }
.auth-logo { text-align:center; margin-bottom:8px; font-size:44px; line-height:1; }
.auth-title { text-align:center; font-size:22px; font-weight:800; color:var(--text); margin-bottom:4px; }
.auth-sub   { text-align:center; font-size:13px; color:var(--muted); margin-bottom:28px; }
.field { margin-bottom:16px; }
.field label { display:flex; align-items:center; gap:7px; font-size:13px; font-weight:600; color:var(--text-soft); margin-bottom:6px; }
.field label svg { width:15px; height:15px; color:var(--muted); flex-shrink:0; }
.field input { width:100%; padding:12px 14px; border:1.5px solid var(--border); border-radius:var(--radius-sm); background:var(--bg); color:var(--text); font-size:15px; font-family:inherit; transition:border-color .15s; box-sizing:border-box; }
.field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent); }
.field input::placeholder { color:var(--muted); }
.btn-auth { width:100%; padding:14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; transition:background .15s,transform .1s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-auth:hover { background:var(--primary-dark); }
.btn-auth:active { transform:scale(.98); }
.btn-auth:disabled { opacity:.6; cursor:not-allowed; }
.auth-divider { text-align:center; color:var(--muted); font-size:13px; margin:20px 0 16px; position:relative; }
.auth-divider::before,.auth-divider::after { content:''; position:absolute; top:50%; width:38%; height:1px; background:var(--border); }
.auth-divider::before { left:0; } .auth-divider::after { right:0; }
.btn-cadastro { width:100%; padding:13px; background:transparent; color:var(--primary); border:1.5px solid var(--primary); border-radius:var(--radius-sm); font-size:14px; font-weight:700; cursor:pointer; transition:background .15s; text-align:center; text-decoration:none; display:block; }
.btn-cadastro:hover { background:color-mix(in srgb,var(--primary) 8%,transparent); }
.auth-erro { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; border-radius:var(--radius-sm); padding:10px 14px; font-size:13px; font-weight:500; margin-bottom:16px; display:none; }
.auth-guest { text-align:center; margin-top:20px; font-size:13px; color:var(--muted); }
.auth-guest a { color:var(--primary); font-weight:600; text-decoration:none; }
.spinner { width:18px; height:18px; border:2.5px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
@keyframes spin { to { transform:rotate(360deg); } }
.show-pw { position:relative; } .show-pw input { padding-right:42px; }
.show-pw button { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--muted); padding:0; display:flex; align-items:center; }
.show-pw button svg { width:18px; height:18px; }
</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <?php if (!empty($config['logo'])): ?>
        <img src="<?= h($config['logo']) ?>" style="width:52px;height:52px;border-radius:14px;object-fit:cover;">
      <?php else: ?>
        <?= $config['logo_emoji'] ?: '🍽️' ?>
      <?php endif; ?>
    </div>
    <div class="auth-title">Bem-vindo de volta!</div>
    <div class="auth-sub"><?= h($config['nome_restaurante']) ?> — faça login para continuar</div>
    <div class="auth-erro" id="erro"></div>
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        WhatsApp
      </label>
      <input type="tel" id="whatsapp" placeholder="(51) 99999-9999" maxlength="16" autocomplete="tel" inputmode="numeric">
    </div>
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Senha
      </label>
      <div class="show-pw">
        <input type="password" id="senha" placeholder="Sua senha" autocomplete="current-password">
        <button type="button" onclick="togglePw('senha',this)" tabindex="-1">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>
    <button class="btn-auth" id="btnLogin" onclick="login()">
      <div class="spinner" id="spin"></div>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      <span id="btnTxt">Entrar</span>
    </button>
    <div class="auth-divider">ou</div>
    <a href="cadastro_cliente.php<?= $redir !== 'menu.php' ? '?redir='.urlencode($redir) : '' ?>" class="btn-cadastro">Criar conta gratuita</a>
    <div class="auth-guest"><a href="<?= h($redir) ?>">Continuar sem conta →</a></div>
  </div>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const REDIR = <?= json_encode($redir) ?>;

document.getElementById('whatsapp').addEventListener('input', e => {
  let v = e.target.value.replace(/\D/g,'').slice(0,11);
  if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
  e.target.value = v;
});
document.getElementById('senha').addEventListener('keydown', e => { if (e.key === 'Enter') login(); });

function togglePw(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
  btn.querySelector('svg').innerHTML = i.type === 'password'
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
}
function setLoading(on) {
  document.getElementById('spin').style.display  = on ? 'block' : 'none';
  document.getElementById('btnTxt').textContent  = on ? 'Entrando...' : 'Entrar';
  document.getElementById('btnLogin').disabled   = on;
}
function showErro(msg) {
  const el = document.getElementById('erro');
  el.textContent = msg; el.style.display = 'block';
}
async function login() {
  const wpp = document.getElementById('whatsapp').value;
  const senha = document.getElementById('senha').value;
  document.getElementById('erro').style.display = 'none';
  if (!wpp || !senha) { showErro('Preencha WhatsApp e senha.'); return; }
  setLoading(true);
  const fd = new FormData();
  fd.append('action','login'); fd.append('csrf_token',CSRF);
  fd.append('whatsapp',wpp); fd.append('senha',senha);
  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) { localStorage.setItem('clienteNome', d.nome); window.location.href = REDIR; }
    else { showErro(d.erro || 'Erro ao entrar.'); setLoading(false); }
  } catch { showErro('Erro de conexão. Tente novamente.'); setLoading(false); }
}
const saved = localStorage.getItem('darkMode') ?? '1';
document.documentElement.setAttribute('data-theme', saved === '1' ? 'dark' : 'light');
</script>
</body>
</html>
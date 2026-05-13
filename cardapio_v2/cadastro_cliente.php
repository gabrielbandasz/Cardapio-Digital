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
<title>Criar conta — <?= h($config['nome_restaurante']) ?></title>
<style>:root{--primary:<?= $cor ?>;--primary-dark:color-mix(in srgb,<?= $cor ?> 80%,black);}</style>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.auth-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
}
.auth-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 36px 32px 32px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 8px 40px rgba(0,0,0,.12);
}
.auth-logo { text-align:center; margin-bottom:8px; font-size:44px; line-height:1; }
.auth-title { text-align:center; font-size:22px; font-weight:800; color:var(--text); margin-bottom:4px; }
.auth-sub   { text-align:center; font-size:13px; color:var(--muted); margin-bottom:28px; }
.field { margin-bottom:16px; }
.field label { display:block; font-size:13px; font-weight:600; color:var(--text-soft); margin-bottom:6px; }
.field input {
  width:100%; padding:12px 14px;
  border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  background:var(--bg); color:var(--text);
  font-size:15px; font-family:inherit;
  transition:border-color .15s; box-sizing:border-box;
}
.field input:focus {
  outline:none; border-color:var(--primary);
  box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent);
}
.field input::placeholder { color:var(--muted); }
.field input.ok  { border-color:#16a34a; }
.field input.err { border-color:#dc2626; }
.hint { font-size:11.5px; color:var(--muted); margin-top:4px; }
.hint.err { color:#dc2626; }
.hint.ok  { color:#16a34a; }
.btn-auth {
  width:100%; padding:14px;
  background:var(--primary); color:#fff;
  border:none; border-radius:var(--radius-sm);
  font-size:15px; font-weight:700;
  cursor:pointer; margin-top:8px;
  transition:background .15s, transform .1s;
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.btn-auth:hover { background:var(--primary-dark); }
.btn-auth:active { transform:scale(.98); }
.btn-auth:disabled { opacity:.6; cursor:not-allowed; }
.auth-footer { text-align:center; margin-top:20px; font-size:13px; color:var(--muted); }
.auth-footer a { color:var(--primary); font-weight:600; text-decoration:none; }
.auth-erro {
  background:#fef2f2; color:#dc2626;
  border:1px solid #fca5a5;
  border-radius:var(--radius-sm);
  padding:10px 14px; font-size:13px;
  font-weight:500; margin-bottom:16px; display:none;
}
.auth-ok {
  background:#f0fdf4; color:#16a34a;
  border:1px solid #86efac;
  border-radius:var(--radius-sm);
  padding:10px 14px; font-size:13px;
  font-weight:500; margin-bottom:16px; display:none;
}
.pw-strength {
  display:flex; gap:4px; margin-top:6px;
}
.pw-bar {
  flex:1; height:3px; border-radius:99px;
  background:var(--border); transition:background .2s;
}
.spinner {
  width:18px; height:18px;
  border:2.5px solid rgba(255,255,255,.4);
  border-top-color:#fff; border-radius:50%;
  animation:spin .7s linear infinite; display:none;
}
@keyframes spin { to { transform:rotate(360deg); } }
.show-pw { position:relative; }
.show-pw input { padding-right:42px; }
.show-pw button {
  position:absolute; right:12px; top:50%;
  transform:translateY(-50%);
  background:none; border:none;
  cursor:pointer; color:var(--muted);
  font-size:17px; padding:0; line-height:1;
}
.beneficios {
  background:var(--surface2);
  border:1px solid var(--border);
  border-radius:var(--radius-sm);
  padding:14px 16px;
  margin-bottom:24px;
  font-size:13px;
  color:var(--text-soft);
  line-height:1.8;
}
.beneficios strong { color:var(--text); display:block; margin-bottom:4px; font-size:13px; }
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

    <div class="auth-title">Criar minha conta</div>
    <div class="auth-sub">É rápido, grátis e vale a pena 😄</div>

    <div class="beneficios">
      <strong>✨ Vantagens de ter uma conta:</strong>
      🎯 Acompanhe seus pedidos em tempo real<br>
      🔄 Repita pedidos anteriores com 1 clique<br>
      🎁 Participe do programa de fidelidade<br>
      💬 Receba ofertas exclusivas pelo WhatsApp
    </div>

    <div class="auth-erro" id="erro"></div>
    <div class="auth-ok"  id="ok"></div>

    <div class="field">
      <label>👤 Seu nome</label>
      <input type="text" id="nome" placeholder="Como quer ser chamado?" autocomplete="name">
    </div>

    <div class="field">
      <label>📱 WhatsApp</label>
      <input type="tel" id="whatsapp" placeholder="(51) 99999-9999" maxlength="16" autocomplete="tel" inputmode="numeric">
      <div class="hint" id="wppHint"></div>
    </div>

    <div class="field">
      <label>🔒 Senha</label>
      <div class="show-pw">
        <input type="password" id="senha" placeholder="Mínimo 6 caracteres" oninput="checkSenha()">
        <button type="button" onclick="togglePw('senha',this)" tabindex="-1">👁️</button>
      </div>
      <div class="pw-strength">
        <div class="pw-bar" id="bar1"></div>
        <div class="pw-bar" id="bar2"></div>
        <div class="pw-bar" id="bar3"></div>
        <div class="pw-bar" id="bar4"></div>
      </div>
      <div class="hint" id="senhaHint"></div>
    </div>

    <div class="field">
      <label>🔒 Confirme a senha</label>
      <div class="show-pw">
        <input type="password" id="confirma" placeholder="Repita a senha" oninput="checkConfirma()">
        <button type="button" onclick="togglePw('confirma',this)" tabindex="-1">👁️</button>
      </div>
      <div class="hint" id="confirmaHint"></div>
    </div>

    <button class="btn-auth" id="btnCad" onclick="cadastrar()">
      <div class="spinner" id="spin"></div>
      <span id="btnTxt">Criar conta</span>
    </button>

    <div class="auth-footer">
      Já tem conta? <a href="login_cliente.php<?= $redir !== 'menu.php' ? '?redir='.urlencode($redir) : '' ?>">Fazer login</a>
    </div>

  </div>
</div>

<script>
const CSRF  = <?= json_encode(csrf_token()) ?>;
const REDIR = <?= json_encode($redir) ?>;

// Máscara WhatsApp
document.getElementById('whatsapp').addEventListener('input', e => {
  let v = e.target.value.replace(/\D/g,'').slice(0,11);
  if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
  e.target.value = v;
  const digits = v.replace(/\D/g,'');
  const hint = document.getElementById('wppHint');
  if (digits.length === 11) { hint.textContent = '✔ Número válido'; hint.className = 'hint ok'; }
  else if (digits.length > 0) { hint.textContent = 'Digite o DDD + número (11 dígitos)'; hint.className = 'hint err'; }
  else { hint.textContent = ''; }
});

function togglePw(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
  btn.textContent = i.type === 'password' ? '👁️' : '🙈';
}

function checkSenha() {
  const v = document.getElementById('senha').value;
  const bars = [document.getElementById('bar1'), document.getElementById('bar2'),
                document.getElementById('bar3'), document.getElementById('bar4')];
  const hint = document.getElementById('senhaHint');
  let force = 0;
  if (v.length >= 6)  force++;
  if (v.length >= 8)  force++;
  if (/[0-9]/.test(v) && /[a-zA-Z]/.test(v)) force++;
  if (/[^a-zA-Z0-9]/.test(v)) force++;
  const colors = ['#dc2626','#f59e0b','#3b82f6','#16a34a'];
  bars.forEach((b, i) => b.style.background = i < force ? colors[force-1] : 'var(--border)');
  const labels = ['','Fraca','Média','Boa','Forte'];
  hint.textContent  = v ? `Força: ${labels[force]}` : '';
  hint.className    = v.length >= 6 ? 'hint ok' : 'hint err';
}

function checkConfirma() {
  const s = document.getElementById('senha').value;
  const c = document.getElementById('confirma').value;
  const hint = document.getElementById('confirmaHint');
  const inp  = document.getElementById('confirma');
  if (!c) { hint.textContent = ''; return; }
  if (s === c) { hint.textContent = '✔ Senhas iguais'; hint.className = 'hint ok'; inp.className = 'ok'; }
  else         { hint.textContent = '✖ Senhas diferentes'; hint.className = 'hint err'; inp.className = 'err'; }
}

document.getElementById('confirma').addEventListener('keydown', e => {
  if (e.key === 'Enter') cadastrar();
});

function setLoading(on) {
  document.getElementById('spin').style.display = on ? 'block' : 'none';
  document.getElementById('btnTxt').textContent  = on ? 'Criando...' : 'Criar conta';
  document.getElementById('btnCad').disabled     = on;
}

function showErro(msg) {
  document.getElementById('ok').style.display   = 'none';
  const el = document.getElementById('erro');
  el.textContent = msg; el.style.display = 'block';
}

async function cadastrar() {
  document.getElementById('erro').style.display = 'none';
  document.getElementById('ok').style.display   = 'none';

  const nome     = document.getElementById('nome').value.trim();
  const whatsapp = document.getElementById('whatsapp').value;
  const senha    = document.getElementById('senha').value;
  const confirma = document.getElementById('confirma').value;

  if (!nome)     { showErro('Informe seu nome.'); return; }
  if (whatsapp.replace(/\D/g,'').length < 11) { showErro('WhatsApp inválido.'); return; }
  if (senha.length < 6) { showErro('Senha muito curta (mínimo 6 caracteres).'); return; }
  if (senha !== confirma) { showErro('As senhas não coincidem.'); return; }

  setLoading(true);
  const fd = new FormData();
  fd.append('action',     'cadastro');
  fd.append('csrf_token', CSRF);
  fd.append('nome',       nome);
  fd.append('whatsapp',   whatsapp);
  fd.append('senha',      senha);
  fd.append('confirma',   confirma);

  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      localStorage.setItem('clienteNome', d.nome);
      const ok = document.getElementById('ok');
      ok.textContent = `✔ Conta criada! Bem-vindo, ${d.nome}! Redirecionando...`;
      ok.style.display = 'block';
      setTimeout(() => window.location.href = REDIR, 1200);
    } else {
      showErro(d.erro || 'Erro ao criar conta.');
      setLoading(false);
    }
  } catch {
    showErro('Erro de conexão. Tente novamente.');
    setLoading(false);
  }
}

// Dark mode
const saved = localStorage.getItem('darkMode') ?? '1';
document.documentElement.setAttribute('data-theme', saved === '1' ? 'dark' : 'light');
</script>
</body>
</html>

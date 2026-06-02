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
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; }
.auth-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:36px 32px 32px; width:100%; max-width:460px; box-shadow:0 8px 40px rgba(0,0,0,.12); }
.auth-logo { text-align:center; margin-bottom:8px; font-size:44px; line-height:1; }
.auth-title { text-align:center; font-size:22px; font-weight:800; color:var(--text); margin-bottom:4px; }
.auth-sub   { text-align:center; font-size:13px; color:var(--muted); margin-bottom:28px; }
.field { margin-bottom:16px; }
.field label { display:flex; align-items:center; gap:7px; font-size:13px; font-weight:600; color:var(--text-soft); margin-bottom:6px; }
.field label svg { width:15px; height:15px; color:var(--muted); flex-shrink:0; }
.field input { width:100%; padding:12px 14px; border:1.5px solid var(--border); border-radius:var(--radius-sm); background:var(--bg); color:var(--text); font-size:15px; font-family:inherit; transition:border-color .15s; box-sizing:border-box; }
.field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent); }
.field input::placeholder { color:var(--muted); }
.field input.ok  { border-color:#16a34a; }
.field input.err { border-color:#dc2626; }
.hint { font-size:11.5px; color:var(--muted); margin-top:4px; }
.hint.err { color:#dc2626; } .hint.ok { color:#16a34a; }
.btn-auth { width:100%; padding:14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; transition:background .15s,transform .1s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-auth:hover { background:var(--primary-dark); } .btn-auth:active { transform:scale(.98); } .btn-auth:disabled { opacity:.6; cursor:not-allowed; }
.auth-footer { text-align:center; margin-top:20px; font-size:13px; color:var(--muted); }
.auth-footer a { color:var(--primary); font-weight:600; text-decoration:none; }
.auth-erro { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; border-radius:var(--radius-sm); padding:10px 14px; font-size:13px; font-weight:500; margin-bottom:16px; display:none; }
.auth-ok   { background:#f0fdf4; color:#16a34a; border:1px solid #86efac; border-radius:var(--radius-sm); padding:10px 14px; font-size:13px; font-weight:500; margin-bottom:16px; display:none; }
.aviso-debug { background:#fefce8; color:#92400e; border:1px solid #fcd34d; border-radius:var(--radius-sm); padding:10px 14px; font-size:13px; font-weight:600; margin-bottom:16px; display:none; }
.pw-strength { display:flex; gap:4px; margin-top:6px; }
.pw-bar { flex:1; height:3px; border-radius:99px; background:var(--border); transition:background .2s; }
.spinner { width:18px; height:18px; border:2.5px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
@keyframes spin { to { transform:rotate(360deg); } }
.show-pw { position:relative; } .show-pw input { padding-right:42px; }
.show-pw button { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--muted); padding:0; display:flex; align-items:center; }
.show-pw button svg { width:18px; height:18px; }
.step { display:none; } .step.active { display:block; }
.verificacao-info { text-align:center; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px; margin-bottom:20px; font-size:13px; color:var(--text-soft); line-height:1.6; }
.verificacao-info strong { color:var(--text); font-size:15px; display:block; margin-bottom:4px; }
.codigo-input { text-align:center; font-size:28px; font-weight:800; letter-spacing:12px; padding:16px; }
.reenviar { text-align:center; margin-top:12px; font-size:13px; color:var(--muted); }
.reenviar button { background:none; border:none; color:var(--primary); font-weight:600; cursor:pointer; font-size:13px; font-family:inherit; }
.reenviar button:disabled { color:var(--muted); cursor:default; }
.step-back { background:none; border:none; color:var(--muted); font-size:13px; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:6px; margin-bottom:16px; padding:0; }
.step-back:hover { color:var(--text); }
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

    <!-- PASSO 1: Formulário -->
    <div class="step active" id="step1">
      <div class="auth-title">Criar minha conta</div>
      <div class="auth-sub">É rápido e grátis</div>
      <div class="auth-erro" id="erro1"></div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Seu nome</label>
        <input type="text" id="nome" placeholder="Como quer ser chamado?" autocomplete="name">
      </div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>WhatsApp</label>
        <input type="tel" id="whatsapp" placeholder="(51) 99999-9999" maxlength="16" autocomplete="tel" inputmode="numeric">
        <div class="hint" id="wppHint"></div>
      </div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>E-mail (para verificação)</label>
        <input type="email" id="email" placeholder="seu@email.com" autocomplete="email">
        <div class="hint" id="emailHint"></div>
      </div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>Endereço de entrega</label>
        <input type="text" id="endereco" placeholder="Rua, número, bairro" autocomplete="street-address">
      </div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Senha</label>
        <div class="show-pw">
          <input type="password" id="senha" placeholder="Mínimo 6 caracteres" oninput="checkSenha()">
          <button type="button" onclick="togglePw('senha',this)" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="pw-strength">
          <div class="pw-bar" id="bar1"></div><div class="pw-bar" id="bar2"></div>
          <div class="pw-bar" id="bar3"></div><div class="pw-bar" id="bar4"></div>
        </div>
        <div class="hint" id="senhaHint"></div>
      </div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Confirmar senha</label>
        <div class="show-pw">
          <input type="password" id="confirma" placeholder="Repita a senha" oninput="checkConfirma()">
          <button type="button" onclick="togglePw('confirma',this)" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="hint" id="confirmaHint"></div>
      </div>

      <button class="btn-auth" id="btnEnviarCodigo" onclick="enviarCodigo()">
        <div class="spinner" id="spin1"></div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <span id="btnCodTxt">Enviar código de verificação</span>
      </button>

      <div class="auth-footer">
        Já tem conta? <a href="login_cliente.php<?= $redir !== 'menu.php' ? '?redir='.urlencode($redir) : '' ?>">Fazer login</a>
      </div>
    </div>

    <!-- PASSO 2: Verificação do código -->
    <div class="step" id="step2">
      <button class="step-back" onclick="goStep(1)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
      </button>

      <div class="auth-title">Verificar e-mail</div>

      <div class="verificacao-info">
        <strong>Código enviado!</strong>
        Enviamos um código de 6 dígitos para<br>
        <strong id="emailDestino" style="color:var(--primary)"></strong><br>
        <small>Verifique também a caixa de spam.</small>
      </div>

      <!-- Aviso amarelo do modo debug (some em produção) -->
      <div class="aviso-debug" id="avisoDebug"></div>

      <div class="auth-erro" id="erro2"></div>
      <div class="auth-ok"   id="ok2"></div>

      <div class="field">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Código de verificação</label>
        <input type="text" id="codigoEmail" class="codigo-input" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
      </div>

      <button class="btn-auth" id="btnCriarConta" onclick="criarConta()">
        <div class="spinner" id="spin2"></div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="btnCriarTxt">Criar conta</span>
      </button>

      <div class="reenviar">
        <span id="reenviarTxt">Não recebeu? </span>
        <button id="btnReenviar" onclick="reenviarCodigo()">Reenviar código</button>
      </div>
    </div>

  </div>
</div>

<script>
const CSRF  = <?= json_encode(csrf_token()) ?>;
const REDIR = <?= json_encode($redir) ?>;

document.getElementById('whatsapp').addEventListener('input', e => {
  let v = e.target.value.replace(/\D/g,'').slice(0,11);
  if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
  e.target.value = v;
  const digits = v.replace(/\D/g,'');
  const hint = document.getElementById('wppHint');
  if (digits.length === 11) { hint.textContent = 'Número válido'; hint.className = 'hint ok'; }
  else if (digits.length > 0) { hint.textContent = 'Digite o DDD + número (11 dígitos)'; hint.className = 'hint err'; }
  else { hint.textContent = ''; }
});

document.getElementById('email').addEventListener('blur', e => {
  const v = e.target.value.trim();
  const hint = document.getElementById('emailHint');
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  if (!v) { hint.textContent = ''; return; }
  hint.textContent = ok ? 'E-mail válido' : 'E-mail inválido';
  hint.className = ok ? 'hint ok' : 'hint err';
});

function togglePw(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
  btn.querySelector('svg').innerHTML = i.type === 'password'
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
}

function checkSenha() {
  const v = document.getElementById('senha').value;
  const bars = ['bar1','bar2','bar3','bar4'].map(id => document.getElementById(id));
  const hint = document.getElementById('senhaHint');
  let force = 0;
  if (v.length >= 6) force++;
  if (v.length >= 8) force++;
  if (/[0-9]/.test(v) && /[a-zA-Z]/.test(v)) force++;
  if (/[^a-zA-Z0-9]/.test(v)) force++;
  const colors = ['#dc2626','#f59e0b','#3b82f6','#16a34a'];
  bars.forEach((b, i) => b.style.background = i < force ? colors[force-1] : 'var(--border)');
  hint.textContent = v ? 'Força: ' + ['','Fraca','Média','Boa','Forte'][force] : '';
  hint.className   = v.length >= 6 ? 'hint ok' : 'hint err';
}

function checkConfirma() {
  const s = document.getElementById('senha').value;
  const c = document.getElementById('confirma').value;
  const hint = document.getElementById('confirmaHint');
  if (!c) { hint.textContent = ''; return; }
  if (s === c) { hint.textContent = 'Senhas iguais'; hint.className = 'hint ok'; document.getElementById('confirma').className = 'ok'; }
  else         { hint.textContent = 'Senhas diferentes'; hint.className = 'hint err'; document.getElementById('confirma').className = 'err'; }
}

function showErro(el, msg) {
  const e = document.getElementById(el);
  e.textContent = msg; e.style.display = 'block';
}

function goStep(n) {
  document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
  document.getElementById('step' + n).classList.add('active');
}

let contadorReenviar = 0;

function iniciarContador() {
  contadorReenviar = 60;
  const btn = document.getElementById('btnReenviar');
  const txt = document.getElementById('reenviarTxt');
  btn.disabled = true;
  const iv = setInterval(() => {
    contadorReenviar--;
    txt.textContent = 'Reenviar em ' + contadorReenviar + 's. ';
    if (contadorReenviar <= 0) {
      clearInterval(iv);
      txt.textContent = 'Não recebeu? ';
      btn.disabled = false;
    }
  }, 1000);
}

async function enviarCodigo() {
  document.getElementById('erro1').style.display = 'none';
  const nome     = document.getElementById('nome').value.trim();
  const whatsapp = document.getElementById('whatsapp').value;
  const email    = document.getElementById('email').value.trim();
  const endereco = document.getElementById('endereco').value.trim();
  const senha    = document.getElementById('senha').value;
  const confirma = document.getElementById('confirma').value;

  if (!nome)     { showErro('erro1','Informe seu nome.'); return; }
  if (whatsapp.replace(/\D/g,'').length < 11) { showErro('erro1','WhatsApp inválido.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showErro('erro1','Informe um e-mail válido.'); return; }
  if (senha.length < 6) { showErro('erro1','Senha muito curta (mínimo 6 caracteres).'); return; }
  if (senha !== confirma) { showErro('erro1','As senhas não coincidem.'); return; }

  const btn = document.getElementById('btnEnviarCodigo');
  const spin = document.getElementById('spin1');
  const txt = document.getElementById('btnCodTxt');
  btn.disabled = true; spin.style.display = 'block'; txt.textContent = 'Enviando...';

  const fd = new FormData();
  fd.append('action','enviar_codigo'); fd.append('csrf_token',CSRF);
  fd.append('nome',nome); fd.append('whatsapp',whatsapp);
  fd.append('email',email); fd.append('endereco',endereco);
  fd.append('senha',senha); fd.append('confirma',confirma);

  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      document.getElementById('emailDestino').textContent = email;
      goStep(2);
      iniciarContador();
      if (d.debug_codigo) {
        document.getElementById('codigoEmail').value = d.debug_codigo;
        const aviso = document.getElementById('avisoDebug');
        aviso.textContent = '⚙️ Modo teste ativo — código: ' + d.debug_codigo;
        aviso.style.display = 'block';
      }
    } else {
      showErro('erro1', d.erro || 'Erro ao enviar código.');
    }
  } catch { showErro('erro1','Erro de conexão. Tente novamente.'); }

  btn.disabled = false; spin.style.display = 'none'; txt.textContent = 'Enviar código de verificação';
}

async function reenviarCodigo() {
  document.getElementById('btnReenviar').disabled = true;
  const fd = new FormData();
  fd.append('action','reenviar_codigo'); fd.append('csrf_token',CSRF);
  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      iniciarContador();
      if (d.debug_codigo) {
        document.getElementById('codigoEmail').value = d.debug_codigo;
        const aviso = document.getElementById('avisoDebug');
        aviso.textContent = '⚙️ Modo teste ativo — código: ' + d.debug_codigo;
        aviso.style.display = 'block';
      }
    } else { alert(d.erro || 'Erro ao reenviar.'); }
  } catch { alert('Erro de conexão.'); }
}

async function criarConta() {
  document.getElementById('erro2').style.display = 'none';
  document.getElementById('ok2').style.display   = 'none';
  const codigo = document.getElementById('codigoEmail').value.trim();
  if (codigo.length !== 6) { showErro('erro2','Digite o código de 6 dígitos.'); return; }

  const btn = document.getElementById('btnCriarConta');
  const spin = document.getElementById('spin2');
  const txt = document.getElementById('btnCriarTxt');
  btn.disabled = true; spin.style.display = 'block'; txt.textContent = 'Criando conta...';

  const fd = new FormData();
  fd.append('action','cadastro'); fd.append('csrf_token',CSRF);
  fd.append('codigo',codigo);

  try {
    const r = await fetch('api/auth_cliente.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      localStorage.setItem('clienteNome', d.nome);
      const ok = document.getElementById('ok2');
      ok.textContent = 'Conta criada! Bem-vindo, ' + d.nome + '! Redirecionando...';
      ok.style.display = 'block';
      setTimeout(() => window.location.href = REDIR, 1500);
    } else {
      showErro('erro2', d.erro || 'Erro ao criar conta.');
      btn.disabled = false; spin.style.display = 'none'; txt.textContent = 'Criar conta';
    }
  } catch {
    showErro('erro2','Erro de conexão. Tente novamente.');
    btn.disabled = false; spin.style.display = 'none'; txt.textContent = 'Criar conta';
  }
}

const saved = localStorage.getItem('darkMode') ?? '1';
document.documentElement.setAttribute('data-theme', saved === '1' ? 'dark' : 'light');
</script>
</body>
</html>
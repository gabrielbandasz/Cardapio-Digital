<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate_limit.php';
define('BASE_URL', '../');

if (admin_logado()) { header('Location: dashboard.php'); exit; }

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rate_limit('login_admin', 5, 60);

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } elseif (strlen($senha) < 4) {
        $erro = 'Senha muito curta.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        $autenticado = false;

        if ($admin) {
            if (str_starts_with($admin['senha_hash'] ?? '', '$2y$')) {
                $autenticado = password_verify($senha, $admin['senha_hash']);
                if ($autenticado && password_needs_rehash($admin['senha_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    $novo_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare('UPDATE admins SET senha_hash = ? WHERE id = ?')->execute([$novo_hash, $admin['id']]);
                }
            } else {
                $hash = hash('sha256', $senha . ADMIN_SALT);
                $autenticado = hash_equals($admin['senha_hash'] ?? '', $hash);
            }
        } else {
            password_verify($senha, '$2y$12$fakehashfakehashfakehashfakehashfakehashfakehashfake');
        }

        if ($autenticado && $admin) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['_ip']        = $ip;
            $_SESSION['_last_activity'] = time();

            $redir = $_GET['redir'] ?? 'dashboard.php';
            if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redir)) $redir = 'dashboard.php';
            header('Location: ' . $redir); exit;
        } else {
            $erro = 'Email ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Painel Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg);position:relative;overflow:hidden}
    .login-page::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 20% 20%,rgba(232,93,4,.07) 0%,transparent 50%),radial-gradient(circle at 80% 80%,rgba(212,168,67,.05) 0%,transparent 50%);pointer-events:none}
    .login-page::after{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(0deg,transparent,transparent 60px,rgba(255,255,255,.012) 60px,rgba(255,255,255,.012) 61px),repeating-linear-gradient(90deg,transparent,transparent 60px,rgba(255,255,255,.012) 60px,rgba(255,255,255,.012) 61px);pointer-events:none}
    .login-card{width:100%;max-width:420px;position:relative;z-index:1;animation:lfi .4s cubic-bezier(.4,0,.2,1)}
    @keyframes lfi{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
    .login-brand{text-align:center;margin-bottom:32px}
    .login-icon{width:72px;height:72px;background:linear-gradient(135deg,var(--primary),var(--gold));border-radius:20px;display:inline-flex;align-items:center;justify-content:center;font-size:34px;margin-bottom:16px;box-shadow:0 8px 32px rgba(232,93,4,.4)}
    .login-title{font-family:var(--font-display);font-size:28px;font-weight:700;color:var(--text);margin-bottom:6px}
    .login-sub{color:var(--muted);font-size:14px}
    .login-form-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:30px;box-shadow:0 24px 80px rgba(0,0,0,.5)}
    .input-wrapper{position:relative}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none;display:flex}
    .input-icon svg{width:16px;height:16px}
    .form-control-icon{padding-left:42px!important}
    .toggle-senha{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;border-radius:6px;transition:color .15s;display:flex}
    .toggle-senha:hover{color:var(--text)}
    .toggle-senha svg{width:16px;height:16px}
    .alert-erro{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#fca5a5;border-radius:10px;padding:13px 16px;font-size:14px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
    .btn-login{width:100%;padding:14px;font-size:15px;font-weight:700;border-radius:var(--radius);background:var(--primary);color:#fff;border:none;cursor:pointer;font-family:var(--font-body);transition:all .2s;box-shadow:0 4px 18px rgba(232,93,4,.4);margin-top:8px}
    .btn-login:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 8px 28px rgba(232,93,4,.5)}
    .btn-login:active{transform:scale(.98)}
    .btn-login:disabled{opacity:.6;cursor:not-allowed;transform:none!important}
    .login-footer{text-align:center;margin-top:22px}
    .login-footer a{font-size:13px;color:var(--muted);transition:color .15s}
    .login-footer a:hover{color:var(--text-soft)}
    .theme-toggle-btn{background:var(--surface2);border:1px solid var(--border);color:var(--text-soft);border-radius:99px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;margin-top:12px;font-family:var(--font-body);transition:all .15s}
    .theme-toggle-btn:hover{border-color:var(--primary);color:var(--primary)}
    .caps-warn{font-size:11px;color:var(--warning);margin-top:5px;display:none}
    .caps-warn.show{display:block}
    [data-theme="light"] .login-page::after{background-image:repeating-linear-gradient(0deg,transparent,transparent 60px,rgba(0,0,0,.025) 60px,rgba(0,0,0,.025) 61px),repeating-linear-gradient(90deg,transparent,transparent 60px,rgba(0,0,0,.025) 60px,rgba(0,0,0,.025) 61px)}
  </style>
</head>
<body>
<script>if(localStorage.getItem('darkMode')==='0')document.documentElement.setAttribute('data-theme','light');</script>
<div class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-icon">🍽️</div>
      <h1 class="login-title">Painel Admin</h1>
      <p class="login-sub">Acesse para gerenciar seu restaurante</p>
    </div>

    <div class="login-form-card">
      <?php if ($erro): ?>
        <div class="alert-erro">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <?= h($erro) ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" autocomplete="on" novalidate>
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <input type="email" name="email" class="form-control form-control-icon"
              placeholder="admin@restaurante.com"
              value="<?= h($_POST['email'] ?? '') ?>"
              required autofocus autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Senha</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </span>
            <input type="password" name="senha" id="senhaInput" class="form-control form-control-icon"
              style="padding-right:44px"
              placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="toggle-senha" id="toggleSenha" title="Mostrar/ocultar senha">
              <svg id="iconEye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="iconOff" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="caps-warn" id="capsWarn">⚠️ Caps Lock ativado</div>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">Entrar no painel</button>
      </form>
    </div>

    <div class="login-footer">
      <a href="../index.php">← Voltar ao cardápio</a><br>
      <button class="theme-toggle-btn" id="themeToggle">🌙 Alternar tema</button>
    </div>
  </div>
</div>
<script>
(function(){
  const root=document.documentElement,btn=document.getElementById('themeToggle');
  const isDark=()=>root.getAttribute('data-theme')!=='light';
  btn.textContent=isDark()?'☀️ Tema claro':'🌙 Tema escuro';
  btn.onclick=function(){
    const dark=isDark();
    root.setAttribute('data-theme',dark?'light':'dark');
    localStorage.setItem('darkMode',dark?'0':'1');
    btn.textContent=dark?'🌙 Tema escuro':'☀️ Tema claro';
  };
})();
const si=document.getElementById('senhaInput');
document.getElementById('toggleSenha').onclick=function(){
  const isP=si.type==='password';
  si.type=isP?'text':'password';
  document.getElementById('iconEye').style.display=isP?'none':'';
  document.getElementById('iconOff').style.display=isP?'':'none';
};
si.addEventListener('keyup',function(e){
  document.getElementById('capsWarn').classList.toggle('show',e.getModifierState&&e.getModifierState('CapsLock'));
});
document.getElementById('loginForm').addEventListener('submit',function(){
  const b=document.getElementById('btnLogin');
  b.disabled=true;b.textContent='Verificando...';
});
</script>
</body>
</html>

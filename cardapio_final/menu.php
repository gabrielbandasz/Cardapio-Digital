<?php
/**
 * Cardápio Digital — Redesign Responsivo (Mobile + Tablet + Desktop)
 * Compatível com o banco de dados cardapio_digital
 * Basta substituir o menu.php original por este arquivo.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$slug   = $_GET['slug'] ?? null;
if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM config WHERE loja_slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $config = $stmt->fetch();
    if (!$config) { http_response_code(404); die('Restaurante não encontrado.'); }
}

$cor        = $config['cor_primaria'] ?? '#e85d04';
$lojaAberta = (bool)$config['aberto'];
$promoAtiva = (bool)($config['promo_ativa'] ?? 0)
    && (!$config['promo_fim'] || strtotime($config['promo_fim']) > time());

$pedidosAtivos = (int)$pdo->query(
    "SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo')
     AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)"
)->fetchColumn();
$tempoBase  = (int)($config['tempo_preparo_base'] ?? 30);
$tempoMin   = $tempoBase + $pedidosAtivos * (int)($config['tempo_preparo_por_pedido'] ?? 5);
$tempoMax   = $tempoMin + 15;
$tempoLabel = $config['modo_pico']
    ? ($config['pico_tempo'] ?? '60-80 min')
    : "{$tempoMin}–{$tempoMax} min";

$categorias  = $pdo->query(
    "SELECT * FROM categorias WHERE ativo=1 ORDER BY ordem, nome"
)->fetchAll();
$produtosRaw = $pdo->query(
    "SELECT p.*, c.nome AS cat_nome FROM produtos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     WHERE p.disponivel=1 ORDER BY c.ordem, p.ordem, p.nome"
)->fetchAll();

$porCategoria = [];
$destaques    = [];
foreach ($produtosRaw as $p) {
    $porCategoria[$p['categoria_id']][] = $p;
    if ($p['mais_vendido']) $destaques[] = $p;
}

$zonas = [];
if ($config['frete_por_zona'] ?? 0) {
    $zonas = $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1 ORDER BY taxa")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#111111">
<title><?= h($config['nome_restaurante']) ?> — Cardápio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   TOKENS
══════════════════════════════════════════════ */
:root {
  --p:      <?= $cor ?>;
  --pd:     color-mix(in srgb, <?= $cor ?> 75%, #000);
  --bg:     #111111;
  --s1:     #181818;
  --s2:     #222222;
  --s3:     #2b2b2b;
  --brd:    #2e2e2e;
  --tx:     #f0ebe3;
  --muted:  #888078;
  --green:  #22c55e;
  --r:      14px;
  --r-sm:   9px;
  --r-lg:   20px;
  --r-full: 999px;
  --cart-w: 340px;
  --side-w: 220px;
  --hd-h:   64px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg);
  color: var(--tx);
  min-height: 100dvh;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
button { font-family: inherit; cursor: pointer; border: none; outline: none; background: none; }
input, textarea { font-family: inherit; outline: none; }
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--brd); border-radius: var(--r-full); }

/* ══════════════════════════════════════════════
   TOP BAR (Desktop)
══════════════════════════════════════════════ */
.topbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: var(--hd-h);
  background: rgba(17,17,17,.96);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--brd);
  z-index: 100;
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 20px;
}
.topbar-logo {
  width: 40px; height: 40px;
  background: var(--s2);
  border: 1px solid var(--brd);
  border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
  overflow: hidden;
}
.topbar-logo img { width: 100%; height: 100%; object-fit: cover; }
.topbar-info { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.topbar-nome { font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.topbar-sub  { font-size: 11px; color: var(--muted); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.topbar-sub span { display: flex; align-items: center; gap: 4px; }
.topbar-search {
  flex: 0 1 400px;
  position: relative;
}
.topbar-search svg {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  width: 16px; height: 16px; color: var(--muted); pointer-events: none;
}
.topbar-search input {
  width: 100%; height: 38px;
  background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-full);
  color: var(--tx); font-size: 13px; font-weight: 500;
  padding: 0 14px 0 36px;
  transition: border-color .2s;
}
.topbar-search input::placeholder { color: var(--muted); }
.topbar-search input:focus { border-color: var(--p); }

/* Badges inline */
.badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: var(--r-full);
  font-size: 11px; font-weight: 700; border: 1px solid;
  white-space: nowrap;
}
.badge-open   { background: rgba(34,197,94,.1);  color: var(--green); border-color: rgba(34,197,94,.25); }
.badge-closed { background: rgba(100,100,100,.1); color: var(--muted); border-color: var(--brd); }
.badge-tempo  { background: var(--s2); color: var(--tx); border-color: var(--brd); }

/* ══════════════════════════════════════════════
   PROMO BANNER
══════════════════════════════════════════════ */
.promo-bar {
  background: linear-gradient(90deg, #ff4500, #ff8c00);
  color: #fff; padding: 10px 24px;
  display: flex; align-items: center; justify-content: center; gap: 12px;
  font-size: 13px; font-weight: 700; flex-wrap: wrap; text-align: center;
  margin-top: var(--hd-h);
}
.promo-bar button {
  background: rgba(0,0,0,.2); color: #fff; border-radius: var(--r-full);
  width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
  font-size: 12px; flex-shrink: 0;
}

/* ══════════════════════════════════════════════
   PAGE LAYOUT — 3-column on desktop
══════════════════════════════════════════════ */
.page {
  display: flex;
  padding-top: var(--hd-h);
  min-height: 100dvh;
}

/* Left sidebar: categories */
.cat-sidebar {
  width: var(--side-w);
  flex-shrink: 0;
  position: sticky;
  top: var(--hd-h);
  height: calc(100dvh - var(--hd-h));
  overflow-y: auto;
  padding: 24px 0;
  border-right: 1px solid var(--brd);
  scrollbar-width: thin;
  display: none; /* hidden on mobile */
}
.cat-sidebar-inner { display: flex; flex-direction: column; gap: 4px; padding: 0 12px; }
.cat-sidebar-btn {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: var(--r);
  color: var(--muted); font-size: 13px; font-weight: 600;
  transition: all .18s;
  width: 100%; text-align: left;
}
.cat-sidebar-btn:hover { background: var(--s2); color: var(--tx); }
.cat-sidebar-btn.ativo { background: rgba(232,93,4,.12); color: var(--p); border: 1px solid rgba(232,93,4,.2); }
.cat-sidebar-btn .emoji { font-size: 18px; }
.cat-sidebar-divider { height: 1px; background: var(--brd); margin: 8px 12px; }

/* Center: main content */
.content {
  flex: 1;
  min-width: 0;
  padding: 32px 28px 120px;
}

/* Right sidebar: cart (desktop only) */
.cart-sidebar {
  width: var(--cart-w);
  flex-shrink: 0;
  position: sticky;
  top: var(--hd-h);
  height: calc(100dvh - var(--hd-h));
  overflow-y: auto;
  border-left: 1px solid var(--brd);
  padding: 20px 20px 32px;
  display: none; /* hidden on mobile */
  flex-direction: column;
  scrollbar-width: thin;
}

/* ══════════════════════════════════════════════
   MOBILE HEADER (hidden on desktop)
══════════════════════════════════════════════ */
.mobile-hd {
  background: linear-gradient(180deg, var(--s1) 0%, var(--bg) 100%);
  border-bottom: 1px solid var(--brd);
  padding: 20px 20px 18px;
}
.mobile-hd-row { display: flex; gap: 14px; align-items: flex-start; }
.mobile-logo {
  width: 58px; height: 58px; flex-shrink: 0;
  background: var(--s2); border: 1px solid var(--brd);
  border-radius: var(--r); display: flex; align-items: center; justify-content: center;
  font-size: 28px; overflow: hidden;
}
.mobile-logo img { width: 100%; height: 100%; object-fit: cover; }
.mobile-hd-info { flex: 1; min-width: 0; }
.mobile-nome { font-size: 20px; font-weight: 800; line-height: 1.2; }
.mobile-desc { font-size: 12px; color: var(--muted); margin-top: 3px; line-height: 1.4; }
.mobile-badges { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 10px; }

/* Mobile sticky bar (search + cats) */
.mobile-nav {
  position: sticky;
  top: 0; z-index: 80;
  background: rgba(17,17,17,.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--brd);
  padding: 12px 0 0;
}
.mobile-search-wrap { padding: 0 16px 10px; }
.mobile-search { position: relative; }
.mobile-search svg {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  width: 17px; height: 17px; color: var(--muted); pointer-events: none;
}
.mobile-search input {
  width: 100%; height: 42px;
  background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-full);
  color: var(--tx); font-size: 14px; font-weight: 500;
  padding: 0 14px 0 38px;
  transition: border-color .2s;
}
.mobile-search input::placeholder { color: var(--muted); }
.mobile-search input:focus { border-color: var(--p); }
.mobile-cats {
  display: flex; gap: 8px; overflow-x: auto; padding: 0 16px 12px;
  scrollbar-width: none;
}
.mobile-cats::-webkit-scrollbar { display: none; }
.mobile-cat-btn {
  flex-shrink: 0; padding: 7px 15px;
  border-radius: var(--r-full);
  background: var(--s2); color: var(--muted);
  font-size: 12px; font-weight: 700;
  border: 1px solid var(--brd);
  transition: all .18s; white-space: nowrap;
}
.mobile-cat-btn.ativo { background: var(--p); color: #fff; border-color: var(--p); }

/* ══════════════════════════════════════════════
   SECTION TITLES
══════════════════════════════════════════════ */
.section-title {
  font-size: 18px; font-weight: 800; color: var(--tx);
  margin-bottom: 16px;
  display: flex; align-items: center; gap: 8px;
}
.cat-heading {
  font-size: 20px; font-weight: 800; color: var(--tx);
  margin-bottom: 16px; scroll-margin-top: 90px;
  display: flex; align-items: center; gap: 8px;
  border-bottom: 1px solid var(--brd); padding-bottom: 12px;
}
.cat-section { margin-bottom: 40px; scroll-margin-top: 90px; }

/* ══════════════════════════════════════════════
   MAIS PEDIDOS — carrossel
══════════════════════════════════════════════ */
.featured-section { margin-bottom: 40px; }
.featured-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 14px;
}
.featured-card {
  background: var(--s1); border: 1px solid var(--brd);
  border-radius: var(--r-lg);
  padding: 14px; cursor: pointer;
  transition: transform .15s, border-color .2s, box-shadow .2s;
  display: flex; flex-direction: column; gap: 10px;
}
.featured-card:hover { border-color: rgba(232,93,4,.4); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
.featured-card:active { transform: scale(.97); }
.featured-img {
  width: 100%; aspect-ratio: 1;
  background: var(--s2); border-radius: var(--r);
  display: flex; align-items: center; justify-content: center;
  font-size: 56px; overflow: hidden;
}
.featured-img img { width: 100%; height: 100%; object-fit: cover; }
.featured-name { font-size: 13px; font-weight: 700; line-height: 1.35; }
.featured-price { font-size: 16px; font-weight: 800; color: var(--p); }

/* ══════════════════════════════════════════════
   PRODUTO CARDS — lista (mobile) + grid (desktop)
══════════════════════════════════════════════ */
.prod-list { display: flex; flex-direction: column; gap: 12px; }

.prod-card {
  background: var(--s1); border: 1px solid var(--brd);
  border-radius: var(--r);
  display: flex; gap: 0;
  cursor: pointer;
  transition: all .15s;
  overflow: hidden;
}
.prod-card:hover { border-color: rgba(232,93,4,.35); box-shadow: 0 4px 20px rgba(0,0,0,.5); transform: translateY(-1px); }
.prod-card:active { transform: scale(.99); }
.prod-body { flex: 1; padding: 14px; display: flex; flex-direction: column; min-width: 0; }
.prod-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 7px; }
.prod-badge {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: var(--r-full);
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .03em;
}
.prod-badge-hot  { background: rgba(232,93,4,.13); color: var(--p); border: 1px solid rgba(232,93,4,.25); }
.prod-badge-desc { background: rgba(34,197,94,.1);  color: var(--green); border: 1px solid rgba(34,197,94,.2); }
.prod-nome { font-size: 15px; font-weight: 700; line-height: 1.35; color: var(--tx); }
.prod-desc {
  font-size: 12px; color: var(--muted); line-height: 1.5; margin-top: 4px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.prod-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; gap: 8px; }
.prod-preco-wrap { display: flex; flex-direction: column; }
.prod-preco-old  { font-size: 11px; color: var(--muted); text-decoration: line-through; }
.prod-preco      { font-size: 17px; font-weight: 800; color: var(--p); }
.btn-add {
  flex-shrink: 0; background: var(--p); color: #fff;
  border-radius: var(--r-full); padding: 7px 16px;
  font-size: 13px; font-weight: 700;
  transition: background .15s, transform .1s;
  white-space: nowrap;
}
.btn-add:hover  { background: var(--pd); }
.btn-add:active { transform: scale(.93); }

.prod-img {
  width: 120px; flex-shrink: 0;
  background: var(--s2); display: flex; align-items: center; justify-content: center;
  font-size: 52px; overflow: hidden;
}
.prod-img img { width: 120px; height: 100%; object-fit: cover; }

/* Grid mode (desktop) */
.prod-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.prod-grid .prod-card { flex-direction: column; }
.prod-grid .prod-img {
  width: 100%; height: 160px;
  border-radius: 0;
}
.prod-grid .prod-img img { width: 100%; height: 100%; object-fit: cover; }

/* ══════════════════════════════════════════════
   BUSCA — resultado
══════════════════════════════════════════════ */
.search-results { display: none; }
.empty-state { text-align: center; padding: 60px 20px; color: var(--muted); font-size: 14px; font-weight: 500; }

/* ══════════════════════════════════════════════
   MODAL DE PRODUTO
══════════════════════════════════════════════ */
.overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.65);
  z-index: 200; display: none;
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
.overlay.show { display: block; }

.modal {
  position: fixed;
  bottom: 0; left: 50%;
  transform: translateX(-50%) translateY(110%);
  width: 100%; max-width: 560px;
  background: var(--s1);
  border-radius: 24px 24px 0 0;
  z-index: 300;
  max-height: 92dvh;
  overflow-y: auto;
  transition: transform .35s cubic-bezier(.4,0,.2,1);
  scrollbar-width: none;
}
.modal::-webkit-scrollbar { display: none; }
.modal.open { transform: translateX(-50%) translateY(0); }
.modal-handle { width: 40px; height: 4px; background: var(--brd); border-radius: var(--r-full); margin: 14px auto 0; }
.modal-x {
  position: absolute; top: 14px; right: 16px;
  width: 30px; height: 30px; background: var(--s3); border-radius: var(--r-full);
  display: flex; align-items: center; justify-content: center;
  color: var(--muted); font-size: 15px; transition: background .15s;
}
.modal-x:hover { background: var(--brd); color: var(--tx); }
.modal-body { padding: 6px 20px 36px; }
.modal-img {
  width: 100%; aspect-ratio: 16/9;
  background: var(--s2); border-radius: var(--r);
  display: flex; align-items: center; justify-content: center;
  font-size: 88px; overflow: hidden; margin: 14px 0 16px;
}
.modal-img img { width: 100%; height: 100%; object-fit: cover; }
.modal-nome { font-size: 22px; font-weight: 800; margin-bottom: 8px; line-height: 1.3; }
.modal-desc { font-size: 14px; color: var(--muted); line-height: 1.6; margin-bottom: 16px; }
.modal-preco { font-size: 28px; font-weight: 900; color: var(--p); }
.modal-preco-old { font-size: 13px; color: var(--muted); text-decoration: line-through; margin-bottom: 20px; }
.obs-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
.obs-input {
  width: 100%; background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r);
  color: var(--tx); font-size: 14px; padding: 12px 14px; resize: none; height: 76px;
  transition: border-color .2s; margin-bottom: 20px;
}
.obs-input::placeholder { color: var(--muted); opacity: .6; }
.obs-input:focus { border-color: var(--p); }
.modal-footer { display: flex; gap: 12px; align-items: center; }
.qtd-ctrl {
  display: flex; align-items: center;
  background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r); overflow: hidden;
}
.qtd-btn { width: 42px; height: 48px; display: flex; align-items: center; justify-content: center; color: var(--tx); font-size: 20px; font-weight: 700; transition: background .15s; }
.qtd-btn:hover { background: var(--brd); }
.qtd-num { min-width: 36px; text-align: center; font-size: 18px; font-weight: 800; }
.btn-conf {
  flex: 1; height: 52px; background: var(--p); color: #fff;
  border-radius: var(--r); font-size: 15px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s; box-shadow: 0 4px 18px rgba(232,93,4,.35);
}
.btn-conf:hover  { background: var(--pd); }
.btn-conf:active { transform: scale(.97); }

/* ══════════════════════════════════════════════
   CART — sidebar (desktop) & drawer (mobile)
══════════════════════════════════════════════ */
/* Shared cart inner content */
.cart-inner { display: flex; flex-direction: column; height: 100%; }
.cart-head { font-size: 16px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.cart-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--muted); font-size: 13px; font-weight: 500; gap: 10px; text-align: center; padding: 20px 0; }
.cart-items-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; scrollbar-width: thin; padding-right: 2px; }
.cart-item {
  background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r);
  padding: 11px 12px; display: flex; gap: 10px; align-items: flex-start;
}
.ci-emoji { font-size: 28px; flex-shrink: 0; }
.ci-body { flex: 1; min-width: 0; }
.ci-name { font-size: 13px; font-weight: 700; }
.ci-obs  { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-row  { display: flex; align-items: center; justify-content: space-between; margin-top: 7px; }
.ci-price  { font-size: 14px; font-weight: 800; color: var(--p); }
.ci-remove { width: 26px; height: 26px; background: var(--s3); border-radius: var(--r-full); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 13px; transition: all .15s; }
.ci-remove:hover { background: rgba(239,68,68,.15); color: #ef4444; }

/* Cupom */
.cupom-row { display: flex; gap: 8px; margin: 14px 0 4px; }
.cupom-input {
  flex: 1; height: 40px; background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-sm);
  color: var(--tx); font-size: 12px; padding: 0 12px; text-transform: uppercase; letter-spacing: .04em; transition: border-color .2s;
}
.cupom-input:focus { border-color: var(--p); }
.btn-cupom {
  height: 40px; padding: 0 14px; background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-sm);
  color: var(--tx); font-size: 12px; font-weight: 700; transition: border-color .15s;
}
.btn-cupom:hover { border-color: var(--p); color: var(--p); }
.cupom-msg { font-size: 11px; font-weight: 600; min-height: 18px; margin-bottom: 4px; }

/* Totais */
.cart-totals { border-top: 1px solid var(--brd); padding-top: 14px; margin-top: 12px; display: flex; flex-direction: column; gap: 7px; }
.total-row { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); }
.total-row.final { font-size: 19px; font-weight: 900; color: var(--tx); margin-top: 4px; }
.total-row.final span:last-child { color: var(--p); }

/* Entrega tabs */
.entrega-tabs { display: flex; background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-sm); overflow: hidden; margin-bottom: 10px; }
.entrega-tab { flex: 1; padding: 9px; font-size: 12px; font-weight: 700; color: var(--muted); transition: all .2s; }
.entrega-tab.ativo { background: var(--p); color: #fff; }

/* Botão finalizar */
.btn-finalizar {
  width: 100%; height: 50px; background: var(--green); color: #fff; border-radius: var(--r);
  font-size: 14px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s; box-shadow: 0 4px 16px rgba(34,197,94,.3);
  margin-top: 12px;
}
.btn-finalizar:hover  { background: #16a34a; }
.btn-finalizar:active { transform: scale(.98); }
.btn-finalizar:disabled { opacity: .45; pointer-events: none; }

/* ── Mobile: FAB + Drawer ── */
.cart-fab {
  position: fixed; bottom: 18px; left: 50%;
  transform: translateX(-50%) translateY(120px);
  width: calc(100% - 32px); max-width: 480px;
  background: var(--p); color: #fff; border-radius: 18px;
  padding: 13px 18px;
  display: flex; align-items: center; justify-content: space-between;
  z-index: 100; transition: transform .35s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 8px 28px rgba(232,93,4,.5);
}
.cart-fab.show { transform: translateX(-50%) translateY(0); }
.cart-fab:active { transform: translateX(-50%) scale(.97); }
.fab-left { display: flex; align-items: center; gap: 10px; }
.fab-count { width: 34px; height: 34px; background: rgba(0,0,0,.2); border-radius: var(--r-full); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; }
.fab-label { font-size: 14px; font-weight: 700; }
.fab-total { font-size: 16px; font-weight: 900; }

.cart-drawer {
  position: fixed; bottom: 0; left: 50%;
  transform: translateX(-50%) translateY(110%);
  width: 100%; max-width: 520px;
  background: var(--s1); border-radius: 24px 24px 0 0;
  z-index: 400; max-height: 90dvh;
  display: flex; flex-direction: column;
  transition: transform .35s cubic-bezier(.4,0,.2,1);
}
.cart-drawer.open { transform: translateX(-50%) translateY(0); }
.drawer-head {
  padding: 16px 20px 12px; border-bottom: 1px solid var(--brd);
  display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
.drawer-title { font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.drawer-close { width: 32px; height: 32px; background: var(--s2); border-radius: var(--r-full); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 15px; }
.drawer-body { flex: 1; overflow-y: auto; padding: 16px 20px 0; scrollbar-width: none; }
.drawer-body::-webkit-scrollbar { display: none; }
.drawer-foot { padding: 4px 20px 28px; flex-shrink: 0; }

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
.toast {
  position: fixed; bottom: 90px; left: 50%;
  transform: translateX(-50%) translateY(16px);
  background: var(--s2); color: var(--tx); border: 1px solid var(--brd);
  border-radius: var(--r-full); padding: 9px 20px;
  font-size: 13px; font-weight: 600; z-index: 600;
  opacity: 0; transition: all .25s; white-space: nowrap;
  pointer-events: none; box-shadow: 0 6px 20px rgba(0,0,0,.5);
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.toast.success { border-color: rgba(34,197,94,.35); color: var(--green); }

/* ══════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
══════════════════════════════════════════════ */

/* Tablet ≥ 768px */
@media (min-width: 768px) {
  .topbar { display: flex; }
  .mobile-hd { display: none; }
  .mobile-nav { top: var(--hd-h); }
  .content { padding-top: 0; }
  /* Hide mobile nav on tablet+ since topbar has search */
  .mobile-nav { display: none; }
  /* Product grid 2 cols on tablet */
  .prod-list { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .prod-card { flex-direction: column; }
  .prod-img { width: 100%; height: 150px; border-radius: 0; }
  .prod-img img { width: 100%; height: 100%; }
  /* Content search */
  .content-search-wrap { display: block; }
  .page { padding-top: var(--hd-h); }
  /* Featured grid more cols on tablet */
  .featured-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
  .cat-section { scroll-margin-top: 80px; }
  .cat-heading { scroll-margin-top: 80px; }
}

/* Desktop ≥ 1100px: show sidebars */
@media (min-width: 1100px) {
  .cat-sidebar   { display: flex; flex-direction: column; }
  .cart-sidebar  { display: flex; }
  .cart-fab      { display: none; }
  .cart-drawer   { display: none; }
  /* 3 col products */
  .prod-list { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
  .prod-img  { height: 160px; }
  .content   { padding: 32px 32px 60px; }
  .featured-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
}

/* Only mobile sees old mobile header & single col */
@media (max-width: 767px) {
  .topbar { display: none; }
  .mobile-hd  { display: block; }
  .mobile-nav { display: block; }
  .page { padding-top: 0; }
  .cat-sidebar { display: none; }
  .cart-sidebar { display: none; }
  .content { padding: 20px 16px 100px; }
  .featured-grid { grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)); }
}

/* ══════════════════════════════════════════════
   MISC
══════════════════════════════════════════════ */
.content-search-wrap { margin-bottom: 24px; display: none; }
.content-search { position: relative; }
.content-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--muted); pointer-events: none; }
.content-search input {
  width: 100%; height: 46px; background: var(--s2); border: 1px solid var(--brd); border-radius: var(--r-full);
  color: var(--tx); font-size: 14px; font-weight: 500; padding: 0 16px 0 44px; transition: border-color .2s;
}
.content-search input::placeholder { color: var(--muted); }
.content-search input:focus { border-color: var(--p); }

@supports (padding-top: env(safe-area-inset-top)) {
  .mobile-hd { padding-top: max(20px, calc(env(safe-area-inset-top) + 12px)); }
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════
     TOP BAR (tablet/desktop)
══════════════════════════════════════════════ -->
<header class="topbar">
  <div class="topbar-logo">
    <?php if (!empty($config['logo'])): ?>
      <img src="<?= h($config['logo']) ?>" alt="logo">
    <?php else: ?>
      <?= h($config['logo_emoji'] ?? '🍔') ?>
    <?php endif; ?>
  </div>
  <div class="topbar-info">
    <div class="topbar-nome"><?= h($config['nome_restaurante']) ?></div>
    <div class="topbar-sub">
      <span class="badge <?= $lojaAberta ? 'badge-open' : 'badge-closed' ?>">
        <svg viewBox="0 0 8 8" width="6" height="6" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
        <?= $lojaAberta ? 'Aberto' : 'Fechado' ?>
      </span>
      <span class="badge badge-tempo">
        <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <?= h($tempoLabel) ?>
      </span>
      <?php if ($config['taxa_entrega']): ?>
        <span>
          <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          R$ <?= number_format((float)$config['taxa_entrega'], 2, ',', '.') ?> entrega
        </span>
      <?php endif; ?>
      <?php if ($config['endereco']): ?>
        <span>
          <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
          <?= h($config['endereco']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <div class="topbar-search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="search" id="topbarSearch" placeholder="Buscar no cardápio..." autocomplete="off">
  </div>
</header>

<!-- PROMO -->
<?php if ($promoAtiva): ?>
<div class="promo-bar" id="promoBanner">
  <span><?= h($config['promo_titulo'] ?? 'Promoção') ?> — <strong><?= (int)$config['promo_desconto'] ?>% OFF</strong></span>
  <?php if ($config['promo_fim']): ?>
    <span id="promoTimer" data-fim="<?= strtotime($config['promo_fim']) * 1000 ?>"></span>
  <?php endif; ?>
  <button onclick="this.closest('.promo-bar').remove()">&#x2715;</button>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     MOBILE HEADER
══════════════════════════════════════════════ -->
<div class="mobile-hd">
  <div class="mobile-hd-row">
    <div class="mobile-logo">
      <?php if (!empty($config['logo'])): ?>
        <img src="<?= h($config['logo']) ?>" alt="logo">
      <?php else: ?>
        <?= h($config['logo_emoji'] ?? '🍔') ?>
      <?php endif; ?>
    </div>
    <div class="mobile-hd-info">
      <div class="mobile-nome"><?= h($config['nome_restaurante']) ?></div>
      <?php if ($config['descricao']): ?><div class="mobile-desc"><?= h($config['descricao']) ?></div><?php endif; ?>
      <div class="mobile-badges">
        <span class="badge <?= $lojaAberta ? 'badge-open' : 'badge-closed' ?>">
          <svg viewBox="0 0 8 8" width="6" height="6" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
          <?= $lojaAberta ? 'Aberto' : 'Fechado' ?>
        </span>
        <span class="badge badge-tempo">
          <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          <?= h($tempoLabel) ?>
        </span>
      </div>
    </div>
  </div>
</div>

<!-- MOBILE NAV (sticky search + cat pills) -->
<div class="mobile-nav">
  <div class="mobile-search-wrap">
    <div class="mobile-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="search" id="mobileSearch" placeholder="Buscar no cardápio..." autocomplete="off">
    </div>
  </div>
  <div class="mobile-cats" id="mobileCats">
    <button class="mobile-cat-btn ativo" data-cat="todos">Todos</button>
    <?php foreach ($categorias as $cat): ?>
      <?php if (!empty($porCategoria[$cat['id']])): ?>
        <button class="mobile-cat-btn" data-cat="<?= $cat['id'] ?>">
          <?= $cat['emoji'] ? h($cat['emoji']) . ' ' : '' ?><?= h($cat['nome']) ?>
        </button>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════
     PAGE (3 cols)
══════════════════════════════════════════════ -->
<div class="page">

  <!-- LEFT: Category Sidebar -->
  <nav class="cat-sidebar">
    <div class="cat-sidebar-inner">
      <button class="cat-sidebar-btn ativo" data-cat="todos">
        <span class="emoji">🏠</span> Início
      </button>
      <div class="cat-sidebar-divider"></div>
      <?php foreach ($categorias as $cat): ?>
        <?php if (!empty($porCategoria[$cat['id']])): ?>
          <button class="cat-sidebar-btn" data-cat="<?= $cat['id'] ?>">
            <span class="emoji"><?= h($cat['emoji'] ?? '🍽️') ?></span>
            <?= h($cat['nome']) ?>
          </button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </nav>

  <!-- CENTER: Content -->
  <main class="content" id="mainContent">

    <!-- Desktop inline search (tablet shows here) -->
    <div class="content-search-wrap" id="contentSearchWrap">
      <div class="content-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" id="tabletSearch" placeholder="Buscar no cardápio..." autocomplete="off">
      </div>
    </div>

    <!-- Search results -->
    <div id="searchResults" class="search-results">
      <div class="section-title" style="margin-bottom:18px">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        Resultados
      </div>
      <div class="prod-list" id="searchList"></div>
    </div>

    <!-- Normal content -->
    <div id="normalContent">

      <!-- Mais Pedidos -->
      <?php if (!empty($destaques)): ?>
      <div class="featured-section">
        <div class="section-title">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/></svg>
          Mais Pedidos
        </div>
        <div class="featured-grid">
          <?php foreach (array_slice($destaques, 0, 8) as $p): ?>
          <?php $pd = ['id'=>(int)$p['id'],'nome'=>$p['nome'],'desc'=>$p['descricao']??'','preco'=>(float)$p['preco'],'preco_original'=>$p['preco_original']?(float)$p['preco_original']:null,'emoji'=>$p['emoji']??'🍽️','img'=>$p['imagem']??null]; ?>
          <div class="featured-card" onclick="abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">
            <div class="featured-img">
              <?php if (!empty($p['imagem'])): ?>
                <img src="<?= h($p['imagem']) ?>" alt="<?= h($p['nome']) ?>" loading="lazy" onerror="this.parentElement.innerHTML='<?= addslashes(h($p['emoji']??'🍽️')) ?>'">
              <?php else: ?>
                <?= h($p['emoji'] ?? '🍽️') ?>
              <?php endif; ?>
            </div>
            <div class="featured-name"><?= h($p['nome']) ?></div>
            <div class="featured-price"><?= formatar_dinheiro((float)$p['preco']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Categories + Products -->
      <?php foreach ($categorias as $cat): ?>
        <?php if (empty($porCategoria[$cat['id']])) continue; ?>
        <div class="cat-section" id="cat-<?= $cat['id'] ?>" data-cat-id="<?= $cat['id'] ?>">
          <div class="cat-heading">
            <?= $cat['emoji'] ? '<span>' . h($cat['emoji']) . '</span>' : '' ?>
            <?= h($cat['nome']) ?>
          </div>
          <div class="prod-list" id="prodlist-<?= $cat['id'] ?>">
            <?php foreach ($porCategoria[$cat['id']] as $p): ?>
              <?php $pd = ['id'=>(int)$p['id'],'nome'=>$p['nome'],'desc'=>$p['descricao']??'','preco'=>(float)$p['preco'],'preco_original'=>$p['preco_original']?(float)$p['preco_original']:null,'emoji'=>$p['emoji']??'🍽️','img'=>$p['imagem']??null]; ?>
              <div class="prod-card" onclick="abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">
                <div class="prod-body">
                  <div class="prod-badges">
                    <?php if ($p['mais_vendido']): ?><span class="prod-badge prod-badge-hot">Mais Pedido</span><?php endif; ?>
                    <?php if ($p['preco_original'] && (float)$p['preco_original'] > (float)$p['preco']): ?><span class="prod-badge prod-badge-desc">Oferta</span><?php endif; ?>
                  </div>
                  <div class="prod-nome"><?= h($p['nome']) ?></div>
                  <?php if ($p['descricao']): ?><div class="prod-desc"><?= h($p['descricao']) ?></div><?php endif; ?>
                  <div class="prod-footer">
                    <div class="prod-preco-wrap">
                      <?php if ($p['preco_original'] && (float)$p['preco_original'] > (float)$p['preco']): ?>
                        <span class="prod-preco-old"><?= formatar_dinheiro((float)$p['preco_original']) ?></span>
                      <?php endif; ?>
                      <span class="prod-preco"><?= formatar_dinheiro((float)$p['preco']) ?></span>
                    </div>
                    <button class="btn-add" onclick="event.stopPropagation();abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">+ Adicionar</button>
                  </div>
                </div>
                <div class="prod-img">
                  <?php if (!empty($p['imagem'])): ?>
                    <img src="<?= h($p['imagem']) ?>" alt="<?= h($p['nome']) ?>" loading="lazy" onerror="this.parentElement.innerHTML='<?= addslashes(h($p['emoji']??'🍽️')) ?>'">
                  <?php else: ?>
                    <?= h($p['emoji'] ?? '🍽️') ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- RIGHT: Cart Sidebar (desktop) -->
  <aside class="cart-sidebar" id="cartSidebar">
    <div class="cart-inner" id="cartSidebarInner">
      <div class="cart-head">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        Seu Pedido
      </div>
      <div class="cart-empty" id="sidebarEmpty">
        <span style="font-size:48px">🛒</span>
        <p>Seu carrinho está vazio.<br>Adicione itens do cardápio.</p>
      </div>
      <div class="cart-items-list" id="sidebarItems" style="display:none"></div>
      <div id="sidebarBottom" style="display:none">
        <div class="cupom-row">
          <input type="text" class="cupom-input" id="cupomInputSidebar" placeholder="CUPOM" maxlength="30">
          <button class="btn-cupom" onclick="aplicarCupom('sidebar')">Aplicar</button>
        </div>
        <div class="cupom-msg" id="cupomMsgSidebar"></div>
        <div class="cart-totals">
          <?php if (!empty($zonas)): ?>
          <div class="entrega-tabs">
            <button class="entrega-tab ativo" onclick="setEntrega('entrega',this,'sidebar')">Entrega</button>
            <button class="entrega-tab" onclick="setEntrega('retirada',this,'sidebar')">Retirada</button>
          </div>
          <?php endif; ?>
          <div class="total-row"><span>Subtotal</span><span id="sTotSub">R$ 0,00</span></div>
          <div class="total-row" id="sFrete"><span>Entrega</span><span id="sTotFrete">R$ <?= number_format((float)$config['taxa_entrega'],2,',','.') ?></span></div>
          <div class="total-row" id="sDesc" style="display:none;color:var(--green)"><span>Desconto</span><span id="sTotDesc"></span></div>
          <div class="total-row final"><span>Total</span><span id="sTotTotal">R$ 0,00</span></div>
          <button class="btn-finalizar" id="sBtnFinalizar" onclick="finalizarPedido()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Finalizar via WhatsApp
          </button>
        </div>
      </div>
    </div>
  </aside>

</div><!-- /page -->

<!-- ══════════════════════════════════════════
     OVERLAY
══════════════════════════════════════════════ -->
<div class="overlay" id="overlay" onclick="fecharTudo()"></div>

<!-- ══════════════════════════════════════════
     MODAL DE PRODUTO
══════════════════════════════════════════════ -->
<div class="modal" id="modalProduto" role="dialog" aria-modal="true">
  <div class="modal-handle"></div>
  <button class="modal-x" onclick="fecharModal()">&#x2715;</button>
  <div class="modal-body">
    <div class="modal-img" id="modalImg"></div>
    <div class="modal-nome" id="modalNome"></div>
    <div class="modal-desc" id="modalDesc"></div>
    <div class="modal-preco" id="modalPreco"></div>
    <div class="modal-preco-old" id="modalPrecoOld"></div>
    <label class="obs-label">Observações</label>
    <textarea class="obs-input" id="modalObs" placeholder="Ex: tirar cebola, ponto da carne..."></textarea>
    <div class="modal-footer">
      <div class="qtd-ctrl">
        <button class="qtd-btn" id="btnMenos">&#8722;</button>
        <span class="qtd-num" id="qtdNum">1</span>
        <button class="qtd-btn" id="btnMais">&#43;</button>
      </div>
      <button class="btn-conf" id="btnConf">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span id="btnConfTxt">Adicionar</span>
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MOBILE: FAB + Drawer
══════════════════════════════════════════════ -->
<button class="cart-fab" id="cartFab" onclick="abrirDrawer()">
  <div class="fab-left">
    <span class="fab-count" id="fabCount">0</span>
    <span class="fab-label">Ver carrinho</span>
  </div>
  <span class="fab-total" id="fabTotal">R$ 0,00</span>
</button>

<div class="cart-drawer" id="cartDrawer">
  <div class="drawer-head">
    <div class="drawer-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Seu Pedido
    </div>
    <button class="drawer-close" onclick="fecharDrawer()">&#x2715;</button>
  </div>
  <div class="drawer-body">
    <div class="cart-items-list" id="drawerItems"></div>
  </div>
  <div class="drawer-foot">
    <div class="cupom-row">
      <input type="text" class="cupom-input" id="cupomInputDrawer" placeholder="CUPOM" maxlength="30">
      <button class="btn-cupom" onclick="aplicarCupom('drawer')">Aplicar</button>
    </div>
    <div class="cupom-msg" id="cupomMsgDrawer"></div>
    <div class="cart-totals">
      <?php if (!empty($zonas)): ?>
      <div class="entrega-tabs">
        <button class="entrega-tab ativo" onclick="setEntrega('entrega',this,'drawer')">Entrega</button>
        <button class="entrega-tab" onclick="setEntrega('retirada',this,'drawer')">Retirada</button>
      </div>
      <?php endif; ?>
      <div class="total-row"><span>Subtotal</span><span id="dTotSub">R$ 0,00</span></div>
      <div class="total-row" id="dFrete"><span>Entrega</span><span id="dTotFrete">R$ <?= number_format((float)$config['taxa_entrega'],2,',','.') ?></span></div>
      <div class="total-row" id="dDesc" style="display:none;color:var(--green)"><span>Desconto</span><span id="dTotDesc"></span></div>
      <div class="total-row final"><span>Total</span><span id="dTotTotal">R$ 0,00</span></div>
      <button class="btn-finalizar" onclick="finalizarPedido()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Finalizar via WhatsApp
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
const CFG = {
  nome:      <?= json_encode($config['nome_restaurante']) ?>,
  whatsapp:  <?= json_encode(preg_replace('/\D/','',$config['whatsapp'])) ?>,
  taxa:      <?= (float)$config['taxa_entrega'] ?>,
  pedMin:    <?= (float)$config['pedido_minimo'] ?>,
  aberto:    <?= $lojaAberta ? 'true' : 'false' ?>,
};

// ── State ──────────────────────────────────────────────────────
let cart = JSON.parse(localStorage.getItem('cdCart') ?? '[]');
let prodAtual = null, qty = 1;
let tipoEntrega = 'entrega';
let cupom = null;

// ── Search ─────────────────────────────────────────────────────
const allCards = [];
document.querySelectorAll('.prod-card').forEach(c => {
  allCards.push({ el: c.cloneNode(true), txt: c.innerText.toLowerCase() });
});

function doSearch(q) {
  const sr = document.getElementById('searchResults');
  const nc = document.getElementById('normalContent');
  const sl = document.getElementById('searchList');
  const csb = document.querySelector('.cat-sidebar');
  q = q.trim().toLowerCase();
  if (!q) {
    sr.style.display = 'none'; nc.style.display = '';
    if (csb) csb.style.opacity = '1';
    return;
  }
  sr.style.display = ''; nc.style.display = 'none';
  if (csb) csb.style.opacity = '.4';
  sl.innerHTML = '';
  const res = allCards.filter(c => c.txt.includes(q));
  if (!res.length) {
    sl.innerHTML = '<div class="empty-state">Nenhum item encontrado para "' + esc(q) + '"</div>';
  } else {
    res.forEach(c => sl.appendChild(c.el.cloneNode(true)));
  }
}

['topbarSearch','mobileSearch','tabletSearch'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', e => doSearch(e.target.value));
});

// show tablet content search wrap on tablet
const mq768 = window.matchMedia('(min-width: 768px) and (max-width: 1099px)');
function checkTablet() { document.getElementById('contentSearchWrap').style.display = mq768.matches ? 'block' : ''; }
mq768.addEventListener('change', checkTablet); checkTablet();

// ── Category nav ───────────────────────────────────────────────
function catClick(catId, btn, scope) {
  document.querySelectorAll('.' + scope + '.ativo').forEach(b => b.classList.remove('ativo'));
  btn.classList.add('ativo');
  if (catId === 'todos') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } else {
    const el = document.getElementById('cat-' + catId);
    if (el) {
      const offset = window.innerWidth >= 768 ? 90 : 130;
      window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
    }
  }
}

document.querySelectorAll('.cat-sidebar-btn').forEach(btn => {
  btn.addEventListener('click', () => catClick(btn.dataset.cat, btn, 'cat-sidebar-btn'));
});
document.querySelectorAll('.mobile-cat-btn').forEach(btn => {
  btn.addEventListener('click', () => catClick(btn.dataset.cat, btn, 'mobile-cat-btn'));
});

// IntersectionObserver — update active category
if ('IntersectionObserver' in window) {
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const id = e.target.dataset.catId;
        document.querySelectorAll('.cat-sidebar-btn').forEach(b => b.classList.toggle('ativo', b.dataset.cat === id));
        document.querySelectorAll('.mobile-cat-btn').forEach(b => b.classList.toggle('ativo', b.dataset.cat === id));
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });
  document.querySelectorAll('.cat-section').forEach(s => obs.observe(s));
}

// ── Modal ──────────────────────────────────────────────────────
function abrirModal(prod) {
  prodAtual = prod; qty = 1;
  document.getElementById('qtdNum').textContent = 1;
  document.getElementById('modalObs').value = '';
  document.getElementById('modalNome').textContent = prod.nome;
  document.getElementById('modalDesc').textContent = prod.desc || '';
  document.getElementById('modalPreco').textContent = brl(prod.preco);
  document.getElementById('modalPrecoOld').textContent =
    prod.preco_original && prod.preco_original > prod.preco ? 'De ' + brl(prod.preco_original) : '';
  const iw = document.getElementById('modalImg');
  if (prod.img) {
    iw.innerHTML = '<img src="' + esc(prod.img) + '" alt="' + esc(prod.nome) + '" onerror="this.parentElement.textContent=\'' + esc(prod.emoji) + '\'">';
  } else { iw.textContent = prod.emoji || '🍽️'; }
  updBtnConf();
  document.getElementById('overlay').classList.add('show');
  document.getElementById('modalProduto').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function fecharModal() {
  document.getElementById('modalProduto').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
  document.body.style.overflow = '';
}
function fecharTudo() { fecharModal(); fecharDrawer(); }
document.getElementById('btnMenos').onclick = () => { if (qty > 1) { qty--; document.getElementById('qtdNum').textContent = qty; updBtnConf(); } };
document.getElementById('btnMais').onclick  = () => { qty++; document.getElementById('qtdNum').textContent = qty; updBtnConf(); };
function updBtnConf() { if (!prodAtual) return; document.getElementById('btnConfTxt').textContent = 'Adicionar • ' + brl(prodAtual.preco * qty); }
document.getElementById('btnConf').onclick = () => {
  if (!prodAtual) return;
  cart.push({ produto: prodAtual, qty, obs: document.getElementById('modalObs').value.trim() });
  saveCart(); fecharModal(); toast('Adicionado! ✓', 'success');
};

// ── Cart render ────────────────────────────────────────────────
function saveCart() { localStorage.setItem('cdCart', JSON.stringify(cart)); renderAll(); }

function renderAll() { renderFab(); renderSidebar(); }

function renderFab() {
  const count = cart.reduce((a,i) => a + i.qty, 0);
  const total = cart.reduce((a,i) => a + i.produto.preco * i.qty, 0);
  const fab = document.getElementById('cartFab');
  document.getElementById('fabCount').textContent = count;
  document.getElementById('fabTotal').textContent = brl(total);
  fab.classList.toggle('show', count > 0);
}

function renderSidebar() {
  const items = document.getElementById('sidebarItems');
  const empty = document.getElementById('sidebarEmpty');
  const bottom = document.getElementById('sidebarBottom');
  if (!items) return;
  items.innerHTML = '';
  if (!cart.length) {
    empty.style.display = ''; items.style.display = 'none'; bottom.style.display = 'none';
    return;
  }
  empty.style.display = 'none'; items.style.display = ''; bottom.style.display = '';
  cart.forEach((item, idx) => items.appendChild(buildCartItem(item, idx, 'sidebar')));
  updTotals('sidebar');
}

function renderDrawer() {
  const list = document.getElementById('drawerItems');
  list.innerHTML = '';
  cart.forEach((item, idx) => list.appendChild(buildCartItem(item, idx, 'drawer')));
  updTotals('drawer');
}

function buildCartItem(item, idx, scope) {
  const div = document.createElement('div');
  div.className = 'cart-item';
  div.innerHTML =
    '<div class="ci-emoji">' + esc(item.produto.emoji || '🍽️') + '</div>' +
    '<div class="ci-body">' +
      '<div class="ci-name">' + item.qty + 'x ' + esc(item.produto.nome) + '</div>' +
      (item.obs ? '<div class="ci-obs">📝 ' + esc(item.obs) + '</div>' : '') +
      '<div class="ci-row">' +
        '<span class="ci-price">' + brl(item.produto.preco * item.qty) + '</span>' +
        '<button class="ci-remove" onclick="remover(' + idx + ',\'' + scope + '\')">&#x2715;</button>' +
      '</div>' +
    '</div>';
  return div;
}

function remover(idx, scope) {
  cart.splice(idx, 1);
  saveCart();
  if (scope === 'drawer') { if (!cart.length) fecharDrawer(); else renderDrawer(); }
}

function updTotals(scope) {
  const sub = cart.reduce((a,i) => a + i.produto.preco * i.qty, 0);
  const frete = tipoEntrega === 'retirada' ? 0 : CFG.taxa;
  let desc = 0;
  if (cupom) desc = cupom.tipo === 'percentual' ? sub * cupom.valor / 100 : Math.min(cupom.valor, sub);
  const total = Math.max(0, sub + frete - desc);
  const p = scope === 'sidebar' ? 's' : 'd';
  document.getElementById(p + 'TotSub').textContent = brl(sub);
  document.getElementById(p + 'TotFrete').textContent = frete === 0 ? 'Grátis' : brl(frete);
  const fr = document.getElementById(p + 'Frete');
  if (fr) fr.style.display = tipoEntrega === 'retirada' ? 'none' : '';
  const dr = document.getElementById(p + 'Desc');
  if (dr) { dr.style.display = desc > 0 ? '' : 'none'; document.getElementById(p + 'TotDesc').textContent = '- ' + brl(desc); }
  document.getElementById(p + 'TotTotal').textContent = brl(total);
}

function setEntrega(tipo, btn, scope) {
  tipoEntrega = tipo;
  const parent = btn.closest('.entrega-tabs');
  if (parent) parent.querySelectorAll('.entrega-tab').forEach(t => t.classList.remove('ativo'));
  btn.classList.add('ativo');
  updTotals(scope);
}

// ── Mobile drawer ──────────────────────────────────────────────
function abrirDrawer() {
  renderDrawer();
  document.getElementById('cartDrawer').classList.add('open');
  document.getElementById('overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function fecharDrawer() {
  document.getElementById('cartDrawer').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
  document.body.style.overflow = '';
}

// ── Cupom ──────────────────────────────────────────────────────
async function aplicarCupom(scope) {
  const inputId = scope === 'sidebar' ? 'cupomInputSidebar' : 'cupomInputDrawer';
  const msgId   = scope === 'sidebar' ? 'cupomMsgSidebar'  : 'cupomMsgDrawer';
  const codigo  = document.getElementById(inputId).value.trim().toUpperCase();
  const msgEl   = document.getElementById(msgId);
  if (!codigo) return;
  try {
    const r = await fetch('api/aplicar_cupom.php?codigo=' + encodeURIComponent(codigo));
    const d = await r.json();
    if (d.ok) {
      cupom = d.cupom;
      msgEl.textContent = '✓ ' + (d.mensagem || 'Cupom aplicado!');
      msgEl.style.color = 'var(--green)';
    } else {
      cupom = null;
      msgEl.textContent = d.erro || 'Cupom inválido.';
      msgEl.style.color = '#ef4444';
    }
  } catch { msgEl.textContent = 'Erro ao verificar.'; msgEl.style.color = '#ef4444'; }
  renderAll();
}

// ── Finalizar ──────────────────────────────────────────────────
function finalizarPedido() {
  if (!cart.length) return;
  if (!CFG.aberto) { toast('A loja está fechada.'); return; }
  const sub = cart.reduce((a,i) => a + i.produto.preco * i.qty, 0);
  if (sub < CFG.pedMin) { toast('Pedido mínimo: ' + brl(CFG.pedMin)); return; }
  const frete = tipoEntrega === 'retirada' ? 0 : CFG.taxa;
  let desc = 0;
  if (cupom) desc = cupom.tipo === 'percentual' ? sub * cupom.valor / 100 : Math.min(cupom.valor, sub);
  const total = Math.max(0, sub + frete - desc);
  let msg = '*Novo Pedido — ' + CFG.nome + '*\n\n';
  cart.forEach(i => {
    msg += '*' + i.qty + 'x ' + i.produto.nome + '* — ' + brl(i.produto.preco * i.qty) + '\n';
    if (i.obs) msg += '   📝 ' + i.obs + '\n';
  });
  msg += '\n──────────────────\n';
  msg += 'Subtotal: ' + brl(sub) + '\n';
  if (tipoEntrega === 'entrega') msg += 'Entrega: ' + brl(frete) + '\n';
  if (desc > 0) msg += 'Desconto: -' + brl(desc) + '\n';
  msg += '*TOTAL: ' + brl(total) + '*\n';
  msg += '\n*Forma:* ' + (tipoEntrega === 'entrega' ? 'Entrega' : 'Retirada no local');
  window.open('https://wa.me/' + CFG.whatsapp + '?text=' + encodeURIComponent(msg), '_blank');
}

// ── Promo timer ────────────────────────────────────────────────
const timerEl = document.getElementById('promoTimer');
if (timerEl) {
  const fim = parseInt(timerEl.dataset.fim);
  const tick = () => {
    const d = Math.max(0, Math.floor((fim - Date.now()) / 1000));
    if (!d) { timerEl.textContent = 'Encerrada'; return; }
    const h = Math.floor(d/3600), m = Math.floor((d%3600)/60), s = d%60;
    timerEl.textContent = (h ? h+'h ' : '') + String(m).padStart(2,'0') + 'min ' + String(s).padStart(2,'0') + 's';
  };
  tick(); setInterval(tick, 1000);
}

// ── Helpers ────────────────────────────────────────────────────
function brl(v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+,)/g,'$1.'); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className = 'toast', 2800);
}

// Esc key
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharTudo(); });

// Init
renderAll();
</script>
<footer style="text-align:center;padding:20px;font-size:12px;color:var(--muted);border-top:1px solid var(--border);">
  <?= h($config['nome_restaurante']) ?> · Cardápio Digital
  <a href="admin/login.php" style="margin-left:16px;color:var(--border);font-size:11px;" title="Acesso restrito">⚙</a>
</footer>
</body>
</html>

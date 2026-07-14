<?php
/**
 * Cardápio Digital — Redesign Responsivo (Mobile + Tablet + Desktop)
 * Versão com Modal aprimorado: remover ingredientes, adicionar extras, contador de comentário
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funcoes.php';

require_once __DIR__ . '/includes/auth.php';

$clienteLogado = !empty($_SESSION['cliente_id']);
$cliente = $clienteLogado;
$clienteNome = h($_SESSION['cliente_nome'] ?? '');
$lancheria_id = 1;

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();
$slug = $_GET['slug'] ?? null;
if ($slug) {
  $stmt = $pdo->prepare("SELECT * FROM config WHERE loja_slug = ? LIMIT 1");
  $stmt->execute([$slug]);
  $config = $stmt->fetch();
  if (!$config) {
    http_response_code(404);
    die('Restaurante não encontrado.');
  }
}

$cor = $config['cor_primaria'] ?? '#e85d04';
$lojaAberta = (bool) $config['aberto'];
$promoAtiva = (bool) ($config['promo_ativa'] ?? 0)
  && (!$config['promo_fim'] || strtotime($config['promo_fim']) > time());

$pedidosAtivos = (int) $pdo->query(
  "SELECT COUNT(*) FROM pedidos WHERE status IN ('novo','confirmado','preparo')
     AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)"
)->fetchColumn();
$tempoBase = (int) ($config['tempo_preparo_base'] ?? 30);
$tempoMin = $tempoBase + $pedidosAtivos * (int) ($config['tempo_preparo_por_pedido'] ?? 5);
$tempoMax = $tempoMin + 15;
$tempoLabel = $config['modo_pico']
  ? ($config['pico_tempo'] ?? '60-80 min')
  : "{$tempoMin}–{$tempoMax} min";

$categorias = $pdo->query(
  "SELECT * FROM categorias WHERE ativo=1 ORDER BY ordem, nome"
)->fetchAll();
$produtosRaw = $pdo->query(
  "SELECT p.*, c.nome AS cat_nome FROM produtos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     WHERE p.disponivel=1 ORDER BY c.ordem, p.ordem, p.nome"
)->fetchAll();

$porCategoria = [];
$destaques = [];
foreach ($produtosRaw as $p) {
  $porCategoria[$p['categoria_id']][] = $p;
  if ($p['mais_vendido'])
    $destaques[] = $p;
}

$zonas = [];
if ($config['frete_por_zona'] ?? 0) {
  $zonas = $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1 ORDER BY taxa")->fetchAll();
}

/* ── Ingredientes removíveis e extras — buscados da tabela produto_opcoes ── */
/* Estrutura esperada: produto_opcoes(id, produto_id, tipo ENUM('remover','extra'), nome, preco, emoji) */
$opcoesRaw = [];
try {
  $opcoesRaw = $pdo->query(
    "SELECT * FROM produto_opcoes WHERE ativo=1 ORDER BY tipo, ordem, nome"
  )->fetchAll();
} catch (\Exception $e) { /* tabela pode não existir ainda */
}

$opcoesPorProduto = [];
foreach ($opcoesRaw as $o) {
  $opcoesPorProduto[(int) $o['produto_id']][$o['tipo']][] = $o;
}

function buildProdData(array $p, array $opcoesPorProduto): array {
  $id = (int)($p['id'] ?? 0);

  $formatarOpcoes = function(array $itens): array {
    return array_map(function($o) {
      return [
        'id' => (int)($o['id'] ?? 0),
        'nome' => $o['nome'] ?? '',
        'preco' => (float)($o['preco'] ?? 0),
        'emoji' => $o['emoji'] ?? '🍴',
        'img' => $o['imagem'] ?? ''
      ];
    }, $itens);
  };

  return [
    'id' => $id,
    'nome' => $p['nome'] ?? '',
    'desc' => $p['descricao'] ?? '',
    'preco' => (float)($p['preco'] ?? 0),
    'preco_original' => (float)($p['preco_original'] ?? 0),
    'img' => $p['imagem'] ?? '',
    'emoji' => $p['emoji'] ?? '🍽️',
    'remover' => $formatarOpcoes($opcoesPorProduto[$id]['remover'] ?? []),
    'extras' => $formatarOpcoes($opcoesPorProduto[$id]['extra'] ?? [])
  ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#111111">
  <link rel="manifest" href="manifest.json">
  <title><?= h($config['nome_restaurante']) ?> — Cardápio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <style>
    /* ══════════════════════════════════════════════
   TOKENS
══════════════════════════════════════════════ */
    :root {
      --p:
        <?= $cor ?>
      ;
      --pd: color-mix(in srgb,
          <?= $cor ?>
          75%, #000);
      --bg: #111111;
      --s1: #181818;
      --s2: #222222;
      --s3: #2b2b2b;
      --brd: #2e2e2e;
      --tx: #f0ebe3;
      --muted: #888078;
      --green: #22c55e;
      --r: 14px;
      --r-sm: 9px;
      --r-lg: 20px;
      --r-full: 999px;
      --cart-w: 340px;
      --side-w: 220px;
      --hd-h: 64px;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--bg);
      color: var(--tx);
      min-height: 100dvh;
      -webkit-font-smoothing: antialiased;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    img {
      max-width: 100%;
      display: block;
    }

    button {
      font-family: inherit;
      cursor: pointer;
      border: none;
      outline: none;
      background: none;
    }

    input,
    textarea {
      font-family: inherit;
      outline: none;
    }

    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--brd);
      border-radius: var(--r-full);
    }

    /* ══════════════════════════════════════════════
   TOP BAR (Desktop)
══════════════════════════════════════════════ */
    .topbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--hd-h);
      background: rgba(17, 17, 17, .96);
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
      width: 40px;
      height: 40px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
      overflow: hidden;
    }

    .topbar-logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .topbar-info {
      display: flex;
      flex-direction: column;
      gap: 1px;
      flex: 1;
      min-width: 0;
    }

    .topbar-nome {
      font-size: 15px;
      font-weight: 800;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .topbar-sub {
      font-size: 11px;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .topbar-sub span {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .topbar-search {
      flex: 0 1 400px;
      position: relative;
    }

    .topbar-search svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: var(--muted);
      pointer-events: none;
    }

    .topbar-search input {
      width: 100%;
      height: 38px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-full);
      color: var(--tx);
      font-size: 13px;
      font-weight: 500;
      padding: 0 14px 0 36px;
      transition: border-color .2s;
    }

    .topbar-search input::placeholder {
      color: var(--muted);
    }

    .topbar-search input:focus {
      border-color: var(--p);
    }

    .btn-login-topbar {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 8px 16px;
      border-radius: 99px;
      background: var(--p);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: background .15s;
      flex-shrink: 0;
    }

    .btn-login-topbar:hover {
      background: var(--pd);
    }

    .btn-login-topbar svg {
      width: 15px;
      height: 15px;
    }

    .btn-usuario-topbar {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px 6px 8px;
      border-radius: 99px;
      background: var(--s2);
      border: 1px solid var(--brd);
      cursor: pointer;
      flex-shrink: 0;
      text-decoration: none;
      color: var(--tx);
      font-size: 13px;
      font-weight: 700;
      transition: border-color .15s;
      white-space: nowrap;
    }

    .btn-usuario-topbar:hover {
      border-color: var(--p);
    }

    .btn-usuario-topbar .uavatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: color-mix(in srgb, var(--p) 18%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--p);
    }

    .btn-usuario-topbar .uavatar svg {
      width: 15px;
      height: 15px;
    }

    .mobile-auth-row {
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }

    .btn-login-mobile {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 99px;
      background: var(--p);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      text-decoration: none;
    }

    .btn-usuario-mobile {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 12px 6px 7px;
      border-radius: 99px;
      background: var(--s2);
      border: 1px solid var(--brd);
      color: var(--tx);
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
    }

    .btn-usuario-mobile .uavatar {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: color-mix(in srgb, var(--p) 18%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--p);
    }

    .btn-usuario-mobile .uavatar svg {
      width: 13px;
      height: 13px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: var(--r-full);
      font-size: 11px;
      font-weight: 700;
      border: 1px solid;
      white-space: nowrap;
    }

    .badge-open {
      background: rgba(34, 197, 94, .1);
      color: var(--green);
      border-color: rgba(34, 197, 94, .25);
    }

    .badge-closed {
      background: rgba(100, 100, 100, .1);
      color: var(--muted);
      border-color: var(--brd);
    }

    .badge-tempo {
      background: var(--s2);
      color: var(--tx);
      border-color: var(--brd);
    }

    /* ══ PROMO ══ */
    .promo-bar {
      background: linear-gradient(90deg, #ff4500, #ff8c00);
      color: #fff;
      padding: 10px 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      font-size: 13px;
      font-weight: 700;
      flex-wrap: wrap;
      text-align: center;
      margin-top: var(--hd-h);
    }

    .promo-bar button {
      background: rgba(0, 0, 0, .2);
      color: #fff;
      border-radius: var(--r-full);
      width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
    }

    /* ══ PAGE LAYOUT ══ */
    .page {
      display: flex;
      padding-top: var(--hd-h);
      min-height: 100dvh;
    }

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
      display: none;
    }

    .cat-sidebar-inner {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 0 12px;
    }

    .cat-sidebar-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: var(--r);
      color: var(--muted);
      font-size: 13px;
      font-weight: 600;
      transition: all .18s;
      width: 100%;
      text-align: left;
    }

    .cat-sidebar-btn:hover {
      background: var(--s2);
      color: var(--tx);
    }

    .cat-sidebar-btn.ativo {
      background: rgba(232, 93, 4, .12);
      color: var(--p);
      border: 1px solid rgba(232, 93, 4, .2);
    }

    .cat-sidebar-btn .emoji {
      font-size: 18px;
    }

    .cat-sidebar-divider {
      height: 1px;
      background: var(--brd);
      margin: 8px 12px;
    }

    .content {
      flex: 1;
      min-width: 0;
      padding: 32px 28px 120px;
    }

    .cart-sidebar {
      width: var(--cart-w);
      flex-shrink: 0;
      position: sticky;
      top: var(--hd-h);
      height: calc(100dvh - var(--hd-h));
      overflow-y: auto;
      border-left: 1px solid var(--brd);
      padding: 20px 20px 32px;
      display: none;
      flex-direction: column;
      scrollbar-width: thin;
    }

    /* ══ MOBILE HEADER ══ */
    .mobile-hd {
      background: linear-gradient(180deg, var(--s1) 0%, var(--bg) 100%);
      border-bottom: 1px solid var(--brd);
      padding: 20px 20px 18px;
    }

    .mobile-hd-row {
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .mobile-logo {
      width: 58px;
      height: 58px;
      flex-shrink: 0;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      overflow: hidden;
    }

    .mobile-logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .mobile-hd-info {
      flex: 1;
      min-width: 0;
    }

    .mobile-nome {
      font-size: 20px;
      font-weight: 800;
      line-height: 1.2;
    }

    .mobile-desc {
      font-size: 12px;
      color: var(--muted);
      margin-top: 3px;
      line-height: 1.4;
    }

    .mobile-badges {
      display: flex;
      gap: 7px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .mobile-nav {
      position: sticky;
      top: 0;
      z-index: 80;
      background: rgba(17, 17, 17, .94);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--brd);
      padding: 12px 0 0;
    }

    .mobile-search-wrap {
      padding: 0 16px 10px;
    }

    .mobile-search {
      position: relative;
    }

    .mobile-search svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 17px;
      height: 17px;
      color: var(--muted);
      pointer-events: none;
    }

    .mobile-search input {
      width: 100%;
      height: 42px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-full);
      color: var(--tx);
      font-size: 14px;
      font-weight: 500;
      padding: 0 14px 0 38px;
      transition: border-color .2s;
    }

    .mobile-search input::placeholder {
      color: var(--muted);
    }

    .mobile-search input:focus {
      border-color: var(--p);
    }

    .mobile-cats {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding: 0 16px 12px;
      scrollbar-width: none;
    }

    .mobile-cats::-webkit-scrollbar {
      display: none;
    }

    .mobile-cat-btn {
      flex-shrink: 0;
      padding: 7px 15px;
      border-radius: var(--r-full);
      background: var(--s2);
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      border: 1px solid var(--brd);
      transition: all .18s;
      white-space: nowrap;
    }

    .mobile-cat-btn.ativo {
      background: var(--p);
      color: #fff;
      border-color: var(--p);
    }

    /* ══ SECTION TITLES ══ */
    .section-title {
      font-size: 18px;
      font-weight: 800;
      color: var(--tx);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .cat-heading {
      font-size: 20px;
      font-weight: 800;
      color: var(--tx);
      margin-bottom: 16px;
      scroll-margin-top: 90px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid var(--brd);
      padding-bottom: 12px;
    }

    .cat-section {
      margin-bottom: 40px;
      scroll-margin-top: 90px;
    }

    /* ══ FEATURED CARDS ══ */
    .featured-section {
      margin-bottom: 40px;
    }

    .featured-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 14px;
    }

    .featured-card {
      background: var(--s1);
      border: 1px solid var(--brd);
      border-radius: var(--r-lg);
      padding: 14px;
      cursor: pointer;
      transition: transform .15s, border-color .2s, box-shadow .2s;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .featured-card:hover {
      border-color: rgba(232, 93, 4, .4);
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, .4);
    }

    .featured-card:active {
      transform: scale(.97);
    }

    .featured-img {
      width: 100%;
      aspect-ratio: 1;
      background: var(--s2);
      border-radius: var(--r);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 56px;
      overflow: hidden;
    }

    .featured-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .featured-name {
      font-size: 13px;
      font-weight: 700;
      line-height: 1.35;
    }

    .featured-price {
      font-size: 16px;
      font-weight: 800;
      color: var(--p);
    }

    /* ══ PRODUTO CARDS ══ */
    .prod-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .prod-card {
      background: var(--s1);
      border: 1px solid var(--brd);
      border-radius: var(--r);
      display: flex;
      cursor: pointer;
      transition: all .15s;
      overflow: hidden;
    }

    .prod-card:hover {
      border-color: rgba(232, 93, 4, .35);
      box-shadow: 0 4px 20px rgba(0, 0, 0, .5);
      transform: translateY(-1px);
    }

    .prod-card:active {
      transform: scale(.99);
    }

    .prod-body {
      flex: 1;
      padding: 14px;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .prod-badges {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 7px;
    }

    .prod-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 2px 8px;
      border-radius: var(--r-full);
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .03em;
    }

    .prod-badge-hot {
      background: rgba(232, 93, 4, .13);
      color: var(--p);
      border: 1px solid rgba(232, 93, 4, .25);
    }

    .prod-badge-desc {
      background: rgba(34, 197, 94, .1);
      color: var(--green);
      border: 1px solid rgba(34, 197, 94, .2);
    }

    .prod-nome {
      font-size: 15px;
      font-weight: 700;
      line-height: 1.35;
      color: var(--tx);
    }

    .prod-desc {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.5;
      margin-top: 4px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .prod-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
      gap: 8px;
    }

    .prod-preco-wrap {
      display: flex;
      flex-direction: column;
    }

    .prod-preco-old {
      font-size: 11px;
      color: var(--muted);
      text-decoration: line-through;
    }

    .prod-preco {
      font-size: 17px;
      font-weight: 800;
      color: var(--p);
    }

    .btn-add {
      flex-shrink: 0;
      background: var(--p);
      color: #fff;
      border-radius: var(--r-full);
      padding: 7px 16px;
      font-size: 13px;
      font-weight: 700;
      transition: background .15s, transform .1s;
      white-space: nowrap;
    }

    .btn-add:hover {
      background: var(--pd);
    }

    .btn-add:active {
      transform: scale(.93);
    }

    .prod-img {
      width: 120px;
      flex-shrink: 0;
      background: var(--s2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 52px;
      overflow: hidden;
    }

    .prod-img img {
      width: 120px;
      height: 100%;
      object-fit: cover;
    }

    /* Grid mode desktop */
    .prod-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 16px;
    }

    .prod-grid .prod-card {
      flex-direction: column;
    }

    .prod-grid .prod-img {
      width: 100%;
      height: 160px;
      border-radius: 0;
    }

    .prod-grid .prod-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ══ BUSCA ══ */
    .search-results {
      display: none;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
      font-size: 14px;
      font-weight: 500;
    }

    .search-vazio {
      text-align: center;
      padding: 52px 24px 40px;
    }
    .search-vazio-icon {
      font-size: 52px;
      margin-bottom: 16px;
      opacity: .7;
    }
    .search-vazio-titulo {
      font-size: 18px;
      font-weight: 800;
      color: var(--tx);
      margin-bottom: 8px;
    }
    .search-vazio-sub {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.7;
    }
    .search-vazio-sub strong {
      color: var(--tx);
    }

    /* ══════════════════════════════════════════════
   MODAL DE PRODUTO — VERSÃO APRIMORADA
══════════════════════════════════════════════ */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .65);
      z-index: 200;
      display: none;
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
    }

    .overlay.show {
      display: block;
    }

    /* ══════════════════════════════════════════
       TELA DE PRODUTO — estilo iFood
    ══════════════════════════════════════════ */
    .prod-page {
      position: fixed;
      inset: 0;
      z-index: 300;
      background: var(--bg);
      display: flex;
      flex-direction: column;
      transform: translateX(100%);
      transition: transform .32s cubic-bezier(.4,0,.2,1);
      overflow: hidden;
    }
    .prod-page.open {
      transform: translateX(0);
    }

    /* Hero */
    .pp-hero {
      position: relative;
      width: 100%;
      flex-shrink: 0;
      background: var(--s2);
    }
    .pp-img {
      width: 100%;
      height: 52vw;
      max-height: 340px;
      min-height: 200px;
      background: var(--s2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 96px;
      overflow: hidden;
    }
    .pp-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .pp-back {
      position: absolute;
      top: 14px;
      left: 14px;
      z-index: 10;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      cursor: pointer;
      border: none;
      transition: background .15s;
    }
    .pp-back:hover { background: rgba(0,0,0,.75); }

    /* Corpo scrollável */
    .pp-body {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-width: none;
      padding-bottom: 90px;
    }
    .pp-body::-webkit-scrollbar { display: none; }

    .pp-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 10px;
    }
    .pp-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: var(--r-full);
    }
    .pp-badge-desc {
      background: var(--p);
      color: #fff;
    }
    .pp-badge-gratis {
      background: rgba(34,197,94,.15);
      color: var(--green);
      border: 1px solid rgba(34,197,94,.3);
    }

    /* Desktop: limitar largura e centralizar */
    @media (min-width: 700px) {
      .prod-page {
        left: 50%;
        right: auto;
        transform: translateX(200%);
        width: 100%;
        max-width: 520px;
        border-radius: var(--r-lg) var(--r-lg) 0 0;
        top: auto;
        bottom: 0;
        max-height: 96dvh;
        box-shadow: 0 -8px 40px rgba(0,0,0,.4);
      }
      .prod-page.open {
        transform: translateX(-50%) translateY(0);
      }
      .prod-page:not(.open) {
        transform: translateX(-50%) translateY(110%);
      }
      .pp-img {
        height: 280px;
        max-height: 280px;
      }
    }

    .modal {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%) translateY(110%);
      width: 100%;
      max-width: 620px;
      background: var(--s1);
      border-radius: 24px 24px 0 0;
      z-index: 300;
      max-height: 93dvh;
      overflow-y: auto;
      overflow-x: hidden;
      transition: transform .35s cubic-bezier(.4, 0, .2, 1);
      scrollbar-width: none;
    }

    .modal::-webkit-scrollbar {
      display: none;
    }

    .modal.open {
      transform: translateX(-50%) translateY(0);
    }

    /* Modal handle + close */
    .modal-handle {
      width: 40px;
      height: 4px;
      background: var(--brd);
      border-radius: var(--r-full);
      margin: 14px auto 0;
    }

    .modal-x {
      position: sticky;
      top: 0;
      float: right;
      margin: -4px 16px 0 0;
      width: 30px;
      height: 30px;
      background: var(--s3);
      border-radius: var(--r-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 15px;
      transition: background .15s;
      z-index: 2;
      cursor: pointer;
    }

    .modal-x:hover {
      background: var(--brd);
      color: var(--tx);
    }

    /* Modal split layout — desktop: imagem à esquerda, conteúdo à direita */
    .modal-layout {
      display: flex;
      flex-direction: column;
      padding: 0 0 100px;
    }

    .modal-img {
      width: 100%;
      aspect-ratio: 16/9;
      background: var(--s2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 88px;
      overflow: hidden;
      flex-shrink: 0;
    }

    .modal-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .modal-content {
      padding: 20px 18px 0;
    }

    .modal-nome {
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .modal-desc {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 14px;
    }

    .modal-preco-area {
      margin-bottom: 22px;
    }

    .modal-preco-old {
      font-size: 12px;
      color: var(--muted);
      text-decoration: line-through;
      margin-bottom: 2px;
    }

    .modal-preco {
      font-size: 26px;
      font-weight: 900;
      color: var(--p);
    }

    /* ── Opcoes section header ── */
    .opcoes-section {
      margin-bottom: 4px;
    }

    .opcoes-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--s2);
      border-radius: var(--r) var(--r) 0 0;
      padding: 13px 16px;
      border: 1px solid var(--brd);
      border-bottom: none;
    }

    .opcoes-header-left {}

    .opcoes-header-title {
      font-size: 14px;
      font-weight: 800;
    }

    .opcoes-header-sub {
      font-size: 12px;
      color: var(--muted);
      margin-top: 2px;
    }

    .opcoes-check {
      width: 26px;
      height: 26px;
      border-radius: var(--r-full);
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(34, 197, 94, .12);
      color: var(--green);
      font-size: 13px;
      flex-shrink: 0;
    }

    /* ── Opcoes list ── */
    .opcoes-list {
      border: 1px solid var(--brd);
      border-radius: 0 0 var(--r) var(--r);
      overflow: hidden;
      margin-bottom: 18px;
    }

    .opcao-item {
      display: flex;
      align-items: center;
      padding: 13px 16px;
      gap: 14px;
      border-bottom: 1px solid var(--brd);
      transition: background .15s;
      cursor: pointer;
      user-select: none;
    }

    .opcao-item:last-child {
      border-bottom: none;
    }

    .opcao-item:hover {
      background: var(--s3);
    }

    .opcao-item.selecionado {
      background: rgba(232, 93, 4, .06);
    }

    .opcao-emoji {
      width: 46px;
      height: 46px;
      flex-shrink: 0;
      background: var(--s2);
      border-radius: var(--r-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      overflow: hidden;
      border: 1px solid var(--brd);
    }

    .opcao-emoji img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .opcao-label {
      flex: 1;
      min-width: 0;
    }

    .opcao-nome {
      font-size: 14px;
      font-weight: 600;
    }

    .opcao-preco {
      font-size: 12px;
      color: var(--p);
      font-weight: 700;
      margin-top: 2px;
    }

    /* Toggle remover = checkbox style */
    .opcao-toggle {
      width: 28px;
      height: 28px;
      flex-shrink: 0;
      border-radius: var(--r-full);
      border: 2px solid var(--brd);
      display: flex;
      align-items: center;
      justify-content: center;
      color: transparent;
      font-size: 13px;
      transition: all .18s;
    }

    .opcao-item.selecionado .opcao-toggle {
      background: var(--p);
      border-color: var(--p);
      color: #fff;
    }

    /* Toggle extra = + / − */
    .opcao-btn-extra {
      width: 28px;
      height: 28px;
      flex-shrink: 0;
      border-radius: var(--r-full);
      border: 1.5px solid var(--brd);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 18px;
      font-weight: 700;
      transition: all .18s;
      line-height: 1;
    }

    .opcao-item.selecionado .opcao-btn-extra {
      background: var(--p);
      border-color: var(--p);
      color: #fff;
    }

    /* ── Comentário ── */
    .obs-wrap {
      margin-bottom: 22px;
    }

    .obs-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .obs-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--tx);
    }

    .obs-counter {
      font-size: 11px;
      color: var(--muted);
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    .obs-input {
      width: 100%;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r);
      color: var(--tx);
      font-size: 14px;
      padding: 12px 14px;
      resize: none;
      height: 82px;
      transition: border-color .2s;
    }

    .obs-input::placeholder {
      color: var(--muted);
      opacity: .6;
    }

    .obs-input:focus {
      border-color: var(--p);
    }

    /* ── Modal footer fixo ── */
    .modal-footer-fixed {
      position: sticky;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--s1);
      border-top: 1px solid var(--brd);
      padding: 14px 18px 20px;
      display: flex;
      gap: 12px;
      align-items: center;
      z-index: 4;
      flex-shrink: 0;
    }

    @supports (padding-bottom: env(safe-area-inset-bottom)) {
      .modal-footer-fixed {
        padding-bottom: max(20px, calc(env(safe-area-inset-bottom) + 14px));
      }
    }

    .qtd-ctrl {
      display: flex;
      align-items: center;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r);
      overflow: hidden;
    }

    .qtd-btn {
      width: 42px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--tx);
      font-size: 20px;
      font-weight: 700;
      transition: background .15s;
    }

    .qtd-btn:hover {
      background: var(--brd);
    }

    .qtd-num {
      min-width: 36px;
      text-align: center;
      font-size: 18px;
      font-weight: 800;
    }

    .btn-conf {
      flex: 1;
      height: 52px;
      background: var(--p);
      color: #fff;
      border-radius: var(--r);
      font-size: 15px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background .15s;
      box-shadow: 0 4px 18px rgba(232, 93, 4, .35);
    }

    .btn-conf:hover {
      background: var(--pd);
    }

    .btn-conf:active {
      transform: scale(.97);
    }

    /* ══ CART (sidebar + drawer) ══ */
    .cart-inner {
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .cart-head {
      font-size: 16px;
      font-weight: 800;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .cart-empty {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 13px;
      font-weight: 500;
      gap: 10px;
      text-align: center;
      padding: 20px 0;
    }

    .cart-items-list {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
      scrollbar-width: thin;
      padding-right: 2px;
    }

    .cart-item {
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r);
      padding: 11px 12px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }

    .ci-emoji {
      font-size: 28px;
      flex-shrink: 0;
    }

    .ci-body {
      flex: 1;
      min-width: 0;
    }

    .ci-name {
      font-size: 13px;
      font-weight: 700;
    }

    .ci-extras {
      font-size: 11px;
      color: var(--p);
      margin-top: 2px;
    }

    .ci-obs {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .ci-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 7px;
    }

    .ci-price {
      font-size: 14px;
      font-weight: 800;
      color: var(--p);
    }

    .ci-remove {
      width: 26px;
      height: 26px;
      background: var(--s3);
      border-radius: var(--r-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 13px;
      transition: all .15s;
    }

    .ci-remove:hover {
      background: rgba(239, 68, 68, .15);
      color: #ef4444;
    }

    .cupom-row {
      display: flex;
      gap: 8px;
      margin: 14px 0 4px;
    }

    .cupom-input {
      flex: 1;
      height: 40px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-sm);
      color: var(--tx);
      font-size: 12px;
      padding: 0 12px;
      text-transform: uppercase;
      letter-spacing: .04em;
      transition: border-color .2s;
    }

    .cupom-input:focus {
      border-color: var(--p);
    }

    .btn-cupom {
      height: 40px;
      padding: 0 14px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-sm);
      color: var(--tx);
      font-size: 12px;
      font-weight: 700;
      transition: border-color .15s;
    }

    .btn-cupom:hover {
      border-color: var(--p);
      color: var(--p);
    }

    .cupom-msg {
      font-size: 11px;
      font-weight: 600;
      min-height: 18px;
      margin-bottom: 4px;
    }

    .cart-totals {
      border-top: 1px solid var(--brd);
      padding-top: 14px;
      margin-top: 12px;
      display: flex;
      flex-direction: column;
      gap: 7px;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: var(--muted);
    }

    .total-row.final {
      font-size: 19px;
      font-weight: 900;
      color: var(--tx);
      margin-top: 4px;
    }

    .total-row.final span:last-child {
      color: var(--p);
    }

    .entrega-tabs {
      display: flex;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-sm);
      overflow: hidden;
      margin-bottom: 10px;
    }

    .entrega-tab {
      flex: 1;
      padding: 9px;
      font-size: 12px;
      font-weight: 700;
      color: var(--muted);
      transition: all .2s;
    }

    .entrega-tab.ativo {
      background: var(--p);
      color: #fff;
    }

    .btn-finalizar {
      width: 100%;
      height: 50px;
      background: var(--green);
      color: #fff;
      border-radius: var(--r);
      font-size: 14px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background .15s;
      box-shadow: 0 4px 16px rgba(34, 197, 94, .3);
      margin-top: 12px;
    }

    .btn-finalizar:hover {
      background: #16a34a;
    }

    .btn-finalizar:active {
      transform: scale(.98);
    }

    .btn-finalizar:disabled {
      opacity: .45;
      pointer-events: none;
    }

    /* ── FAB + Drawer (mobile) ── */
    .cart-fab {
      position: fixed;
      bottom: 18px;
      left: 50%;
      transform: translateX(-50%) translateY(120px);
      width: calc(100% - 32px);
      max-width: 480px;
      background: var(--p);
      color: #fff;
      border-radius: 18px;
      padding: 13px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 100;
      transition: transform .35s cubic-bezier(.4, 0, .2, 1);
      box-shadow: 0 8px 28px rgba(232, 93, 4, .5);
    }

    .cart-fab.show {
      transform: translateX(-50%) translateY(0);
    }

    .cart-fab:active {
      transform: translateX(-50%) scale(.97);
    }

    .fab-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .fab-count {
      width: 34px;
      height: 34px;
      background: rgba(0, 0, 0, .2);
      border-radius: var(--r-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 800;
    }

    .fab-label {
      font-size: 14px;
      font-weight: 700;
    }

    .fab-total {
      font-size: 16px;
      font-weight: 900;
    }

    .cart-drawer {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%) translateY(110%);
      width: 100%;
      max-width: 520px;
      background: var(--s1);
      border-radius: 24px 24px 0 0;
      z-index: 400;
      max-height: 90dvh;
      display: flex;
      flex-direction: column;
      transition: transform .35s cubic-bezier(.4, 0, .2, 1);
    }

    .cart-drawer.open {
      transform: translateX(-50%) translateY(0);
    }

    .drawer-head {
      padding: 16px 20px 12px;
      border-bottom: 1px solid var(--brd);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .drawer-title {
      font-size: 17px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .drawer-close {
      width: 32px;
      height: 32px;
      background: var(--s2);
      border-radius: var(--r-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 15px;
    }

    .drawer-body {
      flex: 1;
      overflow-y: auto;
      padding: 16px 20px 0;
      scrollbar-width: none;
    }

    .drawer-body::-webkit-scrollbar {
      display: none;
    }

    .drawer-foot {
      padding: 4px 20px 28px;
      flex-shrink: 0;
    }

    /* ══ TOAST ══ */
    .toast {
      position: fixed;
      bottom: 90px;
      left: 50%;
      transform: translateX(-50%) translateY(16px);
      background: var(--s2);
      color: var(--tx);
      border: 1px solid var(--brd);
      border-radius: var(--r-full);
      padding: 9px 20px;
      font-size: 13px;
      font-weight: 600;
      z-index: 600;
      opacity: 0;
      transition: all .25s;
      white-space: nowrap;
      pointer-events: none;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .5);
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    .toast.success {
      border-color: rgba(34, 197, 94, .35);
      color: var(--green);
    }

    /* ══ RESPONSIVE ══ */
    @media (min-width: 768px) {
      .topbar {
        display: flex;
      }

      .mobile-hd {
        display: none;
      }

      .mobile-nav {
        top: var(--hd-h);
        display: none;
      }

      .content {
        padding-top: 0;
      }

      .prod-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
      }

      .prod-card {
        flex-direction: column;
      }

      .prod-img {
        width: 100%;
        height: 150px;
        border-radius: 0;
      }

      .prod-img img {
        width: 100%;
        height: 100%;
      }

      .content-search-wrap {
        display: block;
      }

      .page {
        padding-top: var(--hd-h);
      }

      .featured-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      }

      .cat-section,
      .cat-heading {
        scroll-margin-top: 80px;
      }

      /* Modal desktop: imagem esquerda, conteúdo direita */
      .modal {
        max-width: 780px;
        max-height: 88dvh;
        border-radius: 20px;
        bottom: auto;
        top: 50%;
        left: 50%;
        transform: translate(-50%, calc(-50% + 30px));
        transition: transform .3s cubic-bezier(.4, 0, .2, 1), opacity .3s;
        opacity: 0;
        pointer-events: none;
      }

      .modal.open {
        transform: translate(-50%, -50%);
        opacity: 1;
        pointer-events: all;
      }

      .modal-layout {
        flex-direction: row;
        padding: 0;
        align-items: stretch;
      }

      .modal-img {
        width: 42%;
        aspect-ratio: auto;
        border-radius: 0;
        min-height: 340px;
      }

      .modal-left {
        width: 42%;
        flex-shrink: 0;
      }

      .modal-right {
        flex: 1;
        overflow-y: auto;
        padding: 0;
        display: flex;
        flex-direction: column;
        scrollbar-width: thin;
      }

      .modal-content {
        padding: 24px 24px 0;
      }

      .modal-footer-fixed {
        padding: 14px 24px 20px;
        border-radius: 0 0 20px 0;
      }

      .modal-handle {
        display: none;
      }

      .modal-x {
        position: absolute;
        top: 14px;
        right: 16px;
        float: none;
        margin: 0;
      }
    }

    @media (min-width: 1100px) {
      .cat-sidebar {
        display: flex;
        flex-direction: column;
      }

      .cart-sidebar {
        display: flex;
      }

      .cart-fab {
        display: none;
      }

      .cart-drawer {
        display: none;
      }

      .prod-list {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      }

      .prod-img {
        height: 160px;
      }

      .content {
        padding: 32px 32px 60px;
      }

      .featured-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      }
    }

    @media (max-width: 767px) {
      .topbar {
        display: none;
      }

      .mobile-hd {
        display: block;
      }

      .mobile-nav {
        display: block;
      }

      .page {
        padding-top: 0;
      }

      .cat-sidebar {
        display: none;
      }

      .cart-sidebar {
        display: none;
      }

      .content {
        padding: 20px 16px 100px;
      }

      .featured-grid {
        grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
      }
    }

    /* ══ MISC ══ */
    .content-search-wrap {
      margin-bottom: 24px;
      display: none;
    }

    .content-search {
      position: relative;
    }

    .content-search svg {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: var(--muted);
      pointer-events: none;
    }

    .content-search input {
      width: 100%;
      height: 46px;
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-full);
      color: var(--tx);
      font-size: 14px;
      font-weight: 500;
      padding: 0 16px 0 44px;
      transition: border-color .2s;
    }

    .content-search input::placeholder {
      color: var(--muted);
    }

    .content-search input:focus {
      border-color: var(--p);
    }

    @supports (padding-top: env(safe-area-inset-top)) {
      .mobile-hd {
        padding-top: max(20px, calc(env(safe-area-inset-top) + 12px));
      }
    }
    /* ══ CHECKOUT MODAL ══ */
    .checkout-modal {
      /* Sobrescreve o comportamento de bottom-sheet do .modal base */
      bottom: auto !important;
      left: 50% !important;
      top: 50% !important;
      transform: translate(-50%, -50%) scale(.94) !important;
      opacity: 0;
      pointer-events: none;
      border-radius: var(--r-lg) !important;
      max-width: 480px !important;
      width: calc(100% - 32px) !important;
      max-height: 92dvh !important;
      z-index: 1100 !important;
      transition: transform .28s cubic-bezier(.4,0,.2,1), opacity .28s ease !important;
    }
    .checkout-modal.open {
      transform: translate(-50%, -50%) scale(1) !important;
      opacity: 1 !important;
      pointer-events: auto !important;
    }
    .checkout-modal .modal-handle { display: none; }
    .checkout-modal .modal-x {
      top: 14px; right: 16px;
    }
    .checkout-body {
      padding: 20px 20px 28px;
    }
    .checkout-title {
      font-size: 18px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      padding-right: 32px;
    }
    .checkout-resumo {
      background: var(--s2);
      border-radius: var(--r);
      padding: 12px 14px;
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 16px;
      max-height: 140px;
      overflow-y: auto;
    }
    .checkout-resumo .cr-item {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      padding: 4px 0;
      color: var(--tx);
      font-size: 13px;
    }
    .checkout-resumo .cr-total {
      display: flex;
      justify-content: space-between;
      padding-top: 8px;
      margin-top: 8px;
      border-top: 1px solid var(--brd);
      font-weight: 700;
      color: var(--p);
      font-size: 14px;
    }
    .checkout-section {
      margin-bottom: 16px;
    }
    .checkout-section-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--muted);
      margin-bottom: 10px;
    }
    .checkout-field {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .checkout-field label {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
    }
    .checkout-field input,
    .checkout-field textarea {
      background: var(--s2);
      border: 1px solid var(--brd);
      border-radius: var(--r-sm);
      color: var(--tx);
      font-size: 15px;
      padding: 10px 12px;
      transition: border .2s;
    }
    .checkout-field input:focus,
    .checkout-field textarea:focus {
      border-color: var(--p);
    }
    .checkout-field textarea {
      resize: none;
    }
    .pagamento-opts {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .pag-opt {
      display: flex;
      align-items: center;
      gap: 6px;
      background: var(--s2);
      border: 1.5px solid var(--brd);
      border-radius: var(--r-sm);
      padding: 8px 14px;
      font-size: 13px;
      cursor: pointer;
      transition: border .2s, background .2s;
    }
    .pag-opt input { display: none; }
    .pag-opt.ativo {
      border-color: var(--p);
      background: color-mix(in srgb, var(--p) 15%, var(--s2));
      color: var(--p);
      font-weight: 700;
    }
    .checkout-error {
      background: #ef444422;
      border: 1px solid #ef4444;
      border-radius: var(--r-sm);
      color: #ef4444;
      font-size: 13px;
      padding: 10px 12px;
      margin-bottom: 12px;
    }
  </style>
</head>

<body>

  <!-- TOP BAR -->
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
          <svg viewBox="0 0 8 8" width="6" height="6" fill="currentColor">
            <circle cx="4" cy="4" r="4" />
          </svg>
          <?= $lojaAberta ? 'Aberto' : 'Fechado' ?>
        </span>
        <span class="badge badge-tempo">
          <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 6v6l4 2" />
          </svg>
          <?= h($tempoLabel) ?>
        </span>
        <?php if ($config['taxa_entrega']): ?>
          <span>
            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5">
              <rect x="1" y="3" width="15" height="13" />
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
              <circle cx="5.5" cy="18.5" r="2.5" />
              <circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
            R$ <?= number_format((float) $config['taxa_entrega'], 2, ',', '.') ?> entrega
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="11" cy="11" r="8" />
        <path d="M21 21l-4.35-4.35" />
      </svg>
      <input type="search" id="topbarSearch" placeholder="Buscar no cardápio..." autocomplete="off">
    </div>
    <?php if (!$clienteLogado): ?>
      <a href="login_cliente.php" class="btn-login-topbar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
          <polyline points="10 17 15 12 10 7" />
          <line x1="15" y1="12" x2="3" y2="12" />
        </svg>
        Entrar
      </a>
    <?php else: ?>
      <a href="perfil_usu.php" class="btn-usuario-topbar">
        <div class="uavatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
        </div>
        <?= $clienteNome ?>
      </a>
    <?php endif; ?>
  </header>

  <?php if (!empty($config['banner'])): ?>
    <div class="cardapio-banner" style="width:100%;max-height:280px;overflow:hidden">
      <img src="<?= h($config['banner']) ?>" alt="<?= h($config['nome_restaurante']) ?>" style="width:100%;height:100%;max-height:280px;object-fit:cover;display:block">
    </div>
  <?php endif; ?>

  <?php if ($promoAtiva): ?>
    <div class="promo-bar" id="promoBanner">
      <span><?= h($config['promo_titulo'] ?? 'Promoção') ?> — <strong><?= (int) $config['promo_desconto'] ?>%
          OFF</strong></span>
      <?php if ($config['promo_fim']): ?>
        <span id="promoTimer" data-fim="<?= strtotime($config['promo_fim']) * 1000 ?>"></span>
      <?php endif; ?>
      <button onclick="this.closest('.promo-bar').remove()">&#x2715;</button>
    </div>
  <?php endif; ?>

  <!-- MOBILE HEADER -->
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
        <?php if ($config['descricao']): ?>
          <div class="mobile-desc"><?= h($config['descricao']) ?></div><?php endif; ?>
        <div class="mobile-badges">
          <span class="badge <?= $lojaAberta ? 'badge-open' : 'badge-closed' ?>">
            <svg viewBox="0 0 8 8" width="6" height="6" fill="currentColor">
              <circle cx="4" cy="4" r="4" />
            </svg>
            <?= $lojaAberta ? 'Aberto' : 'Fechado' ?>
          </span>
          <span class="badge badge-tempo">
            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" />
            </svg>
            <?= h($tempoLabel) ?>
          </span>
          <div class="mobile-auth-row">
            <?php if (!$clienteLogado): ?>
              <a href="login_cliente.php?redir=menu.php" class="btn-login-mobile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
                  <polyline points="10 17 15 12 10 7" />
                  <line x1="15" y1="12" x2="3" y2="12" />
                </svg>
                Entrar
              </a>
            <?php else: ?>
              <a href="perfil_usu.php" class="btn-usuario-mobile">
                <div class="uavatar">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                </div>
                <?= $clienteNome ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MOBILE NAV -->
  <div class="mobile-nav">
    <div class="mobile-search-wrap">
      <div class="mobile-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="8" />
          <path d="M21 21l-4.35-4.35" />
        </svg>
        <input type="search" id="mobileSearch" placeholder="Buscar no cardápio..." autocomplete="off">
      </div>
    </div>
    <div class="mobile-cats" id="mobileCats">
      <button class="mobile-cat-btn ativo" data-cat="todos">Todos</button>
      <?php foreach ($categorias as $cat): ?>
        <?php if (!empty($porCategoria[$cat['id']])): ?>
          <button class="mobile-cat-btn" data-cat="<?= $cat['id'] ?>">
            <?= $cat['emoji'] ? h($cat['emoji']) . ' ' : '' ?>     <?= h($cat['nome']) ?>
          </button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- PAGE 3 cols -->
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

      <div class="content-search-wrap" id="contentSearchWrap">
        <div class="content-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
          <input type="search" id="tabletSearch" placeholder="Buscar no cardápio..." autocomplete="off">
        </div>
      </div>

      <div id="searchResults" class="search-results">
        <div class="section-title" style="margin-bottom:18px">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
          Resultados
        </div>
        <div class="prod-list" id="searchList"></div>
      </div>

      <div id="normalContent">

        <?php if (!empty($destaques)): ?>
          <div class="featured-section">
            <div class="section-title">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z" />
              </svg>
              Mais Pedidos
            </div>
            <div class="featured-grid">
              <?php foreach (array_slice($destaques, 0, 8) as $p):
                $pd = buildProdData($p, $opcoesPorProduto);
                ?>
                <div class="featured-card" onclick="abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">
                  <div class="featured-img">
                    <?php if (!empty($p['imagem'])): ?>
                      <img src="<?= h($p['imagem']) ?>" alt="<?= h($p['nome']) ?>" loading="lazy"
                        onerror="this.parentElement.innerHTML='<?= addslashes(h($p['emoji'] ?? '🍽️')) ?>'">
                    <?php else: ?>
                      <?= h($p['emoji'] ?? '🍽️') ?>
                    <?php endif; ?>
                  </div>
                  <div class="featured-name"><?= h($p['nome']) ?></div>
                  <div class="featured-price"><?= formatar_dinheiro((float) $p['preco']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php foreach ($categorias as $cat): ?>
          <?php if (empty($porCategoria[$cat['id']]))
            continue; ?>
          <div class="cat-section" id="cat-<?= $cat['id'] ?>" data-cat-id="<?= $cat['id'] ?>">
            <div class="cat-heading">
              <?= $cat['emoji'] ? '<span>' . h($cat['emoji']) . '</span>' : '' ?>
              <?= h($cat['nome']) ?>
            </div>
            <div class="prod-list" id="prodlist-<?= $cat['id'] ?>">
              <?php foreach ($porCategoria[$cat['id']] as $p):
                $pd = buildProdData($p, $opcoesPorProduto);
                ?>
                <div class="prod-card" onclick="abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">
                  <div class="prod-body">
                    <div class="prod-badges">
                      <?php if ($p['mais_vendido']): ?><span class="prod-badge prod-badge-hot">Mais
                          Pedido</span><?php endif; ?>
                      <?php if ($p['preco_original'] && (float) $p['preco_original'] > (float) $p['preco']): ?><span
                          class="prod-badge prod-badge-desc">Oferta</span><?php endif; ?>
                    </div>
                    <div class="prod-nome"><?= h($p['nome']) ?></div>
                    <?php if ($p['descricao']): ?>
                      <div class="prod-desc"><?= h($p['descricao']) ?></div><?php endif; ?>
                    <div class="prod-footer">
                      <div class="prod-preco-wrap">
                        <?php if ($p['preco_original'] && (float) $p['preco_original'] > (float) $p['preco']): ?>
                          <span class="prod-preco-old"><?= formatar_dinheiro((float) $p['preco_original']) ?></span>
                        <?php endif; ?>
                        <span class="prod-preco"><?= formatar_dinheiro((float) $p['preco']) ?></span>
                      </div>
                      <button class="btn-add"
                        onclick="event.stopPropagation();abrirModal(<?= htmlspecialchars(json_encode($pd), ENT_QUOTES) ?>)">+
                        Adicionar</button>
                    </div>
                  </div>
                  <div class="prod-img">
                    <?php if (!empty($p['imagem'])): ?>
                      <img src="<?= h($p['imagem']) ?>" alt="<?= h($p['nome']) ?>" loading="lazy"
                        onerror="this.parentElement.innerHTML='<?= addslashes(h($p['emoji'] ?? '🍽️')) ?>'">
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
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 01-8 0" />
          </svg>
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
            <div class="total-row" id="sFrete"><span>Entrega</span><span id="sTotFrete">R$
                <?= number_format((float) $config['taxa_entrega'], 2, ',', '.') ?></span></div>
            <div class="total-row" id="sDesc" style="display:none;color:var(--green)"><span>Desconto</span><span
                id="sTotDesc"></span></div>
            <div class="total-row final"><span>Total</span><span id="sTotTotal">R$ 0,00</span></div>
            <button class="btn-finalizar" id="sBtnFinalizar" onclick="finalizarPedido()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              Finalizar via WhatsApp
            </button>
          </div>
        </div>
      </div>
    </aside>
  </div>

  <!-- OVERLAY -->
  <div class="overlay" id="overlay" onclick="fecharTudo()"></div>

  <!-- ══════════════════════════════════
     TELA DE PRODUTO (estilo iFood)
══════════════════════════════════════ -->
  <div class="prod-page" id="modalProduto" role="dialog" aria-modal="true">

    <!-- Hero: foto grande com botão voltar sobreposto -->
    <div class="pp-hero" id="ppHero">
      <button class="pp-back" onclick="fecharModal()" aria-label="Voltar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>
      <div class="pp-img" id="modalImg"></div>
    </div>

    <!-- Conteúdo scrollável -->
    <div class="pp-body">
      <div class="modal-content">

        <div class="pp-badges" id="ppBadges"></div>
        <div class="modal-nome" id="modalNome"></div>
        <div class="modal-desc" id="modalDesc"></div>
        <div class="modal-preco-area">
          <div class="modal-preco-old" id="modalPrecoOld"></div>
          <div class="modal-preco" id="modalPreco"></div>
        </div>

        <!-- Remover ingredientes -->
        <div id="secaoRemover" class="opcoes-section" style="display:none">
          <div class="opcoes-header">
            <div class="opcoes-header-left">
              <div class="opcoes-header-title">Deseja remover algum ingrediente?</div>
              <div class="opcoes-header-sub" id="removerSub"></div>
            </div>
            <div class="opcoes-check">✓</div>
          </div>
          <div class="opcoes-list" id="listaRemover"></div>
        </div>

        <!-- Adicionar extras -->
        <div id="secaoExtras" class="opcoes-section" style="display:none">
          <div class="opcoes-header">
            <div class="opcoes-header-left">
              <div class="opcoes-header-title">Deseja adicionar algum ingrediente?</div>
              <div class="opcoes-header-sub" id="extrasSub"></div>
            </div>
            <div class="opcoes-check">✓</div>
          </div>
          <div class="opcoes-list" id="listaExtras"></div>
        </div>

        <!-- Comentário -->
        <div class="obs-wrap">
          <div class="obs-header">
            <label class="obs-label" for="modalObs">Algum comentário?</label>
            <span class="obs-counter" id="obsCounter">0 / 140</span>
          </div>
          <textarea class="obs-input" id="modalObs" maxlength="140"
            placeholder="Ex: tirar a cebola, maionese à parte etc." oninput="updCounter()"></textarea>
        </div>
      </div>
    </div>

    <!-- Barra fixa inferior: quantidade + adicionar -->
    <div class="modal-footer-fixed">
      <div class="qtd-ctrl">
        <button class="qtd-btn" id="btnMenos">&#8722;</button>
        <span class="qtd-num" id="qtdNum">1</span>
        <button class="qtd-btn" id="btnMais">&#43;</button>
      </div>
      <button class="btn-conf" id="btnConf">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 01-8 0"/>
        </svg>
        <span id="btnConfTxt">Adicionar</span>
      </button>
    </div>
  </div>

  <!-- MOBILE FAB + DRAWER -->
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
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
          <line x1="3" y1="6" x2="21" y2="6" />
          <path d="M16 10a4 4 0 01-8 0" />
        </svg>
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
        <div class="total-row" id="dFrete"><span>Entrega</span><span id="dTotFrete">R$
            <?= number_format((float) $config['taxa_entrega'], 2, ',', '.') ?></span></div>
        <div class="total-row" id="dDesc" style="display:none;color:var(--green)"><span>Desconto</span><span
            id="dTotDesc"></span></div>
        <div class="total-row final"><span>Total</span><span id="dTotTotal">R$ 0,00</span></div>
        <button class="btn-finalizar" onclick="finalizarPedido()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          Finalizar via WhatsApp
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL DE CHECKOUT -->
  <div class="modal checkout-modal" id="checkoutModal" role="dialog" aria-modal="true">
    <div class="modal-handle"></div>
    <button class="modal-x" onclick="fecharCheckout()">&#x2715;</button>
    <div class="checkout-body">
      <div class="checkout-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Finalizar Pedido
      </div>

      <!-- Resumo -->
      <div class="checkout-resumo" id="checkoutResumo"></div>

      <!-- Campos do cliente -->
      <div class="checkout-section">
        <div class="checkout-section-title">Seus dados</div>
        <div class="checkout-field">
          <label>Nome completo *</label>
          <input type="text" id="ckNome" placeholder="Ex: João Silva" maxlength="80" autocomplete="name">
        </div>
        <div class="checkout-field">
          <label>WhatsApp (com DDD) *</label>
          <input type="tel" id="ckWhatsapp" placeholder="Ex: 51999990000" maxlength="20" autocomplete="tel">
        </div>
      </div>

      <!-- Tipo de entrega -->
      <div class="checkout-section">
        <div class="checkout-section-title">Entrega ou Retirada?</div>
        <div class="entrega-tabs" style="margin-bottom:0">
          <button class="entrega-tab ativo" id="ckTabEntrega" onclick="ckSetEntrega('entrega')">🚴 Entrega</button>
          <button class="entrega-tab" id="ckTabRetirada" onclick="ckSetEntrega('retirada')">🏠 Retirada</button>
        </div>
        <div id="ckEnderecoWrap" class="checkout-field" style="margin-top:10px">
          <label>Endereço completo *</label>
          <input type="text" id="ckEndereco" placeholder="Rua, número, bairro" maxlength="200" autocomplete="street-address">
        </div>
      </div>

      <!-- Pagamento -->
      <div class="checkout-section">
        <div class="checkout-section-title">Forma de pagamento</div>
        <div class="pagamento-opts" id="ckPagamento">
          <label class="pag-opt ativo"><input type="radio" name="ckPag" value="dinheiro" checked> 💵 Dinheiro</label>
          <label class="pag-opt"><input type="radio" name="ckPag" value="pix"> 📲 Pix</label>
          <label class="pag-opt"><input type="radio" name="ckPag" value="cartao"> 💳 Cartão</label>
        </div>
      </div>

      <!-- Observações gerais -->
      <div class="checkout-section">
        <div class="checkout-field">
          <label>Observações gerais (opcional)</label>
          <textarea id="ckObs" placeholder="Alguma observação para o pedido?" maxlength="200" rows="2"></textarea>
        </div>
      </div>

      <div class="checkout-error" id="ckErro" style="display:none"></div>

      <button class="btn-finalizar" id="ckBtnFinalizar" onclick="confirmarPedido()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <span id="ckBtnTxt">Confirmar e abrir WhatsApp</span>
      </button>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast" id="toast"></div>

  <script>

    const CFG = {
      nome: <?= json_encode($config['nome_restaurante']) ?>,
      whatsapp: <?= json_encode(preg_replace('/\D/', '', $config['whatsapp'])) ?>,
      taxa: <?= (float) $config['taxa_entrega'] ?>,
      pedMin: <?= (float) $config['pedido_minimo'] ?>,
      aberto: <?= $lojaAberta ? 'true' : 'false' ?>,
      logado: <?= $clienteLogado ? 'true' : 'false' ?>,
      lancheriaId: <?= (int) $lancheria_id ?>,
      clienteId: <?= $clienteLogado ? (int) $_SESSION['cliente_id'] : 'null' ?>
    };

    // ── State ──────────────────────────────────────────────────────
    let cart = JSON.parse(localStorage.getItem('cdCart') ?? '[]');
    let prodAtual = null, qty = 1;
    let tipoEntrega = 'entrega';
    let cupom = null;
    // ingredientes selecionados no modal atual
    let removerSel = new Set(); // ids de ingredientes para remover
    let extrasSel = {};        // id -> {nome, preco, emoji, qty}

    // ── Search ─────────────────────────────────────────────────────
    // Normaliza texto removendo acentos para busca sem acento funcionar
    function normText(s) {
      return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    const allCards = [];
    document.querySelectorAll('.prod-card').forEach(c => {
      allCards.push({
        onclick: c.getAttribute('onclick'),   // preserva o handler original
        el: c.cloneNode(true),
        txt: normText(c.innerText)
      });
    });

    function doSearch(q) {
      const sr  = document.getElementById('searchResults');
      const nc  = document.getElementById('normalContent');
      const sl  = document.getElementById('searchList');
      const csb = document.querySelector('.cat-sidebar');
      const qNorm = normText(q.trim());

      // Sincroniza os outros inputs de busca
      ['topbarSearch', 'mobileSearch', 'tabletSearch'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value !== q) el.value = q;
      });

      if (!qNorm) {
        sr.style.display = 'none';
        nc.style.display = '';
        if (csb) csb.style.opacity = '1';
        return;
      }

      // FIX: usar 'block' em vez de '' para sobrescrever o display:none do CSS
      sr.style.display = 'block';
      nc.style.display = 'none';
      if (csb) csb.style.opacity = '.4';
      sl.innerHTML = '';

      // Busca por múltiplas palavras (todas precisam estar presentes)
      const palavras = qNorm.split(/\s+/).filter(Boolean);
      const res = allCards.filter(c => palavras.every(p => c.txt.includes(p)));

      if (!res.length) {
        sl.innerHTML =
          '<div class="search-vazio">' +
            '<div class="search-vazio-icon">🔍</div>' +
            '<div class="search-vazio-titulo">Produto não encontrado</div>' +
            '<div class="search-vazio-sub">Não temos <strong>"' + esc(q.trim()) + '"</strong> no cardápio.<br>Tente outro nome ou verifique a digitação.</div>' +
          '</div>';
      } else {
        res.forEach(({ el, onclick }) => {
          const clone = el.cloneNode(true);
          if (onclick) clone.setAttribute('onclick', onclick); // garante que o handler está correto
          sl.appendChild(clone);
        });
      }
    }

    ['topbarSearch', 'mobileSearch', 'tabletSearch'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', e => doSearch(e.target.value));
    });

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
      removerSel = new Set();
      extrasSel = {};
      document.getElementById('qtdNum').textContent = 1;
      document.getElementById('modalObs').value = '';
      document.getElementById('obsCounter').textContent = '0 / 140';
      document.getElementById('modalNome').textContent = prod.nome;
      document.getElementById('modalDesc').textContent = prod.desc || '';
      document.getElementById('modalPreco').textContent = brl(prod.preco);
      document.getElementById('modalPrecoOld').textContent =
        prod.preco_original && prod.preco_original > prod.preco ? 'De ' + brl(prod.preco_original) : '';

      // Badges de desconto
      const badges = document.getElementById('ppBadges');
      badges.innerHTML = '';
      if (prod.preco_original && prod.preco_original > prod.preco) {
        const pct = Math.round((1 - prod.preco / prod.preco_original) * 100);
        badges.innerHTML = '<span class="pp-badge pp-badge-desc">-' + pct + '%</span>';
      }

      const iw = document.getElementById('modalImg');
      if (prod.img) {
        iw.innerHTML = '<img src="' + esc(prod.img) + '" alt="' + esc(prod.nome) + '" onerror="this.parentElement.textContent=\'' + esc(prod.emoji) + '\'">';
      } else { iw.textContent = prod.emoji || '🍽️'; }

      // Remover ingredientes
      const secR = document.getElementById('secaoRemover');
      const listR = document.getElementById('listaRemover');
      listR.innerHTML = '';
      if (prod.remover && prod.remover.length) {
        document.getElementById('removerSub').textContent = 'Escolha até ' + prod.remover.length + ' opções.';
        prod.remover.forEach(opt => {
          const item = buildOpcaoItem(opt, 'remover');
          listR.appendChild(item);
        });
        secR.style.display = '';
      } else { secR.style.display = 'none'; }

      // Extras
      const secE = document.getElementById('secaoExtras');
      const listE = document.getElementById('listaExtras');
      listE.innerHTML = '';
      if (prod.extras && prod.extras.length) {
        document.getElementById('extrasSub').textContent = 'Escolha até ' + prod.extras.length + ' opções.';
        prod.extras.forEach(opt => {
          const item = buildOpcaoItem(opt, 'extra');
          listE.appendChild(item);
        });
        secE.style.display = '';
      } else { secE.style.display = 'none'; }

      updBtnConf();
      document.getElementById('modalProduto').classList.add('open');
      document.body.style.overflow = 'hidden';
      // scroll body area to top
      const pb = document.querySelector('.pp-body');
      if (pb) pb.scrollTop = 0;
    }

    function buildOpcaoItem(opt, tipo) {
      const div = document.createElement('div');
      div.className = 'opcao-item';
      div.dataset.id = opt.id;
      const emojiHtml = opt.img
        ? '<div class="opcao-emoji"><img src="' + esc(opt.img) + '" alt="' + esc(opt.nome) + '" onerror="this.parentElement.textContent=\'' + esc(opt.emoji || '') + '\'"></div>'
        : '<div class="opcao-emoji">' + esc(opt.emoji || '🍴') + '</div>';
      const precoHtml = tipo === 'extra' && opt.preco > 0
        ? '<div class="opcao-preco">+ ' + brl(opt.preco) + '</div>'
        : '';
      const controlHtml = tipo === 'remover'
        ? '<div class="opcao-toggle">✓</div>'
        : '<div class="opcao-btn-extra">+</div>';
      div.innerHTML =
        emojiHtml +
        '<div class="opcao-label"><div class="opcao-nome">' + esc(opt.nome) + '</div>' + precoHtml + '</div>' +
        controlHtml;
      div.addEventListener('click', () => toggleOpcao(div, opt, tipo));
      return div;
    }

    function toggleOpcao(div, opt, tipo) {
      const sel = div.classList.toggle('selecionado');
      if (tipo === 'remover') {
        sel ? removerSel.add(opt.id) : removerSel.delete(opt.id);
        const tog = div.querySelector('.opcao-toggle');
        if (tog) tog.textContent = sel ? '✓' : '';
      } else {
        if (sel) {
          extrasSel[opt.id] = opt;
          const btn = div.querySelector('.opcao-btn-extra');
          if (btn) btn.textContent = '−';
        } else {
          delete extrasSel[opt.id];
          const btn = div.querySelector('.opcao-btn-extra');
          if (btn) btn.textContent = '+';
        }
      }
      updBtnConf();
    }

    function fecharModal() {
      document.getElementById('modalProduto').classList.remove('open');
      document.body.style.overflow = '';
    }
    function fecharTudo() { fecharModal(); fecharDrawer(); fecharCheckout(); }

    document.getElementById('btnMenos').onclick = () => { if (qty > 1) { qty--; document.getElementById('qtdNum').textContent = qty; updBtnConf(); } };
    document.getElementById('btnMais').onclick = () => { qty++; document.getElementById('qtdNum').textContent = qty; updBtnConf(); };

    function extrasTotalPreco() {
      return Object.values(extrasSel).reduce((s, o) => s + (o.preco || 0), 0);
    }

    function updBtnConf() {
      if (!prodAtual) return;
      const total = (prodAtual.preco + extrasTotalPreco()) * qty;
      document.getElementById('btnConfTxt').textContent = 'Adicionar • ' + brl(total);
    }

    function updCounter() {
      const v = document.getElementById('modalObs').value.length;
      document.getElementById('obsCounter').textContent = v + ' / 140';
    }

   document.getElementById('btnConf').onclick = () => {
  if (!prodAtual) return;

      const extrasArr = Object.values(extrasSel);
      const removerArr = prodAtual.remover
        ? prodAtual.remover.filter(r => removerSel.has(r.id)).map(r => r.nome)
        : [];
      const precoFinal = prodAtual.preco + extrasTotalPreco();
      cart.push({
        produto: { ...prodAtual, preco: precoFinal },
        qty,
        obs: document.getElementById('modalObs').value.trim(),
        remover: removerArr,
        extras: extrasArr,
      });
      saveCart(); fecharModal(); toast('Adicionado! ✓', 'success');
    };

    // ── Cart ────────────────────────────────────────────────────────
    function saveCart() { localStorage.setItem('cdCart', JSON.stringify(cart)); renderAll(); }

    function renderAll() { renderFab(); renderSidebar(); }

    function renderFab() {
      const count = cart.reduce((a, i) => a + i.qty, 0);
      const total = cart.reduce((a, i) => a + i.produto.preco * i.qty, 0);
      document.getElementById('fabCount').textContent = count;
      document.getElementById('fabTotal').textContent = brl(total);
      document.getElementById('cartFab').classList.toggle('show', count > 0);
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
      let extrasText = '';
      if (item.extras && item.extras.length) {
        extrasText = '<div class="ci-extras">+ ' + item.extras.map(e => esc(e.nome)).join(', ') + '</div>';
      }
      let removerText = '';
      if (item.remover && item.remover.length) {
        removerText = '<div class="ci-obs">Sem: ' + item.remover.map(esc).join(', ') + '</div>';
      }
      div.innerHTML =
        '<div class="ci-emoji">' + esc(item.produto.emoji || '🍽️') + '</div>' +
        '<div class="ci-body">' +
        '<div class="ci-name">' + item.qty + 'x ' + esc(item.produto.nome) + '</div>' +
        extrasText + removerText +
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
      const sub = cart.reduce((a, i) => a + i.produto.preco * i.qty, 0);
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
      const msgId = scope === 'sidebar' ? 'cupomMsgSidebar' : 'cupomMsgDrawer';
      const codigo = document.getElementById(inputId).value.trim().toUpperCase();
      const msgEl = document.getElementById(msgId);
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

    // ── Checkout ──────────────────────────────────────────────────
    let ckTipoEntrega = 'entrega';

    function finalizarPedido() {
      if (!cart.length) return;
      if (!CFG.aberto) { toast('A loja está fechada.'); return; }
      const sub = cart.reduce((a, i) => a + i.produto.preco * i.qty, 0);
      if (sub < CFG.pedMin) { toast('Pedido mínimo: ' + brl(CFG.pedMin)); return; }

      // Preenche o resumo do carrinho no modal
      const resumo = document.getElementById('checkoutResumo');
      resumo.innerHTML = '';
      cart.forEach(i => {
        const div = document.createElement('div');
        div.className = 'cr-item';
        div.innerHTML = '<span>' + i.qty + 'x ' + esc(i.produto.nome) + '</span><span>' + brl(i.produto.preco * i.qty) + '</span>';
        resumo.appendChild(div);
      });
      const frete = ckTipoEntrega === 'retirada' ? 0 : CFG.taxa;
      let desc = 0;
      if (cupom) desc = cupom.tipo === 'percentual' ? sub * cupom.valor / 100 : Math.min(cupom.valor, sub);
      const total = Math.max(0, sub + frete - desc);
      const totDiv = document.createElement('div');
      totDiv.className = 'cr-total';
      totDiv.innerHTML = '<span>Total</span><span>' + brl(total) + '</span>';
      resumo.appendChild(totDiv);

      // Pré-preenche nome/wpp se logado
      if (CFG.logado) {
        document.getElementById('ckNome').value = document.querySelector('.btn-usuario-topbar')?.textContent?.trim() || '';
      }

      ckSetEntrega(ckTipoEntrega);
      abrirCheckout();
    }

    function abrirCheckout() {
      document.getElementById('checkoutModal').classList.add('open');
      document.getElementById('overlay').classList.add('show');
      document.body.style.overflow = 'hidden';
      fecharDrawer();
    }

    function fecharCheckout() {
      document.getElementById('checkoutModal').classList.remove('open');
      document.getElementById('overlay').classList.remove('show');
      document.body.style.overflow = '';
    }

    function ckSetEntrega(tipo) {
      ckTipoEntrega = tipo;
      document.getElementById('ckTabEntrega').classList.toggle('ativo', tipo === 'entrega');
      document.getElementById('ckTabRetirada').classList.toggle('ativo', tipo === 'retirada');
      document.getElementById('ckEnderecoWrap').style.display = tipo === 'entrega' ? '' : 'none';
    }

    // Selecionar forma de pagamento
    document.addEventListener('change', e => {
      if (e.target.name === 'ckPag') {
        document.querySelectorAll('.pag-opt').forEach(l => l.classList.remove('ativo'));
        e.target.closest('.pag-opt')?.classList.add('ativo');
      }
    });

    async function confirmarPedido() {
      const nome = document.getElementById('ckNome').value.trim();
      const wpp  = document.getElementById('ckWhatsapp').value.trim().replace(/\D/g, '');
      const end  = document.getElementById('ckEndereco').value.trim();
      const obs  = document.getElementById('ckObs').value.trim();
      const pag  = document.querySelector('input[name="ckPag"]:checked')?.value || 'dinheiro';
      const erroEl = document.getElementById('ckErro');
      const btnEl  = document.getElementById('ckBtnFinalizar');

      erroEl.style.display = 'none';

      if (!nome) { mostrarErroChk('Informe seu nome.'); return; }
      if (wpp.length < 10) { mostrarErroChk('Informe um WhatsApp válido com DDD.'); return; }
      if (ckTipoEntrega === 'entrega' && !end) { mostrarErroChk('Informe o endereço de entrega.'); return; }

      // Montar itens no formato da API (inclui extras e removidos)
      const itens = cart.map(i => ({
        produto_id: i.produto.id,
        quantidade: i.qty,
        obs: i.obs || '',
        variacoes: [],
        extras: (i.extras || []).map(e => ({ id: e.id, nome: e.nome, preco: e.preco || 0, emoji: e.emoji || '' })),
        removidos: (i.remover || []).map(r => typeof r === 'string' ? r : (r.nome || r))
      }));

      btnEl.disabled = true;
      document.getElementById('ckBtnTxt').textContent = 'Enviando pedido…';

      try {
        const resp = await fetch('api/criar_pedido.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            nome,
            whatsapp: wpp,
            itens,
            endereco: end,
            observacoes: obs,
            tipo_entrega: ckTipoEntrega,
            pagamento: pag,
            cupom: cupom?.codigo || ''
          })
        });
        const data = await resp.json();

        if (!data.ok) {
          mostrarErroChk(data.erro || 'Erro ao criar pedido.');
          btnEl.disabled = false;
          document.getElementById('ckBtnTxt').textContent = 'Confirmar e abrir WhatsApp';
          return;
        }

        // Pedido salvo! Montar mensagem WhatsApp e abrir
        const sub = cart.reduce((a, i) => a + i.produto.preco * i.qty, 0);
        let msg = '*Novo Pedido #' + data.numero + ' — ' + CFG.nome + '*\n\n';
        cart.forEach(i => {
          msg += '*' + i.qty + 'x ' + i.produto.nome + '* — ' + brl(i.produto.preco * i.qty) + '\n';
          if (i.remover && i.remover.length) msg += '   🚫 Sem: ' + i.remover.join(', ') + '\n';
          if (i.extras && i.extras.length) msg += '   ➕ Extra: ' + i.extras.map(e => e.nome).join(', ') + '\n';
          if (i.obs) msg += '   📝 ' + i.obs + '\n';
        });
        msg += '\n──────────────────\n';
        msg += 'Subtotal: ' + brl(data.subtotal) + '\n';
        if (ckTipoEntrega === 'entrega') msg += 'Entrega: ' + brl(data.taxa_entrega) + '\n';
        if (data.desconto_cupom > 0) msg += 'Desconto: -' + brl(data.desconto_cupom) + '\n';
        msg += '*TOTAL: ' + brl(data.total) + '*\n';
        msg += '\n*Entrega:* ' + (ckTipoEntrega === 'entrega' ? 'Entregar em ' + end : 'Retirada no local') + '\n';
        msg += '*Pagamento:* ' + ({ dinheiro: 'Dinheiro', pix: 'Pix', cartao: 'Cartão' }[pag] || pag) + '\n';
        msg += '*Cliente:* ' + nome + ' · ' + wpp;
        if (obs) msg += '\n*Obs:* ' + obs;

        let waNum = data.whatsapp_loja;
        if (!waNum.startsWith('55')) waNum = '55' + waNum;

        cart = [];
        saveCart();
        fecharCheckout();
        toast('Pedido #' + data.numero + ' criado! ✓', 'success');

        setTimeout(() => {
          window.open('https://wa.me/' + waNum + '?text=' + encodeURIComponent(msg), '_blank');
        }, 400);

      } catch (err) {
        mostrarErroChk('Falha de conexão. Tente novamente.');
        btnEl.disabled = false;
        document.getElementById('ckBtnTxt').textContent = 'Confirmar e abrir WhatsApp';
      }
    }

    function mostrarErroChk(msg) {
      const el = document.getElementById('ckErro');
      el.textContent = msg;
      el.style.display = '';
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Promo timer ────────────────────────────────────────────────
    const timerEl = document.getElementById('promoTimer');
    if (timerEl) {
      const fim = parseInt(timerEl.dataset.fim);
      const tick = () => {
        const d = Math.max(0, Math.floor((fim - Date.now()) / 1000));
        if (!d) { timerEl.textContent = 'Encerrada'; return; }
        const h = Math.floor(d / 3600), m = Math.floor((d % 3600) / 60), s = d % 60;
        timerEl.textContent = (h ? h + 'h ' : '') + String(m).padStart(2, '0') + 'min ' + String(s).padStart(2, '0') + 's';
      };
      tick(); setInterval(tick, 1000);
    }

    // ── Helpers ────────────────────────────────────────────────────
    function brl(v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+,)/g, '$1.'); }
    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function toast(msg, type) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.className = 'toast show' + (type ? ' ' + type : '');
      clearTimeout(t._t);
      t._t = setTimeout(() => t.className = 'toast', 2800);
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharTudo(); });
    renderAll();
  </script>

  <footer style="text-align:center;padding:20px;font-size:12px;color:var(--muted);border-top:1px solid var(--brd);">
    <?php if (!empty($config['instagram']) || !empty($config['facebook']) || !empty($config['tiktok'])): ?>
      <div style="display:flex;justify-content:center;gap:14px;margin-bottom:10px">
        <?php if (!empty($config['instagram'])): ?>
          <a href="<?= h($config['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram" style="color:var(--muted);display:inline-flex">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($config['facebook'])): ?>
          <a href="<?= h($config['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook" style="color:var(--muted);display:inline-flex">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($config['tiktok'])): ?>
          <a href="<?= h($config['tiktok']) ?>" target="_blank" rel="noopener" aria-label="TikTok" style="color:var(--muted);display:inline-flex">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?= h($config['nome_restaurante']) ?> · Cardápio Digital
    <a href="admin/login.php" style="margin-left:16px;color:var(--brd);font-size:11px;" title="Acesso restrito">⚙</a>
  </footer>




<script>
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.register("sw.js").catch(() => {});
}
</script>
</body>

</html>
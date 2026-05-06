/* ============================================================
   Cardápio Digital PLUS — cart.js v2
   ============================================================ */

// ── Utilitários ──────────────────────────────────────────────
const fmtMoeda = v => 'R$ ' + Number(v).toFixed(2).replace('.', ',');
const qs  = s => document.querySelector(s);
const qsa = s => document.querySelectorAll(s);
const $ = id => document.getElementById(id);

// ── Dark mode ────────────────────────────────────────────────
(function initDarkMode() {
  const root = document.documentElement;
  const saved = localStorage.getItem('darkMode');
  if (saved === '1') root.setAttribute('data-theme', 'dark');
  document.addEventListener('DOMContentLoaded', () => {
    const btn = $('darkToggle');
    if (!btn) return;
    btn.textContent = root.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
    btn.addEventListener('click', () => {
      const isDark = root.getAttribute('data-theme') === 'dark';
      root.setAttribute('data-theme', isDark ? 'light' : 'dark');
      localStorage.setItem('darkMode', isDark ? '0' : '1');
      btn.textContent = isDark ? '🌙' : '☀️';
    });
  });
})();

// ── Aplica cor primária dinâmica ─────────────────────────────
(function applyColor() {
  if (typeof LOJA_CONFIG === 'undefined') return;
})();

// ── Carrinho (localStorage) ───────────────────────────────────
function getCarrinho() { try { return JSON.parse(localStorage.getItem('carrinho') || '[]'); } catch { return []; } }
function salvarCarrinho(c) { localStorage.setItem('carrinho', JSON.stringify(c)); }

function addItem(produto_id, nome, preco, quantidade, obs, variacoes, customNomes) {
  const c = getCarrinho();
  const key = produto_id + '_' + (variacoes||[]).join('_');
  const idx = c.findIndex(i => i._key === key);
  if (idx >= 0) { c[idx].quantidade += quantidade; }
  else { c.push({ _key: key, produto_id, nome, preco, quantidade, obs: obs||'', variacoes: variacoes||[], customNomes: customNomes||[] }); }
  salvarCarrinho(c);
  atualizarBotaoCarrinho();
  return c;
}

function removeItem(key) { salvarCarrinho(getCarrinho().filter(i => i._key !== key)); atualizarBotaoCarrinho(); renderCarrinho(); }
function changeQtd(key, delta) {
  const c = getCarrinho();
  const idx = c.findIndex(i => i._key === key);
  if (idx < 0) return;
  c[idx].quantidade = Math.max(1, c[idx].quantidade + delta);
  salvarCarrinho(c); atualizarBotaoCarrinho(); renderCarrinho();
}

function calcTotais() {
  const c = getCarrinho();
  let sub = c.reduce((s,i) => s + i.preco * i.quantidade, 0);
  let taxa = 0;
  if (typeof LOJA_CONFIG !== 'undefined') {
    if (_tipoEntrega === 'entrega') {
      taxa = _taxaZona !== null ? _taxaZona : LOJA_CONFIG.taxaEntrega;
    }
  }
  let descontoPromo = 0;
  if (typeof LOJA_CONFIG !== 'undefined' && LOJA_CONFIG.promoAtiva) {
    descontoPromo = Math.round(sub * LOJA_CONFIG.promoDesconto / 100 * 100) / 100;
  }
  const descontoCupom = _descontoCupom;
  const total = Math.max(0, sub - descontoPromo - descontoCupom + taxa);
  return { sub, taxa, descontoPromo, descontoCupom, total };
}

function atualizarBotaoCarrinho() {
  const c = getCarrinho();
  const btn = $('cartBtn'), cnt = $('cartCount'), tot = $('cartTotal');
  if (!btn) return;
  const qtd = c.reduce((s,i) => s + i.quantidade, 0);
  if (qtd === 0) { btn.style.display = 'none'; return; }
  btn.style.display = 'flex';
  if (cnt) cnt.textContent = qtd;
  if (tot) tot.textContent = fmtMoeda(c.reduce((s,i) => s + i.preco * i.quantidade, 0));
}

// ── Estado do carrinho ───────────────────────────────────────
let _tipoEntrega = 'retirada';
let _pagamento   = 'dinheiro';
let _taxaZona    = null;
let _bairro      = '';
let _descontoCupom = 0;
let _cupomCode   = '';
let _totalPedidos = parseInt(localStorage.getItem('totalPedidos') || '0');

function setTipoEntrega(tipo) {
  _tipoEntrega = tipo;
  qsa('.tipo-btn').forEach(b => { b.className = b.dataset.tipo === tipo ? 'btn btn-primary tipo-btn' : 'btn btn-outline tipo-btn'; });
  const ce = $('camposEntrega'); if (ce) ce.style.display = tipo === 'entrega' ? 'block' : 'none';
  const li = $('linhaEntrega'); if (li) li.style.display = tipo === 'entrega' ? 'flex' : 'none';
  atualizarResumo();
}

function setPagamento(pag) {
  _pagamento = pag;
  qsa('.pag-btn').forEach(b => {
    const isMP = b.dataset.pag === 'mercadopago';
    if (b.dataset.pag === pag) {
      b.className = 'btn pag-btn active';
      b.style.cssText = isMP ? 'background:#0077b6;color:#fff;border:none;width:100%;padding:12px;font-size:15px' : '';
    } else {
      b.className = isMP ? 'btn pag-btn' : 'btn btn-outline pag-btn';
      b.style.cssText = isMP ? 'background:#009ee3;color:#fff;border:none;width:100%;padding:12px;font-size:15px' : '';
    }
  });
  const pi = $('pixInfo'); if (pi) pi.style.display = pag === 'pix' ? 'block' : 'none';
  const mi = $('mpInfo');  if (mi) mi.style.display = pag === 'mercadopago' ? 'block' : 'none';
  const btn = $('btnFinalizar');
  if (btn) btn.textContent = pag === 'mercadopago' ? '💳 Ir para pagamento' : '📲 Enviar pedido via WhatsApp';
}

function selecionarZona(val) {
  if (!val) { _taxaZona = null; _bairro = ''; atualizarResumo(); return; }
  const sel = $('bairroSelect');
  const opt = sel.options[sel.selectedIndex];
  _taxaZona = parseFloat(opt.dataset.taxa);
  _bairro = val.startsWith('outro_') ? '' : val;
  atualizarResumo();
}

// ── Modal produto ────────────────────────────────────────────
let _modalId = null, _modalPrecoBase = 0, _modalQtd = 1, _modalVariacoes = {};

function abrirProduto(id, nome, preco, desc, img) {
  _modalId = id; _modalPrecoBase = parseFloat(preco); _modalQtd = 1; _modalVariacoes = {};
  $('modalNome').textContent = nome;
  $('modalDesc').textContent = desc || '';
  const imgEl = $('modalImg'), imgSrc = $('modalImgSrc');
  if (img && imgEl && imgSrc) { imgSrc.src = img; imgEl.style.display = 'block'; } else if (imgEl) imgEl.style.display = 'none';
  $('modalQtd').textContent = '1';
  $('modalObs').value = '';
  $('variacoesContainer').innerHTML = '<p style="color:var(--muted);font-size:13px">Carregando opções...</p>';
  atualizarPrecoModal();
  $('modalProduto').style.display = 'flex';
  document.body.style.overflow = 'hidden';
  // Buscar variações
  fetch(`api/variacoes_produto.php?produto_id=${id}`)
    .then(r => r.json()).then(grupos => {
      const c = $('variacoesContainer'); c.innerHTML = '';
      const keys = Object.keys(grupos);
      if (!keys.length) { c.innerHTML = ''; return; }
      keys.forEach(grupo => {
        const div = document.createElement('div'); div.style.marginBottom = '12px';
        div.innerHTML = `<div class="form-label" style="font-weight:700;margin-bottom:6px">${grupo}</div>`;
        grupos[grupo].forEach(v => {
          const sign = v.preco_extra > 0 ? '+' : (v.preco_extra < 0 ? '' : '');
          const label = document.createElement('label');
          label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px;background:var(--bg-alt);border-radius:8px;cursor:pointer;margin-bottom:4px;font-size:14px';
          label.innerHTML = `<input type="radio" name="var_${grupo.replace(/\s/g,'_')}" value="${v.id}" data-extra="${v.preco_extra}"> <span>${v.nome}</span> ${v.preco_extra != 0 ? `<span style="margin-left:auto;color:var(--primary)">${sign}${fmtMoeda(v.preco_extra)}</span>` : ''}`;
          label.querySelector('input').addEventListener('change', function() {
            _modalVariacoes[grupo] = { id: parseInt(this.value), extra: parseFloat(this.dataset.extra) };
            atualizarPrecoModal();
          });
          div.appendChild(label);
        });
        c.appendChild(div);
      });
    }).catch(() => { $('variacoesContainer').innerHTML = ''; });
}

function fecharModal() {
  $('modalProduto').style.display = 'none';
  document.body.style.overflow = '';
}

function atualizarPrecoModal() {
  const extra = Object.values(_modalVariacoes).reduce((s,v) => s + v.extra, 0);
  const preco = (_modalPrecoBase + extra) * _modalQtd;
  if ($('modalPrecoDisplay')) $('modalPrecoDisplay').textContent = fmtMoeda(_modalPrecoBase + extra);
  if ($('modalBtnPreco')) $('modalBtnPreco').textContent = fmtMoeda(preco);
}

function ajustarQtdModal(d) {
  _modalQtd = Math.max(1, _modalQtd + d);
  $('modalQtd').textContent = _modalQtd;
  atualizarPrecoModal();
}

function confirmarAdicaoModal() {
  if (!_modalId) return;
  const extra = Object.values(_modalVariacoes).reduce((s,v) => s + v.extra, 0);
  const preco = _modalPrecoBase + extra;
  const varIds = Object.values(_modalVariacoes).map(v => v.id);
  const varNomes = Object.values(_modalVariacoes).map(v => '');
  const nome = $('modalNome').textContent;
  const obs  = $('modalObs').value;
  addItem(_modalId, nome, preco, _modalQtd, obs, varIds, varNomes);
  fecharModal();
  mostrarToast(`✅ ${nome} adicionado!`);
}

// ── Toast ────────────────────────────────────────────────────
function mostrarToast(msg, dur=2500) {
  let t = $('toast');
  if (!t) { t = document.createElement('div'); t.id='toast'; t.className='toast-notif'; document.body.appendChild(t); }
  t.textContent = msg; t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), dur);
}

// ── Renderizar carrinho (carrinho.php) ───────────────────────
function renderCarrinho() {
  const wrap = $('cartItems'), empty = $('emptyCart'), content = $('cartContent');
  if (!wrap) return;
  const c = getCarrinho();
  if (!c.length) {
    if (empty) empty.style.display = 'block';
    if (content) content.style.display = 'none';
    return;
  }
  if (empty) empty.style.display = 'none';
  if (content) content.style.display = 'block';
  wrap.innerHTML = c.map(item => `
    <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="flex:1">
        <div style="font-weight:600">${item.nome}</div>
        ${item.customNomes && item.customNomes.length ? `<div style="font-size:12px;color:var(--muted)">${item.customNomes.join(', ')}</div>` : ''}
        ${item.obs ? `<div style="font-size:12px;color:var(--muted);font-style:italic">"${item.obs}"</div>` : ''}
        <div style="color:var(--primary);font-weight:700;font-size:15px;margin-top:2px">${fmtMoeda(item.preco * item.quantidade)}</div>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <button class="btn btn-outline" style="padding:4px 10px;font-size:16px" onclick="changeQtd('${item._key}',-1)">−</button>
        <span style="font-weight:700;min-width:20px;text-align:center">${item.quantidade}</span>
        <button class="btn btn-outline" style="padding:4px 10px;font-size:16px" onclick="changeQtd('${item._key}',1)">+</button>
        <button class="btn btn-danger" style="padding:4px 8px;font-size:13px" onclick="removeItem('${item._key}')">🗑</button>
      </div>
    </div>`).join('');
  atualizarResumo();
}

function atualizarResumo() {
  const t = calcTotais();
  const rs = $('resumoSubtotal'), re = $('resumoEntrega'), rp = $('resumoPromo'), rc = $('resumoCupom'), rt = $('resumoTotal');
  const le = $('linhaEntrega'), lp = $('linhaPromo'), lc = $('linhaCupom');
  if (rs) rs.textContent = fmtMoeda(t.sub);
  if (re) re.textContent = fmtMoeda(t.taxa);
  if (rp) rp.textContent = '−' + fmtMoeda(t.descontoPromo);
  if (rc) rc.textContent = '−' + fmtMoeda(t.descontoCupom);
  if (rt) rt.textContent = fmtMoeda(t.total);
  if (le) le.style.display = t.taxa > 0 ? 'flex' : 'none';
  if (lp) lp.style.display = t.descontoPromo > 0 ? 'flex' : 'none';
  if (lc) lc.style.display = t.descontoCupom > 0 ? 'flex' : 'none';
  // Pedido mínimo
  const minEl = $('minPedidoAlerta');
  const min = typeof LOJA_CONFIG !== 'undefined' ? LOJA_CONFIG.minPedido : 0;
  if (minEl && min > 0 && t.sub < min) {
    minEl.style.display = 'block';
    minEl.textContent = `⚠️ Pedido mínimo de ${fmtMoeda(min)}. Faltam ${fmtMoeda(min - t.sub)}.`;
  } else if (minEl) minEl.style.display = 'none';
  // Fidelidade
  if (typeof LOJA_CONFIG !== 'undefined' && LOJA_CONFIG.fidelidadeAtivo) {
    const fid = $('fidelidadeMsg');
    if (fid) {
      const meta = LOJA_CONFIG.fidelidadePedidos;
      const faltam = meta - (_totalPedidos % meta);
      if (faltam === meta) {
        fid.style.display = 'block';
        fid.textContent = `🎉 Você ganhou ${LOJA_CONFIG.fidelidadeDesconto}% de desconto neste pedido!`;
      } else {
        fid.style.display = 'block';
        fid.textContent = `⭐ Faltam ${faltam} pedido(s) para ganhar ${LOJA_CONFIG.fidelidadeDesconto}% de desconto!`;
      }
    }
  }
}

// ── Cupom ────────────────────────────────────────────────────
function aplicarCupom() {
  const code = ($('cupomInput')?.value || '').toUpperCase().trim();
  if (!code) return;
  const t = calcTotais();
  fetch('api/aplicar_cupom.php', { method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ codigo: code, subtotal: t.sub }) })
    .then(r => r.json()).then(d => {
      const fb = $('cupomFeedback');
      if (d.ok) {
        _descontoCupom = d.desconto; _cupomCode = code;
        if (fb) fb.innerHTML = `<span style="color:green">✅ Cupom aplicado! Desconto: ${fmtMoeda(d.desconto)}</span>`;
        atualizarResumo();
      } else {
        _descontoCupom = 0; _cupomCode = '';
        if (fb) fb.innerHTML = `<span style="color:var(--danger)">❌ ${d.erro}</span>`;
        atualizarResumo();
      }
    });
}

// ── Finalizar pedido ─────────────────────────────────────────
function finalizarPedido() {
  const c = getCarrinho();
  if (!c.length) { alert('Seu carrinho está vazio!'); return; }
  const nome  = ($('nomeCliente')?.value || '').trim();
  const wa    = ($('whatsappCliente')?.value || '').replace(/\D/g,'');
  const end   = ($('enderecoEntrega')?.value || '').trim();
  if (!nome) { alert('Por favor, informe seu nome.'); $('nomeCliente')?.focus(); return; }
  if (!wa || wa.length < 10) { alert('Por favor, informe seu WhatsApp com DDD.'); $('whatsappCliente')?.focus(); return; }
  if (_tipoEntrega === 'entrega' && !end) { alert('Por favor, informe seu endereço de entrega.'); $('enderecoEntrega')?.focus(); return; }
  const min = typeof LOJA_CONFIG !== 'undefined' ? LOJA_CONFIG.minPedido : 0;
  const t = calcTotais();
  if (min > 0 && t.sub < min) { alert(`Pedido mínimo de ${fmtMoeda(min)}.`); return; }

  const btn = $('btnFinalizar');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Enviando...'; }

  fetch('api/criar_pedido.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      nome, whatsapp: wa, itens: c.map(i => ({ produto_id: i.produto_id, quantidade: i.quantidade, obs: i.obs, variacoes: i.variacoes })),
      endereco: end, tipo_entrega: _tipoEntrega, pagamento: _pagamento, cupom: _cupomCode, bairro: _bairro,
      observacoes: $('observacoes')?.value || ''
    })
  })
  .then(r => r.json()).then(d => {
    if (!d.ok) {
      alert('Erro: ' + d.erro);
      if (btn) { btn.disabled = false; btn.textContent = _pagamento === 'mercadopago' ? '💳 Ir para pagamento' : '📲 Enviar pedido via WhatsApp'; }
      return;
    }
    // Salvar dados do cliente para reutilizar
    localStorage.setItem('clienteNome', nome);
    localStorage.setItem('clienteWa', $('whatsappCliente')?.value || '');
    localStorage.setItem('clienteEnd', end);
    localStorage.setItem('totalPedidos', String(_totalPedidos + 1));
    localStorage.setItem('ultimoPedido', JSON.stringify(c));

    // ── Mercado Pago: criar preferência e redirecionar ────
    if (_pagamento === 'mercadopago') {
      fetch('api/mercadopago.php?action=criar', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ pedido_id: d.pedido_id, total: d.total, nome, numero: d.numero })
      })
      .then(r => r.json()).then(mp => {
        if (mp.ok && mp.init_point) {
          localStorage.removeItem('carrinho');
          window.location.href = mp.init_point;
        } else {
          alert('Erro ao iniciar pagamento: ' + (mp.erro || 'Tente outro método.'));
          if (btn) { btn.disabled = false; btn.textContent = '💳 Ir para pagamento'; }
        }
      }).catch(() => {
        alert('Erro de conexão com Mercado Pago. Tente outro método.');
        if (btn) { btn.disabled = false; btn.textContent = '💳 Ir para pagamento'; }
      });
      return;
    }
    // Montar mensagem WhatsApp
    let msg = `🍔 *Pedido #${d.numero}*\n👤 *${nome}*\n\n`;
    c.forEach(i => {
      msg += `• ${i.quantidade}x *${i.nome}* — ${fmtMoeda(i.preco * i.quantidade)}\n`;
      if (i.customNomes && i.customNomes.length) msg += `  _(${i.customNomes.join(', ')})_\n`;
      if (i.obs) msg += `  _"${i.obs}"_\n`;
    });
    msg += `\n💰 *Subtotal:* ${fmtMoeda(d.total - d.taxa_entrega + d.desconto_promo + d.desconto_cupom)}\n`;
    if (d.desconto_promo > 0) msg += `⚡ *Desconto promoção:* −${fmtMoeda(d.desconto_promo)}\n`;
    if (d.desconto_cupom > 0) msg += `🎟 *Cupom:* −${fmtMoeda(d.desconto_cupom)}\n`;
    if (d.taxa_entrega > 0) msg += `🛵 *Taxa de entrega:* ${fmtMoeda(d.taxa_entrega)}\n`;
    msg += `💳 *Total:* ${fmtMoeda(d.total)}\n`;
    msg += `\n📦 *Entrega:* ${_tipoEntrega === 'retirada' ? 'Retirada no local' : 'Entrega — ' + end}\n`;
    msg += `💳 *Pagamento:* ${{ dinheiro:'Dinheiro', pix:'PIX', cartao:'Cartão' }[_pagamento]}\n`;
    if (d.fidelidade_msg) msg += `\n${d.fidelidade_msg}\n`;
    msg += `\n🔍 Acompanhe: ${location.origin}/status.php?numero=${d.numero}`;
    // Salvar pedido para status
    localStorage.setItem('ultimoNumeroPedido', d.numero);
    // Limpar carrinho e redirecionar
    localStorage.removeItem('carrinho');
    const waLink = `https://wa.me/${d.whatsapp_loja}?text=${encodeURIComponent(msg)}`;
    location.href = `pedido-confirmado.php?numero=${d.numero}&pix=${encodeURIComponent(d.pix_chave||'')}&total=${d.total}&wa=${encodeURIComponent(waLink)}`;
  })
  .catch(e => {
    alert('Erro de conexão. Tente novamente.');
    if (btn) { btn.disabled = false; btn.textContent = '📲 Enviar pedido via WhatsApp'; }
  });
}

// ── Repetir último pedido ────────────────────────────────────
function repetirUltimoPedido() {
  const lp = localStorage.getItem('ultimoPedido');
  if (!lp) return;
  const itens = JSON.parse(lp);
  localStorage.setItem('carrinho', JSON.stringify(itens));
  renderCarrinho(); atualizarBotaoCarrinho();
  mostrarToast('🔄 Último pedido carregado!');
}

// ── Áudio do pedido ──────────────────────────────────────────
function lerPedidoEmVoz() {
  if (!window.speechSynthesis) { alert('Áudio não suportado neste navegador.'); return; }
  const c = getCarrinho();
  let texto = 'Seu carrinho: ';
  c.forEach(i => texto += `${i.quantidade} ${i.nome}. `);
  const t = calcTotais();
  texto += `Total: R$ ${t.total.toFixed(2).replace('.', ' reais e ')}.`;
  const utt = new SpeechSynthesisUtterance(texto);
  utt.lang = 'pt-BR'; utt.rate = 0.9;
  window.speechSynthesis.cancel();
  window.speechSynthesis.speak(utt);
}

// ── Compartilhar ─────────────────────────────────────────────
function compartilharPedido() {
  const c = getCarrinho();
  let txt = '🍔 Meu pedido:\n';
  c.forEach(i => txt += `• ${i.quantidade}x ${i.nome} — ${fmtMoeda(i.preco * i.quantidade)}\n`);
  txt += `\nTotal: ${fmtMoeda(calcTotais().total)}\n${location.href}`;
  if (navigator.share) { navigator.share({ title: 'Meu pedido', text: txt }); }
  else { navigator.clipboard.writeText(txt).then(() => mostrarToast('🔗 Link copiado!')); }
}

// ── Timer promoção ───────────────────────────────────────────
function iniciarTimerPromo() {
  const el = $('promoTimer');
  if (!el) return;
  const fim = parseInt(el.dataset.fim) * 1000;
  function tick() {
    const r = fim - Date.now();
    if (r <= 0) { el.textContent = 'Encerrada'; return; }
    const h = Math.floor(r/3600000), m = Math.floor((r%3600000)/60000), s = Math.floor((r%60000)/1000);
    el.textContent = `${h}h ${m}m ${s}s`;
    setTimeout(tick, 1000);
  }
  tick();
}

// ── Filtros de categoria + busca ─────────────────────────────
function initFiltros() {
  const nav = $('categoriasNav');
  if (nav) {
    nav.addEventListener('click', e => {
      const btn = e.target.closest('.cat-btn'); if (!btn) return;
      qsa('.cat-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const cat = btn.dataset.cat;
      qsa('.categoria-section').forEach(s => {
        s.style.display = cat === 'todos' || s.dataset.cat === cat ? 'block' : 'none';
      });
    });
  }
  const si = $('searchInput');
  if (si) {
    si.addEventListener('input', e => {
      const q = e.target.value.toLowerCase().trim();
      qsa('.produto-card').forEach(card => {
        const nome = card.querySelector('.produto-nome')?.textContent.toLowerCase() || '';
        const desc = card.querySelector('.produto-desc')?.textContent.toLowerCase() || '';
        card.style.display = (!q || nome.includes(q) || desc.includes(q)) ? '' : 'none';
      });
    });
  }
}

// ── Boas-vindas cliente recorrente ───────────────────────────
function initBoasVindas() {
  const nome = localStorage.getItem('clienteNome');
  const el = $('boasVindasToast');
  if (!el) return;
  if (nome) {
    el.textContent = `👋 Bem-vindo de volta, ${nome}!`;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 4000);
  }
}

// ── Preencher dados do cliente ───────────────────────────────
function initDadosCliente() {
  const n = $('nomeCliente'), w = $('whatsappCliente'), e = $('enderecoEntrega');
  if (n && localStorage.getItem('clienteNome')) n.value = localStorage.getItem('clienteNome');
  if (w && localStorage.getItem('clienteWa')) w.value = localStorage.getItem('clienteWa');
  if (e && localStorage.getItem('clienteEnd')) e.value = localStorage.getItem('clienteEnd');
}

// ── Repetir pedido na página do carrinho ─────────────────────
function initRepetirPedido() {
  const ul = localStorage.getItem('ultimoPedido');
  const area = $('repetirPedidoArea');
  if (area && ul) area.style.display = 'block';
}

// ── DOMContentLoaded ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  _totalPedidos = parseInt(localStorage.getItem('totalPedidos') || '0');
  atualizarBotaoCarrinho();
  initFiltros();
  iniciarTimerPromo();
  initBoasVindas();
  initDadosCliente();
  initRepetirPedido();
  renderCarrinho();
  // Compartilhar cardápio
  const shareBtn = $('shareBtn');
  if (shareBtn) {
    shareBtn.addEventListener('click', () => {
      const url = location.href;
      if (navigator.share) { navigator.share({ title: document.title, url }); }
      else { navigator.clipboard.writeText(url).then(() => mostrarToast('🔗 Link copiado!')); }
    });
  }
});

// ── Sugestões (ex: quem pediu X pediu Y) ────────────────────
async function carregarSugestoes(produtoIds) {
  if (!produtoIds.length) return;
  try {
    const r = await fetch('api/sugestoes.php?ids=' + produtoIds.join(','));
    const d = await r.json();
    const el = $('sugestoesSection');
    if (el && d.length) {
      el.innerHTML = '<div class="section-title">💡 Quem pediu isso também quis</div><div class="produtos-grid">' +
        d.map(p => `<div class="produto-card"><div class="produto-info"><div class="produto-nome">${p.nome}</div><div class="produto-preco">${fmtMoeda(p.preco)}</div><button class="btn btn-add" onclick="abrirProduto(${p.id},'${p.nome.replace(/'/g,"\\'")}',${p.preco},'','')">+ Adicionar</button></div></div>`).join('') +
        '</div>';
      el.style.display = 'block';
    }
  } catch {}
}

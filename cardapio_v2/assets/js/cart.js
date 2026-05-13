/* ============================================================
   Cardápio Digital — cart.js completo
   ============================================================ */

const fmtMoeda = v => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',');
const qs  = s => document.querySelector(s);
const qsa = s => document.querySelectorAll(s);
const $   = id => document.getElementById(id);

// ── Dark mode (init antes do DOM) ───────────────────────────
(function initDark() {
  const root = document.documentElement;
  const saved = localStorage.getItem('darkMode');
  if (saved === '1') root.setAttribute('data-theme','dark');
  else if (saved === '0') root.setAttribute('data-theme','light');
})();

// ── Carrinho (localStorage) ──────────────────────────────────
function getCarrinho() {
  try { return JSON.parse(localStorage.getItem('carrinho') || '[]'); } catch { return []; }
}
function salvarCarrinho(c) { localStorage.setItem('carrinho', JSON.stringify(c)); }

function addItem(produto_id, nome, preco, quantidade, obs, variacoes, customNomes) {
  const c   = getCarrinho();
  const key = produto_id + '_' + (variacoes || []).join('_');
  const idx = c.findIndex(i => i._key === key);
  if (idx >= 0) c[idx].quantidade += quantidade;
  else c.push({ _key: key, produto_id, nome, preco, quantidade, obs: obs || '', variacoes: variacoes || [], customNomes: customNomes || [] });
  salvarCarrinho(c);
  atualizarBotaoCarrinho();
}

function removeItem(key) {
  salvarCarrinho(getCarrinho().filter(i => i._key !== key));
  atualizarBotaoCarrinho();
  if ($('cartItems')) renderCarrinho();
}

function changeQtd(key, delta) {
  const c = getCarrinho();
  const idx = c.findIndex(i => i._key === key);
  if (idx < 0) return;
  c[idx].quantidade = Math.max(1, c[idx].quantidade + delta);
  salvarCarrinho(c);
  atualizarBotaoCarrinho();
  if ($('cartItems')) renderCarrinho();
}

function calcTotais() {
  const c   = getCarrinho();
  const cfg = typeof LOJA_CONFIG !== 'undefined' ? LOJA_CONFIG : (typeof LOJA !== 'undefined' ? LOJA : {});
  const sub = c.reduce((s, i) => s + Number(i.preco) * Number(i.quantidade), 0);
  let taxa  = 0;
  if (_tipoEntrega === 'entrega') taxa = _taxaZona !== null ? Number(_taxaZona) : Number(cfg.taxaEntrega || 0);
  const descontoPromo = cfg.promoAtiva ? Math.round(sub * Number(cfg.promoDesconto || 0) / 100 * 100) / 100 : 0;
  const descontoCupom = Number(_descontoCupom || 0);
  const total = Math.max(0, sub - descontoPromo - descontoCupom + taxa);
  return { sub, taxa, descontoPromo, descontoCupom, total };
}

function atualizarBotaoCarrinho() {
  const c   = getCarrinho();
  const qtd = c.reduce((s, i) => s + Number(i.quantidade), 0);
  const fab = $('cartFab');
  if (!fab) return;
  if (!qtd) { fab.style.display = 'none'; return; }
  fab.style.display = 'flex';
  const cc = $('cartCount'); if (cc) cc.textContent = qtd;
  const ct = $('cartTotal'); if (ct) ct.textContent = fmtMoeda(c.reduce((s,i) => s + i.preco * i.quantidade, 0));
}

// ── Estado ──────────────────────────────────────────────────
let _tipoEntrega   = 'retirada';
let _pagamento     = 'dinheiro';
let _taxaZona      = null;
let _bairro        = '';
let _descontoCupom = 0;
let _cupomCode     = '';

function setTipoEntrega(tipo) {
  _tipoEntrega = tipo;
  qsa('.tipo-btn').forEach(b => b.className = b.dataset.tipo === tipo ? 'btn btn-primary tipo-btn' : 'btn btn-outline tipo-btn');
  const ce = $('camposEntrega'); if (ce) ce.style.display = tipo === 'entrega' ? 'block' : 'none';
  const le = $('linhaEntrega');  if (le) le.style.display = tipo === 'entrega' ? 'flex'  : 'none';
  atualizarResumo();
}

function setPagamento(pag) {
  _pagamento = pag;
  qsa('.pag-btn').forEach(b => {
    const isMP = b.dataset.pag === 'mercadopago';
    if (b.dataset.pag === pag) {
      b.className = 'btn btn-primary pag-btn active';
      if (isMP) b.style.cssText = 'background:#0077b6;color:#fff;border:none;width:100%;padding:12px;font-size:15px';
    } else {
      b.className = isMP ? 'btn pag-btn' : 'btn btn-outline pag-btn';
      if (isMP) b.style.cssText = 'background:#009ee3;color:#fff;border:none;width:100%;padding:12px;font-size:15px';
    }
  });
  const pi = $('pixInfo'); if (pi) pi.style.display = pag === 'pix' ? 'block' : 'none';
  const mi = $('mpInfo');  if (mi) mi.style.display = pag === 'mercadopago' ? 'block' : 'none';
  const btn = $('btnFinalizar');
  if (btn) btn.textContent = pag === 'mercadopago' ? '💳 Ir para pagamento' : '📲 Enviar pedido via WhatsApp';
}

function selecionarZona(val) {
  if (!val) { _taxaZona = null; _bairro = ''; atualizarResumo(); return; }
  const sel = $('bairroSelect'); if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  _taxaZona = parseFloat(opt.dataset.taxa || 0);
  _bairro   = val.startsWith('outro_') ? '' : val;
  atualizarResumo();
}

// ── Resumo ──────────────────────────────────────────────────
function atualizarResumo() {
  const { sub, taxa, descontoPromo, descontoCupom, total } = calcTotais();
  const cfg = typeof LOJA_CONFIG !== 'undefined' ? LOJA_CONFIG : (typeof LOJA !== 'undefined' ? LOJA : {});

  const rs = $('resumoSubtotal'); if (rs) rs.textContent = fmtMoeda(sub);
  const re = $('resumoEntrega');  if (re) re.textContent = fmtMoeda(taxa);
  const rt = $('resumoTotal');    if (rt) rt.textContent = fmtMoeda(total);

  const lp = $('linhaPromo'), rp = $('resumoPromo');
  if (lp) lp.style.display = descontoPromo > 0 ? 'flex' : 'none';
  if (rp && descontoPromo > 0) rp.textContent = '−' + fmtMoeda(descontoPromo);

  const lc = $('linhaCupom'), rc = $('resumoCupom');
  if (lc) lc.style.display = descontoCupom > 0 ? 'flex' : 'none';
  if (rc && descontoCupom > 0) rc.textContent = '−' + fmtMoeda(descontoCupom);

  const minPedido = Number(cfg.minPedido || 0);
  const al = $('minPedidoAlerta');
  if (al) {
    if (minPedido > 0 && sub < minPedido) {
      al.style.display = 'block';
      al.textContent   = `⚠️ Pedido mínimo: ${fmtMoeda(minPedido)} (faltam ${fmtMoeda(minPedido - sub)})`;
    } else al.style.display = 'none';
  }

  const totalPedidos = parseInt(localStorage.getItem('totalPedidos') || '0');
  const fidEl = $('fidelidadeMsg');
  if (fidEl) {
    if (cfg.fidelidadeAtivo) {
      const faltam = cfg.fidelidadePedidos - (totalPedidos % cfg.fidelidadePedidos);
      fidEl.style.display = 'block';
      fidEl.textContent = faltam === cfg.fidelidadePedidos
        ? `🎉 Você ganhou ${cfg.fidelidadeDesconto}% de desconto por fidelidade!`
        : `⭐ Faltam ${faltam} pedido${faltam > 1 ? 's' : ''} para ganhar ${cfg.fidelidadeDesconto}% de desconto!`;
    } else fidEl.style.display = 'none';
  }
}

// ── Cupom ────────────────────────────────────────────────────
async function aplicarCupom() {
  const inp  = $('cupomInput'); if (!inp) return;
  const code = inp.value.trim().toUpperCase();
  const fb   = $('cupomFeedback');
  if (!code) { if (fb) { fb.textContent = 'Digite um código.'; fb.style.color = 'var(--danger)'; } return; }
  if (fb) { fb.textContent = 'Verificando...'; fb.style.color = 'var(--muted)'; }
  const { sub } = calcTotais();
  try {
    const r = await fetch('api/aplicar_cupom.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: code, subtotal: sub })
    });
    const d = await r.json();
    if (d.ok) {
      _descontoCupom = Number(d.desconto); _cupomCode = code;
      if (fb) { fb.textContent = `✅ Cupom aplicado! −${fmtMoeda(_descontoCupom)}`; fb.style.color = 'var(--success)'; }
    } else {
      _descontoCupom = 0; _cupomCode = '';
      if (fb) { fb.textContent = d.erro || 'Cupom inválido.'; fb.style.color = 'var(--danger)'; }
    }
    atualizarResumo();
  } catch { if (fb) { fb.textContent = 'Erro ao verificar cupom.'; fb.style.color = 'var(--danger)'; } }
}

// ── Renderizar carrinho ──────────────────────────────────────
function renderCarrinho() {
  const wrap = $('cartItems'), empty = $('emptyCart'), content = $('cartContent');
  if (!wrap) return;
  const c = getCarrinho();
  if (!c.length) {
    if (empty)   empty.style.display   = 'block';
    if (content) content.style.display = 'none';
    return;
  }
  if (empty)   empty.style.display   = 'none';
  if (content) content.style.display = 'block';

  wrap.innerHTML = c.map(item => `
    <div style="display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid var(--border)">
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:15px">${item.nome}</div>
        ${item.customNomes && item.customNomes.length ? `<div style="font-size:12px;color:var(--muted)">${item.customNomes.join(', ')}</div>` : ''}
        ${item.obs ? `<div style="font-size:12px;color:var(--muted);font-style:italic">"${item.obs}"</div>` : ''}
        <div style="color:var(--primary,#e85d04);font-weight:700;font-size:15px;margin-top:2px">${fmtMoeda(item.preco * item.quantidade)}</div>
      </div>
      <div style="display:flex;align-items:center;gap:4px;flex-shrink:0">
        <button style="padding:4px 10px;font-size:16px;background:var(--surface2,#eee);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text)" onclick="changeQtd('${item._key}',-1)">−</button>
        <span style="font-weight:700;min-width:22px;text-align:center">${item.quantidade}</span>
        <button style="padding:4px 10px;font-size:16px;background:var(--surface2,#eee);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text)" onclick="changeQtd('${item._key}',1)">+</button>
        <button style="padding:4px 8px;font-size:13px;background:var(--danger,#ef4444);color:#fff;border:none;border-radius:8px;cursor:pointer" onclick="removeItem('${item._key}')">🗑</button>
      </div>
    </div>
  `).join('');

  atualizarResumo();
}

// ── Finalizar pedido ─────────────────────────────────────────
async function finalizarPedido() {
  const cfg  = typeof LOJA_CONFIG !== 'undefined' ? LOJA_CONFIG : (typeof LOJA !== 'undefined' ? LOJA : {});
  const nome = ($('nomeCliente')?.value || '').trim();
  const wa   = ($('whatsappCliente')?.value || '').replace(/\D/g,'');
  const end  = ($('enderecoEntrega')?.value || '').trim();
  const obs  = ($('observacoes')?.value || '').trim();

  if (!nome)          { mostrarToast('⚠️ Informe seu nome!', 3000); $('nomeCliente')?.focus(); return; }
  if (wa.length < 10) { mostrarToast('⚠️ WhatsApp inválido!', 3000); $('whatsappCliente')?.focus(); return; }
  if (_tipoEntrega === 'entrega' && !end) { mostrarToast('⚠️ Informe o endereço!', 3000); $('enderecoEntrega')?.focus(); return; }

  const { sub, total } = calcTotais();
  const minPedido = Number(cfg.minPedido || 0);
  if (minPedido > 0 && sub < minPedido) { mostrarToast(`⚠️ Pedido mínimo: ${fmtMoeda(minPedido)}`, 3000); return; }

  const c = getCarrinho();
  if (!c.length) { mostrarToast('⚠️ Carrinho vazio!', 3000); return; }

  if (cfg.aberta === false || cfg.aberto === false) {
    mostrarToast('🔴 ' + (cfg.offlineMsg || cfg.whatsapp_offline_msg || 'Loja fechada no momento.'), 4000);
    return;
  }

  const btn = $('btnFinalizar');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Enviando...'; }

  try {
    const r = await fetch('api/criar_pedido.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nome, whatsapp: wa, itens: c, endereco: end, observacoes: obs, tipo_entrega: _tipoEntrega, pagamento: _pagamento, cupom: _cupomCode, bairro: _bairro })
    });

    let d;
    try { d = await r.json(); }
    catch (e) { const t = await r.text().catch(()=>''); console.error('Resposta inválida:', t); mostrarToast('❌ Erro no servidor. Veja F12 > Console.', 5000); if (btn) { btn.disabled = false; btn.textContent = '📲 Enviar pedido via WhatsApp'; } return; }

    if (!d.ok) {
      mostrarToast('❌ ' + (d.erro || 'Erro ao criar pedido.'), 4000);
      if (btn) { btn.disabled = false; btn.textContent = _pagamento === 'mercadopago' ? '💳 Ir para pagamento' : '📲 Enviar pedido via WhatsApp'; }
      return;
    }

    localStorage.setItem('clienteNome', nome);
    localStorage.setItem('clienteWa', wa);
    localStorage.setItem('clienteEnd', end);
    localStorage.setItem('totalPedidos', String(parseInt(localStorage.getItem('totalPedidos') || '0') + 1));
    localStorage.setItem('ultimoPedido', JSON.stringify(c));

    // Mercado Pago
    if (_pagamento === 'mercadopago') {
      try {
        const mr = await fetch('api/mercadopago.php?action=criar', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ pedido_id: d.pedido_id, total: d.total, nome, numero: d.numero }) });
        const mp = await mr.json();
        if (mp.ok && mp.init_point) { localStorage.removeItem('carrinho'); window.location.href = mp.init_point; return; }
        mostrarToast('❌ ' + (mp.erro || 'Erro MP.'), 4000);
        if (btn) { btn.disabled = false; btn.textContent = '💳 Ir para pagamento'; }
      } catch { mostrarToast('❌ Erro de conexão com Mercado Pago.', 4000); if (btn) { btn.disabled = false; btn.textContent = '💳 Ir para pagamento'; } }
      return;
    }

    // Montar mensagem WhatsApp
    const pagNomes = { dinheiro: '💵 Dinheiro', pix: '💸 PIX', cartao: '💳 Cartão' };
    let msg = `🛒 *NOVO PEDIDO #${d.numero}*\n👤 *Cliente:* ${nome}\n📱 *WhatsApp:* ${wa}\n`;
    msg += _tipoEntrega === 'entrega' ? `🛵 *Entrega em:* ${end}\n` : `🏃 *Retirada no local*\n`;
    if (_bairro) msg += `📍 *Bairro:* ${_bairro}\n`;
    msg += `\n📋 *Itens:*\n`;
    c.forEach(i => {
      msg += `• ${i.quantidade}x ${i.nome} — ${fmtMoeda(i.preco * i.quantidade)}`;
      if (i.customNomes && i.customNomes.length) msg += ` (${i.customNomes.join(', ')})`;
      if (i.obs) msg += ` [${i.obs}]`;
      msg += '\n';
    });
    msg += `\n💰 *Subtotal:* ${fmtMoeda(d.subtotal)}`;
    if (d.desconto_promo > 0)  msg += `\n⚡ *Promoção:* −${fmtMoeda(d.desconto_promo)}`;
    if (d.desconto_cupom > 0)  msg += `\n🎟 *Cupom:* −${fmtMoeda(d.desconto_cupom)}`;
    if (d.taxa_entrega > 0)    msg += `\n🛵 *Frete:* ${fmtMoeda(d.taxa_entrega)}`;
    msg += `\n💳 *Total: ${fmtMoeda(d.total)}*\n💳 *Pagamento:* ${pagNomes[_pagamento] || _pagamento}`;
    if (_pagamento === 'pix' && d.pix_chave) msg += `\n📲 *Chave PIX:* ${d.pix_chave}`;
    if (obs) msg += `\n📝 *Obs:* ${obs}`;
    if (d.fidelidade_msg) msg += `\n\n⭐ ${d.fidelidade_msg}`;

    localStorage.removeItem('carrinho');
    const waLoja = d.whatsapp_loja || '';
    window.location.href = `https://wa.me/${waLoja}?text=${encodeURIComponent(msg)}`;

  } catch (err) {
    console.error(err);
    mostrarToast('❌ Erro de conexão. Tente novamente.', 4000);
    if (btn) { btn.disabled = false; btn.textContent = '📲 Enviar pedido via WhatsApp'; }
  }
}

// ── Repetir pedido ───────────────────────────────────────────
function repetirUltimoPedido() {
  try {
    const itens = JSON.parse(localStorage.getItem('ultimoPedido') || '[]');
    if (!itens.length) return;
    salvarCarrinho(itens); renderCarrinho(); mostrarToast('🔄 Último pedido restaurado!');
  } catch { mostrarToast('❌ Erro ao restaurar pedido.', 3000); }
}

// ── Modal produto ────────────────────────────────────────────
let _mid = null, _mpreco = 0, _mqtd = 1, _mvar = {};

function abrirProduto(id, nome, preco, desc, img) {
  _mid = id; _mpreco = parseFloat(preco); _mqtd = 1; _mvar = {};
  const mn = $('modalNome'); if (mn) mn.textContent = nome;
  const md = $('modalDesc'); if (md) md.textContent = desc || '';
  const mq = $('modalQtd');  if (mq) mq.textContent = '1';
  const mo = $('modalObs');  if (mo) mo.value = '';
  const imgEl = $('modalImg') || $('modalImgSrc');
  if (imgEl) { if (img) { imgEl.src = img; imgEl.style.display = 'block'; } else imgEl.style.display = 'none'; }
  const vw = $('variacoesWrap') || $('variacoesContainer');
  if (vw) vw.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:8px 0">Carregando opções...</p>';
  atuPreco();
  const mo2 = $('modalProduto'); if (mo2) mo2.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  fetch('api/variacoes_produto.php?produto_id=' + id)
    .then(r => r.json())
    .then(grupos => {
      const wrap = $('variacoesWrap') || $('variacoesContainer');
      if (!wrap) return;
      const keys = Object.keys(grupos || {});
      if (!keys.length) { wrap.innerHTML = ''; return; }
      wrap.innerHTML = keys.map(g => `
        <div style="margin-bottom:14px">
          <div style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">${g}</div>
          ${grupos[g].map(v => `
            <label style="display:flex;align-items:center;gap:10px;padding:10px;border:2px solid var(--border);border-radius:10px;cursor:pointer;margin-bottom:6px;font-size:14px;transition:border-color .15s">
              <input type="radio" name="v_${g.replace(/\s/g,'_')}" value="${v.id}" data-extra="${v.preco_extra}" style="width:18px;height:18px;accent-color:var(--primary,#e85d04)">
              <span style="flex:1">${v.nome}</span>
              ${v.preco_extra != 0 ? `<span style="color:var(--primary,#e85d04);font-weight:700">${v.preco_extra > 0 ? '+' : ''}${fmtMoeda(v.preco_extra)}</span>` : ''}
            </label>`).join('')}
        </div>`).join('');
      wrap.querySelectorAll('input[type=radio]').forEach(inp => {
        inp.addEventListener('change', function() {
          const g = this.name.replace('v_','').replace(/_/g,' ');
          _mvar[g] = { id: +this.value, extra: +this.dataset.extra };
          atuPreco();
          this.closest('label').parentElement.querySelectorAll('label').forEach(l => l.style.borderColor='');
          this.closest('label').style.borderColor = 'var(--primary,#e85d04)';
        });
      });
    })
    .catch(() => { const vw2=$('variacoesWrap')||$('variacoesContainer'); if(vw2) vw2.innerHTML=''; });
}

function fecharModal() {
  const m = $('modalProduto'); if (m) m.style.display = 'none';
  document.body.style.overflow = '';
}

function atuPreco() {
  const extra = Object.values(_mvar).reduce((s,v) => s + Number(v.extra||0), 0);
  const unit  = _mpreco + extra;
  const mp = $('modalPreco') || $('modalPrecoDisplay'); if (mp) mp.textContent = fmtMoeda(unit);
  const mb = $('modalBtnPreco'); if (mb) mb.textContent = fmtMoeda(unit * _mqtd);
}
const atualizarPrecoModal = atuPreco;

function adjQtd(d) { _mqtd = Math.max(1, _mqtd + d); const mq=$('modalQtd'); if(mq) mq.textContent=_mqtd; atuPreco(); }
const ajustarQtdModal = adjQtd;

function confirmarModal() {
  if (!_mid) return;
  const extra    = Object.values(_mvar).reduce((s,v) => s + Number(v.extra||0), 0);
  const varIds   = Object.values(_mvar).map(v => v.id);
  const varNomes = Object.entries(_mvar).map(([g]) => g);
  const nome = ($('modalNome')||{}).textContent || '';
  const obs  = ($('modalObs')||{}).value || '';
  addItem(_mid, nome, _mpreco + extra, _mqtd, obs, varIds, varNomes);
  fecharModal();
  mostrarToast('✅ ' + nome + ' adicionado!');
}
const confirmarAdicaoModal = confirmarModal;
// alias para compatibilidade com menu.php (usa confirmarModal)
window.confirmarModal = confirmarModal;

// ── Toast ────────────────────────────────────────────────────
function mostrarToast(msg, dur = 2500) {
  let t = $('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; t.className = 'toast'; document.body.appendChild(t); }
  t.textContent = msg; t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), dur);
}
const toast = mostrarToast;

// ── Extras ───────────────────────────────────────────────────
function lerPedidoEmVoz() {
  const c = getCarrinho();
  if (!c.length) { mostrarToast('Carrinho vazio!', 2000); return; }
  const { total } = calcTotais();
  const txt = `Seu pedido: ${c.map(i=>`${i.quantidade} ${i.nome}`).join(', ')}. Total: ${fmtMoeda(total).replace('R$','R')}`;
  const utt = new SpeechSynthesisUtterance(txt);
  utt.lang = 'pt-BR'; utt.rate = 0.95;
  speechSynthesis.speak(utt);
}

function compartilharPedido() {
  const c = getCarrinho();
  if (!c.length) { mostrarToast('Carrinho vazio!', 2000); return; }
  const { total } = calcTotais();
  const txt = `Meu pedido:\n${c.map(i=>`• ${i.quantidade}x ${i.nome}`).join('\n')}\nTotal: ${fmtMoeda(total)}`;
  if (navigator.share) navigator.share({ title: 'Meu pedido', text: txt }).catch(()=>{});
  else { navigator.clipboard.writeText(txt); mostrarToast('✅ Pedido copiado!'); }
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  atualizarBotaoCarrinho();
  if ($('cartItems')) renderCarrinho();
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

  // Pré-preencher campos salvos
  const ni = $('nomeCliente');     if (ni && !ni.value) ni.value = localStorage.getItem('clienteNome') || '';
  const wi = $('whatsappCliente'); if (wi && !wi.value) wi.value = localStorage.getItem('clienteWa')   || '';
  const ei = $('enderecoEntrega'); if (ei && !ei.value) ei.value = localStorage.getItem('clienteEnd')  || '';

  // Dark mode button
  const btn = $('darkToggle');
  if (btn) {
    const root = document.documentElement;
    const isDark = () => root.getAttribute('data-theme') !== 'light';
    btn.textContent = isDark() ? '☀️ Tema' : '🌙 Tema';
    btn.onclick = () => {
      const d = isDark();
      root.setAttribute('data-theme', d ? 'light' : 'dark');
      localStorage.setItem('darkMode', d ? '0' : '1');
      btn.textContent = d ? '🌙 Tema' : '☀️ Tema';
    };
  }
});

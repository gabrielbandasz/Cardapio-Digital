/* ============================================================
   Cardápio Digital PLUS — cart.js v2 corrigido
   Pedido finaliza direto no WhatsApp
   ============================================================ */

// ── Utilitários ──────────────────────────────────────────────
const fmtMoeda = v => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',');
const qs  = s => document.querySelector(s);
const qsa = s => document.querySelectorAll(s);
const $ = id => document.getElementById(id);

/**
 * Lê resposta de fetch como texto e tenta converter para JSON.
 * Isso evita o erro genérico "Erro de conexão" quando o PHP retorna HTML/erro.
 */
async function lerRespostaJson(response, nomeArquivo = 'requisição') {
  const texto = await response.text();

  try {
    return JSON.parse(texto);
  } catch (e) {
    console.error(`Resposta não JSON de ${nomeArquivo}:`, texto);

    throw new Error(
      `${nomeArquivo} retornou uma resposta inválida.\n\n` +
      `Provável erro no PHP. Abra o F12 > Console para ver o erro completo.`
    );
  }
}

// ── Dark mode ────────────────────────────────────────────────
(function initDarkMode() {
  const root = document.documentElement;
  const saved = localStorage.getItem('darkMode');

  if (saved === '1') {
    root.setAttribute('data-theme', 'dark');
  }

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

// ── Carrinho localStorage ────────────────────────────────────
function getCarrinho() {
  try {
    return JSON.parse(localStorage.getItem('carrinho') || '[]');
  } catch {
    return [];
  }
}

function salvarCarrinho(c) {
  localStorage.setItem('carrinho', JSON.stringify(c));
}

function addItem(produto_id, nome, preco, quantidade, obs, variacoes, customNomes) {
  const c = getCarrinho();
  const key = produto_id + '_' + (variacoes || []).join('_');
  const idx = c.findIndex(i => i._key === key);

  if (idx >= 0) {
    c[idx].quantidade += quantidade;
  } else {
    c.push({
      _key: key,
      produto_id,
      nome,
      preco,
      quantidade,
      obs: obs || '',
      variacoes: variacoes || [],
      customNomes: customNomes || []
    });
  }

  salvarCarrinho(c);
  atualizarBotaoCarrinho();

  return c;
}

function removeItem(key) {
  salvarCarrinho(getCarrinho().filter(i => i._key !== key));
  atualizarBotaoCarrinho();
  renderCarrinho();
}

function changeQtd(key, delta) {
  const c = getCarrinho();
  const idx = c.findIndex(i => i._key === key);

  if (idx < 0) return;

  c[idx].quantidade = Math.max(1, Number(c[idx].quantidade || 1) + delta);

  salvarCarrinho(c);
  atualizarBotaoCarrinho();
  renderCarrinho();
}

function calcTotais() {
  const c = getCarrinho();

  let sub = c.reduce((s, i) => {
    return s + Number(i.preco || 0) * Number(i.quantidade || 0);
  }, 0);

  let taxa = 0;

  if (typeof LOJA_CONFIG !== 'undefined') {
    if (_tipoEntrega === 'entrega') {
      taxa = _taxaZona !== null ? Number(_taxaZona || 0) : Number(LOJA_CONFIG.taxaEntrega || 0);
    }
  }

  let descontoPromo = 0;

  if (typeof LOJA_CONFIG !== 'undefined' && LOJA_CONFIG.promoAtiva) {
    descontoPromo = Math.round(sub * Number(LOJA_CONFIG.promoDesconto || 0) / 100 * 100) / 100;
  }

  const descontoCupom = Number(_descontoCupom || 0);
  const total = Math.max(0, sub - descontoPromo - descontoCupom + taxa);

  return {
    sub,
    taxa,
    descontoPromo,
    descontoCupom,
    total
  };
}

function atualizarBotaoCarrinho() {
  const c = getCarrinho();
  const btn = $('cartBtn');
  const cnt = $('cartCount');
  const tot = $('cartTotal');

  if (!btn) return;

  const qtd = c.reduce((s, i) => s + Number(i.quantidade || 0), 0);

  if (qtd === 0) {
    btn.style.display = 'none';
    return;
  }

  btn.style.display = 'flex';

  if (cnt) {
    cnt.textContent = qtd;
  }

  if (tot) {
    tot.textContent = fmtMoeda(
      c.reduce((s, i) => s + Number(i.preco || 0) * Number(i.quantidade || 0), 0)
    );
  }
}

// ── Estado do carrinho ───────────────────────────────────────
let _tipoEntrega = 'retirada';
let _pagamento = 'dinheiro';
let _taxaZona = null;
let _bairro = '';
let _descontoCupom = 0;
let _cupomCode = '';
let _totalPedidos = parseInt(localStorage.getItem('totalPedidos') || '0');

function setTipoEntrega(tipo) {
  _tipoEntrega = tipo;

  qsa('.tipo-btn').forEach(b => {
    b.className = b.dataset.tipo === tipo
      ? 'btn btn-primary tipo-btn'
      : 'btn btn-outline tipo-btn';
  });

  const ce = $('camposEntrega');

  if (ce) {
    ce.style.display = tipo === 'entrega' ? 'block' : 'none';
  }

  const li = $('linhaEntrega');

  if (li) {
    li.style.display = tipo === 'entrega' ? 'flex' : 'none';
  }

  atualizarResumo();
}

function setPagamento(pag) {
  _pagamento = pag;

  qsa('.pag-btn').forEach(b => {
    const isMP = b.dataset.pag === 'mercadopago';

    if (b.dataset.pag === pag) {
      b.className = 'btn pag-btn active';

      b.style.cssText = isMP
        ? 'background:#0077b6;color:#fff;border:none;width:100%;padding:12px;font-size:15px'
        : '';
    } else {
      b.className = isMP
        ? 'btn pag-btn'
        : 'btn btn-outline pag-btn';

      b.style.cssText = isMP
        ? 'background:#009ee3;color:#fff;border:none;width:100%;padding:12px;font-size:15px'
        : '';
    }
  });

  const pi = $('pixInfo');

  if (pi) {
    pi.style.display = pag === 'pix' ? 'block' : 'none';
  }

  const mi = $('mpInfo');

  if (mi) {
    mi.style.display = pag === 'mercadopago' ? 'block' : 'none';
  }

  const btn = $('btnFinalizar');

  if (btn) {
    btn.textContent = pag === 'mercadopago'
      ? '💳 Ir para pagamento'
      : '📲 Enviar pedido via WhatsApp';
  }
}

function selecionarZona(val) {
  if (!val) {
    _taxaZona = null;
    _bairro = '';
    atualizarResumo();
    return;
  }

  const sel = $('bairroSelect');

  if (!sel) return;

  const opt = sel.options[sel.selectedIndex];

  _taxaZona = parseFloat(opt.dataset.taxa || 0);
  _bairro = val.startsWith('outro_') ? '' : val;

  atualizarResumo();
}

// ── Modal produto ────────────────────────────────────────────
let _modalId = null;
let _modalPrecoBase = 0;
let _modalQtd = 1;
let _modalVariacoes = {};

function abrirProduto(id, nome, preco, desc, img) {
  _modalId = id;
  _modalPrecoBase = parseFloat(preco);
  _modalQtd = 1;
  _modalVariacoes = {};

  $('modalNome').textContent = nome;
  $('modalDesc').textContent = desc || '';

  const imgEl = $('modalImg');
  const imgSrc = $('modalImgSrc');

  if (img && imgEl && imgSrc) {
    imgSrc.src = img;
    imgEl.style.display = 'block';
  } else if (imgEl) {
    imgEl.style.display = 'none';
  }

  $('modalQtd').textContent = '1';
  $('modalObs').value = '';
  $('variacoesContainer').innerHTML = '<p style="color:var(--muted);font-size:13px">Carregando opções...</p>';

  atualizarPrecoModal();

  $('modalProduto').style.display = 'flex';
  document.body.style.overflow = 'hidden';

  fetch(`api/variacoes_produto.php?produto_id=${id}`)
    .then(r => lerRespostaJson(r, 'variacoes_produto.php'))
    .then(grupos => {
      const c = $('variacoesContainer');

      c.innerHTML = '';

      const keys = Object.keys(grupos || {});

      if (!keys.length) {
        c.innerHTML = '';
        return;
      }

      keys.forEach(grupo => {
        const div = document.createElement('div');

        div.style.marginBottom = '12px';
        div.innerHTML = `<div class="form-label" style="font-weight:700;margin-bottom:6px">${grupo}</div>`;

        grupos[grupo].forEach(v => {
          const sign = Number(v.preco_extra) > 0 ? '+' : Number(v.preco_extra) < 0 ? '' : '';
          const label = document.createElement('label');

          label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px;background:var(--bg-alt);border-radius:8px;cursor:pointer;margin-bottom:4px;font-size:14px';

          label.innerHTML = `
            <input type="radio" name="var_${grupo.replace(/\s/g, '_')}" value="${v.id}" data-extra="${v.preco_extra}">
            <span>${v.nome}</span>
            ${
              Number(v.preco_extra) !== 0
                ? `<span style="margin-left:auto;color:var(--primary)">${sign}${fmtMoeda(v.preco_extra)}</span>`
                : ''
            }
          `;

          label.querySelector('input').addEventListener('change', function() {
            _modalVariacoes[grupo] = {
              id: parseInt(this.value),
              extra: parseFloat(this.dataset.extra || 0)
            };

            atualizarPrecoModal();
          });

          div.appendChild(label);
        });

        c.appendChild(div);
      });
    })
    .catch(e => {
      console.error('Erro ao carregar variações:', e);
      $('variacoesContainer').innerHTML = '';
    });
}

function fecharModal() {
  $('modalProduto').style.display = 'none';
  document.body.style.overflow = '';
}

function atualizarPrecoModal() {
  const extra = Object.values(_modalVariacoes).reduce((s, v) => s + Number(v.extra || 0), 0);
  const preco = (_modalPrecoBase + extra) * _modalQtd;

  if ($('modalPrecoDisplay')) {
    $('modalPrecoDisplay').textContent = fmtMoeda(_modalPrecoBase + extra);
  }

  if ($('modalBtnPreco')) {
    $('modalBtnPreco').textContent = fmtMoeda(preco);
  }
}

function ajustarQtdModal(d) {
  _modalQtd = Math.max(1, _modalQtd + d);

  $('modalQtd').textContent = _modalQtd;

  atualizarPrecoModal();
}

function confirmarAdicaoModal() {
  if (!_modalId) return;

  const extra = Object.values(_modalVariacoes).reduce((s, v) => s + Number(v.extra || 0), 0);
  const preco = _modalPrecoBase + extra;
  const varIds = Object.values(_modalVariacoes).map(v => v.id);
  const varNomes = Object.values(_modalVariacoes).map(v => '');
  const nome = $('modalNome').textContent;
  const obs = $('modalObs').value;

  addItem(_modalId, nome, preco, _modalQtd, obs, varIds, varNomes);

  fecharModal();

  mostrarToast(`✅ ${nome} adicionado!`);
}

// ── Toast ────────────────────────────────────────────────────
function mostrarToast(msg, dur = 2500) {
  let t = $('toast');

  if (!t) {
    t = document.createElement('div');
    t.id = 'toast';
    t.className = 'toast-notif';
    document.body.appendChild(t);
  }

  t.textContent = msg;
  t.classList.add('show');

  clearTimeout(t._timer);

  t._timer = setTimeout(() => {
    t.classList.remove('show');
  }, dur);
}

// ── Renderizar carrinho ──────────────────────────────────────
function renderCarrinho() {
  const wrap = $('cartItems');
  const empty = $('emptyCart');
  const content = $('cartContent');

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

        ${
          item.customNomes && item.customNomes.length
            ? `<div style="font-size:12px;color:var(--muted)">${item.customNomes.join(', ')}</div>`
            : ''
        }

        ${
          item.obs
            ? `<div style="font-size:12px;color:var(--muted);font-style:italic">"${item.obs}"</div>`
            : ''
        }

        <div style="color:var(--primary);font-weight:700;font-size:15px;margin-top:2px">
          ${fmtMoeda(Number(item.preco || 0) * Number(item.quantidade || 0))}
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:6px">
        <button class="btn btn-outline" style="padding:4px 10px;font-size:16px" onclick="changeQtd('${item._key}', -1)">−</button>

        <span style="font-weight:700;min-width:20px;text-align:center">
          ${item.quantidade}
        </span>

        <button class="btn btn-outline" style="padding:4px 10px;font-size:16px" onclick="changeQtd('${item._key}', 1)">+</button>

        <button class="btn btn-danger" style="padding:4px 8px;font-size:13px" onclick="removeItem('${item._key}')">🗑</button>
      </div>
    </div>
  `).join('');

  atualizarResumo();
}
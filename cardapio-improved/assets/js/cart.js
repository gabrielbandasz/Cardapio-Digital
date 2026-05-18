/**
 * Cardápio Digital — Cart Manager
 * Gerencia o carrinho com localStorage, toasts e validações
 */
const CartManager = (function () {
  const STORAGE_KEY = 'cdCart';

  function load() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
    catch { return []; }
  }

  function save(items) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)); }
    catch { console.warn('Cart: localStorage indisponível'); }
  }

  function add(produto, qty, obs, extras, remover) {
    const cart = load();
    cart.push({ produto, qty: qty || 1, obs: obs || '', extras: extras || [], remover: remover || [] });
    save(cart);
    return cart;
  }

  function remove(index) {
    const cart = load();
    cart.splice(index, 1);
    save(cart);
    return cart;
  }

  function clear() { save([]); return []; }

  function count() { return load().reduce((a, i) => a + i.qty, 0); }

  function subtotal() { return load().reduce((a, i) => a + i.produto.preco * i.qty, 0); }

  return { load, save, add, remove, clear, count, subtotal };
})();

/**
 * Utilitários de formatação
 */
const Fmt = {
  brl: v => 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+,)/g, '$1.'),
  esc: s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
};

/**
 * Toast system
 */
function showToast(msg, type = '', duration = 2800) {
  let t = document.getElementById('toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast';
    t.className = 'toast';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.className = 'toast', duration);
}

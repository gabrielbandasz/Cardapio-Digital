// Cardápio Digital — Service Worker PWA
const CACHE_NAME = 'cardapio-v2';

// Instalar: sem pré-cache (evita erros de path)
self.addEventListener('install', event => {
  self.skipWaiting();
});

// Ativar: limpa caches antigos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch: Network first, cache fallback apenas para assets estáticos
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Só interceptar GET
  if (event.request.method !== 'GET') return;

  // Ignorar chamadas de API, admin e extensões PHP (sempre rede)
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('/admin/') ||
    url.pathname.endsWith('.php')
  ) return;

  // Apenas assets do mesmo origin ou Google Fonts
  if (url.origin !== self.location.origin && !url.hostname.includes('fonts.g')) return;

  event.respondWith(
    fetch(event.request)
      .then(resp => {
        // Cachear apenas CSS, JS e fontes
        if (resp.ok && (url.pathname.match(/\.(css|js|woff2?)$/) || url.hostname.includes('fonts.g'))) {
          const clone = resp.clone();
          caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
        }
        return resp;
      })
      .catch(() =>
        caches.match(event.request).then(cached =>
          cached || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } })
        )
      )
  );
});

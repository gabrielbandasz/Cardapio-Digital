// Cardápio Digital — Service Worker PWA
const CACHE_NAME = 'cardapio-v1';
const STATIC_ASSETS = [
  '/assets/css/style.css',
  '/assets/js/cart.js',
  'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,600;9..40,700&display=swap',
];

// Instalar: cacheia assets estáticos
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
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

// Fetch: Network first, cache fallback para assets estáticos
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Só interceptar GET do mesmo origin + fonts
  if (event.request.method !== 'GET') return;
  if (url.origin !== location.origin && !url.hostname.includes('fonts.g')) return;
  // Não cachear chamadas de API/admin
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin/')) return;

  event.respondWith(
    fetch(event.request)
      .then(resp => {
        // Cachear assets CSS/JS/fontes
        if (url.pathname.match(/\.(css|js|woff2?)$/) || url.hostname.includes('fonts.g')) {
          const clone = resp.clone();
          caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
        }
        return resp;
      })
      .catch(() => caches.match(event.request))
  );
});

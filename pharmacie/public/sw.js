/* Service worker : garde l'interface en cache pour que la caisse s'ouvre même sans réseau.
   Les données (ventes, stock) ne passent jamais par ce cache. */
const CACHE = 'pharma-v1';
const SHELL = ['assets/app.css?v=1', 'assets/app.js?v=1', 'assets/pos.js?v=1', 'assets/icon.svg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  // Fichiers statiques : cache d'abord
  if (url.pathname.includes('/assets/')) {
    e.respondWith(caches.match(req).then((r) => r || fetch(req).then((res) => { const c = res.clone(); caches.open(CACHE).then((x) => x.put(req, c)); return res; })));
    return;
  }
  // Page de la caisse : réseau d'abord, dernière version gardée en secours
  if (url.searchParams.get('r') === 'caisse' && req.mode === 'navigate') {
    e.respondWith(fetch(req).then((res) => { const c = res.clone(); caches.open(CACHE).then((x) => x.put(req, c)); return res; }).catch(() => caches.match(req)));
  }
});

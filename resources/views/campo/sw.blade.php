/*
 * Service worker de la inspección sin conexión (alcance: /campo).
 * Guarda la pantalla /campo y sus recursos para poder abrirla sin señal.
 * Los datos de las inspecciones NO pasan por aquí: viven en IndexedDB (ver campo/app.blade.php).
 */
const CACHE = 'ivs-campo-v1';
const PANTALLA = '/campo';
const ESTATICOS = ['/images/logo-sm.png', '/images/icono-192.png', '/manifest-campo.webmanifest'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ESTATICOS)).catch(() => null));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((k) => k.startsWith('ivs-campo-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);

    if (e.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // La pantalla: primero la red (para tener siempre la última versión), y sin señal la copia guardada
    if (url.pathname === PANTALLA) {
        e.respondWith(
            fetch(e.request)
                .then((r) => {
                    // Si la sesión venció, la respuesta es la página de login: esa no se guarda
                    if (r.ok && ! r.redirected) {
                        const copia = r.clone();
                        caches.open(CACHE).then((c) => c.put(PANTALLA, copia));
                    }
                    return r;
                })
                .catch(() => caches.match(PANTALLA).then((r) => r || new Response(
                    '<h1>Sin conexión</h1><p>Abra esta pantalla una vez con señal para poder usarla sin conexión.</p>',
                    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                )))
        );
        return;
    }

    if (ESTATICOS.includes(url.pathname)) {
        e.respondWith(caches.match(e.request).then((r) => r || fetch(e.request)));
    }
});

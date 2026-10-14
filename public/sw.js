/*
 * Service worker do Condomínio.
 * - Arquivos do build (com hash no nome), fontes e ícones: cache primeiro.
 * - Páginas: sempre da rede; sem rede, mostra a página "Sem conexão".
 * - Nunca guarda respostas do Inertia, arquivos (/arquivos: PDFs e comprovantes) nem POSTs.
 */
const VERSAO = 'condominio-v1';
const ESTATICO = `${VERSAO}-estatico`;
const OFFLINE = '/offline.html';

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches
            .open(ESTATICO)
            .then((cache) =>
                cache.addAll([
                    OFFLINE,
                    '/icones/icone-192.png',
                    '/manifest.webmanifest',
                ]),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((chaves) =>
                Promise.all(
                    chaves
                        .filter((c) => !c.startsWith(VERSAO))
                        .map((c) => caches.delete(c)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function estatico(url) {
    return (
        (url.origin === self.location.origin &&
            (url.pathname.startsWith('/build/') ||
                url.pathname.startsWith('/icones/') ||
                url.pathname === '/favicon.svg')) ||
        url.hostname === 'fonts.bunny.net'
    );
}

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;

    if (pedido.method !== 'GET') {
        return;
    }

    const url = new URL(pedido.url);

    if (estatico(url)) {
        evento.respondWith(
            caches.open(ESTATICO).then(async (cache) => {
                const guardado = await cache.match(pedido);

                if (guardado) {
                    return guardado;
                }

                const resposta = await fetch(pedido);

                if (resposta.ok) {
                    cache.put(pedido, resposta.clone());
                }

                return resposta;
            }),
        );

        return;
    }

    // Navegação (abrir o app, recarregar): rede; sem rede, a página offline.
    if (pedido.mode === 'navigate' && !pedido.headers.get('X-Inertia')) {
        evento.respondWith(fetch(pedido).catch(() => caches.match(OFFLINE)));
    }
});

// Avisos por push (futuro): abre a tela do aviso ao tocar.
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const url = evento.notification.data?.url || '/';

    evento.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((janelas) => {
                const aberta = janelas.find(
                    (j) => new URL(j.url).pathname === url,
                );

                return aberta ? aberta.focus() : self.clients.openWindow(url);
            }),
    );
});

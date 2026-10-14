/**
 * Service worker do PWA: instala o app na tela inicial e guarda os arquivos estáticos.
 * Em desenvolvimento (Vite) não registra, para não servir build velho do cache.
 */
export function registrarServiceWorker(): void {
    if (
        typeof navigator === 'undefined' ||
        !('serviceWorker' in navigator) ||
        import.meta.env.DEV
    ) {
        return;
    }

    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js');
    });
}

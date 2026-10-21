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

type EventoInstalacao = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type EstadoInstalacao = {
    /** Já aberto como app (tela inicial): nada a oferecer. */
    instalado: boolean;
    /** O navegador oferece o pedido nativo (Chrome, Edge, Android). */
    nativo: boolean;
    /** iPhone/iPad: só dá para instalar pelo Compartilhar do Safari. */
    ios: boolean;
};

let pedidoAdiado: EventoInstalacao | null = null;
let estado: EstadoInstalacao = {
    instalado: false,
    nativo: false,
    ios: false,
};
const ouvintes = new Set<() => void>();

function atualizar(parcial: Partial<EstadoInstalacao>): void {
    estado = { ...estado, ...parcial };
    ouvintes.forEach((ouvinte) => ouvinte());
}

function aparelhoIos(): boolean {
    const agente = navigator.userAgent.toLowerCase();

    return (
        /iphone|ipad|ipod/.test(agente) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

/**
 * O `beforeinstallprompt` dispara uma vez por carregamento, muitas vezes ainda na tela de
 * login; por isso é capturado aqui, na partida, e não no componente que mostra o convite.
 */
export function capturarInstalacao(): void {
    if (typeof window === 'undefined') {
        return;
    }

    atualizar({
        instalado:
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone ===
                true,
        ios: aparelhoIos(),
    });

    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        pedidoAdiado = evento as EventoInstalacao;
        atualizar({ nativo: true });
    });

    window.addEventListener('appinstalled', () => {
        pedidoAdiado = null;
        atualizar({ instalado: true, nativo: false });
    });
}

export function assinarInstalacao(ouvinte: () => void): () => void {
    ouvintes.add(ouvinte);

    return () => ouvintes.delete(ouvinte);
}

export function estadoInstalacao(): EstadoInstalacao {
    return estado;
}

const semInstalacao: EstadoInstalacao = {
    instalado: false,
    nativo: false,
    ios: false,
};

/** No SSR não há aparelho: nada é oferecido até a hidratação terminar. */
export function estadoInstalacaoServidor(): EstadoInstalacao {
    return semInstalacao;
}

/** Abre o pedido nativo; o evento só vale uma vez, então é descartado depois. */
export async function pedirInstalacao(): Promise<void> {
    if (pedidoAdiado === null) {
        return;
    }

    const pedido = pedidoAdiado;
    pedidoAdiado = null;
    atualizar({ nativo: false });
    await pedido.prompt();

    const { outcome } = await pedido.userChoice;

    if (outcome === 'accepted') {
        atualizar({ instalado: true });
    }
}

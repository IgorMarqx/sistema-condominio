import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import LayoutEntrada from '@/layouts/layout-entrada';
import LayoutGestao from '@/layouts/layout-gestao';
import LayoutPerfil from '@/layouts/layout-perfil';
import LayoutUnidade from '@/layouts/layout-unidade';
import { registrarServiceWorker } from '@/lib/pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Condomínio';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return LayoutEntrada;
            case name.startsWith('gestao/'):
                return LayoutGestao;
            case name.startsWith('unidade/'):
                return LayoutUnidade;
            default:
                return LayoutPerfil;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#f5cf3c',
    },
});

registrarServiceWorker();

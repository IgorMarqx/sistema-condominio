import { usePage } from '@inertiajs/react';
import type { LayoutProps } from '@/types';
import LayoutGestao from './layout-gestao';
import LayoutUnidade from './layout-unidade';

/**
 * Telas comuns (avisos, conta): o layout segue o perfil de quem entrou.
 */
export default function LayoutPerfil({ children }: LayoutProps) {
    const tipo = usePage().props.auth.user?.tipo;

    return tipo === 'unidade' ? (
        <LayoutUnidade>{children}</LayoutUnidade>
    ) : (
        <LayoutGestao>{children}</LayoutGestao>
    );
}

import { useEffect } from 'react';
import { reabrirConviteInstalar } from '@/components/condominio/convite-instalar';
import { useFlash } from '@/hooks/use-flash';
import type { LayoutProps } from '@/types';

export default function LayoutEntrada({ children }: LayoutProps) {
    useFlash();
    // Quem passa pela entrada (login, 2FA, troca de senha) vê o convite inteiro de novo.
    useEffect(reabrirConviteInstalar, []);

    return <>{children}</>;
}

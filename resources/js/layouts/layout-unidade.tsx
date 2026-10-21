import { Building2, ChartLine, House, Wallet } from 'lucide-react';
import ConviteInstalar from '@/components/condominio/convite-instalar';
import { useFlash } from '@/hooks/use-flash';
import type { LayoutProps } from '@/types';
import BarraInferior, { type ItemNavegacao } from './barra-inferior';

const itens: ItemNavegacao[] = [
    {
        rotulo: 'Início',
        href: '/',
        icone: House,
        ativo: (u) =>
            u === '/' || u.startsWith('/cobranca') || u.startsWith('/avisos'),
    },
    {
        rotulo: 'Condomínio',
        href: '/condominio',
        icone: Building2,
        ativo: (u) => u.startsWith('/condominio'),
    },
    {
        rotulo: 'Caixa',
        href: '/caixa',
        icone: Wallet,
        ativo: (u) => u.startsWith('/caixa'),
    },
    {
        rotulo: 'Minhas contas',
        href: '/minhas-contas',
        icone: ChartLine,
        ativo: (u) => u.startsWith('/minhas-contas') || u.startsWith('/conta'),
    },
];

export default function LayoutUnidade({ children }: LayoutProps) {
    useFlash();

    return (
        <div className="min-h-svh bg-fundo">
            <main className="mx-auto max-w-lg pb-28 md:pb-32">{children}</main>
            <BarraInferior itens={itens} />
            <ConviteInstalar />
        </div>
    );
}

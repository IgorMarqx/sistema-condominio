import { Building2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tamanho = 'sm' | 'md' | 'lg' | 'xl';

const tamanhos: Record<
    Tamanho,
    { caixa: string; icone: string; halo: string }
> = {
    sm: { caixa: 'size-9', icone: 'size-4', halo: 'p-0' },
    md: { caixa: 'size-12', icone: 'size-5', halo: 'p-1' },
    lg: { caixa: 'size-20', icone: 'size-8', halo: 'p-2.5' },
    xl: { caixa: 'size-28', icone: 'size-12', halo: 'p-3.5' },
};

/**
 * Círculo amarelo com o prédio, com o halo translúcido dos mockups de entrada.
 */
export default function LogoPredio({
    tamanho = 'md',
    halo = false,
    className,
}: {
    tamanho?: Tamanho;
    halo?: boolean;
    className?: string;
}) {
    const t = tamanhos[tamanho];

    const circulo = (
        <div
            className={cn(
                'flex items-center justify-center rounded-full bg-ouro-500 text-marinho-900 shadow-[inset_0_-3px_0_rgb(0_0_0/0.08)] ring-2 ring-marinho-900/90',
                t.caixa,
                !halo && className,
            )}
        >
            <Building2 className={t.icone} strokeWidth={2.2} />
        </div>
    );

    if (!halo) {
        return circulo;
    }

    return (
        <div
            className={cn(
                'rounded-full bg-white/8 ring-1 ring-white/10',
                t.halo,
                className,
            )}
        >
            {circulo}
        </div>
    );
}

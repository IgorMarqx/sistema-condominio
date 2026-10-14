import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TomSelo =
    | 'aberto'
    | 'pago'
    | 'conferir'
    | 'atrasado'
    | 'neutro'
    | 'alerta'
    | 'marinho';

const tons: Record<TomSelo, string> = {
    aberto: 'bg-ouro-100 text-[#8a6100]',
    pago: 'bg-emerald-100 text-emerald-700',
    conferir: 'bg-sky-100 text-sky-700',
    atrasado: 'bg-red-100 text-red-700',
    neutro: 'bg-marinho-50 text-suave',
    alerta: 'bg-orange-100 text-orange-700',
    marinho: 'bg-marinho-900 text-white',
};

export default function Selo({
    tom = 'neutro',
    children,
    className,
}: {
    tom?: TomSelo;
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap',
                tons[tom],
                className,
            )}
        >
            {children}
        </span>
    );
}

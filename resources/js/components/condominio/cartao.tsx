import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export default function Cartao({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'rounded-2xl border border-linha bg-white p-4 shadow-[0_1px_2px_rgb(19_37_79/0.04)]',
                className,
            )}
            {...props}
        />
    );
}

export function TituloSecao({
    className,
    acao,
    children,
}: {
    className?: string;
    acao?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'mb-2 flex items-end justify-between gap-3',
                className,
            )}
        >
            <h2 className="text-[15px] font-extrabold tracking-tight text-texto">
                {children}
            </h2>
            {acao && (
                <div className="text-xs font-semibold text-suave">{acao}</div>
            )}
        </div>
    );
}

export function Rotulo({ className, ...props }: ComponentProps<'p'>) {
    return (
        <p
            className={cn(
                'text-[11px] font-bold tracking-[0.08em] text-suave uppercase',
                className,
            )}
            {...props}
        />
    );
}

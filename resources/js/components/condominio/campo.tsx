import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Rótulo em caixa-alta + campo arredondado dos mockups ("SENHA", "QUEM ESTÁ ACESSANDO?").
 */
export function Campo({
    rotulo,
    detalhe,
    erro,
    ajuda,
    htmlFor,
    children,
    className,
}: {
    rotulo?: ReactNode;
    detalhe?: ReactNode;
    erro?: string;
    ajuda?: ReactNode;
    htmlFor?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid min-w-0 gap-1.5', className)}>
            {rotulo && (
                <label
                    htmlFor={htmlFor}
                    className="text-[11px] font-bold tracking-[0.08em] text-suave uppercase"
                >
                    {rotulo}
                    {detalhe && (
                        <span className="ml-1 font-semibold tracking-normal normal-case opacity-80">
                            {detalhe}
                        </span>
                    )}
                </label>
            )}
            {children}
            {erro ? (
                <p className="text-xs font-semibold text-red-600">{erro}</p>
            ) : (
                ajuda && <p className="text-xs text-suave">{ajuda}</p>
            )}
        </div>
    );
}

export function Entrada({
    className,
    icone,
    sufixo,
    ...props
}: ComponentProps<'input'> & { icone?: ReactNode; sufixo?: ReactNode }) {
    return (
        <div
            className={cn(
                'flex h-12 min-w-0 items-center gap-2.5 rounded-xl border border-input bg-[#f7f8fb] px-3.5 transition focus-within:border-marinho-700 focus-within:bg-white focus-within:ring-4 focus-within:ring-marinho-600/10 has-[[aria-invalid=true]]:border-red-400',
                className,
            )}
        >
            {icone && (
                <span className="shrink-0 text-suave [&_svg]:size-[18px]">
                    {icone}
                </span>
            )}
            <input
                className="h-full min-w-0 flex-1 bg-transparent text-[15px] text-texto outline-none placeholder:text-[#9aa3b8] disabled:opacity-60"
                {...props}
            />
            {sufixo && <span className="shrink-0">{sufixo}</span>}
        </div>
    );
}

export function AreaTexto({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'min-h-24 rounded-xl border border-input bg-[#f7f8fb] px-3.5 py-3 text-[15px] text-texto outline-none placeholder:text-[#9aa3b8] focus:border-marinho-700 focus:bg-white focus:ring-4 focus:ring-marinho-600/10',
                className,
            )}
            {...props}
        />
    );
}

export function Escolha({
    className,
    children,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'h-12 rounded-xl border border-input bg-[#f7f8fb] px-3 text-[15px] text-texto outline-none focus:border-marinho-700 focus:bg-white focus:ring-4 focus:ring-marinho-600/10',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    );
}

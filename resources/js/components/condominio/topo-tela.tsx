import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Cabeçalho das telas internas: voltar, linha de contexto e título ("Apartamento 302 / Cobrança de outubro").
 */
export default function TopoTela({
    voltar,
    contexto,
    titulo,
    acao,
    className,
}: {
    voltar?: string;
    contexto?: ReactNode;
    titulo: ReactNode;
    acao?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn('flex items-center gap-3 px-4 pt-5 pb-3', className)}
        >
            {voltar && (
                <Link
                    href={voltar}
                    className="flex size-9 shrink-0 items-center justify-center rounded-full border border-linha bg-white text-texto shadow-xs active:scale-95"
                    aria-label="Voltar"
                >
                    <ChevronLeft className="size-5" />
                </Link>
            )}
            <div className="min-w-0 flex-1">
                {contexto && (
                    <p className="truncate text-[11px] font-semibold text-suave">
                        {contexto}
                    </p>
                )}
                <h1 className="truncate text-xl font-extrabold tracking-tight text-texto">
                    {titulo}
                </h1>
            </div>
            {acao}
        </header>
    );
}

import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Linha de lista: ícone, título, linha de apoio e o que vai à direita (valor, selo, seta).
 */
export default function LinhaLista({
    icone,
    titulo,
    apoio,
    direita,
    href,
    onClick,
    seta = false,
    className,
}: {
    icone?: ReactNode;
    titulo: ReactNode;
    apoio?: ReactNode;
    direita?: ReactNode;
    href?: string;
    onClick?: () => void;
    seta?: boolean;
    className?: string;
}) {
    const conteudo = (
        <>
            {icone}
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-bold text-texto">
                    {titulo}
                </div>
                {apoio && (
                    <div className="truncate text-xs text-suave">{apoio}</div>
                )}
            </div>
            {direita && <div className="shrink-0 text-right">{direita}</div>}
            {seta && <ChevronRight className="size-4 shrink-0 text-suave" />}
        </>
    );

    const classes = cn(
        'flex w-full items-center gap-3 py-3 text-left',
        (href || onClick) && 'active:opacity-70',
        className,
    );

    if (href) {
        return (
            <Link href={href} className={classes}>
                {conteudo}
            </Link>
        );
    }

    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={classes}>
                {conteudo}
            </button>
        );
    }

    return <div className={classes}>{conteudo}</div>;
}

import { partesDinheiro } from '@/lib/formato';
import { cn } from '@/lib/utils';

/**
 * "R$ 104,22" com o R$ pequeno e os centavos no mesmo peso, como no cartão da cobrança.
 */
export default function ValorGrande({
    centavos,
    className,
    tamanho = 'text-4xl',
}: {
    centavos: number;
    className?: string;
    tamanho?: string;
}) {
    const { inteiro, centavos: dec } = partesDinheiro(centavos);

    return (
        <p
            className={cn(
                'tabular font-extrabold tracking-tight',
                tamanho,
                className,
            )}
        >
            <span className="mr-1 align-[0.35em] text-[0.42em] font-bold opacity-80">
                R$
            </span>
            {inteiro}
            <span>,{dec}</span>
        </p>
    );
}

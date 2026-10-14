import { cn } from '@/lib/utils';

export type Barra = {
    rotulo: string;
    valor: number | null;
    destaque?: boolean;
    alerta?: boolean;
    titulo?: string;
};

/**
 * Barras simples do histórico de consumo: mês atual em azul-marinho, alerta em âmbar.
 */
export default function GraficoBarras({
    barras,
    altura = 'h-24',
    mostrarRotulos = true,
    className,
}: {
    barras: Barra[];
    altura?: string;
    mostrarRotulos?: boolean;
    className?: string;
}) {
    const maximo = Math.max(1, ...barras.map((b) => b.valor ?? 0));

    return (
        <div className={className}>
            <div className={cn('flex items-end gap-2', altura)}>
                {barras.map((barra, i) => {
                    const proporcao = barra.valor
                        ? Math.max(0.08, barra.valor / maximo)
                        : 0;

                    return (
                        <div
                            key={`${barra.rotulo}-${i}`}
                            className="flex h-full flex-1 flex-col justify-end"
                            title={barra.titulo}
                        >
                            {barra.valor === null ? (
                                <div className="h-1 rounded-full bg-linha" />
                            ) : (
                                <div
                                    className={cn(
                                        'w-full rounded-lg transition-[height] duration-500',
                                        barra.alerta
                                            ? 'bg-[#e0a21b]'
                                            : barra.destaque
                                              ? 'bg-marinho-900'
                                              : 'bg-[#c9d3ea]',
                                    )}
                                    style={{ height: `${proporcao * 100}%` }}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
            {mostrarRotulos && (
                <div className="mt-1.5 flex gap-2">
                    {barras.map((barra, i) => (
                        <span
                            key={`${barra.rotulo}-r-${i}`}
                            className={cn(
                                'flex-1 text-center text-[10px] font-semibold text-suave',
                                barra.destaque && 'font-extrabold text-texto',
                            )}
                        >
                            {barra.rotulo}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

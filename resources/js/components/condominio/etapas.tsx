import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Gerada → Paga → Confirmada, como no rodapé da cobrança.
 */
export default function Etapas({
    etapas,
    atual,
}: {
    etapas: string[];
    atual: number;
}) {
    return (
        <ol className="flex items-center">
            {etapas.map((etapa, i) => {
                const feita = i <= atual;

                return (
                    <li
                        key={etapa}
                        className="flex flex-1 items-center last:flex-none"
                    >
                        <div className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'flex size-5 items-center justify-center rounded-full border-2',
                                    feita
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-linha bg-white',
                                )}
                            >
                                {feita && (
                                    <Check className="size-3" strokeWidth={3} />
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-xs font-semibold',
                                    feita ? 'text-texto' : 'text-suave',
                                )}
                            >
                                {etapa}
                            </span>
                        </div>
                        {i < etapas.length - 1 && (
                            <span
                                className={cn(
                                    'mx-2 h-0.5 flex-1 rounded-full',
                                    i < atual ? 'bg-emerald-600' : 'bg-linha',
                                )}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

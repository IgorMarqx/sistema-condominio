import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type ItemNavegacao = {
    rotulo: string;
    href?: string;
    aoClicar?: () => void;
    icone: LucideIcon;
    ativo: (url: string) => boolean;
    contagem?: number;
};

/**
 * Barra de navegação inferior: o item ativo ganha a pílula amarela atrás do ícone.
 */
export default function BarraInferior({ itens }: { itens: ItemNavegacao[] }) {
    const { url } = usePage();

    return (
        <nav className="area-segura-baixo fixed inset-x-0 bottom-0 z-40 border-t border-linha bg-white/95 backdrop-blur md:inset-x-auto md:bottom-4 md:left-1/2 md:w-[28rem] md:-translate-x-1/2 md:rounded-3xl md:border md:pb-2 md:shadow-lg">
            <ul className="mx-auto flex max-w-lg items-stretch justify-around px-2 pt-2">
                {itens.map((item) => {
                    const ativo = item.ativo(url);
                    const Icone = item.icone;

                    const conteudo = (
                        <>
                            <span
                                className={cn(
                                    'relative flex h-8 w-12 items-center justify-center rounded-full transition',
                                    ativo
                                        ? 'bg-ouro-500 text-marinho-900'
                                        : 'text-suave',
                                )}
                            >
                                <Icone
                                    className="size-[19px]"
                                    strokeWidth={ativo ? 2.4 : 2}
                                />
                                {!!item.contagem && (
                                    <span className="absolute -top-1 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                                        {item.contagem > 9
                                            ? '9+'
                                            : item.contagem}
                                    </span>
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-[11px]',
                                    ativo
                                        ? 'font-extrabold text-texto'
                                        : 'font-semibold text-suave',
                                )}
                            >
                                {item.rotulo}
                            </span>
                        </>
                    );

                    return (
                        <li key={item.rotulo} className="flex-1">
                            {item.href ? (
                                <Link
                                    href={item.href}
                                    prefetch
                                    className="flex flex-col items-center gap-1 py-0.5"
                                    aria-current={ativo ? 'page' : undefined}
                                >
                                    {conteudo}
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    onClick={item.aoClicar}
                                    className="flex w-full flex-col items-center gap-1 py-0.5"
                                >
                                    {conteudo}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import LogoPredio from '@/components/condominio/logo-predio';
import { cn } from '@/lib/utils';

/**
 * Moldura das telas de entrada: tela cheia no celular; no computador, cartão central
 * sobre o branco que escurece para o marinho embaixo.
 */
export default function TelaEntrada({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <main className="fixed inset-0 isolate flex flex-col overflow-y-auto bg-white md:items-center md:px-6 md:py-12">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 hidden h-[38%] bg-[linear-gradient(to_top,var(--color-marinho-950)_0%,rgb(26_49_102/0.7)_45%,transparent_100%)] md:block"
            />
            <div
                className={cn(
                    'flex flex-1 flex-col bg-white md:my-auto md:w-full md:max-w-[440px] md:flex-none md:rounded-[32px] md:border md:border-marinho-50 md:shadow-[0_30px_80px_rgb(11_23_52/0.18)]',
                    className,
                )}
            >
                {children}
            </div>
        </main>
    );
}

/**
 * Topo marinho no celular; no cartão do computador, logo, título e descrição centralizados.
 */
export function CabecalhoEntrada({
    titulo,
    descricao,
    voltar,
}: {
    titulo: ReactNode;
    descricao: ReactNode;
    voltar?: { href: string; rotulo: string };
}) {
    return (
        <>
            <header
                className={cn(
                    'topo-marinho area-segura-topo rounded-b-[28px] px-6 pb-7 text-white md:hidden',
                    voltar ? 'pt-6' : 'pt-8',
                )}
            >
                {voltar && (
                    <Link
                        href={voltar.href}
                        className="mb-5 inline-flex items-center gap-1 text-sm font-semibold text-white/80"
                    >
                        <ChevronLeft className="size-4" /> {voltar.rotulo}
                    </Link>
                )}
                <LogoPredio tamanho="md" />
                <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                    {titulo}
                </h1>
                <p className="mt-1 text-sm text-white/75">{descricao}</p>
            </header>

            <header
                className={cn(
                    'relative hidden flex-col items-center px-8 text-center md:flex',
                    voltar ? 'pt-16' : 'pt-10',
                )}
            >
                {voltar && (
                    <Link
                        href={voltar.href}
                        className="absolute top-6 left-6 inline-flex items-center gap-1 text-sm font-semibold text-marinho-700 hover:text-marinho-900"
                    >
                        <ChevronLeft className="size-4" /> {voltar.rotulo}
                    </Link>
                )}
                <div className="rounded-full bg-marinho-50 p-3">
                    <LogoPredio tamanho="lg" />
                </div>
                <h1 className="mt-5 text-2xl leading-tight font-extrabold tracking-tight text-texto">
                    {titulo}
                </h1>
                <p className="mt-1 max-w-[320px] text-sm text-suave">
                    {descricao}
                </p>
            </header>
        </>
    );
}

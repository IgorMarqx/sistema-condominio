import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ClipboardCheck,
    Ellipsis,
    FileText,
    Gauge,
    History,
    Lock,
    LogOut,
    ReceiptText,
    Settings,
    ShieldCheck,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import Folha from '@/components/condominio/folha';
import LogoPredio from '@/components/condominio/logo-predio';
import { useFlash } from '@/hooks/use-flash';
import { cn } from '@/lib/utils';
import type { LayoutProps } from '@/types';
import BarraInferior, { type ItemNavegacao } from './barra-inferior';

type ItemMenu = {
    rotulo: string;
    href: string;
    icone: typeof Gauge;
    prefixo: string;
};

const principais: ItemMenu[] = [
    { rotulo: 'Painel', href: '/gestao', icone: Gauge, prefixo: '/gestao$' },
    {
        rotulo: 'Faturas',
        href: '/gestao/faturas',
        icone: FileText,
        prefixo: '/gestao/faturas',
    },
    {
        rotulo: 'Cobranças',
        href: '/gestao/cobrancas',
        icone: ReceiptText,
        prefixo: '/gestao/cobrancas',
    },
    {
        rotulo: 'Caixa',
        href: '/gestao/caixa',
        icone: Wallet,
        prefixo: '/gestao/caixa',
    },
];

const outros: ItemMenu[] = [
    {
        rotulo: 'Calendário de leituras',
        href: '/gestao/leituras',
        icone: CalendarDays,
        prefixo: '/gestao/leituras',
    },
    {
        rotulo: 'Fechamento e conciliação',
        href: '/gestao/fechamento',
        icone: Lock,
        prefixo: '/gestao/fechamento',
    },
    {
        rotulo: 'Unidades e senhas',
        href: '/gestao/unidades',
        icone: UsersRound,
        prefixo: '/gestao/unidades',
    },
    {
        rotulo: 'Medidores',
        href: '/gestao/medidores',
        icone: Building2,
        prefixo: '/gestao/medidores',
    },
    {
        rotulo: 'Auditoria',
        href: '/gestao/auditoria',
        icone: History,
        prefixo: '/gestao/auditoria',
    },
    {
        rotulo: 'Configurações',
        href: '/gestao/configuracoes',
        icone: Settings,
        prefixo: '/gestao/configuracoes',
    },
    {
        rotulo: 'Segurança (2FA)',
        href: '/gestao/seguranca',
        icone: ShieldCheck,
        prefixo: '/gestao/seguranca',
    },
    {
        rotulo: 'Avisos',
        href: '/avisos',
        icone: ClipboardCheck,
        prefixo: '/avisos',
    },
];

function ativo(url: string, prefixo: string): boolean {
    const caminho = url.split('?')[0];

    return prefixo.endsWith('$')
        ? caminho === prefixo.slice(0, -1)
        : caminho.startsWith(prefixo);
}

/**
 * Gestão: no celular, barra inferior com "Mais"; no computador, menu lateral completo.
 */
export default function LayoutGestao({ children }: LayoutProps) {
    useFlash();
    const { url, props } = usePage();
    const [mais, setMais] = useState(false);
    const user = props.auth.user;

    const itensBarra: ItemNavegacao[] = principais.map((item) => ({
        rotulo: item.rotulo,
        href: item.href,
        icone: item.icone,
        ativo: (u) => ativo(u, item.prefixo),
    }));

    const algumOutroAtivo = outros.some((o) => ativo(url, o.prefixo));

    return (
        <div className="min-h-svh bg-fundo lg:flex">
            <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col bg-marinho-900 text-white lg:flex">
                <div className="flex items-center gap-3 px-5 py-6">
                    <LogoPredio tamanho="sm" />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-extrabold">
                            {props.edificio}
                        </p>
                        <p className="text-xs text-white/60">
                            {user?.tipo === 'conselho'
                                ? 'Conselho · leitura'
                                : 'Gestão do síndico'}
                        </p>
                    </div>
                </div>
                <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
                    {[...principais, ...outros].map((item) => {
                        const Icone = item.icone;
                        const marcado = ativo(url, item.prefixo);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                    marcado
                                        ? 'bg-ouro-500 text-marinho-900'
                                        : 'text-white/75 hover:bg-white/10 hover:text-white',
                                )}
                            >
                                <Icone className="size-[18px]" />
                                {item.rotulo}
                                {item.href === '/avisos' &&
                                    props.avisos_nao_lidos > 0 && (
                                        <span className="ml-auto rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                                            {props.avisos_nao_lidos}
                                        </span>
                                    )}
                            </Link>
                        );
                    })}
                </nav>
                <div className="border-t border-white/10 p-3">
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/75 hover:bg-white/10 hover:text-white"
                    >
                        <LogOut className="size-[18px]" />
                        Sair
                    </button>
                </div>
            </aside>

            <main className="mx-auto w-full max-w-lg flex-1 pb-28 md:max-w-3xl lg:max-w-5xl lg:px-6 lg:pb-10">
                {children}
            </main>

            <div className="lg:hidden">
                <BarraInferior
                    itens={[
                        ...itensBarra,
                        {
                            rotulo: 'Mais',
                            aoClicar: () => setMais(true),
                            icone: Ellipsis,
                            ativo: () => algumOutroAtivo,
                            contagem: props.avisos_nao_lidos,
                        },
                    ]}
                />
            </div>

            <Folha
                aberta={mais}
                aoMudar={setMais}
                titulo="Gestão"
                descricao={user?.nome}
            >
                <ul className="grid grid-cols-2 gap-2 pb-2">
                    {outros.map((item) => {
                        const Icone = item.icone;

                        return (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    onClick={() => setMais(false)}
                                    className={cn(
                                        'flex h-full flex-col gap-2 rounded-2xl border border-linha p-3.5 text-sm font-bold',
                                        ativo(url, item.prefixo)
                                            ? 'border-ouro-500 bg-ouro-50'
                                            : 'bg-white',
                                    )}
                                >
                                    <Icone className="size-5 text-marinho-700" />
                                    {item.rotulo}
                                    {item.href === '/avisos' &&
                                        props.avisos_nao_lidos > 0 && (
                                            <span className="text-xs font-semibold text-red-600">
                                                {props.avisos_nao_lidos} não
                                                lido(s)
                                            </span>
                                        )}
                                </Link>
                            </li>
                        );
                    })}
                    <li className="col-span-2">
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="flex w-full items-center justify-center gap-2 rounded-2xl border border-linha bg-white p-3.5 text-sm font-bold text-red-600"
                        >
                            <LogOut className="size-4" />
                            Sair
                        </button>
                    </li>
                </ul>
            </Folha>
        </div>
    );
}

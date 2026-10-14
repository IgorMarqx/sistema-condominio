import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Search, ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao from '@/components/condominio/cartao';
import { Entrada, Escolha } from '@/components/condominio/campo';
import TopoTela from '@/components/condominio/topo-tela';
import { dataHora } from '@/lib/formato';
import { cn } from '@/lib/utils';

type Registro = {
    id: number;
    quem: string;
    ator_tipo: string;
    acao: string;
    entidade: string | null;
    entidade_id: number | null;
    antes: Record<string, unknown> | null;
    depois: Record<string, unknown> | null;
    ip: string | null;
    hash: string;
    hash_anterior: string | null;
    quando: string;
};

type Paginado<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    registros: Paginado<Registro>;
    acoes: string[];
    filtro: { acao?: string; busca?: string };
    verificacao: {
        integra: boolean;
        total: number;
        ultimo_hash: string | null;
        falha: { id: number; motivo: string } | null;
        em: string;
    } | null;
};

const nomesAcoes: Record<string, string> = {
    acesso: 'Acessos',
    caixa: 'Caixa',
    caixa_entrada: 'Endereços de e-mail',
    cobranca: 'Cobranças',
    competencia: 'Leituras',
    configuracao: 'Configurações',
    fatura: 'Faturas',
    medidor: 'Medidores',
    pagamento: 'Pagamentos',
    rateio: 'Rateio',
    unidade: 'Unidades',
};

export default function Auditoria({
    registros,
    acoes,
    filtro,
    verificacao,
}: Props) {
    const { auth } = usePage().props;
    const [busca, setBusca] = useState(filtro.busca ?? '');
    const [aberto, setAberto] = useState<number | null>(null);
    const [verificando, setVerificando] = useState(false);

    const filtrar = (novo: Partial<Props['filtro']>) =>
        router.get(
            '/gestao/auditoria',
            { ...filtro, ...novo },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Auditoria" />
            <TopoTela
                titulo="Auditoria"
                contexto={`${registros.total} registros · append-only e encadeado por hash`}
            />

            <div className="space-y-4 px-4 lg:px-0">
                <Cartao className="space-y-3">
                    {verificacao ? (
                        <div
                            className={cn(
                                'flex items-start gap-3 rounded-xl p-3 text-sm',
                                verificacao.integra
                                    ? 'bg-emerald-50 text-emerald-800'
                                    : 'bg-red-50 text-red-800',
                            )}
                        >
                            {verificacao.integra ? (
                                <ShieldCheck className="size-5 shrink-0" />
                            ) : (
                                <ShieldAlert className="size-5 shrink-0" />
                            )}
                            <div className="min-w-0">
                                <p className="font-extrabold">
                                    {verificacao.integra
                                        ? `Cadeia íntegra: ${verificacao.total} registros conferidos`
                                        : `Cadeia quebrada no registro #${verificacao.falha?.id}`}
                                </p>
                                <p className="text-xs">
                                    {verificacao.integra
                                        ? `Último hash ${verificacao.ultimo_hash}`
                                        : verificacao.falha?.motivo}
                                </p>
                                <p className="text-[11px] opacity-70">
                                    Verificado {dataHora(verificacao.em)}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-suave">
                            Cada registro guarda o hash do anterior. A
                            verificação recalcula a cadeia inteira e acusa
                            qualquer alteração feita direto no banco.
                        </p>
                    )}
                    <Botao
                        tom="marinho"
                        largo
                        carregando={verificando}
                        onClick={() =>
                            router.post(
                                '/gestao/auditoria/verificar',
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setVerificando(true),
                                    onFinish: () => setVerificando(false),
                                },
                            )
                        }
                        disabled={!auth.user}
                    >
                        <ShieldCheck /> Verificar a cadeia agora
                    </Botao>
                </Cartao>

                <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({ busca: busca || undefined });
                        }}
                    >
                        <Entrada
                            icone={<Search />}
                            placeholder="Buscar por apto, nome ou ação"
                            value={busca}
                            onChange={(e) => setBusca(e.target.value)}
                        />
                    </form>
                    <Escolha
                        className="w-32 sm:w-40"
                        value={filtro.acao ?? ''}
                        onChange={(e) =>
                            filtrar({ acao: e.target.value || undefined })
                        }
                    >
                        <option value="">Todas</option>
                        {acoes.map((a) => (
                            <option key={a} value={a}>
                                {nomesAcoes[a] ?? a}
                            </option>
                        ))}
                    </Escolha>
                </div>

                <Cartao className="divide-y divide-linha py-1">
                    {registros.data.map((r) => {
                        const expandido = aberto === r.id;

                        return (
                            <div key={r.id} className="py-2.5">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setAberto(expandido ? null : r.id)
                                    }
                                    className="flex w-full items-start gap-3 text-left"
                                >
                                    <span className="mt-0.5 w-10 shrink-0 font-mono text-[11px] text-suave">
                                        #{r.id}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-bold">
                                            {r.acao}
                                        </span>
                                        <span className="block truncate text-xs text-suave">
                                            {r.quem} · {dataHora(r.quando)}
                                            {r.entidade &&
                                                ` · ${r.entidade} ${r.entidade_id ?? ''}`}
                                        </span>
                                    </span>
                                    <ChevronDown
                                        className={cn(
                                            'mt-1 size-4 shrink-0 text-suave transition',
                                            expandido && 'rotate-180',
                                        )}
                                    />
                                </button>
                                {expandido && (
                                    <div className="mt-2 space-y-2 rounded-xl bg-[#f7f8fb] p-3 text-xs">
                                        {r.antes && (
                                            <Json
                                                rotulo="Antes"
                                                valor={r.antes}
                                            />
                                        )}
                                        {r.depois && (
                                            <Json
                                                rotulo="Depois"
                                                valor={r.depois}
                                            />
                                        )}
                                        <p className="font-mono break-all text-suave">
                                            IP {r.ip ?? '—'}
                                        </p>
                                        <p className="font-mono break-all text-suave">
                                            hash {r.hash}
                                        </p>
                                        <p className="font-mono break-all text-suave">
                                            anterior{' '}
                                            {r.hash_anterior ??
                                                '(início da cadeia)'}
                                        </p>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </Cartao>

                <div className="flex items-center justify-between">
                    {registros.prev_page_url ? (
                        <Botao asChild tom="branco" tamanho="sm">
                            <Link href={registros.prev_page_url} preserveScroll>
                                Mais recentes
                            </Link>
                        </Botao>
                    ) : (
                        <span />
                    )}
                    <span className="text-xs text-suave">
                        página {registros.current_page} de {registros.last_page}
                    </span>
                    {registros.next_page_url ? (
                        <Botao asChild tom="branco" tamanho="sm">
                            <Link href={registros.next_page_url} preserveScroll>
                                Mais antigos
                            </Link>
                        </Botao>
                    ) : (
                        <span />
                    )}
                </div>
            </div>
        </>
    );
}

function Json({
    rotulo,
    valor,
}: {
    rotulo: string;
    valor: Record<string, unknown>;
}) {
    return (
        <div>
            <p className="mb-0.5 font-bold text-suave">{rotulo}</p>
            <pre className="overflow-x-auto rounded-lg bg-white p-2 font-mono text-[11px] whitespace-pre-wrap">
                {JSON.stringify(valor, null, 2)}
            </pre>
        </div>
    );
}

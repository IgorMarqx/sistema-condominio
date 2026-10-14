import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeftRight,
    ArrowUp,
    FileText,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import {
    data,
    diaMes,
    dinheiro,
    dinheiroSinal,
    dataHora,
    somarMes,
    valor,
} from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { Lancamento, MesCaixa } from '@/types';
import Botao from './botao';
import Cartao from './cartao';
import Folha from './folha';
import Selo from './selo';

/**
 * Extrato agrupado por mês ("Outubro  +R$ 729,55  −R$ 650,61"), como no mockup do caixa.
 */
export default function ExtratoCaixa({
    meses,
    base,
    acoes,
}: {
    meses: MesCaixa[];
    base: string;
    acoes?: (lancamento: Lancamento, fechar: () => void) => React.ReactNode;
}) {
    const [aberto, setAberto] = useState<Lancamento | null>(null);
    const ultimo = meses.at(-1);

    return (
        <div className="space-y-5">
            {meses.map((mes) => (
                <section key={mes.mes}>
                    <div className="mb-2 flex items-end justify-between gap-2 px-1">
                        <h2 className="text-[15px] font-extrabold">
                            {mes.mes_rotulo}
                            {mes.fechado && (
                                <span className="ml-2 text-[11px] font-bold text-emerald-600">
                                    fechado
                                </span>
                            )}
                        </h2>
                        <p className="tabular text-xs font-bold">
                            <span className="text-emerald-600">
                                {dinheiroSinal(mes.entradas)}
                            </span>
                            <span className="ml-2 text-red-600">
                                −R$ {valor(mes.saidas)}
                            </span>
                        </p>
                    </div>
                    <Cartao className="divide-y divide-linha py-1">
                        {mes.lancamentos.length === 0 && (
                            <p className="py-5 text-center text-sm text-suave">
                                Sem lançamentos.
                            </p>
                        )}
                        {mes.lancamentos.map((l) => (
                            <LinhaLancamento
                                key={l.id}
                                lancamento={l}
                                aoAbrir={() => setAberto(l)}
                            />
                        ))}
                    </Cartao>
                </section>
            ))}

            {ultimo && (
                <Botao asChild tom="branco" largo>
                    <Link
                        href={`${base}?ate=${somarMes(ultimo.mes, -1)}`}
                        preserveScroll={false}
                    >
                        Ver meses anteriores
                    </Link>
                </Botao>
            )}

            <Folha
                aberta={aberto !== null}
                aoMudar={(v) => !v && setAberto(null)}
                titulo={aberto ? `Lançamento nº ${aberto.id}` : ''}
                descricao={aberto?.descricao}
            >
                {aberto && (
                    <div className="space-y-4 pb-2">
                        <p
                            className={cn(
                                'tabular text-3xl font-extrabold',
                                aberto.tipo === 'entrada'
                                    ? 'text-emerald-600'
                                    : 'text-red-600',
                            )}
                        >
                            {aberto.tipo === 'entrada' ? '+' : '−'}{' '}
                            {dinheiro(aberto.valor)}
                        </p>
                        <dl className="divide-y divide-linha rounded-2xl border border-linha px-4 text-sm">
                            <Item rotulo="Data" valor={data(aberto.data)} />
                            <Item rotulo="Conta" valor={aberto.conta_nome} />
                            <Item
                                rotulo="Categoria"
                                valor={aberto.categoria_rotulo}
                            />
                            {aberto.autor && (
                                <Item
                                    rotulo="Lançado por"
                                    valor={aberto.autor}
                                />
                            )}
                            {aberto.criado_em && (
                                <Item
                                    rotulo="Registrado em"
                                    valor={dataHora(aberto.criado_em)}
                                />
                            )}
                            {aberto.estorno_de_id && (
                                <Item
                                    rotulo="Estorna"
                                    valor={`lançamento nº ${aberto.estorno_de_id}`}
                                />
                            )}
                            {aberto.motivo && (
                                <Item rotulo="Motivo" valor={aberto.motivo} />
                            )}
                        </dl>
                        {aberto.estornado && (
                            <p className="rounded-xl bg-marinho-50 px-3 py-2 text-xs font-semibold text-suave">
                                Este lançamento foi estornado. Ele continua no
                                extrato; o estorno anula o valor.
                            </p>
                        )}
                        {aberto.pode_ver_comprovante && (
                            <Botao asChild tom="branco" largo>
                                <a
                                    href={`/arquivos/lancamento/${aberto.id}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText /> Ver comprovante
                                </a>
                            </Botao>
                        )}
                        {acoes?.(aberto, () => setAberto(null))}
                    </div>
                )}
            </Folha>
        </div>
    );
}

function LinhaLancamento({
    lancamento: l,
    aoAbrir,
}: {
    lancamento: Lancamento;
    aoAbrir: () => void;
}) {
    const estorno = l.estorno_de_id !== null;
    const transferencia = l.categoria === 'transferencia';
    const Icone = estorno
        ? RotateCcw
        : transferencia
          ? ArrowLeftRight
          : l.tipo === 'entrada'
            ? ArrowUp
            : ArrowDown;

    const apoio = [
        diaMes(l.data),
        estorno
            ? `ref. lançamento nº ${l.estorno_de_id}`
            : l.origem === 'pagamento'
              ? 'comprovante anexo'
              : l.categoria_rotulo.toLowerCase(),
    ].join(' · ');

    return (
        <button
            type="button"
            onClick={aoAbrir}
            className="flex w-full items-center gap-3 py-3 text-left active:opacity-70"
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-full',
                    estorno || transferencia
                        ? 'bg-marinho-50 text-suave'
                        : l.tipo === 'entrada'
                          ? 'bg-emerald-100 text-emerald-600'
                          : 'bg-red-100 text-red-600',
                )}
            >
                <Icone className="size-4" strokeWidth={2.4} />
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block truncate text-sm font-bold',
                        l.estornado && 'text-suave line-through',
                    )}
                >
                    {l.descricao}
                </span>
                <span className="block truncate text-xs text-suave">
                    {apoio}
                </span>
            </span>
            <span className="flex shrink-0 flex-col items-end">
                <span
                    className={cn(
                        'tabular text-sm font-extrabold',
                        l.estornado || estorno
                            ? 'text-suave'
                            : l.tipo === 'entrada'
                              ? 'text-emerald-600'
                              : 'text-red-600',
                    )}
                >
                    {l.tipo === 'entrada' ? '+' : '−'} {valor(l.valor)}
                </span>
                {l.estornado && (
                    <Selo tom="neutro" className="mt-0.5 px-1.5 text-[10px]">
                        estornado
                    </Selo>
                )}
            </span>
        </button>
    );
}

function Item({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="flex items-start justify-between gap-3 py-2.5">
            <dt className="shrink-0 text-suave">{rotulo}</dt>
            <dd className="text-right font-bold">{valor}</dd>
        </div>
    );
}

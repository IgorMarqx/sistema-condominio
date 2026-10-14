import { Head } from '@inertiajs/react';
import { Droplet, FileText, Zap } from 'lucide-react';
import { useState } from 'react';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import DetalheFatura from '@/components/condominio/detalhe-fatura';
import GraficoBarras from '@/components/condominio/grafico-barras';
import Selo from '@/components/condominio/selo';
import SeloFatura from '@/components/condominio/selo-fatura';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import { diaMes, dinheiro, numero } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { ConcessionariaId, Fatura, PontoConsumo } from '@/types';

type Painel = {
    medidor: { identificador: string; descricao: string | null };
    unidade_medida: string;
    ultima: Fatura | null;
    variacao: number | null;
    historico: PontoConsumo[];
    faturas: Fatura[];
} | null;

export default function Condominio({
    paineis,
}: {
    paineis: Record<ConcessionariaId, Painel>;
}) {
    const [aba, setAba] = useState<ConcessionariaId>('energisa');
    const [aberta, setAberta] = useState<Fatura | null>(null);
    const painel = paineis[aba];

    return (
        <>
            <Head title="Contas do condomínio" />
            <TopoTela
                contexto="Medidores das áreas comuns"
                titulo="Contas do condomínio"
            />

            <div className="space-y-4 px-4">
                <div className="grid grid-cols-2 gap-1 rounded-2xl border border-linha bg-white p-1">
                    {(['energisa', 'cagepa'] as const).map((c) => {
                        const Icone = c === 'energisa' ? Zap : Droplet;
                        const ativa = aba === c;

                        return (
                            <button
                                key={c}
                                type="button"
                                onClick={() => setAba(c)}
                                className={cn(
                                    'flex h-10 items-center justify-center gap-2 rounded-xl text-sm font-bold transition',
                                    ativa
                                        ? 'bg-marinho-900 text-white shadow'
                                        : 'text-suave',
                                )}
                            >
                                <Icone
                                    className={cn(
                                        'size-4',
                                        ativa &&
                                            c === 'energisa' &&
                                            'text-ouro-500',
                                    )}
                                />
                                {c === 'energisa' ? 'Energia' : 'Água'}
                            </button>
                        );
                    })}
                </div>

                {!painel ? (
                    <Cartao>
                        <Vazio
                            icone={aba === 'energisa' ? Zap : Droplet}
                            titulo="Medidor não cadastrado"
                        >
                            O síndico ainda não cadastrou o medidor de{' '}
                            {aba === 'energisa' ? 'energia' : 'água'} do
                            condomínio.
                        </Vazio>
                    </Cartao>
                ) : (
                    <>
                        <Cartao>
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-xs font-semibold text-suave">
                                    {aba === 'energisa'
                                        ? 'Energia · UC'
                                        : 'Água · matrícula'}{' '}
                                    {painel.medidor.identificador}
                                </p>
                                <SeloVariacao variacao={painel.variacao} />
                            </div>
                            {painel.ultima ? (
                                <p className="mt-1 text-2xl font-extrabold tracking-tight">
                                    {numero(painel.ultima.consumo)}{' '}
                                    {painel.unidade_medida}
                                    <span className="ml-1.5 text-sm font-semibold text-suave">
                                        em {painel.ultima.mes_nome}
                                    </span>
                                </p>
                            ) : (
                                <p className="mt-1 text-sm text-suave">
                                    Nenhuma fatura ainda.
                                </p>
                            )}
                            <GraficoBarras
                                className="mt-4"
                                barras={painel.historico.map((p, i) => ({
                                    rotulo: p.rotulo,
                                    valor: p.consumo,
                                    destaque: i === painel.historico.length - 1,
                                    titulo:
                                        p.consumo !== null
                                            ? `${numero(p.consumo)} ${painel.unidade_medida} · ${dinheiro(p.valor)}`
                                            : 'sem fatura',
                                }))}
                            />
                        </Cartao>

                        <section>
                            <TituloSecao acao="Todas com PDF original">
                                Faturas
                            </TituloSecao>
                            <Cartao className="divide-y divide-linha py-1">
                                {painel.faturas.length === 0 && (
                                    <Vazio
                                        icone={FileText}
                                        titulo="Sem faturas"
                                        className="py-6"
                                    />
                                )}
                                {painel.faturas.map((f) => (
                                    <button
                                        key={f.id}
                                        type="button"
                                        onClick={() => setAberta(f)}
                                        className="flex w-full items-center gap-3 py-3 text-left"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-extrabold">
                                                {f.mes_rotulo}
                                            </p>
                                            <p className="truncate text-xs text-suave">
                                                Leitura{' '}
                                                {diaMes(f.leitura_anterior)} →{' '}
                                                {diaMes(f.leitura_atual)} ·
                                                vence {diaMes(f.vencimento)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <span className="tabular text-sm font-extrabold">
                                                {dinheiro(f.valor)}
                                            </span>
                                            <SeloFatura fatura={f} />
                                        </div>
                                    </button>
                                ))}
                            </Cartao>
                        </section>
                    </>
                )}
            </div>

            <DetalheFatura fatura={aberta} aoFechar={() => setAberta(null)} />
        </>
    );
}

export function SeloVariacao({
    variacao,
    limite = 30,
}: {
    variacao: number | null;
    limite?: number;
}) {
    if (variacao === null) {
        return null;
    }

    if (variacao > limite) {
        return <Selo tom="alerta">+{variacao}% da média</Selo>;
    }

    if (variacao < -limite) {
        return <Selo tom="conferir">{variacao}% da média</Selo>;
    }

    return <Selo tom="pago">Normal</Selo>;
}

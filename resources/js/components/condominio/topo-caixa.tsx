import { Download, ShieldCheck, TriangleAlert } from 'lucide-react';
import { nomeMes, dinheiro } from '@/lib/formato';
import ValorGrande from './valor-grande';

export type DadosTopoCaixa = {
    saldo: number;
    contas: { id: number; slug: string; nome: string; saldo: number }[];
    ultimo_fechamento: {
        mes: string;
        mes_nome: string;
        diferenca: number;
    } | null;
};

/**
 * Topo azul do caixa: saldo total, contas e o selo do último mês fechado.
 */
export default function TopoCaixa({
    saldo,
    contas,
    ultimo_fechamento,
    acao,
}: DadosTopoCaixa & { acao?: React.ReactNode }) {
    return (
        <>
            <header className="topo-marinho area-segura-topo rounded-b-[28px] px-5 pt-6 pb-5 text-white lg:mt-6 lg:rounded-3xl">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-extrabold">Caixa</h1>
                    {acao ?? (
                        <a
                            href="/caixa/extrato.pdf"
                            className="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                        >
                            <Download className="size-3.5" /> Extrato PDF
                        </a>
                    )}
                </div>
                <p className="mt-4 text-xs font-semibold text-white/70">
                    Saldo total
                </p>
                <ValorGrande centavos={saldo} className="text-white" />
                <div className="mt-4 grid grid-cols-2 gap-2.5">
                    {contas.map((c) => (
                        <div
                            key={c.id}
                            className={
                                c.slug === 'reserva'
                                    ? 'rounded-2xl bg-ouro-500/12 p-3 ring-1 ring-ouro-500/40'
                                    : 'rounded-2xl bg-white/8 p-3 ring-1 ring-white/12'
                            }
                        >
                            <p
                                className={
                                    c.slug === 'reserva'
                                        ? 'text-[11px] font-bold text-ouro-300'
                                        : 'text-[11px] font-bold text-white/70'
                                }
                            >
                                {c.nome}
                            </p>
                            <p className="tabular text-[15px] font-extrabold">
                                {dinheiro(c.saldo)}
                            </p>
                        </div>
                    ))}
                </div>
            </header>

            {ultimo_fechamento && (
                <div
                    className={
                        ultimo_fechamento.diferenca === 0
                            ? 'mx-4 mt-3 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs font-bold text-emerald-700'
                            : 'mx-4 mt-3 flex items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2.5 text-xs font-bold text-orange-700'
                    }
                >
                    {ultimo_fechamento.diferenca === 0 ? (
                        <ShieldCheck className="size-4" />
                    ) : (
                        <TriangleAlert className="size-4" />
                    )}
                    <span>
                        {nomeMes(ultimo_fechamento.mes).replace(/^./, (l) =>
                            l.toUpperCase(),
                        )}{' '}
                        fechado ·{' '}
                        {ultimo_fechamento.diferenca === 0
                            ? 'saldo do banco confere'
                            : `diferença de ${dinheiro(ultimo_fechamento.diferenca)} explicada no fechamento`}
                    </span>
                </div>
            )}
        </>
    );
}

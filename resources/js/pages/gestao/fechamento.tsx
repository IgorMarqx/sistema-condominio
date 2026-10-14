import { Head, useForm, usePage } from '@inertiajs/react';
import { FileText, Lock, ShieldCheck, TriangleAlert } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { AreaTexto, Campo, Entrada } from '@/components/condominio/campo';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import { dataHora, dinheiro, valor } from '@/lib/formato';

type Props = {
    previa: {
        mes: string;
        mes_rotulo: string;
        contas: Record<string, { nome: string; calculado: number }>;
        total: number;
        movimento: { entradas: number; saidas: number };
        pode_fechar: boolean;
        motivo: string | null;
    } | null;
    fechamentos: {
        id: number;
        mes: string;
        mes_rotulo: string;
        saldo_calculado: number;
        saldo_banco: number;
        diferenca: number;
        observacao: string | null;
        hash_auditoria: string | null;
        fechado_em: string;
        fechado_por: string | null;
        tem_pdf: boolean;
    }[];
};

function paraCentavos(texto: string): number | null {
    const limpo = texto.replace(/[^\d,.-]/g, '');

    if (!limpo) {
        return null;
    }

    const normalizado = /,\d{1,2}$/.test(limpo)
        ? limpo.replace(/\./g, '').replace(',', '.')
        : limpo.replace(/,/g, '');
    const numero = Number(normalizado);

    return Number.isFinite(numero) ? Math.round(numero * 100) : null;
}

export default function Fechamento({ previa, fechamentos }: Props) {
    const { auth } = usePage().props;
    const contas = previa ? Object.entries(previa.contas) : [];
    const form = useForm<{
        mes: string;
        saldos: Record<string, string>;
        observacao: string;
    }>({
        mes: previa?.mes ?? '',
        saldos: Object.fromEntries(contas.map(([slug]) => [slug, ''])),
        observacao: '',
    });

    const banco = contas.reduce(
        (soma, [slug]) =>
            soma + (paraCentavos(form.data.saldos[slug] ?? '') ?? 0),
        0,
    );
    const preenchido = contas.every(
        ([slug]) => paraCentavos(form.data.saldos[slug] ?? '') !== null,
    );
    const diferenca = previa ? banco - previa.total : 0;

    return (
        <>
            <Head title="Fechamento do mês" />
            <TopoTela
                titulo="Fechamento e conciliação"
                contexto="Saldo calculado × extrato do banco"
            />

            <div className="space-y-4 px-4 lg:px-0">
                {!previa ? (
                    <Cartao className="text-sm text-suave">
                        Ainda não há lançamentos no caixa.
                    </Cartao>
                ) : (
                    <Cartao className="space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="text-lg font-extrabold">
                                {previa.mes_rotulo}
                            </p>
                            <Selo
                                tom={previa.pode_fechar ? 'aberto' : 'neutro'}
                            >
                                {previa.pode_fechar
                                    ? 'Pronto para fechar'
                                    : 'Ainda não'}
                            </Selo>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-sm">
                            <div className="rounded-xl bg-emerald-50 p-3">
                                <p className="text-xs font-semibold text-emerald-700">
                                    Entradas
                                </p>
                                <p className="tabular font-extrabold text-emerald-700">
                                    {dinheiro(previa.movimento.entradas)}
                                </p>
                            </div>
                            <div className="rounded-xl bg-red-50 p-3">
                                <p className="text-xs font-semibold text-red-700">
                                    Saídas
                                </p>
                                <p className="tabular font-extrabold text-red-700">
                                    {dinheiro(previa.movimento.saidas)}
                                </p>
                            </div>
                        </div>

                        {!previa.pode_fechar && previa.motivo && (
                            <p className="rounded-xl bg-marinho-50 px-3 py-2 text-xs font-semibold text-suave">
                                {previa.motivo}
                            </p>
                        )}

                        <div className="space-y-3">
                            {contas.map(([slug, conta]) => (
                                <div
                                    key={slug}
                                    className="grid grid-cols-2 items-end gap-3"
                                >
                                    <div>
                                        <p className="text-xs font-semibold text-suave">
                                            {conta.nome} · calculado
                                        </p>
                                        <p className="tabular text-lg font-extrabold">
                                            {dinheiro(conta.calculado)}
                                        </p>
                                    </div>
                                    <Campo
                                        rotulo="Extrato do banco"
                                        erro={
                                            form.errors[
                                                `saldos.${slug}` as keyof typeof form.errors
                                            ]
                                        }
                                    >
                                        <Entrada
                                            inputMode="decimal"
                                            placeholder={valor(conta.calculado)}
                                            disabled={
                                                !previa.pode_fechar ||
                                                !auth.user?.pode_editar
                                            }
                                            value={form.data.saldos[slug] ?? ''}
                                            onChange={(e) =>
                                                form.setData('saldos', {
                                                    ...form.data.saldos,
                                                    [slug]: e.target.value,
                                                })
                                            }
                                        />
                                    </Campo>
                                </div>
                            ))}
                        </div>

                        {preenchido && (
                            <div
                                className={
                                    diferenca === 0
                                        ? 'flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm font-bold text-emerald-700'
                                        : 'flex items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2.5 text-sm font-bold text-orange-700'
                                }
                            >
                                {diferenca === 0 ? (
                                    <ShieldCheck className="size-4" />
                                ) : (
                                    <TriangleAlert className="size-4" />
                                )}
                                {diferenca === 0
                                    ? 'Saldo do banco confere com o calculado.'
                                    : `Diferença de ${dinheiro(diferenca)}: explique abaixo.`}
                            </div>
                        )}

                        {preenchido && diferenca !== 0 && (
                            <Campo
                                rotulo="Observação"
                                erro={form.errors.observacao}
                            >
                                <AreaTexto
                                    value={form.data.observacao}
                                    onChange={(e) =>
                                        form.setData(
                                            'observacao',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ex.: tarifa bancária ainda não lançada"
                                />
                            </Campo>
                        )}

                        {auth.user?.pode_editar && (
                            <Botao
                                tom="marinho"
                                tamanho="lg"
                                largo
                                disabled={!previa.pode_fechar || !preenchido}
                                carregando={form.processing}
                                onClick={() =>
                                    form.post('/gestao/fechamento', {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Lock /> Fechar {previa.mes_rotulo}
                            </Botao>
                        )}
                        <p className="text-xs text-suave">
                            Fechar trava o mês: nenhum lançamento com data nele
                            pode ser criado depois. A prestação de contas em PDF
                            vai para todas as unidades.
                        </p>
                    </Cartao>
                )}

                {fechamentos.length > 0 && (
                    <section>
                        <TituloSecao>Meses fechados</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {fechamentos.map((f) => (
                                <div key={f.id} className="space-y-1 py-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-extrabold">
                                            {f.mes_rotulo}
                                        </p>
                                        <Selo
                                            tom={
                                                f.diferenca === 0
                                                    ? 'pago'
                                                    : 'alerta'
                                            }
                                        >
                                            {f.diferenca === 0
                                                ? 'Conciliado'
                                                : `Diferença ${dinheiro(f.diferenca)}`}
                                        </Selo>
                                    </div>
                                    <p className="text-xs text-suave">
                                        Calculado {dinheiro(f.saldo_calculado)}{' '}
                                        · banco {dinheiro(f.saldo_banco)} ·{' '}
                                        {f.fechado_por} em{' '}
                                        {dataHora(f.fechado_em)}
                                    </p>
                                    {f.observacao && (
                                        <p className="text-xs text-orange-700">
                                            {f.observacao}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-between gap-2 pt-1">
                                        <span
                                            className="min-w-0 truncate font-mono text-[10px] text-suave"
                                            title={f.hash_auditoria ?? ''}
                                        >
                                            hash{' '}
                                            {f.hash_auditoria?.slice(0, 20)}…
                                        </span>
                                        {f.tem_pdf && (
                                            <a
                                                href={`/arquivos/prestacao/${f.id}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex shrink-0 items-center gap-1 text-xs font-bold text-marinho-700"
                                            >
                                                <FileText className="size-3.5" />{' '}
                                                Prestação de contas
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>
        </>
    );
}

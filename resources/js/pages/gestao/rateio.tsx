import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Calculator,
    Check,
    ChevronLeft,
    ChevronRight,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import {
    AreaTexto,
    Campo,
    Entrada,
    Escolha,
} from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import {
    data,
    dataHora,
    diaMes,
    dinheiro,
    somarMes,
    valor,
} from '@/lib/formato';
import { cn } from '@/lib/utils';

type Calculo = {
    regra: 'igual' | 'fracao';
    total_faturas: number;
    contribuicao: number;
    total_geral: number;
    vencimento: string;
    resto: number;
    linhas: {
        tipo: string;
        descricao: string;
        total: number;
        fatura_id: number | null;
    }[];
    unidades: Record<
        string,
        {
            numero: string;
            itens: { tipo: string; descricao: string; valor: number }[];
            total: number;
        }
    >;
    recebem_resto: string[];
};

type Props = {
    mes: string;
    mes_rotulo: string;
    disponiveis: {
        id: number;
        servico: string;
        concessionaria: string;
        mes_rotulo: string;
        valor: number;
        vencimento: string | null;
    }[];
    rateio: {
        id: number;
        status: 'rascunho' | 'confirmado';
        vencimento: string;
        confirmado_em: string | null;
        confirmado_por: string | null;
        detalhe: Calculo;
        fatura_ids: number[];
    } | null;
    previa: Calculo | null;
    selecionadas: number[];
    ajustes_pendentes: {
        id: number;
        unidade: string;
        descricao: string;
        valor: number;
        motivo: string;
    }[];
    unidades: string[];
};

export default function Rateio(props: Props) {
    const { auth } = usePage().props;
    const podeEditar = auth.user?.pode_editar ?? false;
    const confirmado = props.rateio?.status === 'confirmado';
    const calculo = confirmado ? props.rateio!.detalhe : props.previa;
    const [selecionadas, setSelecionadas] = useState<number[]>(
        props.selecionadas,
    );
    const [vencimento, setVencimento] = useState(
        props.previa?.vencimento ?? props.rateio?.vencimento ?? '',
    );
    const [ajuste, setAjuste] = useState(false);
    const [enviando, setEnviando] = useState(false);

    const recalcular = (ids: number[], venc = vencimento) => {
        setSelecionadas(ids);
        router.get(
            '/gestao/rateio',
            {
                mes: props.mes,
                faturas: ids.length ? ids : [0],
                vencimento: venc || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['previa', 'selecionadas'],
            },
        );
    };

    return (
        <>
            <Head title="Rateio do mês" />
            <TopoTela
                titulo="Rateio do mês"
                contexto="Contas do condomínio divididas entre as unidades"
            />

            <div className="space-y-4 px-4 lg:px-0">
                <div className="flex items-center justify-between rounded-2xl border border-linha bg-white p-1.5">
                    <Link
                        href={`/gestao/rateio?mes=${somarMes(props.mes, -1)}`}
                        className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                        aria-label="Mês anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Link>
                    <span className="flex items-center gap-2 text-sm font-extrabold">
                        Cobrança de {props.mes_rotulo}
                        {props.rateio && (
                            <Selo tom={confirmado ? 'pago' : 'aberto'}>
                                {confirmado ? 'Confirmado' : 'Rascunho'}
                            </Selo>
                        )}
                    </span>
                    <Link
                        href={`/gestao/rateio?mes=${somarMes(props.mes, 1)}`}
                        className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                        aria-label="Próximo mês"
                    >
                        <ChevronRight className="size-4" />
                    </Link>
                </div>

                {confirmado && (
                    <Cartao className="flex items-center gap-3 border-emerald-200 bg-emerald-50 text-sm text-emerald-800">
                        <Check className="size-5 shrink-0" />
                        <span>
                            Confirmado por{' '}
                            <strong>{props.rateio!.confirmado_por}</strong> em{' '}
                            {dataHora(props.rateio!.confirmado_em)}. Travado:
                            correção só por ajuste no mês seguinte.
                        </span>
                    </Cartao>
                )}

                {!confirmado && (
                    <section>
                        <TituloSecao>Faturas confirmadas</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.disponiveis.length === 0 && (
                                <Vazio
                                    icone={Calculator}
                                    titulo="Nenhuma fatura para ratear"
                                    className="py-6"
                                >
                                    Confirme as faturas do condomínio na tela de
                                    Faturas.
                                </Vazio>
                            )}
                            {props.disponiveis.map((f) => {
                                const marcada = selecionadas.includes(f.id);

                                return (
                                    <label
                                        key={f.id}
                                        className="flex items-center gap-3 py-3"
                                    >
                                        <input
                                            type="checkbox"
                                            className="size-5 accent-marinho-900"
                                            checked={marcada}
                                            disabled={!podeEditar}
                                            onChange={() =>
                                                recalcular(
                                                    marcada
                                                        ? selecionadas.filter(
                                                              (id) =>
                                                                  id !== f.id,
                                                          )
                                                        : [
                                                              ...selecionadas,
                                                              f.id,
                                                          ],
                                                )
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-bold">
                                                {f.servico} · {f.mes_rotulo}
                                            </span>
                                            <span className="block text-xs text-suave">
                                                {f.concessionaria} · vence{' '}
                                                {diaMes(f.vencimento)}
                                            </span>
                                        </span>
                                        <span className="tabular text-sm font-extrabold">
                                            {dinheiro(f.valor)}
                                        </span>
                                    </label>
                                );
                            })}
                        </Cartao>
                    </section>
                )}

                {calculo && (
                    <>
                        <Cartao className="space-y-3">
                            <ul className="space-y-1.5 text-sm">
                                {calculo.linhas.map((l, i) => (
                                    <li
                                        key={i}
                                        className="flex justify-between gap-3"
                                    >
                                        <span className="min-w-0 text-suave">
                                            {l.descricao}
                                        </span>
                                        <span className="tabular shrink-0 font-bold whitespace-nowrap">
                                            {dinheiro(l.total)}
                                        </span>
                                    </li>
                                ))}
                                <li className="flex justify-between gap-3 border-t border-linha pt-1.5 font-extrabold">
                                    <span>Total cobrado das unidades</span>
                                    <span className="tabular">
                                        {dinheiro(calculo.total_geral)}
                                    </span>
                                </li>
                            </ul>
                            <p className="rounded-xl bg-marinho-50 px-3 py-2 text-xs text-suave">
                                Regra:{' '}
                                {calculo.regra === 'igual'
                                    ? `divisão igual entre ${Object.keys(calculo.unidades).length} unidades`
                                    : 'fração ideal'}
                                .{' '}
                                {calculo.resto > 0
                                    ? `Resto de ${calculo.resto} centavo${calculo.resto > 1 ? 's' : ''}: +0,01 para ${calculo.recebem_resto.join(', ')} (ordem fixa).`
                                    : 'Divisão exata, sem resto.'}
                            </p>
                            {!confirmado && podeEditar ? (
                                <Campo
                                    rotulo="Vencimento interno"
                                    ajuda="Sugestão: vencimento da concessionária − dias configurados."
                                >
                                    <Entrada
                                        type="date"
                                        value={vencimento}
                                        onChange={(e) => {
                                            setVencimento(e.target.value);
                                            recalcular(
                                                selecionadas,
                                                e.target.value,
                                            );
                                        }}
                                    />
                                </Campo>
                            ) : (
                                <p className="text-sm">
                                    Vencimento interno:{' '}
                                    <strong>{data(calculo.vencimento)}</strong>
                                </p>
                            )}
                        </Cartao>

                        <section>
                            <TituloSecao>Por unidade</TituloSecao>
                            <Cartao className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                {Object.values(calculo.unidades).map((u) => (
                                    <div
                                        key={u.numero}
                                        className={cn(
                                            'rounded-xl border p-2.5',
                                            calculo.recebem_resto.includes(
                                                u.numero,
                                            )
                                                ? 'border-ouro-300 bg-ouro-50'
                                                : 'border-linha',
                                        )}
                                        title={u.itens
                                            .map(
                                                (i) =>
                                                    `${i.descricao}: ${valor(i.valor)}`,
                                            )
                                            .join('\n')}
                                    >
                                        <p className="text-xs font-bold text-suave">
                                            Apto {u.numero}
                                        </p>
                                        <p className="tabular text-sm font-extrabold">
                                            {dinheiro(u.total)}
                                        </p>
                                        {u.itens.some(
                                            (i) => i.tipo === 'ajuste',
                                        ) && (
                                            <p className="text-[10px] font-bold text-orange-600">
                                                com ajuste
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </Cartao>
                        </section>
                    </>
                )}

                {props.ajustes_pendentes.length > 0 && (
                    <section>
                        <TituloSecao>Ajustes para o próximo rateio</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.ajustes_pendentes.map((a) => (
                                <div
                                    key={a.id}
                                    className="flex items-center justify-between gap-3 py-2.5 text-sm"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-bold">
                                            Apto {a.unidade} · {a.descricao}
                                        </span>
                                        <span className="block truncate text-xs text-suave">
                                            {a.motivo}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'tabular font-extrabold',
                                            a.valor < 0
                                                ? 'text-emerald-600'
                                                : 'text-red-600',
                                        )}
                                    >
                                        {dinheiro(a.valor)}
                                    </span>
                                </div>
                            ))}
                        </Cartao>
                    </section>
                )}

                {podeEditar && (
                    <div className="space-y-2">
                        {!confirmado && props.rateio?.status === 'rascunho' && (
                            <Botao
                                tamanho="lg"
                                largo
                                carregando={enviando}
                                onClick={() =>
                                    router.post(
                                        `/gestao/rateio/${props.rateio!.id}/confirmar`,
                                        {},
                                        {
                                            onStart: () => setEnviando(true),
                                            onFinish: () => setEnviando(false),
                                        },
                                    )
                                }
                            >
                                <Check /> Confirmar e enviar cobranças
                            </Botao>
                        )}
                        {!confirmado && (
                            <Botao
                                tom={props.rateio ? 'branco' : 'ouro'}
                                tamanho="lg"
                                largo
                                disabled={selecionadas.length === 0}
                                onClick={() =>
                                    router.post(
                                        '/gestao/rateio',
                                        {
                                            mes: props.mes,
                                            faturas: selecionadas,
                                            vencimento: vencimento || null,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Calculator />{' '}
                                {props.rateio
                                    ? 'Atualizar rascunho'
                                    : 'Gerar rascunho do rateio'}
                            </Botao>
                        )}
                        {props.rateio?.status === 'rascunho' && (
                            <Botao
                                tom="fantasma"
                                largo
                                className="text-red-600"
                                onClick={() =>
                                    router.delete(
                                        `/gestao/rateio/${props.rateio!.id}`,
                                    )
                                }
                            >
                                <Trash2 /> Descartar rascunho
                            </Botao>
                        )}
                        <Botao
                            tom="branco"
                            largo
                            onClick={() => setAjuste(true)}
                        >
                            <Plus /> Ajuste (crédito ou débito) para uma unidade
                        </Botao>
                    </div>
                )}
            </div>

            <NovoAjuste
                aberta={ajuste}
                aoMudar={setAjuste}
                unidades={props.unidades}
            />
        </>
    );
}

function NovoAjuste({
    aberta,
    aoMudar,
    unidades,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
    unidades: string[];
}) {
    const form = useForm({
        unidade: unidades[0] ?? '',
        tipo: 'credito',
        valor: '',
        descricao: '',
        motivo: '',
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Ajuste no próximo rateio"
            descricao="Rateio confirmado não muda. A correção entra como linha na próxima cobrança da unidade."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    onClick={() =>
                        form.post('/gestao/ajustes', {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoMudar(false);
                            },
                        })
                    }
                >
                    Registrar ajuste
                </Botao>
            }
        >
            <div className="grid grid-cols-2 gap-3 pb-2">
                <Campo rotulo="Unidade" erro={form.errors.unidade}>
                    <Escolha
                        value={form.data.unidade}
                        onChange={(e) =>
                            form.setData('unidade', e.target.value)
                        }
                    >
                        {unidades.map((u) => (
                            <option key={u} value={u}>
                                {u}
                            </option>
                        ))}
                    </Escolha>
                </Campo>
                <Campo rotulo="Tipo" erro={form.errors.tipo}>
                    <Escolha
                        value={form.data.tipo}
                        onChange={(e) => form.setData('tipo', e.target.value)}
                    >
                        <option value="credito">Crédito (abate)</option>
                        <option value="debito">Débito (soma)</option>
                    </Escolha>
                </Campo>
                <Campo rotulo="Valor (R$)" erro={form.errors.valor}>
                    <Entrada
                        inputMode="decimal"
                        value={form.data.valor}
                        onChange={(e) => form.setData('valor', e.target.value)}
                    />
                </Campo>
                <Campo rotulo="Descrição" erro={form.errors.descricao}>
                    <Entrada
                        placeholder="Ex.: crédito de setembro"
                        value={form.data.descricao}
                        onChange={(e) =>
                            form.setData('descricao', e.target.value)
                        }
                    />
                </Campo>
                <Campo
                    rotulo="Motivo"
                    erro={form.errors.motivo}
                    className="col-span-2"
                >
                    <AreaTexto
                        value={form.data.motivo}
                        onChange={(e) => form.setData('motivo', e.target.value)}
                    />
                </Campo>
            </div>
        </Folha>
    );
}

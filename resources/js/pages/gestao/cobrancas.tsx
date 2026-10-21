import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronDown, FileText, ReceiptText, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import {
    AreaTexto,
    Campo,
    Entrada,
    Escolha,
} from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import { data, diaMes, dinheiro, hojeIso, valor } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { CobrancaDetalhe, Pagamento } from '@/types';

type Props = {
    mes: string | null;
    mes_rotulo: string | null;
    meses: { valor: string; rotulo: string }[];
    cobrancas: (CobrancaDetalhe & { em_aberto: number })[];
    totais: { cobrado: number; recebido: number; aguardando: number };
    inadimplencia: { unidade: string; meses: number; valor: number }[];
};

export default function Cobrancas(props: Props) {
    const { auth } = usePage().props;
    const podeEditar = auth.user?.pode_editar ?? false;
    const [aberta, setAberta] = useState<number | null>(null);
    const [recusar, setRecusar] = useState<Pagamento | null>(null);
    const [receber, setReceber] = useState<Props['cobrancas'][number] | null>(
        null,
    );

    useEffect(() => {
        const alvo = window.location.hash.replace('#apto-', '');
        const cobranca = props.cobrancas.find((c) => c.unidade === alvo);

        if (cobranca) {
            setAberta(cobranca.id);
            document
                .getElementById(`apto-${alvo}`)
                ?.scrollIntoView({ block: 'center' });
        }
    }, [props.cobrancas]);

    return (
        <>
            <Head title="Cobranças" />
            <TopoTela
                titulo="Cobranças"
                contexto={
                    props.mes_rotulo
                        ? `Cobranças de ${props.mes_rotulo}`
                        : 'Nenhuma conta lançada'
                }
                acao={
                    props.meses.length > 1 && (
                        <Escolha
                            className="h-10 w-36 text-sm"
                            value={props.mes ?? ''}
                            onChange={(e) =>
                                router.get(
                                    '/gestao/cobrancas',
                                    { mes: e.target.value },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {props.meses.map((m) => (
                                <option key={m.valor} value={m.valor}>
                                    {m.rotulo}
                                </option>
                            ))}
                        </Escolha>
                    )
                }
            />

            <div className="space-y-4 px-4 lg:px-0">
                {props.cobrancas.length === 0 ? (
                    <Cartao>
                        <Vazio icone={ReceiptText} titulo="Sem cobranças">
                            Lance a conta da Energisa ou da Cagepa no painel
                            para gerar as cobranças das unidades.
                        </Vazio>
                        <Botao asChild largo>
                            <Link href="/gestao">Ir para o painel</Link>
                        </Botao>
                    </Cartao>
                ) : (
                    <>
                        <div className="grid grid-cols-3 gap-2">
                            <Total
                                rotulo="Cobrado"
                                valor={dinheiro(props.totais.cobrado)}
                            />
                            <Total
                                rotulo="Recebido"
                                valor={dinheiro(props.totais.recebido)}
                                cor="text-emerald-600"
                            />
                            <Total
                                rotulo="Conferir"
                                valor={`${props.totais.aguardando} apto${props.totais.aguardando === 1 ? '' : 's'}`}
                                cor="text-sky-700"
                            />
                        </div>

                        <Cartao className="divide-y divide-linha py-1">
                            {props.cobrancas.map((c) => {
                                const expandida = aberta === c.id;
                                const aguardando = c.pagamentos.filter(
                                    (p) => p.status === 'aguardando',
                                );

                                return (
                                    <div
                                        key={c.id}
                                        id={`apto-${c.unidade}`}
                                        className="py-1"
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setAberta(
                                                    expandida ? null : c.id,
                                                )
                                            }
                                            className="flex w-full items-center gap-3 py-2.5 text-left"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-marinho-900 text-sm font-extrabold text-ouro-500">
                                                {c.unidade}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="tabular block text-sm font-extrabold">
                                                    {dinheiro(c.valor)}
                                                </span>
                                                <span className="block truncate text-xs text-suave">
                                                    {c.servico} · vence{' '}
                                                    {diaMes(c.vencimento)}
                                                    {c.em_aberto > 0 &&
                                                        c.em_aberto < c.valor &&
                                                        ` · falta ${dinheiro(c.em_aberto)}`}
                                                </span>
                                            </span>
                                            <SeloCobranca c={c} />
                                            <ChevronDown
                                                className={cn(
                                                    'size-4 text-suave transition',
                                                    expandida && 'rotate-180',
                                                )}
                                            />
                                        </button>

                                        {expandida && (
                                            <div className="mb-2 space-y-3 rounded-xl bg-[#f7f8fb] p-3">
                                                <ul className="space-y-1 text-xs">
                                                    {c.itens.map((i, n) => (
                                                        <li
                                                            key={n}
                                                            className="flex justify-between gap-3"
                                                        >
                                                            <span className="text-suave">
                                                                {i.descricao}
                                                            </span>
                                                            <span className="tabular font-bold">
                                                                {valor(i.valor)}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                                {c.pagamentos.map((p) => (
                                                    <div
                                                        key={p.id}
                                                        className="rounded-xl border border-linha bg-white p-3"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-bold">
                                                                    {dinheiro(
                                                                        p.valor,
                                                                    )}{' '}
                                                                    · pago em{' '}
                                                                    {data(
                                                                        p.pago_em,
                                                                    )}
                                                                </p>
                                                                <p className="truncate text-xs text-suave">
                                                                    {p.nome_informado
                                                                        ? `Enviado por ${p.nome_informado}`
                                                                        : 'Enviado pela unidade'}
                                                                    {p.motivo_recusa &&
                                                                        ` · ${p.motivo_recusa}`}
                                                                </p>
                                                            </div>
                                                            <a
                                                                href={`/arquivos/pagamento/${p.id}`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="flex items-center gap-1 text-xs font-bold text-marinho-700"
                                                            >
                                                                <FileText className="size-4" />{' '}
                                                                Ver
                                                            </a>
                                                        </div>
                                                        {p.status ===
                                                            'aguardando' &&
                                                        podeEditar ? (
                                                            <div className="mt-2 grid grid-cols-2 gap-2">
                                                                <Botao
                                                                    tamanho="sm"
                                                                    tom="branco"
                                                                    onClick={() =>
                                                                        setRecusar(
                                                                            p,
                                                                        )
                                                                    }
                                                                >
                                                                    <X />{' '}
                                                                    Recusar
                                                                </Botao>
                                                                <Botao
                                                                    tamanho="sm"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/gestao/pagamentos/${p.id}/confirmar`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Check />{' '}
                                                                    Confirmar
                                                                </Botao>
                                                            </div>
                                                        ) : (
                                                            <Selo
                                                                className="mt-2"
                                                                tom={
                                                                    p.status ===
                                                                    'confirmado'
                                                                        ? 'pago'
                                                                        : p.status ===
                                                                            'recusado'
                                                                          ? 'atrasado'
                                                                          : 'conferir'
                                                                }
                                                            >
                                                                {p.status ===
                                                                'confirmado'
                                                                    ? 'Confirmado'
                                                                    : p.status ===
                                                                        'recusado'
                                                                      ? 'Recusado'
                                                                      : 'Aguardando'}
                                                            </Selo>
                                                        )}
                                                    </div>
                                                ))}
                                                {aguardando.length === 0 &&
                                                    c.status !== 'paga' &&
                                                    podeEditar && (
                                                        <Botao
                                                            tamanho="sm"
                                                            tom="branco"
                                                            largo
                                                            onClick={() =>
                                                                setReceber(c)
                                                            }
                                                        >
                                                            Registrar
                                                            recebimento sem
                                                            comprovante da
                                                            unidade
                                                        </Botao>
                                                    )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </Cartao>
                    </>
                )}

                {props.inadimplencia.length > 0 && (
                    <section>
                        <TituloSecao>
                            Inadimplência (todos os meses)
                        </TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.inadimplencia.map((i) => (
                                <div
                                    key={i.unidade}
                                    className="flex items-center justify-between py-2.5 text-sm"
                                >
                                    <span className="font-extrabold">
                                        Apto {i.unidade}
                                    </span>
                                    <span className="text-xs text-suave">
                                        {i.meses} cobrança
                                        {i.meses > 1 ? 's' : ''} vencida
                                        {i.meses > 1 ? 's' : ''}
                                    </span>
                                    <span className="tabular font-extrabold text-red-600">
                                        {dinheiro(i.valor)}
                                    </span>
                                </div>
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>

            <Recusar pagamento={recusar} aoFechar={() => setRecusar(null)} />
            {receber && (
                <Receber
                    key={receber.id}
                    cobranca={receber}
                    aoFechar={() => setReceber(null)}
                />
            )}
        </>
    );
}

function SeloCobranca({ c }: { c: CobrancaDetalhe }) {
    if (c.status === 'paga') return <Selo tom="pago">Paga</Selo>;
    if (c.status === 'aguardando_confirmacao')
        return <Selo tom="conferir">Conferir</Selo>;
    if (c.vencida) return <Selo tom="atrasado">Vencida</Selo>;

    return <Selo tom="alerta">Aberta</Selo>;
}

function Total({
    rotulo,
    valor,
    cor,
}: {
    rotulo: string;
    valor: string;
    cor?: string;
}) {
    return (
        <Cartao className="px-3 py-3">
            <p className="text-[11px] font-semibold text-suave">{rotulo}</p>
            <p
                className={cn(
                    'tabular text-[13px] font-extrabold sm:text-[15px]',
                    cor,
                )}
            >
                {valor}
            </p>
        </Cartao>
    );
}

function Recusar({
    pagamento,
    aoFechar,
}: {
    pagamento: Pagamento | null;
    aoFechar: () => void;
}) {
    const form = useForm({ motivo: '' });

    return (
        <Folha
            aberta={pagamento !== null}
            aoMudar={(v) => !v && aoFechar()}
            titulo="Recusar comprovante"
            descricao="A unidade recebe o motivo e pode enviar outro."
            rodape={
                <Botao
                    tom="perigo"
                    tamanho="lg"
                    largo
                    disabled={!form.data.motivo}
                    carregando={form.processing}
                    onClick={() =>
                        pagamento &&
                        form.post(
                            `/gestao/pagamentos/${pagamento.id}/recusar`,
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    aoFechar();
                                },
                            },
                        )
                    }
                >
                    Recusar
                </Botao>
            }
        >
            <Campo rotulo="Motivo" erro={form.errors.motivo} className="pb-2">
                <AreaTexto
                    placeholder="Ex.: o valor não aparece no extrato do dia informado"
                    value={form.data.motivo}
                    onChange={(e) => form.setData('motivo', e.target.value)}
                />
            </Campo>
        </Folha>
    );
}

function Receber({
    cobranca,
    aoFechar,
}: {
    cobranca: Props['cobrancas'][number];
    aoFechar: () => void;
}) {
    const form = useForm<{
        comprovante: File | null;
        pago_em: string;
        valor: string;
    }>({
        comprovante: null,
        pago_em: hojeIso(),
        valor: valor(cobranca.em_aberto),
    });

    return (
        <Folha
            aberta
            aoMudar={(v) => !v && aoFechar()}
            titulo={`Recebimento do apto ${cobranca.unidade}`}
            descricao="Anexe o comprovante (extrato do banco). Entra direto no caixa como confirmado."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    disabled={!form.data.comprovante}
                    carregando={form.processing}
                    onClick={() =>
                        form.post(
                            `/gestao/cobrancas/${cobranca.id}/recebimento`,
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => aoFechar(),
                            },
                        )
                    }
                >
                    Registrar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <SeletorArquivo
                    arquivo={form.data.comprovante}
                    aoEscolher={(f) => form.setData('comprovante', f)}
                    titulo="Comprovante"
                    erro={form.errors.comprovante}
                />
                <div className="grid grid-cols-2 gap-3">
                    <Campo rotulo="Recebido em" erro={form.errors.pago_em}>
                        <Entrada
                            type="date"
                            max={hojeIso()}
                            value={form.data.pago_em}
                            onChange={(e) =>
                                form.setData('pago_em', e.target.value)
                            }
                        />
                    </Campo>
                    <Campo rotulo="Valor (R$)" erro={form.errors.valor}>
                        <Entrada
                            inputMode="decimal"
                            value={form.data.valor}
                            onChange={(e) =>
                                form.setData('valor', e.target.value)
                            }
                        />
                    </Campo>
                </div>
            </div>
        </Folha>
    );
}

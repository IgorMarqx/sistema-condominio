import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    FileText,
    Maximize2,
    Upload,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao from '@/components/condominio/cartao';
import { AreaTexto, Campo, Entrada } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import FormFatura, {
    camposVazios,
    type CamposFatura,
} from '@/components/condominio/form-fatura';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import {
    data,
    dataHora,
    diaMes,
    dinheiro,
    hojeIso,
    numero,
    valor,
} from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { FaturaDetalhe } from '@/types';

type Props = {
    fatura: FaturaDetalhe;
    pagamento: { id: number; data: string; valor: number } | null;
};

export default function RevisarFatura({ fatura, pagamento }: Props) {
    const { auth } = usePage().props;
    const podeEditar = auth.user?.pode_editar ?? false;
    const [corrigir, setCorrigir] = useState(false);
    const [rejeitar, setRejeitar] = useState(false);
    const [pagar, setPagar] = useState(false);
    const [itens, setItens] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    const pendente =
        fatura.status === 'pendente_confirmacao' ||
        fatura.status === 'pendente_revisao';

    return (
        <>
            <Head
                title={`Fatura ${fatura.concessionaria_rotulo} · ${fatura.mes_rotulo}`}
            />
            <TopoTela
                voltar="/gestao/faturas"
                contexto={`${fatura.concessionaria_rotulo} · medidor do condomínio`}
                titulo={
                    pendente
                        ? `Revisar fatura de ${fatura.mes_nome}`
                        : `Fatura de ${fatura.mes_nome}`
                }
                acao={
                    !pendente && (
                        <Selo
                            tom={
                                fatura.status === 'confirmada'
                                    ? 'pago'
                                    : 'atrasado'
                            }
                        >
                            {fatura.status_rotulo}
                        </Selo>
                    )
                }
            />

            <div className="space-y-4 px-4 lg:grid lg:grid-cols-[1.1fr_1fr] lg:items-start lg:gap-5 lg:space-y-0 lg:px-0">
                <PreviaPdf fatura={fatura} podeAnexar={podeEditar} />

                <div className="space-y-4">
                    <Cartao className="px-4 py-1">
                        <dl className="divide-y divide-linha text-sm">
                            <Linha
                                rotulo={
                                    fatura.concessionaria === 'energisa'
                                        ? 'UC'
                                        : 'Matrícula'
                                }
                            >
                                {fatura.medidor.identificador}{' '}
                                <span className="font-semibold text-emerald-600">
                                    · confere
                                </span>
                            </Linha>
                            <Linha rotulo="Competência">
                                {fatura.mes.slice(5, 7)}/
                                {fatura.mes.slice(0, 4)}
                            </Linha>
                            <Linha rotulo="Leituras">
                                {diaMes(fatura.leitura_anterior)} →{' '}
                                {diaMes(fatura.leitura_atual)}
                            </Linha>
                            <Linha rotulo="Próxima leitura">
                                {diaMes(fatura.proxima_leitura)}
                            </Linha>
                            <Linha rotulo="Consumo">
                                {numero(fatura.consumo, 3)}{' '}
                                {fatura.unidade_medida}
                            </Linha>
                            <Linha rotulo="Vencimento">
                                {data(fatura.vencimento)}
                            </Linha>
                            <div className="flex items-center justify-between py-3">
                                <dt className="font-bold text-marinho-700">
                                    Valor total
                                </dt>
                                <dd className="tabular text-lg font-extrabold">
                                    {dinheiro(fatura.valor)}
                                </dd>
                            </div>
                        </dl>
                    </Cartao>

                    {fatura.itens.length > 0 && (
                        <Cartao className="py-1">
                            <button
                                type="button"
                                onClick={() => setItens((v) => !v)}
                                className="flex w-full items-center justify-between py-2.5 text-sm font-bold"
                            >
                                Itens da fatura ({fatura.itens.length})
                                <ChevronDown
                                    className={cn(
                                        'size-4 transition',
                                        itens && 'rotate-180',
                                    )}
                                />
                            </button>
                            {itens && (
                                <ul className="space-y-1.5 border-t border-linha py-2.5 text-sm">
                                    {fatura.itens.map((item, i) => (
                                        <li
                                            key={i}
                                            className="flex justify-between gap-3"
                                        >
                                            <span className="text-suave">
                                                {item.descricao}
                                            </span>
                                            <span className="tabular font-semibold">
                                                {valor(item.valor_centavos)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Cartao>
                    )}

                    <Cartao>
                        <p className="mb-2 text-sm font-extrabold">
                            Verificações automáticas
                        </p>
                        <ul className="space-y-1.5">
                            {fatura.validacoes.map((v) => (
                                <li
                                    key={v.regra}
                                    className="flex items-start gap-2 text-[13px]"
                                >
                                    {v.ok ? (
                                        <Check
                                            className="mt-0.5 size-4 shrink-0 text-emerald-600"
                                            strokeWidth={3}
                                        />
                                    ) : (
                                        <X
                                            className="mt-0.5 size-4 shrink-0 text-orange-600"
                                            strokeWidth={3}
                                        />
                                    )}
                                    <span
                                        className={
                                            v.ok
                                                ? 'text-texto'
                                                : 'font-semibold text-orange-700'
                                        }
                                    >
                                        {v.mensagem}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 border-t border-linha pt-2 text-[11px] text-suave">
                            {fatura.email
                                ? `De ${fatura.email.remetente} · SPF ${fatura.email.spf ?? '—'} · DKIM ${fatura.email.dkim ?? '—'}`
                                : 'Enviada por upload'}
                            {fatura.extrator &&
                                ` · extrator ${fatura.extrator}`}
                            {fatura.confianca !== null &&
                                ` · leitura ${Math.round(fatura.confianca * 100)}%`}
                        </p>
                    </Cartao>

                    {fatura.status === 'confirmada' && (
                        <Cartao className="space-y-2 text-sm">
                            <p>
                                Confirmada por{' '}
                                <strong>{fatura.confirmada_por}</strong> em{' '}
                                {dataHora(fatura.confirmada_em)}.
                                {fatura.rateada && ' Já entrou no rateio.'}
                            </p>
                            {pagamento ? (
                                <p className="font-semibold text-emerald-700">
                                    Paga em {data(pagamento.data)} · lançamento
                                    nº {pagamento.id} no caixa.
                                </p>
                            ) : (
                                podeEditar && (
                                    <Botao
                                        tom="marinho"
                                        largo
                                        onClick={() => setPagar(true)}
                                    >
                                        Registrar pagamento à{' '}
                                        {fatura.concessionaria_rotulo}
                                    </Botao>
                                )
                            )}
                        </Cartao>
                    )}

                    {pendente && podeEditar && (
                        <>
                            <div className="grid grid-cols-2 gap-2">
                                <Botao
                                    tom="branco"
                                    tamanho="lg"
                                    onClick={() => setCorrigir(true)}
                                >
                                    Corrigir campo
                                </Botao>
                                <Botao
                                    tamanho="lg"
                                    carregando={confirmando}
                                    onClick={() =>
                                        router.post(
                                            `/gestao/faturas/${fatura.id}/confirmar`,
                                            {},
                                            {
                                                onStart: () =>
                                                    setConfirmando(true),
                                                onFinish: () =>
                                                    setConfirmando(false),
                                            },
                                        )
                                    }
                                >
                                    Confirmar fatura
                                </Botao>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRejeitar(true)}
                                className="w-full py-1 text-center text-xs font-bold text-red-600"
                            >
                                Rejeitar esta fatura
                            </button>
                        </>
                    )}
                </div>
            </div>

            <Corrigir fatura={fatura} aberta={corrigir} aoMudar={setCorrigir} />
            <Rejeitar fatura={fatura} aberta={rejeitar} aoMudar={setRejeitar} />
            <Pagar fatura={fatura} aberta={pagar} aoMudar={setPagar} />
        </>
    );
}

/**
 * Conta lançada à mão no painel: o PDF pode ser anexado depois, sem mexer nos valores.
 */
function SemPdf({
    fatura,
    podeAnexar,
}: {
    fatura: FaturaDetalhe;
    podeAnexar: boolean;
}) {
    const entrada = useRef<HTMLInputElement>(null);
    const [enviando, setEnviando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);

    return (
        <div className="flex h-40 flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-[#c9d3ea] bg-white px-4 text-center text-sm text-suave">
            <span className="flex flex-col items-center">
                <FileText className="mb-1 size-6" />
                Fatura sem PDF anexo
            </span>
            {podeAnexar && (
                <>
                    <input
                        ref={entrada}
                        type="file"
                        accept="application/pdf"
                        className="hidden"
                        onChange={(e) => {
                            const arquivo = e.target.files?.[0];
                            e.target.value = '';

                            if (!arquivo) {
                                return;
                            }

                            router.post(
                                `/gestao/faturas/${fatura.id}/pdf`,
                                { arquivo },
                                {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onStart: () => {
                                        setEnviando(true);
                                        setErro(null);
                                    },
                                    onError: (erros) =>
                                        setErro(erros.arquivo ?? null),
                                    onFinish: () => setEnviando(false),
                                },
                            );
                        }}
                    />
                    <Botao
                        type="button"
                        tom="branco"
                        tamanho="sm"
                        carregando={enviando}
                        onClick={() => entrada.current?.click()}
                    >
                        {!enviando && <Upload />}
                        Anexar PDF
                    </Botao>
                    {erro && <p className="text-xs text-red-600">{erro}</p>}
                </>
            )}
        </div>
    );
}

function PreviaPdf({
    fatura,
    podeAnexar,
}: {
    fatura: FaturaDetalhe;
    podeAnexar: boolean;
}) {
    if (!fatura.tem_pdf) {
        return <SemPdf fatura={fatura} podeAnexar={podeAnexar} />;
    }

    const url = `/arquivos/fatura/${fatura.id}`;

    return (
        <div className="lg:sticky lg:top-6">
            <a
                href={url}
                target="_blank"
                rel="noreferrer"
                className="flex h-44 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#c9d3ea] bg-[repeating-linear-gradient(135deg,#f7f8fb_0_10px,#eef1f8_10px_20px)] text-center lg:hidden"
            >
                <FileText className="size-7 text-marinho-700" />
                <span className="mt-2 text-xs font-extrabold text-marinho-700">
                    PDF DA FATURA
                </span>
                <span className="text-xs text-suave">
                    Toque para abrir em tela cheia
                </span>
            </a>
            <div className="hidden overflow-hidden rounded-2xl border border-linha bg-white lg:block">
                <div className="flex items-center justify-between border-b border-linha px-4 py-2 text-xs font-bold text-suave">
                    PDF original
                    <a
                        href={url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-1 text-marinho-700"
                    >
                        <Maximize2 className="size-3.5" /> Tela cheia
                    </a>
                </div>
                <iframe
                    src={url}
                    title="PDF da fatura"
                    className="h-[70vh] w-full"
                />
            </div>
        </div>
    );
}

function Linha({
    rotulo,
    children,
}: {
    rotulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 py-3">
            <dt className="text-suave">{rotulo}</dt>
            <dd className="tabular text-right font-bold">{children}</dd>
        </div>
    );
}

function Corrigir({
    fatura,
    aberta,
    aoMudar,
}: {
    fatura: FaturaDetalhe;
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    const form = useForm<CamposFatura & { motivo: string }>({
        ...camposVazios,
        motivo: '',
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Corrigir campo"
            descricao="Preencha só o que difere do PDF. A correção fica na auditoria com o motivo."
            larga
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={!form.data.motivo}
                    onClick={() =>
                        form.patch(`/gestao/faturas/${fatura.id}`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoMudar(false);
                            },
                        })
                    }
                >
                    Salvar correção
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <FormFatura
                    dados={form.data}
                    erros={form.errors}
                    mudar={(c, v) => form.setData(c, v)}
                    unidadeMedida={fatura.unidade_medida}
                    comCompetencia={false}
                />
                <Campo rotulo="Motivo" erro={form.errors.motivo}>
                    <AreaTexto
                        className="min-h-20"
                        placeholder="Ex.: extrator leu o vencimento errado; no PDF é 22/10"
                        value={form.data.motivo}
                        onChange={(e) => form.setData('motivo', e.target.value)}
                    />
                </Campo>
            </div>
        </Folha>
    );
}

function Rejeitar({
    fatura,
    aberta,
    aoMudar,
}: {
    fatura: FaturaDetalhe;
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    const form = useForm({ motivo: '' });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Rejeitar fatura"
            descricao="Use quando o PDF não é desta competência, é duplicado ou não é do condomínio."
            rodape={
                <Botao
                    tom="perigo"
                    tamanho="lg"
                    largo
                    disabled={!form.data.motivo}
                    carregando={form.processing}
                    onClick={() =>
                        form.post(`/gestao/faturas/${fatura.id}/rejeitar`)
                    }
                >
                    Rejeitar
                </Botao>
            }
        >
            <Campo rotulo="Motivo" erro={form.errors.motivo} className="pb-2">
                <AreaTexto
                    value={form.data.motivo}
                    onChange={(e) => form.setData('motivo', e.target.value)}
                />
            </Campo>
        </Folha>
    );
}

function Pagar({
    fatura,
    aberta,
    aoMudar,
}: {
    fatura: FaturaDetalhe;
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    const form = useForm<{ data: string; comprovante: File | null }>({
        data: hojeIso(),
        comprovante: null,
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo={`Pagar ${fatura.concessionaria_rotulo}`}
            descricao={`Saída de ${dinheiro(fatura.valor)} da conta corrente. O comprovante do banco é opcional.`}
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    onClick={() =>
                        form.post(`/gestao/faturas/${fatura.id}/pagar`, {
                            forceFormData: true,
                            onSuccess: () => aoMudar(false),
                        })
                    }
                >
                    Lançar pagamento
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <Campo rotulo="Data do pagamento" erro={form.errors.data}>
                    <Entrada
                        type="date"
                        max={hojeIso()}
                        value={form.data.data}
                        onChange={(e) => form.setData('data', e.target.value)}
                    />
                </Campo>
                <SeletorArquivo
                    arquivo={form.data.comprovante}
                    aoEscolher={(f) => form.setData('comprovante', f)}
                    titulo="Comprovante do pagamento"
                    apoio="Opcional · foto ou PDF"
                    erro={form.errors.comprovante}
                />
            </div>
        </Folha>
    );
}

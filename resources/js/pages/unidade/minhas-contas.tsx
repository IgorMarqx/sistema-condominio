import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Lock,
    Mail,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import BotaoCopiar from '@/components/condominio/botao-copiar';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada, Escolha } from '@/components/condominio/campo';
import DetalheFatura from '@/components/condominio/detalhe-fatura';
import Folha from '@/components/condominio/folha';
import FormFatura, {
    camposVazios,
    type CamposFatura,
} from '@/components/condominio/form-fatura';
import GraficoBarras from '@/components/condominio/grafico-barras';
import IconeServico from '@/components/condominio/icone-servico';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import SeloFatura from '@/components/condominio/selo-fatura';
import TopoTela from '@/components/condominio/topo-tela';
import {
    diaMes,
    dinheiro,
    hojeIso,
    numero,
    quandoComHora,
    valor,
} from '@/lib/formato';
import type {
    ConcessionariaId,
    Fatura,
    FaturaDetalhe,
    PontoConsumo,
    SituacaoRecebimento,
} from '@/types';

type Servico = {
    medidor: { id: number; identificador: string };
    ultima: FaturaDetalhe | null;
    variacao: number | null;
    historico: PontoConsumo[];
} | null;

type Props = {
    servicos: Record<ConcessionariaId, Servico>;
    historico: Fatura[];
    medidores: {
        id: number;
        concessionaria: ConcessionariaId;
        rotulo: string;
        tem_senha_pdf: boolean;
    }[];
    recebimento: SituacaoRecebimento;
    autoriza_admin: boolean;
    limite_anomalia: number;
};

export default function MinhasContas(props: Props) {
    const { auth } = usePage().props;
    const apto = auth.user?.unidade?.numero;
    const [upload, setUpload] = useState(false);
    const [corrigir, setCorrigir] = useState<FaturaDetalhe | null>(null);
    const [aberta, setAberta] = useState<Fatura | null>(null);
    const [excluir, setExcluir] = useState(false);

    const situacao = (c: ConcessionariaId) => {
        const s = props.recebimento[c].situacao;

        return s === 'ativo' ? 'ativa' : s === 'manual' ? 'manual' : 'pendente';
    };

    return (
        <>
            <Head title="Minhas contas" />
            <TopoTela
                contexto={
                    <span className="flex items-center gap-1">
                        <Lock className="size-3" /> Só o apto {apto} vê estas
                        contas
                    </span>
                }
                titulo="Minhas contas"
                acao={
                    <Link
                        href="/receber-faturas"
                        className="flex size-10 items-center justify-center rounded-full border border-linha bg-white text-texto"
                        aria-label="Receber faturas por e-mail"
                    >
                        <Mail className="size-[18px]" />
                    </Link>
                }
            />

            <div className="space-y-4 px-4">
                {(['energisa', 'cagepa'] as const).map((c) => (
                    <CartaoServico
                        key={c}
                        concessionaria={c}
                        servico={props.servicos[c]}
                        limite={props.limite_anomalia}
                        aoCorrigir={setCorrigir}
                        aoEnviar={() => setUpload(true)}
                    />
                ))}

                <Link href="/receber-faturas" className="block">
                    <div className="topo-marinho flex items-center gap-3 rounded-2xl p-4 text-white">
                        <Mail className="size-5 text-ouro-500" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-extrabold">
                                Receber faturas automaticamente
                            </p>
                            <p className="truncate text-xs text-white/70">
                                Energisa {situacao('energisa')} · Cagepa{' '}
                                {situacao('cagepa')}
                            </p>
                        </div>
                        <ChevronRight className="size-4" />
                    </div>
                </Link>

                <Botao tom="branco" largo onClick={() => setUpload(true)}>
                    <Upload /> Enviar PDF de uma fatura
                </Botao>

                {props.historico.length > 0 && (
                    <section>
                        <TituloSecao>Histórico</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.historico.map((f) => (
                                <button
                                    key={f.id}
                                    type="button"
                                    onClick={() => setAberta(f)}
                                    className="flex w-full items-center gap-3 py-3 text-left"
                                >
                                    <IconeServico
                                        concessionaria={f.concessionaria}
                                        className="size-9"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold">
                                            {f.servico} · {f.mes_rotulo}
                                        </p>
                                        <p className="text-xs text-suave">
                                            {numero(f.consumo, 3)}{' '}
                                            {f.unidade_medida} · vence{' '}
                                            {diaMes(f.vencimento)}
                                        </p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        <span className="tabular text-sm font-extrabold">
                                            {dinheiro(f.valor)}
                                        </span>
                                        <SeloFatura fatura={f} individual />
                                    </div>
                                </button>
                            ))}
                        </Cartao>
                    </section>
                )}

                <Privacidade
                    autoriza={props.autoriza_admin}
                    aoExcluir={() => setExcluir(true)}
                />
            </div>

            <EnviarFatura
                aberta={upload}
                aoMudar={setUpload}
                medidores={props.medidores}
            />
            <CorrigirFatura
                fatura={corrigir}
                aoFechar={() => setCorrigir(null)}
            />
            <DetalheFatura
                fatura={aberta}
                aoFechar={() => setAberta(null)}
                individual
            >
                {aberta && !aberta.paga_em && (
                    <MarcarPaga
                        fatura={aberta}
                        aoConcluir={() => setAberta(null)}
                    />
                )}
            </DetalheFatura>
            <ExcluirHistorico aberta={excluir} aoMudar={setExcluir} />
        </>
    );
}

function CartaoServico({
    concessionaria,
    servico,
    limite,
    aoCorrigir,
    aoEnviar,
}: {
    concessionaria: ConcessionariaId;
    servico: Servico;
    limite: number;
    aoCorrigir: (f: FaturaDetalhe) => void;
    aoEnviar: () => void;
}) {
    const nome = concessionaria === 'energisa' ? 'Energia' : 'Água';
    const f = servico?.ultima;

    if (!servico || !f) {
        return (
            <Cartao className="flex items-center gap-3">
                <IconeServico concessionaria={concessionaria} />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-extrabold">{nome}</p>
                    <p className="text-xs text-suave">
                        Nenhuma fatura ainda. Configure o e-mail ou envie o PDF.
                    </p>
                </div>
                <Botao tamanho="sm" tom="branco" onClick={aoEnviar}>
                    Enviar
                </Botao>
            </Cartao>
        );
    }

    const anomalia = servico.variacao !== null && servico.variacao > limite;
    const codigo = f.pix_copia_cola ?? f.linha_digitavel;

    return (
        <Cartao>
            <div className="flex items-start gap-3">
                <IconeServico concessionaria={concessionaria} />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-extrabold">
                        {nome} · {f.mes_nome}
                    </p>
                    <p className="truncate text-xs text-suave">
                        {f.origem === 'email'
                            ? 'Recebida por e-mail'
                            : 'Enviada manualmente'}{' '}
                        {quandoComHora(f.recebida_em)}
                    </p>
                </div>
                <SeloFatura fatura={f} individual />
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-[#f7f8fb] p-3">
                <Dado rotulo="Valor" valor={dinheiro(f.valor)} />
                <Dado
                    rotulo="Consumo"
                    valor={`${numero(f.consumo, 3)} ${f.unidade_medida}`}
                />
                <Dado rotulo="Vence" valor={diaMes(f.vencimento)} />
            </div>

            {f.status === 'pendente_revisao' && (
                <div className="mt-3 rounded-xl border border-orange-200 bg-orange-50 p-3">
                    <p className="flex items-center gap-1.5 text-xs font-extrabold text-orange-700">
                        <TriangleAlert className="size-3.5" /> Confira os dados
                        desta fatura
                    </p>
                    <p className="mt-0.5 text-xs text-orange-700/90">
                        {f.motivo_revisao}
                    </p>
                    <div className="mt-2 flex gap-2">
                        <Botao
                            tamanho="sm"
                            tom="branco"
                            onClick={() => aoCorrigir(f)}
                        >
                            Corrigir
                        </Botao>
                        <Botao
                            tamanho="sm"
                            tom="marinho"
                            onClick={() =>
                                router.post(
                                    `/minhas-contas/faturas/${f.id}/aceitar`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Está certo
                        </Botao>
                    </div>
                </div>
            )}

            {anomalia && (
                <div className="mt-3 flex gap-2.5 rounded-xl border border-ouro-300 bg-ouro-50 p-3">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-[#c27c0e]" />
                    <div>
                        <p className="text-xs font-extrabold text-texto">
                            {numero(f.consumo, 3)} {f.unidade_medida} —{' '}
                            {servico.variacao}% acima da sua média
                        </p>
                        <p className="text-xs text-suave">
                            {concessionaria === 'cagepa'
                                ? 'Vale verificar torneiras e descarga: pode ser vazamento.'
                                : 'Vale conferir aparelhos ligados e o relógio do medidor.'}
                        </p>
                    </div>
                </div>
            )}

            {(anomalia || concessionaria === 'cagepa') && (
                <GraficoBarras
                    className="mt-3"
                    altura="h-16"
                    mostrarRotulos={false}
                    barras={servico.historico.map((p, i) => ({
                        rotulo: p.rotulo,
                        valor: p.consumo,
                        destaque:
                            i === servico.historico.length - 1 && !anomalia,
                        alerta: i === servico.historico.length - 1 && anomalia,
                        titulo: `${p.rotulo}: ${numero(p.consumo, 3)} ${f.unidade_medida}`,
                    }))}
                />
            )}

            {!f.paga_em && (codigo || f.tem_pdf) && (
                <div className="mt-3 grid grid-cols-2 gap-2">
                    {codigo ? (
                        <BotaoCopiar
                            texto={codigo}
                            rotulo="Copiar código"
                            tom="marinho"
                            mensagem="Código copiado. Cole no app do banco."
                        />
                    ) : (
                        <span />
                    )}
                    {f.tem_pdf && (
                        <Botao asChild tom="branco">
                            <a
                                href={`/arquivos/fatura/${f.id}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Ver PDF
                            </a>
                        </Botao>
                    )}
                </div>
            )}
            {!f.paga_em && <MarcarPaga fatura={f} compacto />}
        </Cartao>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[11px] font-semibold text-suave">{rotulo}</p>
            <p className="tabular truncate text-sm font-extrabold">{valor}</p>
        </div>
    );
}

function MarcarPaga({
    fatura,
    compacto = false,
    aoConcluir,
}: {
    fatura: Fatura;
    compacto?: boolean;
    aoConcluir?: () => void;
}) {
    const form = useForm({ paga_em: hojeIso() });
    const [aberto, setAberto] = useState(!compacto);

    if (!aberto) {
        return (
            <button
                type="button"
                onClick={() => setAberto(true)}
                className="mt-3 w-full text-center text-xs font-bold text-marinho-700"
            >
                Já paguei esta conta
            </button>
        );
    }

    return (
        <form
            className="mt-3 flex items-end gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/minhas-contas/faturas/${fatura.id}/paga`, {
                    preserveScroll: true,
                    onSuccess: () => aoConcluir?.(),
                });
            }}
        >
            <Campo
                rotulo="Paga em"
                className="flex-1"
                erro={form.errors.paga_em}
            >
                <Entrada
                    type="date"
                    max={hojeIso()}
                    value={form.data.paga_em}
                    onChange={(e) => form.setData('paga_em', e.target.value)}
                />
            </Campo>
            <Botao
                type="submit"
                tom="marinho"
                carregando={form.processing}
                className="h-12"
            >
                Marcar paga
            </Botao>
        </form>
    );
}

function EnviarFatura({
    aberta,
    aoMudar,
    medidores,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
    medidores: Props['medidores'];
}) {
    const [digitar, setDigitar] = useState(false);
    const form = useForm<
        CamposFatura & { arquivo: File | null; medidor_id: string }
    >({
        ...camposVazios,
        arquivo: null,
        medidor_id: '',
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Enviar fatura"
            descricao="O sistema lê o PDF. Se não conseguir, digite os campos."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    disabled={!form.data.arquivo}
                    carregando={form.processing}
                    onClick={() =>
                        form.post('/minhas-contas/upload', {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoMudar(false);
                            },
                        })
                    }
                >
                    <Upload /> Enviar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <SeletorArquivo
                    arquivo={form.data.arquivo}
                    aoEscolher={(f) => form.setData('arquivo', f)}
                    aceitar="application/pdf"
                    titulo="Escolher o PDF da fatura"
                    apoio="Baixe no app da Energisa ou da Cagepa"
                    erro={form.errors.arquivo}
                />
                <Campo
                    rotulo="De qual conta?"
                    ajuda="Sem escolher, o sistema descobre pelo PDF."
                >
                    <Escolha
                        value={form.data.medidor_id}
                        onChange={(e) =>
                            form.setData('medidor_id', e.target.value)
                        }
                    >
                        <option value="">Descobrir pelo PDF</option>
                        {medidores.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.rotulo}
                            </option>
                        ))}
                    </Escolha>
                </Campo>
                <button
                    type="button"
                    onClick={() => setDigitar((d) => !d)}
                    className="text-sm font-bold text-marinho-700"
                >
                    {digitar
                        ? 'Esconder campos'
                        : 'Digitar os campos (se o PDF não for lido)'}
                </button>
                {digitar && (
                    <FormFatura
                        dados={form.data}
                        erros={form.errors}
                        mudar={(c, v) => form.setData(c, v)}
                    />
                )}
            </div>
        </Folha>
    );
}

function CorrigirFatura({
    fatura,
    aoFechar,
}: {
    fatura: FaturaDetalhe | null;
    aoFechar: () => void;
}) {
    const form = useForm<CamposFatura & { motivo: string }>({
        ...camposVazios,
        motivo: '',
    });

    return (
        <Folha
            aberta={fatura !== null}
            aoMudar={(v) => {
                if (v && fatura) {
                    return;
                }

                aoFechar();
            }}
            titulo="Corrigir fatura"
            descricao="Preencha só o que estiver errado. O PDF continua sendo a referência."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    onClick={() =>
                        fatura &&
                        form.patch(`/minhas-contas/faturas/${fatura.id}`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoFechar();
                            },
                        })
                    }
                >
                    Salvar correção
                </Botao>
            }
        >
            {fatura && (
                <div className="space-y-4 pb-2">
                    <p className="rounded-xl bg-marinho-50 px-3 py-2 text-xs text-suave">
                        Lido do PDF: {dinheiro(fatura.valor)} · vence{' '}
                        {diaMes(fatura.vencimento)} ·{' '}
                        {numero(fatura.consumo, 3)} {fatura.unidade_medida}
                    </p>
                    <FormFatura
                        dados={form.data}
                        erros={form.errors}
                        mudar={(c, v) => form.setData(c, v)}
                        unidadeMedida={fatura.unidade_medida}
                        comCompetencia={false}
                    />
                    <Campo rotulo="Motivo" detalhe="(opcional)">
                        <Entrada
                            value={form.data.motivo}
                            onChange={(e) =>
                                form.setData('motivo', e.target.value)
                            }
                            placeholder={`Ex.: valor do PDF é ${valor(fatura.valor)}`}
                        />
                    </Campo>
                </div>
            )}
        </Folha>
    );
}

function Privacidade({
    autoriza,
    aoExcluir,
}: {
    autoriza: boolean;
    aoExcluir: () => void;
}) {
    return (
        <section className="pt-2">
            <TituloSecao>Privacidade</TituloSecao>
            <Cartao className="space-y-3">
                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={autoriza}
                        onChange={(e) =>
                            router.put(
                                '/minhas-contas/privacidade',
                                { autoriza: e.target.checked },
                                { preserveScroll: true },
                            )
                        }
                        className="mt-1 size-5 accent-marinho-900"
                    />
                    <span>
                        <span className="block text-sm font-bold">
                            Síndico pode ver valores e consumo
                        </span>
                        <span className="block text-xs text-suave">
                            Sem isso, o síndico vê só se a fatura chegou. CPF e
                            endereço nunca aparecem nas telas.
                        </span>
                    </span>
                </label>
                <button
                    type="button"
                    onClick={aoExcluir}
                    className="flex items-center gap-2 text-xs font-bold text-red-600"
                >
                    <Trash2 className="size-3.5" /> Excluir meu histórico
                    individual
                </button>
            </Cartao>
            <p className="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-suave">
                <ShieldCheck className="size-3.5" /> PDFs guardados com acesso
                restrito e registro de quem abriu.
            </p>
        </section>
    );
}

function ExcluirHistorico({
    aberta,
    aoMudar,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    const form = useForm({ confirmacao: '' });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Excluir histórico individual"
            descricao="Apaga as suas faturas de luz e água e os PDFs. As contas do condomínio continuam."
            rodape={
                <Botao
                    tom="perigo"
                    tamanho="lg"
                    largo
                    disabled={form.data.confirmacao !== 'EXCLUIR'}
                    carregando={form.processing}
                    onClick={() =>
                        form.delete('/minhas-contas/historico', {
                            onSuccess: () => aoMudar(false),
                        })
                    }
                >
                    <Trash2 /> Excluir definitivamente
                </Botao>
            }
        >
            <Campo
                rotulo="Digite EXCLUIR para confirmar"
                erro={form.errors.confirmacao}
                className="pb-2"
            >
                <Entrada
                    value={form.data.confirmacao}
                    onChange={(e) =>
                        form.setData(
                            'confirmacao',
                            e.target.value.toUpperCase(),
                        )
                    }
                />
            </Campo>
        </Folha>
    );
}

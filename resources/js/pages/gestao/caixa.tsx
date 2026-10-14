import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Download,
    FileSpreadsheet,
    FileText,
    Plus,
    RotateCcw,
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
import ExtratoCaixa from '@/components/condominio/extrato-caixa';
import Folha from '@/components/condominio/folha';
import GraficoBarras from '@/components/condominio/grafico-barras';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import TopoCaixa, {
    type DadosTopoCaixa,
} from '@/components/condominio/topo-caixa';
import { dinheiro, hojeIso, nomeMes } from '@/lib/formato';
import type { Lancamento, MesCaixa } from '@/types';

type Props = DadosTopoCaixa & {
    meses: MesCaixa[];
    evolucao: {
        mes: string;
        saldo: number;
        entradas: number;
        saidas: number;
    }[];
    previsao: {
        saldo: number;
        a_receber: number;
        a_pagar: number;
        previsto: number;
    };
    categorias: { valor: string; rotulo: string }[];
    prestacoes: {
        id: number;
        mes: string;
        mes_rotulo: string;
        diferenca: number;
        tem_pdf: boolean;
    }[];
};

export default function CaixaGestao(props: Props) {
    const { auth } = usePage().props;
    const podeEditar = auth.user?.pode_editar ?? false;
    const inicial =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('novo')
            : null;
    const [lancar, setLancar] = useState(inicial === 'lancamento');
    const [transferir, setTransferir] = useState(false);
    const [estornar, setEstornar] = useState<Lancamento | null>(null);

    return (
        <>
            <Head title="Caixa" />
            <TopoCaixa
                saldo={props.saldo}
                contas={props.contas}
                ultimo_fechamento={props.ultimo_fechamento}
                acao={
                    <div className="flex gap-1.5">
                        <a
                            href="/caixa/extrato.csv"
                            className="flex items-center gap-1 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                        >
                            <FileSpreadsheet className="size-3.5" /> CSV
                        </a>
                        <a
                            href="/caixa/extrato.pdf"
                            className="flex items-center gap-1 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                        >
                            <Download className="size-3.5" /> PDF
                        </a>
                    </div>
                }
            />

            <div className="mt-4 space-y-5 px-4 lg:px-0">
                {podeEditar && (
                    <div className="grid grid-cols-2 gap-2">
                        <Botao onClick={() => setLancar(true)}>
                            <Plus /> Lançamento
                        </Botao>
                        <Botao tom="branco" onClick={() => setTransferir(true)}>
                            <ArrowLeftRight /> Transferir
                        </Botao>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Cartao>
                        <p className="text-sm font-extrabold">
                            Previsão do mês
                        </p>
                        <dl className="mt-2 space-y-1.5 text-sm">
                            <Linha
                                rotulo="Saldo atual"
                                valor={dinheiro(props.previsao.saldo)}
                            />
                            <Linha
                                rotulo="+ Cobranças em aberto"
                                valor={dinheiro(props.previsao.a_receber)}
                                cor="text-emerald-600"
                            />
                            <Linha
                                rotulo="− Contas a pagar"
                                valor={dinheiro(props.previsao.a_pagar)}
                                cor="text-red-600"
                            />
                            <div className="flex justify-between border-t border-linha pt-1.5 font-extrabold">
                                <dt>Previsto</dt>
                                <dd className="tabular">
                                    {dinheiro(props.previsao.previsto)}
                                </dd>
                            </div>
                        </dl>
                    </Cartao>
                    <Cartao>
                        <p className="text-sm font-extrabold">
                            Saldo em 12 meses
                        </p>
                        <GraficoBarras
                            className="mt-3"
                            altura="h-24"
                            barras={props.evolucao.map((p, i) => ({
                                rotulo: nomeMes(p.mes).slice(0, 3),
                                valor: Math.max(0, p.saldo),
                                destaque: i === props.evolucao.length - 1,
                                titulo: `${nomeMes(p.mes)}: ${dinheiro(p.saldo)} (entradas ${dinheiro(p.entradas)}, saídas ${dinheiro(p.saidas)})`,
                            }))}
                        />
                    </Cartao>
                </div>

                <ExtratoCaixa
                    meses={props.meses}
                    base="/gestao/caixa"
                    acoes={(l, fechar) =>
                        podeEditar &&
                        !l.estornado &&
                        l.estorno_de_id === null && (
                            <Botao
                                tom="branco"
                                largo
                                className="text-red-600"
                                onClick={() => {
                                    fechar();
                                    setEstornar(l);
                                }}
                            >
                                <RotateCcw /> Estornar
                            </Botao>
                        )
                    }
                />

                {props.prestacoes.length > 0 && (
                    <section>
                        <TituloSecao>Prestações de contas</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.prestacoes.map((p) => (
                                <a
                                    key={p.id}
                                    href={`/arquivos/prestacao/${p.id}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 py-3"
                                >
                                    <FileText className="size-4 text-marinho-700" />
                                    <span className="flex-1 text-sm font-bold">
                                        {p.mes_rotulo}
                                    </span>
                                    <Selo
                                        tom={
                                            p.diferenca === 0
                                                ? 'pago'
                                                : 'alerta'
                                        }
                                    >
                                        {p.diferenca === 0
                                            ? 'Conciliado'
                                            : dinheiro(p.diferenca)}
                                    </Selo>
                                </a>
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>

            <NovoLancamento
                aberta={lancar}
                aoMudar={setLancar}
                contas={props.contas}
                categorias={props.categorias}
            />
            <Transferencia
                aberta={transferir}
                aoMudar={setTransferir}
                contas={props.contas}
            />
            <Estorno lancamento={estornar} aoFechar={() => setEstornar(null)} />
        </>
    );
}

function Linha({
    rotulo,
    valor,
    cor,
}: {
    rotulo: string;
    valor: string;
    cor?: string;
}) {
    return (
        <div className="flex justify-between">
            <dt className="text-suave">{rotulo}</dt>
            <dd className={`tabular font-bold ${cor ?? ''}`}>{valor}</dd>
        </div>
    );
}

function NovoLancamento({
    aberta,
    aoMudar,
    contas,
    categorias,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
    contas: Props['contas'];
    categorias: Props['categorias'];
}) {
    const form = useForm<{
        conta: string;
        tipo: 'entrada' | 'saida';
        valor: string;
        data: string;
        categoria: string;
        descricao: string;
        comprovante: File | null;
    }>({
        conta: 'corrente',
        tipo: 'saida',
        valor: '',
        data: hojeIso(),
        categoria: 'manutencao',
        descricao: '',
        comprovante: null,
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={(v) => {
                aoMudar(v);

                if (!v) {
                    router.replace({
                        url: '/gestao/caixa',
                        preserveScroll: true,
                        preserveState: true,
                    });
                }
            }}
            titulo="Novo lançamento"
            descricao="Lançamentos não se editam nem se apagam: erro se corrige com estorno."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={
                        !form.data.comprovante ||
                        !form.data.valor ||
                        !form.data.descricao
                    }
                    onClick={() =>
                        form.post('/gestao/caixa/lancamentos', {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoMudar(false);
                            },
                        })
                    }
                >
                    Registrar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <div className="grid grid-cols-2 gap-1 rounded-2xl bg-marinho-50 p-1">
                    {(['entrada', 'saida'] as const).map((t) => (
                        <button
                            key={t}
                            type="button"
                            onClick={() => form.setData('tipo', t)}
                            className={
                                form.data.tipo === t
                                    ? `h-10 rounded-xl text-sm font-extrabold text-white ${t === 'entrada' ? 'bg-emerald-600' : 'bg-red-600'}`
                                    : 'h-10 rounded-xl text-sm font-bold text-suave'
                            }
                        >
                            {t === 'entrada' ? 'Entrada' : 'Saída'}
                        </button>
                    ))}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Campo rotulo="Valor (R$)" erro={form.errors.valor}>
                        <Entrada
                            inputMode="decimal"
                            placeholder="0,00"
                            value={form.data.valor}
                            onChange={(e) =>
                                form.setData('valor', e.target.value)
                            }
                        />
                    </Campo>
                    <Campo rotulo="Data" erro={form.errors.data}>
                        <Entrada
                            type="date"
                            max={hojeIso()}
                            value={form.data.data}
                            onChange={(e) =>
                                form.setData('data', e.target.value)
                            }
                        />
                    </Campo>
                    <Campo rotulo="Conta" erro={form.errors.conta}>
                        <Escolha
                            value={form.data.conta}
                            onChange={(e) =>
                                form.setData('conta', e.target.value)
                            }
                        >
                            {contas.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.nome}
                                </option>
                            ))}
                        </Escolha>
                    </Campo>
                    <Campo rotulo="Categoria" erro={form.errors.categoria}>
                        <Escolha
                            value={form.data.categoria}
                            onChange={(e) =>
                                form.setData('categoria', e.target.value)
                            }
                        >
                            {categorias.map((c) => (
                                <option key={c.valor} value={c.valor}>
                                    {c.rotulo}
                                </option>
                            ))}
                        </Escolha>
                    </Campo>
                </div>
                <Campo rotulo="Descrição" erro={form.errors.descricao}>
                    <Entrada
                        placeholder="Ex.: Troca da boia da caixa d'água"
                        value={form.data.descricao}
                        onChange={(e) =>
                            form.setData('descricao', e.target.value)
                        }
                    />
                </Campo>
                <SeletorArquivo
                    arquivo={form.data.comprovante}
                    aoEscolher={(f) => form.setData('comprovante', f)}
                    titulo="Comprovante (obrigatório)"
                    erro={form.errors.comprovante}
                />
            </div>
        </Folha>
    );
}

function Transferencia({
    aberta,
    aoMudar,
    contas,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
    contas: Props['contas'];
}) {
    const form = useForm<{
        origem: string;
        destino: string;
        valor: string;
        data: string;
        descricao: string;
        comprovante: File | null;
    }>({
        origem: 'corrente',
        destino: 'reserva',
        valor: '',
        data: hojeIso(),
        descricao: 'Transferência para o fundo de reserva',
        comprovante: null,
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Transferir entre contas"
            descricao="Gera dois lançamentos ligados: saída numa conta, entrada na outra."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={!form.data.comprovante || !form.data.valor}
                    onClick={() =>
                        form.post('/gestao/caixa/transferencias', {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => aoMudar(false),
                        })
                    }
                >
                    Transferir
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <div className="grid grid-cols-2 gap-3">
                    <Campo rotulo="De" erro={form.errors.origem}>
                        <Escolha
                            value={form.data.origem}
                            onChange={(e) =>
                                form.setData('origem', e.target.value)
                            }
                        >
                            {contas.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.nome}
                                </option>
                            ))}
                        </Escolha>
                    </Campo>
                    <Campo rotulo="Para" erro={form.errors.destino}>
                        <Escolha
                            value={form.data.destino}
                            onChange={(e) =>
                                form.setData('destino', e.target.value)
                            }
                        >
                            {contas.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.nome}
                                </option>
                            ))}
                        </Escolha>
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
                    <Campo rotulo="Data" erro={form.errors.data}>
                        <Entrada
                            type="date"
                            max={hojeIso()}
                            value={form.data.data}
                            onChange={(e) =>
                                form.setData('data', e.target.value)
                            }
                        />
                    </Campo>
                </div>
                <Campo rotulo="Descrição" erro={form.errors.descricao}>
                    <Entrada
                        value={form.data.descricao}
                        onChange={(e) =>
                            form.setData('descricao', e.target.value)
                        }
                    />
                </Campo>
                <SeletorArquivo
                    arquivo={form.data.comprovante}
                    aoEscolher={(f) => form.setData('comprovante', f)}
                    titulo="Comprovante da transferência"
                    erro={form.errors.comprovante}
                />
            </div>
        </Folha>
    );
}

function Estorno({
    lancamento,
    aoFechar,
}: {
    lancamento: Lancamento | null;
    aoFechar: () => void;
}) {
    const form = useForm({ motivo: '' });

    return (
        <Folha
            aberta={lancamento !== null}
            aoMudar={(v) => !v && aoFechar()}
            titulo={`Estornar lançamento nº ${lancamento?.id ?? ''}`}
            descricao={
                lancamento
                    ? `${lancamento.descricao} · ${dinheiro(lancamento.valor)}. O original continua no extrato.`
                    : undefined
            }
            rodape={
                <Botao
                    tom="perigo"
                    tamanho="lg"
                    largo
                    disabled={!form.data.motivo}
                    carregando={form.processing}
                    onClick={() =>
                        lancamento &&
                        form.post(
                            `/gestao/caixa/lancamentos/${lancamento.id}/estornar`,
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
                    <RotateCcw /> Estornar
                </Botao>
            }
        >
            <Campo
                rotulo="Motivo (obrigatório)"
                erro={form.errors.motivo}
                className="pb-2"
            >
                <AreaTexto
                    placeholder="Ex.: lançamento duplicado"
                    value={form.data.motivo}
                    onChange={(e) => form.setData('motivo', e.target.value)}
                />
            </Campo>
        </Folha>
    );
}

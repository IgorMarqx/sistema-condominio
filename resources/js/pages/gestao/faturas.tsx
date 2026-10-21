import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    FileText,
    Lock,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao from '@/components/condominio/cartao';
import { Campo, Escolha } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import FormFatura, {
    camposVazios,
    type CamposFatura,
} from '@/components/condominio/form-fatura';
import IconeServico from '@/components/condominio/icone-servico';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import SeloFatura from '@/components/condominio/selo-fatura';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import { diaMes, dinheiro, quandoComHora, somarMes } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { ConcessionariaId, Fatura } from '@/types';

type Props = {
    pendentes: Fatura[];
    condominio: Fatura[];
    unidades: {
        mes: string;
        mes_rotulo: string;
        linhas: { numero: string; autoriza: boolean; faturas: Fatura[] }[];
    };
    medidores: {
        id: number;
        concessionaria: ConcessionariaId;
        rotulo: string;
    }[];
};

type Aba = 'pendentes' | 'condominio' | 'unidades';

export default function Faturas(props: Props) {
    const { auth } = usePage().props;
    const parametros = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    );
    const [aba, setAba] = useState<Aba>(
        parametros.get('aba') === 'unidades' || parametros.has('mes')
            ? 'unidades'
            : parametros.get('aba') === 'condominio'
              ? 'condominio'
              : 'pendentes',
    );
    const [upload, setUpload] = useState(false);
    const totalPendentes = props.pendentes.length;

    return (
        <>
            <Head title="Faturas" />
            <TopoTela
                titulo="Faturas"
                contexto="Energisa e Cagepa"
                acao={
                    auth.user?.pode_editar && (
                        <Botao tamanho="sm" onClick={() => setUpload(true)}>
                            <Upload /> Enviar PDF
                        </Botao>
                    )
                }
            />

            <div className="space-y-4 px-4 lg:px-0">
                <div className="flex gap-1 rounded-2xl border border-linha bg-white p-1">
                    {(
                        [
                            [
                                'pendentes',
                                `Pendentes${totalPendentes ? ` (${totalPendentes})` : ''}`,
                            ],
                            ['condominio', 'Condomínio'],
                            ['unidades', 'Unidades'],
                        ] as const
                    ).map(([id, rotulo]) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setAba(id)}
                            className={cn(
                                'h-9 flex-1 rounded-xl text-[13px] font-bold',
                                aba === id
                                    ? 'bg-marinho-900 text-white'
                                    : 'text-suave',
                            )}
                        >
                            {rotulo}
                        </button>
                    ))}
                </div>

                {aba === 'pendentes' && (
                    <>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.pendentes.length === 0 && (
                                <Vazio
                                    icone={FileText}
                                    titulo="Nenhuma fatura para conferir"
                                    className="py-6"
                                />
                            )}
                            {props.pendentes.map((f) => (
                                <LinhaFatura key={f.id} fatura={f} />
                            ))}
                        </Cartao>
                    </>
                )}

                {aba === 'condominio' && (
                    <Cartao className="divide-y divide-linha py-1">
                        {props.condominio.length === 0 && (
                            <Vazio
                                icone={FileText}
                                titulo="Nenhuma fatura ainda"
                                className="py-6"
                            />
                        )}
                        {props.condominio.map((f) => (
                            <LinhaFatura key={f.id} fatura={f} />
                        ))}
                    </Cartao>
                )}

                {aba === 'unidades' && <GradeUnidades dados={props.unidades} />}
            </div>

            <EnviarFatura
                aberta={upload}
                aoMudar={setUpload}
                medidores={props.medidores}
            />
        </>
    );
}

function LinhaFatura({ fatura: f }: { fatura: Fatura }) {
    return (
        <Link
            href={`/gestao/faturas/${f.id}`}
            className="flex items-center gap-3 py-3"
        >
            <IconeServico concessionaria={f.concessionaria} />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-extrabold">
                    {f.concessionaria_rotulo} · {f.mes_rotulo}
                </p>
                <p className="truncate text-xs text-suave">
                    {f.origem === 'email' ? 'E-mail' : 'Upload'}{' '}
                    {quandoComHora(f.recebida_em)} · vence{' '}
                    {diaMes(f.vencimento)}
                </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
                <span className="tabular text-sm font-extrabold">
                    {dinheiro(f.valor)}
                </span>
                <SeloFatura fatura={f} />
            </div>
        </Link>
    );
}

function GradeUnidades({ dados }: { dados: Props['unidades'] }) {
    const navegar = (mes: string) =>
        router.get(
            '/gestao/faturas',
            { mes, aba: 'unidades' },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between rounded-2xl border border-linha bg-white p-1.5">
                <button
                    type="button"
                    onClick={() => navegar(somarMes(dados.mes, -1))}
                    className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                    aria-label="Mês anterior"
                >
                    <ChevronLeft className="size-4" />
                </button>
                <span className="text-sm font-extrabold">
                    {dados.mes_rotulo}
                </span>
                <button
                    type="button"
                    onClick={() => navegar(somarMes(dados.mes, 1))}
                    className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                    aria-label="Próximo mês"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>
            <p className="flex items-center gap-1.5 px-1 text-xs text-suave">
                <Lock className="size-3.5" /> Contas individuais: você vê se
                chegou. Valor só com autorização da unidade.
            </p>
            <Cartao className="divide-y divide-linha py-1">
                {dados.linhas.map((linha) => (
                    <div
                        key={linha.numero}
                        className="flex items-center gap-3 py-2.5"
                    >
                        <span className="w-10 text-sm font-extrabold">
                            {linha.numero}
                        </span>
                        <div className="flex flex-1 flex-wrap gap-1.5">
                            {(['energisa', 'cagepa'] as const).map((c) => {
                                const f = linha.faturas.find(
                                    (x) => x.concessionaria === c,
                                );

                                return (
                                    <span
                                        key={c}
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold',
                                            f
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-marinho-50 text-suave',
                                        )}
                                    >
                                        {c === 'energisa' ? 'Luz' : 'Água'}:{' '}
                                        {f
                                            ? f.valor !== undefined &&
                                              f.valor !== null
                                                ? dinheiro(f.valor)
                                                : 'chegou'
                                            : '—'}
                                    </span>
                                );
                            })}
                        </div>
                        {linha.autoriza && <Selo tom="neutro">autorizou</Selo>}
                    </div>
                ))}
            </Cartao>
        </div>
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
        medidor_id: medidores[0] ? String(medidores[0].id) : '',
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Enviar fatura do condomínio"
            descricao="O PDF é lido automaticamente; digite os campos só se a leitura falhar."
            larga
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    disabled={!form.data.arquivo || !form.data.medidor_id}
                    carregando={form.processing}
                    onClick={() =>
                        form.post('/gestao/faturas/upload', {
                            forceFormData: true,
                            onSuccess: () => aoMudar(false),
                        })
                    }
                >
                    <Upload /> Enviar e revisar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <SeletorArquivo
                    arquivo={form.data.arquivo}
                    aoEscolher={(f) => form.setData('arquivo', f)}
                    aceitar="application/pdf"
                    titulo="PDF da fatura"
                    erro={form.errors.arquivo}
                />
                <Campo rotulo="Medidor" erro={form.errors.medidor_id}>
                    <Escolha
                        value={form.data.medidor_id}
                        onChange={(e) =>
                            form.setData('medidor_id', e.target.value)
                        }
                    >
                        {medidores.length === 0 && (
                            <option value="">
                                Cadastre os medidores primeiro
                            </option>
                        )}
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

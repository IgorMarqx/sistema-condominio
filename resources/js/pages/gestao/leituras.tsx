import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    FileText,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import IconeServico from '@/components/condominio/icone-servico';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import { data, diaMes, diasAte, hojeIso, somarMes } from '@/lib/formato';
import type { ConcessionariaId } from '@/types';

type Linha = {
    competencia_id: number;
    medidor: {
        id: number;
        concessionaria: ConcessionariaId;
        servico: string;
        identificador: string;
        dono: string;
        condominio: boolean;
    };
    leitura_prevista: string | null;
    origem: 'fatura' | 'estimada' | 'manual' | null;
    leitura_realizada: string | null;
    tem_foto: boolean;
    status: string;
    status_rotulo: string;
    fatura_id: number | null;
    atrasada: boolean;
};

const origens: Record<string, string> = {
    fatura: 'impressa na fatura',
    estimada: 'estimada (+30 dias)',
    manual: 'ajustada à mão',
};

export default function Leituras({
    mes,
    mes_rotulo,
    linhas,
}: {
    mes: string;
    mes_rotulo: string;
    linhas: Linha[];
}) {
    const { auth } = usePage().props;
    const [editar, setEditar] = useState<Linha | null>(null);
    const condominio = linhas.filter((l) => l.medidor.condominio);
    const unidades = linhas.filter((l) => !l.medidor.condominio);

    return (
        <>
            <Head title="Calendário de leituras" />
            <TopoTela
                titulo="Calendário de leituras"
                contexto="Leitura prevista e situação de cada medidor"
            />

            <div className="space-y-4 px-4 lg:px-0">
                <div className="flex items-center justify-between rounded-2xl border border-linha bg-white p-1.5">
                    <Link
                        href={`/gestao/leituras?mes=${somarMes(mes, -1)}`}
                        className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                        aria-label="Mês anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Link>
                    <span className="text-sm font-extrabold">{mes_rotulo}</span>
                    <Link
                        href={`/gestao/leituras?mes=${somarMes(mes, 1)}`}
                        className="flex size-9 items-center justify-center rounded-xl hover:bg-marinho-50"
                        aria-label="Próximo mês"
                    >
                        <ChevronRight className="size-4" />
                    </Link>
                </div>

                <section>
                    <TituloSecao>Condomínio</TituloSecao>
                    <Cartao className="divide-y divide-linha py-1">
                        {condominio.length === 0 && (
                            <p className="py-5 text-center text-sm text-suave">
                                Cadastre os medidores do condomínio.
                            </p>
                        )}
                        {condominio.map((l) => (
                            <LinhaLeitura
                                key={l.competencia_id}
                                linha={l}
                                aoEditar={
                                    auth.user?.pode_editar
                                        ? () => setEditar(l)
                                        : undefined
                                }
                            />
                        ))}
                    </Cartao>
                </section>

                {unidades.length > 0 && (
                    <section>
                        <TituloSecao acao="só datas e situação">
                            Unidades
                        </TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {unidades.map((l) => (
                                <LinhaLeitura
                                    key={l.competencia_id}
                                    linha={l}
                                />
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>

            <EditarLeitura linha={editar} aoFechar={() => setEditar(null)} />
        </>
    );
}

function LinhaLeitura({
    linha: l,
    aoEditar,
}: {
    linha: Linha;
    aoEditar?: () => void;
}) {
    const dias = l.leitura_prevista ? diasAte(l.leitura_prevista) : null;
    const quando =
        dias === null
            ? 'sem previsão'
            : dias === 0
              ? 'hoje'
              : dias > 0
                ? `em ${dias} dia${dias > 1 ? 's' : ''}`
                : `há ${-dias} dia${dias < -1 ? 's' : ''}`;

    return (
        <div className="flex items-center gap-3 py-3">
            <IconeServico concessionaria={l.medidor.concessionaria} />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-extrabold">
                    {l.medidor.dono} · {l.medidor.servico}
                </p>
                <p className="truncate text-xs text-suave">
                    {l.leitura_prevista
                        ? `${diaMes(l.leitura_prevista)} (${quando}) · ${origens[l.origem ?? ''] ?? ''}`
                        : 'Sem data prevista'}
                    {l.leitura_realizada &&
                        ` · lida ${diaMes(l.leitura_realizada)}`}
                </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
                {l.atrasada ? (
                    <Selo tom="atrasado">
                        <TriangleAlert className="size-3" /> Fatura não chegou
                    </Selo>
                ) : (
                    <Selo
                        tom={
                            ['confirmada', 'rateada', 'quitada'].includes(
                                l.status,
                            )
                                ? 'pago'
                                : l.status === 'fatura_recebida'
                                  ? 'conferir'
                                  : 'neutro'
                        }
                    >
                        {l.status_rotulo}
                    </Selo>
                )}
                <div className="flex items-center gap-2">
                    {l.tem_foto && (
                        <a
                            href={`/arquivos/leitura/${l.competencia_id}`}
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Foto da leitura"
                            className="text-suave"
                        >
                            <Camera className="size-4" />
                        </a>
                    )}
                    {l.fatura_id && (
                        <Link
                            href={`/gestao/faturas/${l.fatura_id}`}
                            aria-label="Fatura"
                            className="text-suave"
                        >
                            <FileText className="size-4" />
                        </Link>
                    )}
                    {aoEditar && (
                        <button
                            type="button"
                            onClick={aoEditar}
                            className="text-xs font-bold text-marinho-700"
                        >
                            Editar
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function EditarLeitura({
    linha,
    aoFechar,
}: {
    linha: Linha | null;
    aoFechar: () => void;
}) {
    const form = useForm<{
        leitura_prevista: string;
        leitura_realizada: string;
        foto: File | null;
    }>({
        leitura_prevista: '',
        leitura_realizada: '',
        foto: null,
    });

    return (
        <Folha
            aberta={linha !== null}
            aoMudar={(v) => {
                if (!v) {
                    form.reset();
                    aoFechar();
                }
            }}
            titulo={
                linha ? `${linha.medidor.servico} · ${linha.medidor.dono}` : ''
            }
            descricao={
                linha?.leitura_prevista
                    ? `Prevista para ${data(linha.leitura_prevista)}`
                    : 'Sem leitura prevista'
            }
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    onClick={() =>
                        linha &&
                        form.post(
                            `/gestao/competencias/${linha.competencia_id}`,
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    aoFechar();
                                },
                            },
                        )
                    }
                >
                    Salvar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <Campo
                    rotulo="Ajustar data prevista"
                    ajuda="Vale até chegar uma fatura com a próxima leitura impressa."
                    erro={form.errors.leitura_prevista}
                >
                    <Entrada
                        type="date"
                        value={form.data.leitura_prevista}
                        onChange={(e) =>
                            form.setData('leitura_prevista', e.target.value)
                        }
                    />
                </Campo>
                <Campo
                    rotulo="Leitura feita em"
                    erro={form.errors.leitura_realizada}
                >
                    <Entrada
                        type="date"
                        max={hojeIso()}
                        value={form.data.leitura_realizada}
                        onChange={(e) =>
                            form.setData('leitura_realizada', e.target.value)
                        }
                    />
                </Campo>
                <SeletorArquivo
                    arquivo={form.data.foto}
                    aoEscolher={(f) => form.setData('foto', f)}
                    aceitar="image/*"
                    titulo="Foto do relógio"
                    apoio="Para comparar com o consumo da fatura"
                    erro={form.errors.foto}
                />
            </div>
        </Folha>
    );
}

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    CreditCard,
    FileText,
    Lock,
    Plus,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import IconeServico from '@/components/condominio/icone-servico';
import Selo from '@/components/condominio/selo';
import { diaMes, dinheiro, lerCentavos } from '@/lib/formato';
import { mascararDecimal, mascararMoeda } from '@/lib/mascaras';
import { cn } from '@/lib/utils';
import type {
    Cobranca,
    ConcessionariaId,
    Fatura,
    StatusCobranca,
} from '@/types';

type Conta = {
    medidor_id: number | null;
    unidade_medida: string | null;
    lancada: { valor: number | null; consumo: string | null } | null;
};

type UnidadePainel = {
    numero: string;
    andar: number;
    status: StatusCobranca | null;
    vencida: boolean;
    total: number;
    cobrancas: (Cobranca & { em_aberto: number; tipos: string[] })[];
};

type Props = {
    resumo: {
        recebidas: number;
        total: number;
        em_aberto: number;
        caixa: number;
    };
    pendencias: {
        faturas: Fatura[];
        comprovantes: {
            quantidade: number;
            aptos: string[];
            mes: string | null;
        };
        fechamento: { mes: string; mes_rotulo: string } | null;
    };
    contas: {
        mes_nome: string;
        ocupados: string[];
        energisa: Conta;
        cagepa: Conta;
        internet: Conta;
    };
    cobrancas: {
        mes: string;
        mes_nome: string;
        vencimento: string | null;
        unidades: UnidadePainel[];
    } | null;
};

const CONTAS: ConcessionariaId[] = ['energisa', 'cagepa', 'internet'];
const COM_PDF = ['energisa', 'cagepa'] as const;

const NOMES: Record<
    ConcessionariaId,
    { rotulo: string; servico: string; tipo: string; titulo: string }
> = {
    energisa: {
        rotulo: 'Energisa',
        servico: 'energia',
        tipo: 'energia',
        titulo: 'Energia',
    },
    cagepa: { rotulo: 'Cagepa', servico: 'água', tipo: 'agua', titulo: 'Água' },
    internet: {
        rotulo: 'Internet',
        servico: 'internet',
        tipo: 'internet',
        titulo: 'Internet',
    },
};

function iniciais(nome: string): string {
    return nome
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}

function listaAptos(aptos: string[]): string {
    return aptos.length <= 1
        ? (aptos[0] ?? '')
        : `${aptos.slice(0, -1).join(', ')} e ${aptos.at(-1)}`;
}

export default function Painel({
    resumo,
    pendencias,
    contas,
    cobrancas,
}: Props) {
    const { auth } = usePage().props;
    const user = auth.user!;
    const podeEditar = user.pode_editar;
    const [lancar, setLancar] = useState<ConcessionariaId | null>(null);
    const [apto, setApto] = useState<string | null>(null);
    const semPendencias =
        pendencias.faturas.length === 0 &&
        pendencias.comprovantes.quantidade === 0 &&
        !pendencias.fechamento;
    // Lido das props a cada visita: depois de marcar paga, o modal já mostra o estado novo.
    const unidadeAberta =
        cobrancas?.unidades.find((u) => u.numero === apto) ?? null;

    return (
        <>
            <Head title="Painel" />
            <header className="topo-marinho area-segura-topo rounded-b-[28px] px-5 pt-6 pb-5 text-white lg:mt-6 lg:rounded-3xl">
                <div className="flex items-center gap-3">
                    <Link
                        href="/gestao/seguranca"
                        className="flex size-11 items-center justify-center rounded-full bg-ouro-500 text-sm font-extrabold text-marinho-900"
                    >
                        {iniciais(user.nome)}
                    </Link>
                    <div className="min-w-0">
                        <p className="text-xs font-semibold text-white/70">
                            {user.tipo === 'admin'
                                ? 'Síndico · acesso com 2FA'
                                : 'Conselho · somente leitura'}
                        </p>
                        <p className="truncate text-lg font-extrabold">
                            {user.nome}
                        </p>
                    </div>
                </div>
                <div className="mt-4 grid grid-cols-3 gap-2">
                    <Numero
                        rotulo="Recebido"
                        valor={`${resumo.recebidas} de ${resumo.total}`}
                    />
                    <Numero
                        rotulo="Em aberto"
                        valor={dinheiro(resumo.em_aberto)}
                        destaque
                    />
                    <Numero
                        rotulo="Caixa"
                        valor={dinheiro(resumo.caixa).replace(/,\d{2}$/, '')}
                    />
                </div>
            </header>

            <div className="mt-4 space-y-5 px-4 lg:px-0">
                {podeEditar && (
                    <section>
                        <TituloSecao>Contas de {contas.mes_nome}</TituloSecao>
                        <div className="grid grid-cols-2 gap-2">
                            {COM_PDF.map((c) => (
                                <BotaoConta
                                    key={c}
                                    concessionaria={c}
                                    conta={contas[c]}
                                    aoAbrir={() => setLancar(c)}
                                />
                            ))}
                            {COM_PDF.map((c) => (
                                <BotaoImportar
                                    key={c}
                                    concessionaria={c}
                                    medidorId={contas[c].medidor_id}
                                />
                            ))}
                            {/* Internet é mensalidade: não tem PDF para ler, ocupa a linha toda. */}
                            <div className="col-span-2 grid">
                                <BotaoConta
                                    concessionaria="internet"
                                    conta={contas.internet}
                                    aoAbrir={() => setLancar('internet')}
                                />
                            </div>
                        </div>
                    </section>
                )}

                <section>
                    <TituloSecao>Pendências</TituloSecao>
                    <Cartao className="divide-y divide-linha py-1">
                        {semPendencias && (
                            <p className="py-5 text-center text-sm text-suave">
                                Tudo em dia. 🎉
                            </p>
                        )}
                        {pendencias.faturas.map((f) => (
                            <Pendencia
                                key={f.id}
                                href={`/gestao/faturas/${f.id}`}
                                icone={<FileText className="size-[18px]" />}
                                cor="bg-orange-100 text-orange-600"
                                titulo={`${f.status === 'pendente_revisao' ? 'Revisar' : 'Conferir'} fatura ${f.concessionaria_rotulo} do condomínio`}
                                apoio={`Importada do PDF · ${dinheiro(f.valor)}`}
                            />
                        ))}
                        {pendencias.comprovantes.quantidade > 0 && (
                            <Pendencia
                                href={`/gestao/cobrancas${pendencias.comprovantes.mes ? `?mes=${pendencias.comprovantes.mes}` : ''}`}
                                icone={<CreditCard className="size-[18px]" />}
                                cor="bg-sky-100 text-sky-700"
                                titulo={`Confirmar ${pendencias.comprovantes.quantidade} comprovante${pendencias.comprovantes.quantidade > 1 ? 's' : ''}`}
                                apoio={`${pendencias.comprovantes.aptos.length > 1 ? 'Aptos' : 'Apto'} ${listaAptos(pendencias.comprovantes.aptos)}`}
                            />
                        )}
                        {pendencias.fechamento && (
                            <Pendencia
                                href="/gestao/fechamento"
                                icone={<Lock className="size-[18px]" />}
                                cor="bg-marinho-50 text-marinho-700"
                                titulo={`Fechar ${pendencias.fechamento.mes_rotulo}`}
                                apoio="Conciliar com o extrato do banco e gerar a prestação de contas"
                            />
                        )}
                    </Cartao>
                </section>

                {cobrancas && (
                    <section>
                        <TituloSecao
                            acao={
                                cobrancas.vencimento &&
                                `vence ${diaMes(cobrancas.vencimento)}`
                            }
                        >
                            Cobranças de {cobrancas.mes_nome}
                        </TituloSecao>
                        <Cartao>
                            <div className="grid grid-cols-3 gap-2">
                                {cobrancas.unidades.map((u) => (
                                    <button
                                        key={u.numero}
                                        type="button"
                                        onClick={() => setApto(u.numero)}
                                        disabled={u.cobrancas.length === 0}
                                        className={cn(
                                            'rounded-xl px-2 py-2.5 text-center transition active:scale-[0.97] disabled:active:scale-100',
                                            corBloco(u.status, u.vencida),
                                        )}
                                    >
                                        <p className="text-[15px] font-extrabold text-texto">
                                            {u.numero}
                                        </p>
                                        {u.cobrancas.length > 0 && (
                                            <p className="tabular text-xs font-semibold text-texto/80">
                                                {dinheiro(u.total)}
                                            </p>
                                        )}
                                        <p className="text-[11px] font-bold">
                                            {rotuloBloco(u.status, u.vencida)}
                                        </p>
                                    </button>
                                ))}
                            </div>
                        </Cartao>
                    </section>
                )}

                {podeEditar && (
                    <div className="grid grid-cols-2 gap-2">
                        <Atalho
                            href="/gestao/caixa?novo=lancamento"
                            icone={<Plus className="size-5" />}
                            rotulo="Lançamento"
                        />
                        <Atalho
                            href="/gestao/fechamento"
                            icone={<Lock className="size-5" />}
                            rotulo="Fechar mês"
                            escuro
                        />
                    </div>
                )}
            </div>

            <LancarConta
                concessionaria={lancar}
                contas={contas}
                aoFechar={() => setLancar(null)}
            />
            <PagamentoUnidade
                unidade={unidadeAberta}
                mesNome={cobrancas?.mes_nome ?? ''}
                podeEditar={podeEditar}
                aoFechar={() => setApto(null)}
            />
        </>
    );
}

function BotaoConta({
    concessionaria,
    conta,
    aoAbrir,
}: {
    concessionaria: ConcessionariaId;
    conta: Conta;
    aoAbrir: () => void;
}) {
    const lancada = conta.lancada;

    return (
        <button
            type="button"
            onClick={aoAbrir}
            disabled={lancada !== null}
            className="flex min-w-0 items-center gap-2.5 rounded-2xl border border-linha bg-white p-3 text-left active:scale-[0.98] disabled:active:scale-100"
        >
            <IconeServico concessionaria={concessionaria} />
            <span className="min-w-0 flex-1">
                <span className="flex items-center gap-1 text-sm font-extrabold">
                    {NOMES[concessionaria].rotulo}
                    {lancada && (
                        <Check className="size-3.5 shrink-0 text-emerald-600" />
                    )}
                </span>
                <span className="block text-xs leading-snug text-suave">
                    {lancada ? (
                        <>
                            <span className="tabular block">
                                {dinheiro(lancada.valor)}
                            </span>
                            {lancada.consumo &&
                                conta.unidade_medida &&
                                `${lancada.consumo} ${conta.unidade_medida}`}
                        </>
                    ) : (
                        'Lançar valor'
                    )}
                </span>
            </span>
        </button>
    );
}

/**
 * Abre o seletor de PDF direto; a fatura importada vai para a tela de conferência e, ao ser
 * confirmada, gera as cobranças.
 */
function BotaoImportar({
    concessionaria,
    medidorId,
}: {
    concessionaria: ConcessionariaId;
    medidorId: number | null;
}) {
    const entrada = useRef<HTMLInputElement>(null);
    const [enviando, setEnviando] = useState(false);

    return (
        <>
            <input
                ref={entrada}
                type="file"
                accept="application/pdf"
                className="hidden"
                onChange={(e) => {
                    const arquivo = e.target.files?.[0];
                    e.target.value = '';

                    if (!arquivo || !medidorId) {
                        return;
                    }

                    router.post(
                        '/gestao/faturas/upload',
                        { arquivo, medidor_id: medidorId },
                        {
                            forceFormData: true,
                            onStart: () => setEnviando(true),
                            onFinish: () => setEnviando(false),
                        },
                    );
                }}
            />
            <Botao
                tom="branco"
                tamanho="sm"
                carregando={enviando}
                disabled={!medidorId}
                aria-label={`Importar PDF da conta de ${NOMES[concessionaria].servico}`}
                onClick={() => entrada.current?.click()}
            >
                {!enviando && <Upload />}
                Importar PDF
            </Botao>
        </>
    );
}

/**
 * Divisão igual em centavos, com o resto de 1 centavo para as primeiras unidades na ordem
 * fixa (101, 102…), como o servidor faz.
 */
function dividir(total: number, unidades: string[]): Map<string, number> {
    const n = unidades.length;
    const base = Math.floor(total / n);
    const resto = total - base * n;

    return new Map(unidades.map((u, i) => [u, base + (i < resto ? 1 : 0)]));
}

function LancarConta({
    concessionaria,
    contas,
    aoFechar,
}: {
    concessionaria: ConcessionariaId | null;
    contas: Props['contas'];
    aoFechar: () => void;
}) {
    const form = useForm({ valor: '', consumo: '' });
    // Mantém o último nome enquanto a folha anima ao fechar.
    const [ultima, setUltima] = useState<ConcessionariaId>('energisa');

    if (concessionaria && concessionaria !== ultima) {
        setUltima(concessionaria);
    }

    const atual = concessionaria ?? ultima;
    const unidade = contas[atual].unidade_medida;
    const total = lerCentavos(form.data.valor) ?? 0;
    const ocupados = contas.ocupados;
    const divisao =
        total > 0 && ocupados.length > 0 ? dividir(total, ocupados) : null;
    const valores = divisao ? [...new Set(divisao.values())] : [];

    const fechar = () => {
        form.reset();
        form.clearErrors();
        aoFechar();
    };

    return (
        <Folha
            aberta={concessionaria !== null}
            aoMudar={(aberta) => !aberta && fechar()}
            titulo={
                atual === 'internet'
                    ? 'Conta da internet'
                    : `Conta da ${NOMES[atual].rotulo}`
            }
            descricao={`${contas.mes_nome} · dividida igualmente entre os ${ocupados.length} apartamentos ocupados`}
            rodape={
                <Botao
                    largo
                    tamanho="lg"
                    carregando={form.processing}
                    disabled={total <= 0}
                    onClick={() => {
                        form.transform((dados) => ({
                            ...dados,
                            consumo: dados.consumo
                                .replace(',', '.')
                                .replace(/\.$/, ''),
                            concessionaria: atual,
                        }));
                        form.post('/gestao/contas', {
                            preserveScroll: true,
                            onSuccess: fechar,
                        });
                    }}
                >
                    Lançar e gerar cobranças
                </Botao>
            }
        >
            <div className="space-y-4 pb-1">
                <div
                    className={cn(
                        'grid gap-3',
                        unidade ? 'grid-cols-2' : 'grid-cols-1',
                    )}
                >
                    <Campo
                        rotulo="Valor (R$)"
                        htmlFor="valor-conta"
                        erro={form.errors.valor}
                    >
                        <Entrada
                            id="valor-conta"
                            inputMode="decimal"
                            enterKeyHint={unidade ? 'next' : 'done'}
                            placeholder="0,00"
                            value={form.data.valor}
                            onChange={(e) =>
                                form.setData(
                                    'valor',
                                    mascararMoeda(e.target.value),
                                )
                            }
                        />
                    </Campo>
                    {unidade && (
                        <Campo
                            rotulo={`Consumo (${unidade})`}
                            htmlFor="consumo-conta"
                            erro={form.errors.consumo}
                        >
                            <Entrada
                                id="consumo-conta"
                                inputMode="decimal"
                                enterKeyHint="done"
                                placeholder={
                                    atual === 'energisa' ? '512' : '28'
                                }
                                value={form.data.consumo}
                                onChange={(e) =>
                                    form.setData(
                                        'consumo',
                                        mascararDecimal(e.target.value, 3),
                                    )
                                }
                            />
                        </Campo>
                    )}
                </div>

                {divisao ? (
                    <div className="space-y-3 rounded-2xl bg-marinho-50 p-3.5">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold text-suave">
                                    Cada apartamento
                                </p>
                                <p className="tabular text-2xl font-extrabold text-texto">
                                    {valores.length > 1
                                        ? `${dinheiro(Math.min(...valores))} a ${dinheiro(Math.max(...valores))}`
                                        : dinheiro(valores[0])}
                                </p>
                            </div>
                            <p className="tabular text-right text-xs text-suave">
                                {dinheiro(total)} ÷ {ocupados.length}
                            </p>
                        </div>
                        <div className="grid grid-cols-3 gap-1.5">
                            {ocupados.map((u) => (
                                <div
                                    key={u}
                                    className="rounded-lg bg-white px-2 py-1.5 text-center"
                                >
                                    <p className="text-xs font-extrabold">
                                        {u}
                                    </p>
                                    <p className="tabular text-[11px] text-suave">
                                        {dinheiro(divisao.get(u))}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-suave">
                        Digite o valor para ver quanto fica para cada
                        apartamento.
                    </p>
                )}
            </div>
        </Folha>
    );
}

function PagamentoUnidade({
    unidade,
    mesNome,
    podeEditar,
    aoFechar,
}: {
    unidade: UnidadePainel | null;
    mesNome: string;
    podeEditar: boolean;
    aoFechar: () => void;
}) {
    const [enviando, setEnviando] = useState<string | null>(null);
    const [ultima, setUltima] = useState<UnidadePainel | null>(null);

    if (unidade && unidade !== ultima) {
        setUltima(unidade);
    }

    const atual = unidade ?? ultima;

    const marcar = (cobranca: number, acao: 'paga' | 'nao-paga') =>
        router.post(
            `/gestao/cobrancas/${cobranca}/${acao}`,
            {},
            {
                preserveScroll: true,
                onStart: () => setEnviando(`${cobranca}:${acao}`),
                onFinish: () => setEnviando(null),
            },
        );

    return (
        <Folha
            aberta={unidade !== null}
            aoMudar={(aberta) => !aberta && aoFechar()}
            titulo={`Apto ${atual?.numero ?? ''}`}
            descricao={
                podeEditar
                    ? `Cobranças de ${mesNome}. Foi pago?`
                    : `Cobranças de ${mesNome}`
            }
        >
            <div className="space-y-2.5 pb-2">
                {CONTAS.map((conta) => {
                    const lista = cobrancasDa(atual?.cobrancas ?? [], conta);

                    return lista.length > 0 ? (
                        lista.map((c) => (
                            <LinhaCobranca
                                key={c.id}
                                cobranca={c}
                                podeEditar={podeEditar}
                                enviando={enviando}
                                aoMarcar={marcar}
                            />
                        ))
                    ) : cobreOutra(atual?.cobrancas ?? [], conta) ? null : (
                        <div
                            key={conta}
                            className="flex items-center gap-3 rounded-2xl border border-dashed border-linha p-3.5"
                        >
                            <IconeServico
                                concessionaria={conta}
                                className="size-9 opacity-60"
                            />
                            <div className="min-w-0">
                                <p className="text-sm font-extrabold text-suave">
                                    {NOMES[conta].titulo}
                                </p>
                                <p className="text-xs text-suave">
                                    Conta de {mesNome} ainda não lançada.
                                </p>
                            </div>
                        </div>
                    );
                })}
                {/* Cobrança sem conta (só contribuição ou ajuste) também aparece para ser marcada. */}
                {atual?.cobrancas
                    .filter((c) =>
                        CONTAS.every(
                            (conta) => !c.tipos.includes(NOMES[conta].tipo),
                        ),
                    )
                    .map((c) => (
                        <LinhaCobranca
                            key={c.id}
                            cobranca={c}
                            podeEditar={podeEditar}
                            enviando={enviando}
                            aoMarcar={marcar}
                        />
                    ))}
                {podeEditar && (
                    <p className="px-1 text-xs text-suave">
                        "Pago" lança a entrada no caixa com a data de hoje. "Não
                        pago" estorna o recebimento.
                    </p>
                )}
            </div>
        </Folha>
    );
}

type CobrancaPainel = UnidadePainel['cobrancas'][number];

/** Cobranças daquela conta, na ordem em que foram geradas. */
function cobrancasDa(
    cobrancas: CobrancaPainel[],
    conta: ConcessionariaId,
): CobrancaPainel[] {
    const tipo = NOMES[conta].tipo;

    // Cobrança antiga com energia e água juntas fica na linha da primeira conta que ela cobre.
    return cobrancas.filter(
        (c) =>
            c.tipos.includes(tipo) &&
            CONTAS.find((outra) => c.tipos.includes(NOMES[outra].tipo)) ===
                conta,
    );
}

/** A conta já está dentro de uma cobrança antiga combinada (ex.: "Energia e água"). */
function cobreOutra(
    cobrancas: CobrancaPainel[],
    conta: ConcessionariaId,
): boolean {
    return cobrancas.some((c) => c.tipos.includes(NOMES[conta].tipo));
}

function LinhaCobranca({
    cobranca: c,
    podeEditar,
    enviando,
    aoMarcar,
}: {
    cobranca: CobrancaPainel;
    podeEditar: boolean;
    enviando: string | null;
    aoMarcar: (cobranca: number, acao: 'paga' | 'nao-paga') => void;
}) {
    const paga = c.status === 'paga';
    const aberta = c.status === 'aberta';

    return (
        <div className="space-y-3 rounded-2xl border border-linha p-3.5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-extrabold">{c.servico}</p>
                    <p className="tabular text-xs text-suave">
                        {dinheiro(c.valor)} · vence {diaMes(c.vencimento)}
                        {c.em_aberto > 0 &&
                            c.em_aberto < c.valor &&
                            ` · falta ${dinheiro(c.em_aberto)}`}
                    </p>
                </div>
                <SeloStatus status={c.status} vencida={c.vencida} />
            </div>

            {c.status === 'aguardando_confirmacao' && (
                <p className="rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-800">
                    A unidade enviou o comprovante. Confira no extrato antes de
                    marcar como pago.
                </p>
            )}

            {podeEditar && (
                <div className="grid grid-cols-2 gap-2">
                    <Botao
                        tom={aberta ? 'marinho' : 'branco'}
                        tamanho="sm"
                        // Selecionado: travado, mas com a cor cheia (não parece desabilitado).
                        className="disabled:opacity-100"
                        disabled={aberta}
                        carregando={enviando === `${c.id}:nao-paga`}
                        onClick={() => aoMarcar(c.id, 'nao-paga')}
                    >
                        Não pago
                    </Botao>
                    <Botao
                        tom={paga ? 'marinho' : 'ouro'}
                        tamanho="sm"
                        className="disabled:opacity-100"
                        disabled={paga}
                        carregando={enviando === `${c.id}:paga`}
                        onClick={() => aoMarcar(c.id, 'paga')}
                    >
                        {paga && <Check />}
                        Pago
                    </Botao>
                </div>
            )}
        </div>
    );
}

function SeloStatus({
    status,
    vencida,
}: {
    status: StatusCobranca;
    vencida: boolean;
}) {
    if (status === 'paga') return <Selo tom="pago">Paga</Selo>;
    if (status === 'aguardando_confirmacao')
        return <Selo tom="conferir">Conferir</Selo>;
    if (status === 'aberta' && vencida)
        return <Selo tom="atrasado">Vencida</Selo>;
    if (status === 'aberta') return <Selo tom="aberto">Aberta</Selo>;

    return <Selo>{status}</Selo>;
}

function corBloco(status: StatusCobranca | null, vencida: boolean): string {
    if (status === 'paga')
        return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100';
    if (status === 'aguardando_confirmacao')
        return 'bg-sky-50 text-sky-700 ring-1 ring-sky-100';
    if (status === 'aberta' && vencida)
        return 'bg-red-50 text-red-700 ring-1 ring-red-100';
    if (status === 'aberta')
        return 'bg-orange-50 text-orange-700 ring-1 ring-orange-100';

    return 'bg-marinho-50 text-suave';
}

function rotuloBloco(status: StatusCobranca | null, vencida: boolean): string {
    if (status === 'paga') return 'Paga';
    if (status === 'aguardando_confirmacao') return 'Conferir';
    if (status === 'aberta' && vencida) return 'Vencida';
    if (status === 'aberta') return 'Aberta';

    return '—';
}

function Numero({
    rotulo,
    valor,
    destaque = false,
}: {
    rotulo: string;
    valor: string;
    destaque?: boolean;
}) {
    return (
        <div className="rounded-2xl bg-white/8 px-3 py-2.5 ring-1 ring-white/12">
            <p className="text-[11px] font-semibold text-white/70">{rotulo}</p>
            <p
                className={cn(
                    'tabular truncate text-[15px] font-extrabold',
                    destaque && 'text-ouro-500',
                )}
            >
                {valor}
            </p>
        </div>
    );
}

function Pendencia({
    href,
    icone,
    cor,
    titulo,
    apoio,
}: {
    href: string;
    icone: React.ReactNode;
    cor: string;
    titulo: string;
    apoio: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 py-3 active:opacity-70"
        >
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    cor,
                )}
            >
                {icone}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-extrabold">
                    {titulo}
                </span>
                <span className="block truncate text-xs text-suave">
                    {apoio}
                </span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-suave" />
        </Link>
    );
}

function Atalho({
    href,
    icone,
    rotulo,
    escuro = false,
}: {
    href: string;
    icone: React.ReactNode;
    rotulo: string;
    escuro?: boolean;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'flex flex-col items-center gap-1.5 rounded-2xl border px-2 py-3.5 text-xs font-extrabold active:scale-[0.98]',
                escuro
                    ? 'border-marinho-900 bg-marinho-900 text-white'
                    : 'border-linha bg-white text-texto',
            )}
        >
            <span className={escuro ? 'text-ouro-500' : 'text-marinho-700'}>
                {icone}
            </span>
            {rotulo}
        </Link>
    );
}

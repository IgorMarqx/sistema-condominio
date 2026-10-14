import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calculator,
    ChevronRight,
    CreditCard,
    FileText,
    Inbox,
    Lock,
    Plus,
} from 'lucide-react';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { diaMes, dinheiro } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { Fatura, StatusCobranca } from '@/types';

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
        emails_revisao: number;
        faturas_para_ratear: number;
        fechamento: { mes: string; mes_rotulo: string } | null;
    };
    cobrancas: {
        mes: string;
        mes_nome: string;
        vencimento: string;
        unidades: {
            numero: string;
            andar: number;
            status: StatusCobranca | null;
            vencida: boolean;
            valor: number | null;
        }[];
    } | null;
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

export default function Painel({ resumo, pendencias, cobrancas }: Props) {
    const { auth } = usePage().props;
    const user = auth.user!;
    const podeEditar = user.pode_editar;
    const semPendencias =
        pendencias.faturas.length === 0 &&
        pendencias.comprovantes.quantidade === 0 &&
        pendencias.emails_revisao === 0 &&
        pendencias.faturas_para_ratear === 0 &&
        !pendencias.fechamento;

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
                                apoio={`Chegou ${f.origem === 'email' ? 'por e-mail' : 'por upload'} · ${dinheiro(f.valor)}`}
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
                        {pendencias.emails_revisao > 0 && (
                            <Pendencia
                                href="/gestao/faturas?aba=emails"
                                icone={<Inbox className="size-[18px]" />}
                                cor="bg-orange-100 text-orange-600"
                                titulo={`${pendencias.emails_revisao} e-mail${pendencias.emails_revisao > 1 ? 's' : ''} do condomínio para revisar`}
                                apoio="Sem PDF, PDF com senha ou medidor desconhecido"
                            />
                        )}
                        {pendencias.faturas_para_ratear > 0 && (
                            <Pendencia
                                href="/gestao/rateio"
                                icone={<Calculator className="size-[18px]" />}
                                cor="bg-ouro-100 text-[#8a6100]"
                                titulo="Gerar o rateio"
                                apoio={`${pendencias.faturas_para_ratear} fatura${pendencias.faturas_para_ratear > 1 ? 's' : ''} confirmada${pendencias.faturas_para_ratear > 1 ? 's' : ''} fora de rateio`}
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
                            acao={`vence ${diaMes(cobrancas.vencimento)}`}
                        >
                            Cobranças de {cobrancas.mes_nome}
                        </TituloSecao>
                        <Cartao>
                            <div className="grid grid-cols-3 gap-2">
                                {cobrancas.unidades.map((u) => (
                                    <Link
                                        key={u.numero}
                                        href={`/gestao/cobrancas?mes=${cobrancas.mes}#apto-${u.numero}`}
                                        className={cn(
                                            'rounded-xl px-2 py-2.5 text-center',
                                            corBloco(u.status, u.vencida),
                                        )}
                                    >
                                        <p className="text-[15px] font-extrabold text-texto">
                                            {u.numero}
                                        </p>
                                        <p className="text-[11px] font-bold">
                                            {rotuloBloco(u.status, u.vencida)}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </Cartao>
                    </section>
                )}

                {podeEditar && (
                    <div className="grid grid-cols-3 gap-2">
                        <Atalho
                            href="/gestao/caixa?novo=lancamento"
                            icone={<Plus className="size-5" />}
                            rotulo="Lançamento"
                        />
                        <Atalho
                            href="/gestao/rateio"
                            icone={<Calculator className="size-5" />}
                            rotulo="Gerar rateio"
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
        </>
    );
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

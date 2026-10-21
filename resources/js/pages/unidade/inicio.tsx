import { Head, Link, usePage } from '@inertiajs/react';
import { Bell, QrCode, ReceiptText } from 'lucide-react';
import AvisoLinha from '@/components/condominio/aviso-linha';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import Selo from '@/components/condominio/selo';
import ValorGrande from '@/components/condominio/valor-grande';
import Vazio from '@/components/condominio/vazio';
import { diaMes, dinheiro, dinheiroSinal } from '@/lib/formato';
import type { Aviso, Cobranca, LeituraPrevista } from '@/types';

type Props = {
    cobranca: (Cobranca & { composicao: string[] }) | null;
    caixa: { saldo: number; variacao_mes: number };
    leituras: LeituraPrevista[];
    avisos: Aviso[];
};

const nomesComposicao: Record<string, string> = {
    energia: 'energia',
    agua: 'água',
    contribuicao: 'contribuição',
    ajuste: 'ajuste',
};

function composicao(itens: string[]): string {
    const nomes = itens.map((i) => nomesComposicao[i] ?? i);

    if (nomes.length <= 1) {
        return nomes.join('');
    }

    const texto = `${nomes.slice(0, -1).join(', ')} e ${nomes.at(-1)}`;

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

export default function Inicio({ cobranca, caixa, leituras, avisos }: Props) {
    const { auth, edificio, avisos_nao_lidos } = usePage().props;
    const apto = auth.user?.unidade?.numero ?? auth.user?.login;

    return (
        <>
            <Head title="Início" />
            <header className="topo-marinho area-segura-topo rounded-b-[28px] px-5 pt-6 pb-20 text-white">
                <div className="flex items-center gap-3">
                    <Link
                        href="/conta"
                        aria-label="Minha conta"
                        className="flex size-11 items-center justify-center rounded-full bg-ouro-500 text-sm font-extrabold text-marinho-900"
                    >
                        {apto}
                    </Link>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-xs font-semibold text-white/70">
                            {edificio}
                            {auth.nome_informado && ` · ${auth.nome_informado}`}
                        </p>
                        <p className="truncate text-lg font-extrabold">
                            Olá, apartamento {apto}
                        </p>
                    </div>
                    <Link
                        href="/avisos"
                        className="relative flex size-10 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15"
                        aria-label="Avisos"
                    >
                        <Bell className="size-[18px]" />
                        {avisos_nao_lidos > 0 && (
                            <span className="absolute top-1.5 right-2 size-2 rounded-full bg-ouro-500 ring-2 ring-marinho-900" />
                        )}
                    </Link>
                </div>
            </header>

            <div className="-mt-14 space-y-4 px-4">
                <CartaoCobranca cobranca={cobranca} />

                <div className="grid grid-cols-2 gap-3">
                    <Link href="/caixa" className="block">
                        <Cartao className="h-full">
                            <p className="text-xs font-semibold text-suave">
                                Saldo do caixa
                            </p>
                            <p className="tabular mt-1 text-lg font-extrabold tracking-tight">
                                {dinheiro(caixa.saldo)}
                            </p>
                            <p
                                className={
                                    caixa.variacao_mes >= 0
                                        ? 'text-xs font-bold text-emerald-600'
                                        : 'text-xs font-bold text-red-600'
                                }
                            >
                                {dinheiroSinal(caixa.variacao_mes)} no mês
                            </p>
                        </Cartao>
                    </Link>
                    <Link href="/condominio" className="block">
                        <Cartao className="h-full">
                            <p className="text-xs font-semibold text-suave">
                                Próximas leituras
                            </p>
                            {leituras.length === 0 ? (
                                <p className="mt-2 text-xs text-suave">
                                    Sem data prevista.
                                </p>
                            ) : (
                                <ul className="mt-1.5 space-y-1">
                                    {leituras.map((l) => (
                                        <li
                                            key={l.concessionaria}
                                            className="flex items-center gap-1.5 text-[13px] font-bold"
                                        >
                                            <span
                                                className={
                                                    l.concessionaria ===
                                                    'energisa'
                                                        ? 'size-2 rounded-full bg-ouro-500'
                                                        : 'size-2 rounded-full bg-sky-500'
                                                }
                                            />
                                            {l.servico} · {diaMes(l.data)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Cartao>
                    </Link>
                </div>

                <section>
                    <TituloSecao acao={<Link href="/avisos">Ver todos</Link>}>
                        Avisos
                    </TituloSecao>
                    <Cartao className="divide-y divide-linha py-1">
                        {avisos.length === 0 ? (
                            <p className="py-6 text-center text-sm text-suave">
                                Nenhum aviso por enquanto.
                            </p>
                        ) : (
                            avisos.map((aviso) => (
                                <AvisoLinha key={aviso.id} aviso={aviso} />
                            ))
                        )}
                    </Cartao>
                </section>
            </div>
        </>
    );
}

function CartaoCobranca({ cobranca }: { cobranca: Props['cobranca'] }) {
    if (!cobranca) {
        return (
            <Cartao className="shadow-lg">
                <Vazio
                    icone={ReceiptText}
                    titulo="Nenhuma cobrança no momento"
                    className="py-6"
                >
                    Quando o síndico lançar as contas do mês, a cobrança aparece
                    aqui.
                </Vazio>
            </Cartao>
        );
    }

    const selo =
        cobranca.status === 'paga' ? (
            <Selo tom="pago">Paga</Selo>
        ) : cobranca.status === 'aguardando_confirmacao' ? (
            <Selo tom="conferir">Comprovante enviado</Selo>
        ) : cobranca.vencida ? (
            <Selo tom="atrasado">Vencida</Selo>
        ) : (
            <Selo tom="aberto">Em aberto</Selo>
        );

    return (
        <Cartao className="p-5 shadow-[0_12px_30px_-12px_rgb(19_37_79/0.35)]">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-bold text-suave">
                    {cobranca.servico} · {cobranca.mes_nome}/
                    {cobranca.mes.slice(0, 4)}
                </p>
                {selo}
            </div>
            <ValorGrande
                centavos={cobranca.valor}
                className="mt-1 text-texto"
            />
            <p className="text-xs text-suave">
                Vence em{' '}
                <strong className="text-texto">
                    {diaMes(cobranca.vencimento)}
                </strong>
                {cobranca.composicao.length > 0 && (
                    <> · {composicao(cobranca.composicao)}</>
                )}
            </p>
            <Botao
                asChild
                tamanho="lg"
                largo
                className="mt-4"
                tom={cobranca.status === 'aberta' ? 'ouro' : 'branco'}
            >
                <Link href={`/cobranca/${cobranca.id}`}>
                    {cobranca.status === 'aberta' ? (
                        <>
                            <QrCode /> Pagar com Pix
                        </>
                    ) : (
                        'Ver cobrança'
                    )}
                </Link>
            </Botao>
        </Cartao>
    );
}

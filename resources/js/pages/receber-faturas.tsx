import { Head, Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, Inbox, KeyRound, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import BotaoCopiar from '@/components/condominio/botao-copiar';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import Folha from '@/components/condominio/folha';
import IconeServico from '@/components/condominio/icone-servico';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import { quando, quandoComHora } from '@/lib/formato';
import type { ConcessionariaId, SituacaoRecebimento } from '@/types';

type Email = {
    id: number;
    remetente: string | null;
    assunto: string | null;
    tipo: 'fatura' | 'verificacao' | 'sem_anexo' | 'outro' | null;
    status:
        | 'recebido'
        | 'processado'
        | 'duplicado'
        | 'descartado'
        | 'revisao'
        | 'erro';
    erro: string | null;
    links: string[];
    recebido_em: string;
};

type Props = {
    endereco: string;
    condominio: boolean;
    recebimento: SituacaoRecebimento;
    verificacao: {
        remetente: string | null;
        gmail: boolean;
        codigo: string | null;
        link: string | null;
        recebido_em: string;
    } | null;
    emails: Email[];
};

const textoSituacao: Record<ConcessionariaId, Record<string, string>> = {
    energisa: {
        ativo: 'Última fatura recebida',
        manual: 'Última fatura enviada à mão',
        pendente: 'Cadastre o endereço no app da Energisa',
    },
    cagepa: {
        ativo: 'Última fatura recebida',
        manual: 'Envie o PDF para o endereço acima',
        pendente: 'Envie o PDF para o endereço acima',
    },
};

export default function ReceberFaturas({
    endereco,
    condominio,
    recebimento,
    verificacao,
    emails,
}: Props) {
    const { auth } = usePage().props;
    const [gerar, setGerar] = useState(false);
    const [processando, setProcessando] = useState(false);
    const base = condominio ? '/gestao/receber-faturas' : '/receber-faturas';

    return (
        <>
            <Head title="Receber faturas por e-mail" />
            <TopoTela
                voltar={condominio ? '/gestao/faturas' : '/minhas-contas'}
                contexto={
                    condominio
                        ? 'Medidores do condomínio'
                        : `Apartamento ${auth.user?.unidade?.numero ?? ''}`
                }
                titulo="Receber faturas por e-mail"
            />

            <div className="space-y-4 px-4">
                <div className="topo-marinho rounded-2xl p-4 text-white">
                    <p className="text-xs font-semibold text-white/75">
                        Endereço exclusivo{' '}
                        {condominio ? 'do condomínio' : 'do seu apartamento'}
                    </p>
                    <p className="mt-2 rounded-xl bg-white/10 px-3 py-2.5 font-mono text-[13px] font-medium break-all ring-1 ring-white/15">
                        {endereco}
                    </p>
                    <div className="mt-3 grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <BotaoCopiar
                            texto={endereco}
                            rotulo="Copiar endereço"
                            mensagem="Endereço copiado."
                        />
                        <Botao
                            tom="vidro"
                            onClick={() => setGerar(true)}
                            disabled={!auth.user?.pode_editar && condominio}
                        >
                            Gerar novo
                        </Botao>
                    </div>
                </div>

                <Cartao className="divide-y divide-linha py-1">
                    {(['energisa', 'cagepa'] as const).map((c) => {
                        const r = recebimento[c];

                        return (
                            <div
                                key={c}
                                className="flex items-center gap-3 py-3"
                            >
                                <IconeServico concessionaria={c} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-extrabold">
                                        {c === 'energisa'
                                            ? 'Energisa'
                                            : 'Cagepa'}
                                    </p>
                                    <p className="truncate text-xs text-suave">
                                        {textoSituacao[c][r.situacao]}
                                        {r.ultima &&
                                            r.situacao !== 'pendente' &&
                                            ` ${quando(r.ultima)}`}
                                    </p>
                                </div>
                                <Selo
                                    tom={
                                        r.situacao === 'ativo'
                                            ? 'pago'
                                            : r.situacao === 'manual'
                                              ? 'alerta'
                                              : 'neutro'
                                    }
                                >
                                    {r.situacao === 'ativo'
                                        ? 'Ativo'
                                        : r.situacao === 'manual'
                                          ? 'Manual'
                                          : 'Pendente'}
                                </Selo>
                            </div>
                        );
                    })}
                </Cartao>

                {verificacao && (
                    <div className="rounded-2xl border border-ouro-300 bg-ouro-50 p-4">
                        <p className="flex items-center gap-2 text-sm font-extrabold">
                            <KeyRound className="size-4 text-[#b7791f]" />
                            {verificacao.gmail
                                ? 'Confirmação do Gmail recebida'
                                : 'Confirmação de e-mail recebida'}
                        </p>
                        <p className="mt-1 text-xs text-suave">
                            {verificacao.gmail
                                ? 'Para ativar o encaminhamento, digite este código no Gmail:'
                                : `De ${verificacao.remetente}. Use o código ou o link abaixo para confirmar.`}
                        </p>
                        {verificacao.codigo && (
                            <div className="mt-3 flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-ouro-300">
                                <span className="font-mono text-xl font-bold tracking-[0.2em]">
                                    {verificacao.codigo}
                                </span>
                                <BotaoCopiar
                                    texto={verificacao.codigo}
                                    rotulo="Copiar"
                                    tamanho="sm"
                                    tom="branco"
                                />
                            </div>
                        )}
                        {verificacao.link && (
                            <a
                                href={verificacao.link}
                                target="_blank"
                                rel="noreferrer noopener"
                                className="mt-2 flex items-center gap-1.5 text-xs font-bold text-marinho-700"
                            >
                                <ExternalLink className="size-3.5" /> Abrir o
                                link de confirmação
                            </a>
                        )}
                        <p className="mt-2 text-[11px] text-suave">
                            Recebido {quandoComHora(verificacao.recebido_em)}.
                        </p>
                    </div>
                )}

                <section>
                    <TituloSecao>Como configurar</TituloSecao>
                    <Cartao className="space-y-4">
                        <Passo numero={1} titulo="Direto">
                            No app da Energisa, cadastre este endereço como
                            e-mail da fatura digital.
                        </Passo>
                        <Passo numero={2} titulo="Encaminhar">
                            No Gmail, crie um filtro para os e-mails da
                            concessionária e encaminhe para este endereço. O
                            código de confirmação aparece aqui.
                        </Passo>
                        <Passo numero={3} titulo="Manual">
                            Baixe o PDF no app e envie para o endereço, ou{' '}
                            <Link
                                href={
                                    condominio
                                        ? '/gestao/faturas'
                                        : '/minhas-contas'
                                }
                                className="font-bold text-marinho-700 underline"
                            >
                                faça upload aqui
                            </Link>
                            .
                        </Passo>
                    </Cartao>
                </section>

                <section>
                    <TituloSecao>E-mails recebidos</TituloSecao>
                    <Cartao className="divide-y divide-linha py-1">
                        {emails.length === 0 && (
                            <div className="flex flex-col items-center py-8 text-center text-sm text-suave">
                                <Inbox className="mb-2 size-6" />
                                Nada recebido ainda.
                            </div>
                        )}
                        {emails.map((e) => (
                            <div key={e.id} className="py-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold">
                                            {e.assunto || '(sem assunto)'}
                                        </p>
                                        <p className="truncate text-xs text-suave">
                                            {e.remetente} ·{' '}
                                            {quandoComHora(e.recebido_em)}
                                        </p>
                                    </div>
                                    <SeloEmail email={e} />
                                </div>
                                {e.erro && (
                                    <p className="mt-1 text-xs text-orange-700">
                                        {e.erro}
                                    </p>
                                )}
                                {e.links.length > 0 && (
                                    <ul className="mt-1 space-y-0.5">
                                        {e.links.slice(0, 3).map((l) => (
                                            <li
                                                key={l}
                                                className="truncate font-mono text-[11px] text-suave"
                                                title={l}
                                            >
                                                {l}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </Cartao>
                </section>
            </div>

            <Folha
                aberta={gerar}
                aoMudar={setGerar}
                titulo="Gerar endereço novo?"
                descricao="Use se o endereço atual começou a receber spam."
                rodape={
                    <Botao
                        tamanho="lg"
                        largo
                        carregando={processando}
                        onClick={() =>
                            router.post(
                                `${base}/regenerar`,
                                {},
                                {
                                    onStart: () => setProcessando(true),
                                    onFinish: () => {
                                        setProcessando(false);
                                        setGerar(false);
                                    },
                                },
                            )
                        }
                    >
                        <RefreshCw /> Gerar novo endereço
                    </Botao>
                }
            >
                <p className="pb-2 text-sm text-suave">
                    O endereço antigo para de funcionar na hora: o que chegar
                    nele é descartado. Depois, cadastre o novo no app da
                    concessionária e atualize o filtro do Gmail.
                </p>
            </Folha>
        </>
    );
}

function Passo({
    numero,
    titulo,
    children,
}: {
    numero: number;
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex gap-3">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-marinho-900 text-xs font-extrabold text-white">
                {numero}
            </span>
            <p className="text-sm text-suave">
                <strong className="text-texto">{titulo}:</strong> {children}
            </p>
        </div>
    );
}

function SeloEmail({ email }: { email: Email }) {
    if (email.tipo === 'verificacao') {
        return <Selo tom="aberto">Confirmação</Selo>;
    }

    switch (email.status) {
        case 'processado':
            return <Selo tom="pago">Fatura lida</Selo>;
        case 'duplicado':
            return <Selo tom="neutro">Repetida</Selo>;
        case 'revisao':
            return <Selo tom="alerta">Revisar</Selo>;
        case 'erro':
            return <Selo tom="atrasado">Erro</Selo>;
        case 'descartado':
            return <Selo tom="neutro">Descartado</Selo>;
        default:
            return <Selo tom="conferir">Processando</Selo>;
    }
}

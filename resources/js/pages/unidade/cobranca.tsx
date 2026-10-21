import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    FileText,
    Paperclip,
    QrCode,
    ReceiptText,
    Upload,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import BotaoCopiar from '@/components/condominio/botao-copiar';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Etapas from '@/components/condominio/etapas';
import Folha from '@/components/condominio/folha';
import SeletorArquivo from '@/components/condominio/seletor-arquivo';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import { data, diaMes, dinheiro, hojeIso, valor } from '@/lib/formato';
import type { Cobranca, CobrancaDetalhe } from '@/types';

type Props = {
    cobranca:
        | (CobrancaDetalhe & {
              em_aberto: number;
              faturas: { id: number; rotulo: string; valor: number }[];
          })
        | null;
    historico: Cobranca[];
};

export default function PaginaCobranca({ cobranca, historico }: Props) {
    const [pdfs, setPdfs] = useState(false);
    const [enviar, setEnviar] = useState(false);
    const [qr, setQr] = useState(false);

    if (!cobranca) {
        return (
            <>
                <Head title="Cobrança" />
                <TopoTela voltar="/" titulo="Cobrança" />
                <div className="px-4">
                    <Cartao>
                        <Vazio
                            icone={ReceiptText}
                            titulo="Nenhuma cobrança ainda"
                        >
                            A cobrança aparece quando o síndico lança as contas
                            do mês.
                        </Vazio>
                    </Cartao>
                </div>
            </>
        );
    }

    const etapa =
        cobranca.status === 'paga'
            ? 2
            : cobranca.status === 'aguardando_confirmacao'
              ? 1
              : 0;

    return (
        <>
            <Head title={`Cobrança de ${cobranca.mes_nome}`} />
            <TopoTela
                voltar="/"
                contexto={`Apartamento ${cobranca.unidade}`}
                titulo={`${cobranca.servico} · ${cobranca.mes_nome}`}
            />

            <div className="space-y-4 px-4">
                <Cartao>
                    <div className="flex items-baseline justify-between">
                        <p className="text-sm font-bold text-suave">Total</p>
                        <p className="tabular text-3xl font-extrabold tracking-tight">
                            {dinheiro(cobranca.valor)}
                        </p>
                    </div>
                    <ul className="mt-3 space-y-2.5 border-t border-linha pt-3">
                        {cobranca.itens.map((item, i) => {
                            const [principal, detalhe] =
                                item.descricao.split(' (');

                            return (
                                <li
                                    key={i}
                                    className="flex items-start justify-between gap-3 text-sm"
                                >
                                    <span className="min-w-0 text-texto">
                                        {principal}
                                        {detalhe && (
                                            <span className="text-suave">
                                                {' '}
                                                ({detalhe}
                                            </span>
                                        )}
                                    </span>
                                    <span className="tabular shrink-0 font-bold">
                                        {valor(item.valor)}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                    {cobranca.faturas.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setPdfs(true)}
                            className="mt-3 flex w-full items-center justify-between rounded-xl bg-[#f7f8fb] px-3 py-2.5 text-xs"
                        >
                            <span className="flex items-center gap-2 font-semibold text-suave">
                                <Paperclip className="size-3.5" /> Faturas
                                originais anexadas
                            </span>
                            <span className="font-extrabold text-texto">
                                Ver PDFs
                            </span>
                        </button>
                    )}
                </Cartao>

                {cobranca.status !== 'paga' && cobranca.pix && (
                    <Cartao>
                        <div className="flex items-center justify-between">
                            <p className="font-extrabold">Pagar com Pix</p>
                            <p
                                className={
                                    cobranca.vencida
                                        ? 'text-xs font-bold text-red-600'
                                        : 'text-xs font-semibold text-suave'
                                }
                            >
                                {cobranca.vencida ? 'Venceu' : 'Vence'}{' '}
                                {diaMes(cobranca.vencimento)}
                            </p>
                        </div>
                        {qr ? (
                            <div className="mt-3 flex justify-center rounded-xl border border-linha bg-white p-4">
                                <QRCodeSVG
                                    value={cobranca.pix}
                                    size={200}
                                    level="M"
                                />
                            </div>
                        ) : (
                            <p className="mt-3 rounded-xl bg-[#f7f8fb] px-3 py-2.5 font-mono text-[11px] leading-relaxed break-all text-suave">
                                <span className="line-clamp-3">
                                    {cobranca.pix}
                                </span>
                            </p>
                        )}
                        <BotaoCopiar
                            texto={cobranca.pix}
                            rotulo="Copiar código Pix"
                            mensagem="Código Pix copiado. Cole no app do banco."
                            tamanho="lg"
                            largo
                            className="mt-3"
                        />
                        <button
                            type="button"
                            onClick={() => setQr((v) => !v)}
                            className="mt-2 flex w-full items-center justify-center gap-1.5 py-1 text-xs font-bold text-marinho-700"
                        >
                            <QrCode className="size-3.5" />{' '}
                            {qr ? 'Mostrar o código' : 'Mostrar QR Code'}
                        </button>
                    </Cartao>
                )}

                {cobranca.status !== 'paga' && !cobranca.pix && (
                    <Cartao className="text-sm text-suave">
                        O síndico ainda não cadastrou a chave Pix do condomínio.
                    </Cartao>
                )}

                {cobranca.status !== 'paga' && (
                    <section>
                        <TituloSecao>Já pagou?</TituloSecao>
                        <button
                            type="button"
                            onClick={() => setEnviar(true)}
                            className="w-full"
                        >
                            <Cartao className="flex items-center gap-3 text-left active:scale-[0.99]">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-marinho-50 text-marinho-700">
                                    <Upload className="size-[18px]" />
                                </span>
                                <span className="flex-1">
                                    <span className="block text-sm font-extrabold">
                                        Enviar comprovante
                                    </span>
                                    <span className="block text-xs text-suave">
                                        Foto ou PDF · o síndico confirma
                                    </span>
                                </span>
                                <ChevronRight className="size-4 text-suave" />
                            </Cartao>
                        </button>
                    </section>
                )}

                {cobranca.pagamentos.length > 0 && (
                    <Cartao className="divide-y divide-linha py-1">
                        {cobranca.pagamentos.map((p) => (
                            <div
                                key={p.id}
                                className="flex items-center justify-between gap-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold">
                                        Comprovante de {dinheiro(p.valor)}
                                    </p>
                                    <p className="truncate text-xs text-suave">
                                        Pago em {data(p.pago_em)}
                                        {p.nome_informado &&
                                            ` · enviado por ${p.nome_informado}`}
                                        {p.motivo_recusa &&
                                            ` · ${p.motivo_recusa}`}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <Selo
                                        tom={
                                            p.status === 'confirmado'
                                                ? 'pago'
                                                : p.status === 'recusado'
                                                  ? 'atrasado'
                                                  : 'conferir'
                                        }
                                    >
                                        {p.status === 'confirmado'
                                            ? 'Confirmado'
                                            : p.status === 'recusado'
                                              ? 'Recusado'
                                              : 'Aguardando'}
                                    </Selo>
                                    <a
                                        href={`/arquivos/pagamento/${p.id}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-suave"
                                        aria-label="Abrir comprovante"
                                    >
                                        <FileText className="size-4" />
                                    </a>
                                </div>
                            </div>
                        ))}
                    </Cartao>
                )}

                <div className="px-1 py-2">
                    <Etapas
                        etapas={['Gerada', 'Paga', 'Confirmada']}
                        atual={etapa}
                    />
                </div>

                {historico.length > 1 && (
                    <section>
                        <TituloSecao>Cobranças anteriores</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {historico.map((c) => (
                                <Link
                                    key={c.id}
                                    href={`/cobranca/${c.id}`}
                                    className="flex items-center justify-between gap-3 py-3"
                                >
                                    <span>
                                        <span className="block text-sm font-bold">
                                            {c.mes_rotulo} · {c.servico}
                                        </span>
                                        <span className="block text-xs text-suave">
                                            Vence {data(c.vencimento)}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="tabular text-sm font-bold">
                                            {dinheiro(c.valor)}
                                        </span>
                                        <Selo
                                            tom={
                                                c.status === 'paga'
                                                    ? 'pago'
                                                    : c.status ===
                                                        'aguardando_confirmacao'
                                                      ? 'conferir'
                                                      : c.vencida
                                                        ? 'atrasado'
                                                        : 'aberto'
                                            }
                                        >
                                            {c.status === 'aberta' && c.vencida
                                                ? 'Vencida'
                                                : c.status_rotulo}
                                        </Selo>
                                    </span>
                                </Link>
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>

            <Folha
                aberta={pdfs}
                aoMudar={setPdfs}
                titulo="Faturas originais"
                descricao="As contas do condomínio que entraram nesta cobrança."
            >
                <ul className="divide-y divide-linha pb-2">
                    {cobranca.faturas.map((f) => (
                        <li key={f.id}>
                            <a
                                href={`/arquivos/fatura/${f.id}`}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-3 py-3"
                            >
                                <FileText className="size-5 text-marinho-700" />
                                <span className="flex-1 text-sm font-bold">
                                    {f.rotulo}
                                </span>
                                <span className="tabular text-sm font-bold">
                                    {dinheiro(f.valor)}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            </Folha>

            <EnviarComprovante
                cobranca={cobranca}
                aberta={enviar}
                aoMudar={setEnviar}
            />
        </>
    );
}

function EnviarComprovante({
    cobranca,
    aberta,
    aoMudar,
}: {
    cobranca: NonNullable<Props['cobranca']>;
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    const form = useForm<{
        arquivo: File | null;
        pago_em: string;
        valor: string;
    }>({
        arquivo: null,
        pago_em: hojeIso(),
        valor: valor(cobranca.em_aberto),
    });

    return (
        <Folha
            aberta={aberta}
            aoMudar={aoMudar}
            titulo="Enviar comprovante"
            descricao="O síndico confere no extrato do banco e confirma."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={!form.data.arquivo}
                    onClick={() =>
                        form.post(`/cobranca/${cobranca.id}/comprovante`, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                aoMudar(false);
                            },
                        })
                    }
                >
                    Enviar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <SeletorArquivo
                    arquivo={form.data.arquivo}
                    aoEscolher={(f) => form.setData('arquivo', f)}
                    titulo="Escolher comprovante"
                    erro={form.errors.arquivo}
                />
                <div className="grid grid-cols-2 gap-3">
                    <Campo rotulo="Pago em" erro={form.errors.pago_em}>
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

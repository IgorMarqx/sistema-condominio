import { FileText } from 'lucide-react';
import { diaMes, data, dinheiro, numero, quandoComHora } from '@/lib/formato';
import type { Fatura } from '@/types';
import Botao from './botao';
import BotaoCopiar from './botao-copiar';
import Folha from './folha';
import SeloFatura from './selo-fatura';

/**
 * Folha com os campos de uma fatura e o PDF original.
 */
export default function DetalheFatura({
    fatura,
    aoFechar,
    individual = false,
    children,
}: {
    fatura: Fatura | null;
    aoFechar: () => void;
    individual?: boolean;
    children?: React.ReactNode;
}) {
    return (
        <Folha
            aberta={fatura !== null}
            aoMudar={(v) => !v && aoFechar()}
            titulo={
                fatura
                    ? `${fatura.concessionaria_rotulo} · ${fatura.mes_rotulo}`
                    : ''
            }
            descricao={
                fatura
                    ? `${fatura.origem === 'email' ? 'Recebida por e-mail' : 'Enviada manualmente'} · ${quandoComHora(fatura.recebida_em)}`
                    : undefined
            }
        >
            {fatura && (
                <div className="space-y-4 pb-2">
                    <div className="flex items-center justify-between">
                        <p className="tabular text-3xl font-extrabold">
                            {dinheiro(fatura.valor)}
                        </p>
                        <SeloFatura fatura={fatura} individual={individual} />
                    </div>
                    <dl className="divide-y divide-linha rounded-2xl border border-linha px-4 text-sm">
                        <Linha
                            rotulo={
                                fatura.concessionaria === 'energisa'
                                    ? 'Unidade consumidora'
                                    : 'Matrícula'
                            }
                            valor={fatura.medidor.identificador}
                        />
                        <Linha
                            rotulo="Leituras"
                            valor={`${diaMes(fatura.leitura_anterior)} → ${diaMes(fatura.leitura_atual)}`}
                        />
                        <Linha
                            rotulo="Consumo"
                            valor={`${numero(fatura.consumo, 3)} ${fatura.unidade_medida ?? ''}`}
                        />
                        <Linha
                            rotulo="Vencimento"
                            valor={data(fatura.vencimento)}
                        />
                        {fatura.proxima_leitura && (
                            <Linha
                                rotulo="Próxima leitura"
                                valor={data(fatura.proxima_leitura)}
                            />
                        )}
                        {fatura.paga_em && (
                            <Linha
                                rotulo="Paga em"
                                valor={data(fatura.paga_em)}
                            />
                        )}
                    </dl>
                    {(fatura.pix_copia_cola || fatura.linha_digitavel) &&
                        !fatura.paga_em && (
                            <BotaoCopiar
                                texto={
                                    (fatura.pix_copia_cola ??
                                        fatura.linha_digitavel) as string
                                }
                                rotulo={
                                    fatura.pix_copia_cola
                                        ? 'Copiar Pix'
                                        : 'Copiar código de barras'
                                }
                                tom="marinho"
                                largo
                            />
                        )}
                    {fatura.tem_pdf && (
                        <Botao asChild tom="branco" largo>
                            <a
                                href={`/arquivos/fatura/${fatura.id}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText /> Ver PDF original
                            </a>
                        </Botao>
                    )}
                    {children}
                </div>
            )}
        </Folha>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="flex items-center justify-between gap-3 py-2.5">
            <dt className="text-suave">{rotulo}</dt>
            <dd className="tabular text-right font-bold">{valor}</dd>
        </div>
    );
}

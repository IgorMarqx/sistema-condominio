import { Head, useForm, usePage } from '@inertiajs/react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada, Escolha } from '@/components/condominio/campo';
import TopoTela from '@/components/condominio/topo-tela';
import { valor } from '@/lib/formato';

type Valores = {
    nome_edificio: string;
    endereco: string | null;
    contribuicao_centavos: number;
    dias_antecedencia: number;
    regra_rateio: 'igual' | 'fracao';
    pix_chave: string | null;
    pix_recebedor: string | null;
    pix_cidade: string | null;
    banco_titular: string | null;
    limite_anomalia_percentual: number;
    remetentes_energisa: string[];
    remetentes_cagepa: string[];
};

export default function Configuracoes({
    valores,
    dominio_faturas,
}: {
    valores: Valores;
    dominio_faturas: string;
}) {
    const { auth } = usePage().props;
    const desabilitado = !auth.user?.pode_editar;
    const form = useForm({
        nome_edificio: valores.nome_edificio,
        endereco: valores.endereco ?? '',
        contribuicao: valor(valores.contribuicao_centavos),
        dias_antecedencia: String(valores.dias_antecedencia),
        regra_rateio: valores.regra_rateio,
        pix_chave: valores.pix_chave ?? '',
        pix_recebedor: valores.pix_recebedor ?? '',
        pix_cidade: valores.pix_cidade ?? '',
        banco_titular: valores.banco_titular ?? '',
        limite_anomalia_percentual: String(valores.limite_anomalia_percentual),
        remetentes_energisa: valores.remetentes_energisa.join(', '),
        remetentes_cagepa: valores.remetentes_cagepa.join(', '),
    });

    return (
        <>
            <Head title="Configurações" />
            <TopoTela
                titulo="Configurações"
                contexto="Valem a partir do próximo rateio"
            />
            <form
                className="space-y-5 px-4 pb-6 lg:px-0"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/gestao/configuracoes', { preserveScroll: true });
                }}
            >
                <section>
                    <TituloSecao>Prédio</TituloSecao>
                    <Cartao className="grid gap-3 sm:grid-cols-2">
                        <Campo rotulo="Nome" erro={form.errors.nome_edificio}>
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.nome_edificio}
                                onChange={(e) =>
                                    form.setData(
                                        'nome_edificio',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                        <Campo rotulo="Endereço" erro={form.errors.endereco}>
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.endereco}
                                onChange={(e) =>
                                    form.setData('endereco', e.target.value)
                                }
                            />
                        </Campo>
                    </Cartao>
                </section>

                <section>
                    <TituloSecao>Rateio e cobrança</TituloSecao>
                    <Cartao className="grid gap-3 sm:grid-cols-2">
                        <Campo rotulo="Regra" erro={form.errors.regra_rateio}>
                            <Escolha
                                disabled={desabilitado}
                                value={form.data.regra_rateio}
                                onChange={(e) =>
                                    form.setData(
                                        'regra_rateio',
                                        e.target
                                            .value as Valores['regra_rateio'],
                                    )
                                }
                            >
                                <option value="igual">Divisão igual</option>
                                <option value="fracao">Fração ideal</option>
                            </Escolha>
                        </Campo>
                        <Campo
                            rotulo="Contribuição mensal ao caixa (R$)"
                            erro={form.errors.contribuicao}
                        >
                            <Entrada
                                disabled={desabilitado}
                                inputMode="decimal"
                                value={form.data.contribuicao}
                                onChange={(e) =>
                                    form.setData('contribuicao', e.target.value)
                                }
                            />
                        </Campo>
                        <Campo
                            rotulo="Vencer quantos dias antes da concessionária"
                            erro={form.errors.dias_antecedencia}
                        >
                            <Entrada
                                disabled={desabilitado}
                                inputMode="numeric"
                                value={form.data.dias_antecedencia}
                                onChange={(e) =>
                                    form.setData(
                                        'dias_antecedencia',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                        <Campo
                            rotulo="Alerta de consumo acima da média (%)"
                            erro={form.errors.limite_anomalia_percentual}
                        >
                            <Entrada
                                disabled={desabilitado}
                                inputMode="numeric"
                                value={form.data.limite_anomalia_percentual}
                                onChange={(e) =>
                                    form.setData(
                                        'limite_anomalia_percentual',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                    </Cartao>
                </section>

                <section>
                    <TituloSecao>Pix e banco</TituloSecao>
                    <Cartao className="grid gap-3 sm:grid-cols-2">
                        <Campo
                            rotulo="Chave Pix"
                            erro={form.errors.pix_chave}
                            ajuda="CPF/CNPJ, e-mail, telefone (+55…) ou aleatória."
                        >
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.pix_chave}
                                onChange={(e) =>
                                    form.setData('pix_chave', e.target.value)
                                }
                            />
                        </Campo>
                        <Campo
                            rotulo="Nome do recebedor"
                            erro={form.errors.pix_recebedor}
                        >
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.pix_recebedor}
                                onChange={(e) =>
                                    form.setData(
                                        'pix_recebedor',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                        <Campo rotulo="Cidade" erro={form.errors.pix_cidade}>
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.pix_cidade}
                                onChange={(e) =>
                                    form.setData('pix_cidade', e.target.value)
                                }
                            />
                        </Campo>
                        <Campo
                            rotulo="Titular da conta"
                            ajuda="Sem CNPJ, a conta fica no CPF de alguém: registre quem (e a ata)."
                            erro={form.errors.banco_titular}
                        >
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.banco_titular}
                                onChange={(e) =>
                                    form.setData(
                                        'banco_titular',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                    </Cartao>
                </section>

                <section>
                    <TituloSecao>Recebimento de faturas</TituloSecao>
                    <Cartao className="grid gap-3 sm:grid-cols-2">
                        <Campo
                            rotulo="Remetentes da Energisa"
                            ajuda="Domínios separados por vírgula."
                            erro={form.errors.remetentes_energisa}
                        >
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.remetentes_energisa}
                                onChange={(e) =>
                                    form.setData(
                                        'remetentes_energisa',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                        <Campo
                            rotulo="Remetentes da Cagepa"
                            ajuda="Domínios separados por vírgula."
                            erro={form.errors.remetentes_cagepa}
                        >
                            <Entrada
                                disabled={desabilitado}
                                value={form.data.remetentes_cagepa}
                                onChange={(e) =>
                                    form.setData(
                                        'remetentes_cagepa',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                        <p className="text-xs text-suave sm:col-span-2">
                            Domínio de entrada:{' '}
                            <span className="font-mono font-semibold text-texto">
                                {dominio_faturas}
                            </span>{' '}
                            (definido no servidor, variável FATURAS_DOMINIO).
                        </p>
                    </Cartao>
                </section>

                {!desabilitado && (
                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                    >
                        Salvar configurações
                    </Botao>
                )}
            </form>
        </>
    );
}

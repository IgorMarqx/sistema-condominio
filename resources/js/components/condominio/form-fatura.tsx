import { Campo, Entrada } from './campo';

export type CamposFatura = {
    mes_ref: string;
    valor: string;
    vencimento: string;
    consumo: string;
    leitura_anterior: string;
    leitura_atual: string;
    proxima_leitura: string;
    linha_digitavel: string;
};

export const camposVazios: CamposFatura = {
    mes_ref: '',
    valor: '',
    vencimento: '',
    consumo: '',
    leitura_anterior: '',
    leitura_atual: '',
    proxima_leitura: '',
    linha_digitavel: '',
};

/**
 * Campos digitáveis de uma fatura (upload sem leitura do PDF ou correção na revisão).
 */
export default function FormFatura({
    dados,
    erros,
    mudar,
    unidadeMedida,
    comCompetencia = true,
}: {
    dados: CamposFatura;
    erros: Partial<Record<keyof CamposFatura, string>>;
    mudar: (campo: keyof CamposFatura, valor: string) => void;
    unidadeMedida?: string;
    comCompetencia?: boolean;
}) {
    return (
        <div className="grid grid-cols-2 gap-3">
            {comCompetencia && (
                <Campo rotulo="Competência" erro={erros.mes_ref}>
                    <Entrada
                        type="month"
                        value={dados.mes_ref}
                        onChange={(e) => mudar('mes_ref', e.target.value)}
                    />
                </Campo>
            )}
            <Campo rotulo="Valor total (R$)" erro={erros.valor}>
                <Entrada
                    inputMode="decimal"
                    placeholder="0,00"
                    value={dados.valor}
                    onChange={(e) => mudar('valor', e.target.value)}
                />
            </Campo>
            <Campo rotulo="Vencimento" erro={erros.vencimento}>
                <Entrada
                    type="date"
                    value={dados.vencimento}
                    onChange={(e) => mudar('vencimento', e.target.value)}
                />
            </Campo>
            <Campo
                rotulo={`Consumo${unidadeMedida ? ` (${unidadeMedida})` : ''}`}
                erro={erros.consumo}
            >
                <Entrada
                    inputMode="decimal"
                    value={dados.consumo}
                    onChange={(e) => mudar('consumo', e.target.value)}
                />
            </Campo>
            <Campo rotulo="Leitura anterior" erro={erros.leitura_anterior}>
                <Entrada
                    type="date"
                    value={dados.leitura_anterior}
                    onChange={(e) => mudar('leitura_anterior', e.target.value)}
                />
            </Campo>
            <Campo rotulo="Leitura atual" erro={erros.leitura_atual}>
                <Entrada
                    type="date"
                    value={dados.leitura_atual}
                    onChange={(e) => mudar('leitura_atual', e.target.value)}
                />
            </Campo>
            <Campo rotulo="Próxima leitura" erro={erros.proxima_leitura}>
                <Entrada
                    type="date"
                    value={dados.proxima_leitura}
                    onChange={(e) => mudar('proxima_leitura', e.target.value)}
                />
            </Campo>
            <Campo
                rotulo="Linha digitável"
                erro={erros.linha_digitavel}
                className="col-span-2"
            >
                <Entrada
                    value={dados.linha_digitavel}
                    onChange={(e) => mudar('linha_digitavel', e.target.value)}
                />
            </Campo>
        </div>
    );
}

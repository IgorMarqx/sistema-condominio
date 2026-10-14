<?php

namespace App\Services\Ingestao\Extratores;

use App\Enums\Concessionaria;
use App\Services\Ingestao\DadosFatura;

/**
 * Conta da Cagepa. Layout presumido a partir do modelo impresso; conferir com PDFs reais
 * (a "próxima leitura" pode não vir impressa — então fica null e a previsão vira leitura + 30).
 */
class CagepaExtractor implements FaturaExtractor
{
    use LeitorTexto;

    private const DATA = '\d{2}\/\d{2}\/\d{4}';

    private const DINHEIRO = '\d{1,3}(?:\.\d{3})*,\d{2}';

    public function nome(): string
    {
        return 'cagepa';
    }

    public function versao(): string
    {
        return '1.0.0';
    }

    public function supports(string $texto): bool
    {
        return (bool) preg_match('/cagepa|companhia\s+de\s+[áa]gua\s+e\s+esgotos\s+da\s+para[íi]ba|09\.123\.654\/0001-87/iu', $texto);
    }

    public function extract(string $texto): DadosFatura
    {
        $t = $this->normalizar($texto);
        $dados = new DadosFatura(Concessionaria::Cagepa, extrator: $this->nome(), versao: $this->versao());

        $dados->identificador = $this->campo($t, [
            'MATR[ÍI]CULA',
            'N[ºo°]?\s*(?:DO\s+)?CLIENTE',
            'C[ÓO]DIGO\s+(?:DO\s+)?CLIENTE',
            'INSCRI[ÇC][ÃA]O',
        ], '\d{4,12}(?:-\d)?');

        $dados->mesRef = $this->mes($this->campo($t, [
            'REFER[ÊE]NCIA',
            'M[ÊE]S\s*\/\s*ANO',
            'COMPET[ÊE]NCIA',
        ], '\d{2}\/\d{4}|[A-Z]{3,9}\/\d{4}'));

        $dados->vencimento = $this->data($this->campo($t, ['VENCIMENTO'], self::DATA));
        $dados->emissao = $this->data($this->campo($t, ['DATA\s+(?:DE\s+|DA\s+)?EMISS[ÃA]O', 'EMISS[ÃA]O'], self::DATA));
        $dados->valorCentavos = $this->dinheiro($this->campo($t, ['TOTAL\s+A\s+PAGAR', 'VALOR\s+A\s+PAGAR', 'VALOR\s+TOTAL', 'TOTAL\s+DA\s+CONTA'], self::DINHEIRO));

        $dados->leituraAnterior = $this->data($this->campo($t, ['(?:DATA\s+(?:DA\s+)?)?LEITURA\s+ANTERIOR'], self::DATA));
        $dados->leituraAtual = $this->data($this->campo($t, ['(?:DATA\s+(?:DA\s+)?)?LEITURA\s+ATUAL'], self::DATA));
        $dados->proximaLeitura = $this->data($this->campo($t, ['PR[ÓO]XIMA\s+LEITURA', 'LEITURA\s+PREVISTA'], self::DATA));

        $consumo = $this->campo($t, ['CONSUMO\s*(?:FATURADO)?\s*\(?m[³3]\)?', 'CONSUMO\s+(?:DO\s+)?M[ÊE]S', 'VOLUME\s+FATURADO'], '\d{1,6}(?:,\d+)?');
        $dados->consumo = $consumo === null ? null : str_replace(',', '.', $consumo);

        $dados->linhaDigitavel = $this->linhaDigitavel($t);
        $dados->pixCopiaCola = $this->pix($t);

        $bloco = $this->bloco($t, ['DISCRIMINAÇÃO', 'SERVIÇOS FATURADOS', 'DESCRIÇÃO'], ['TOTAL A PAGAR', 'VALOR TOTAL', 'TOTAL DA CONTA', "\nTOTAL"]);

        if ($bloco !== null) {
            $dados->itens = $this->itens($bloco, ['água', 'agua', 'esgoto', 'tarifa', 'multa', 'juros', 'taxa', 'serviço', 'servico', 'crédito', 'desconto', 'parcela']);
        }

        return $dados;
    }
}

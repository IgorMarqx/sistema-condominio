<?php

namespace App\Services\Ingestao\Extratores;

use App\Enums\Concessionaria;
use App\Services\Ingestao\DadosFatura;

/**
 * Fatura da Energisa Paraíba (NF3e). Os rótulos aceitam as variações vistas no layout atual;
 * o layout ainda precisa ser conferido com PDFs reais (fixtures em tests/Fixtures/faturas).
 */
class EnergisaExtractor implements FaturaExtractor
{
    use LeitorTexto;

    private const DATA = '\d{2}\/\d{2}\/\d{4}';

    private const DINHEIRO = '\d{1,3}(?:\.\d{3})*,\d{2}';

    public function nome(): string
    {
        return 'energisa';
    }

    public function versao(): string
    {
        return '1.0.0';
    }

    public function supports(string $texto): bool
    {
        return (bool) preg_match('/energisa|09\.095\.183\/0001-40/iu', $texto);
    }

    public function extract(string $texto): DadosFatura
    {
        $t = $this->normalizar($texto);
        $dados = new DadosFatura(Concessionaria::Energisa, extrator: $this->nome(), versao: $this->versao());

        $dados->identificador = $this->campo($t, [
            'UNIDADE CONSUMIDORA',
            'N[ºo°]?\s*DA\s*(?:UC|INSTALA[ÇC][ÃA]O)',
            'C[ÓO]DIGO\s+DA\s+(?:UC|INSTALA[ÇC][ÃA]O)',
            '\bUC\b',
            'MATR[ÍI]CULA',
        ], '\d{1,2}\/\d{4,9}-\d|\d{6,12}(?:-\d)?');

        $dados->mesRef = $this->mes($this->campo($t, [
            'REF(?:ER[ÊE]NCIA)?\s*:?\s*M[ÊE]S\s*\/\s*ANO',
            'M[ÊE]S\s+DE\s+REFER[ÊE]NCIA',
            'REFER[ÊE]NCIA',
            'CONTA\s+DO\s+M[ÊE]S',
        ], '\d{2}\/\d{4}|[A-Z]{3,9}\/\d{4}'));

        $dados->vencimento = $this->data($this->campo($t, ['VENCIMENTO'], self::DATA));
        $dados->emissao = $this->data($this->campo($t, ['DATA\s+(?:DE\s+|DA\s+)?EMISS[ÃA]O', 'EMITIDA\s+EM'], self::DATA));
        $dados->valorCentavos = $this->dinheiro($this->campo($t, ['TOTAL\s+A\s+PAGAR', 'VALOR\s+A\s+PAGAR', 'VALOR\s+TOTAL'], self::DINHEIRO));

        $dados->leituraAnterior = $this->data($this->campo($t, ['LEITURA\s+ANTERIOR', 'DATA\s+(?:DA\s+)?LEITURA\s+ANTERIOR'], self::DATA));
        $dados->leituraAtual = $this->data($this->campo($t, ['LEITURA\s+ATUAL', 'DATA\s+(?:DA\s+)?LEITURA\s+ATUAL'], self::DATA));
        $dados->proximaLeitura = $this->data($this->campo($t, ['PR[ÓO]XIMA\s+LEITURA'], self::DATA));

        $consumo = $this->campo($t, ['CONSUMO\s+FATURADO\s*(?:\(?kWh\)?)?', 'CONSUMO\s*\(?kWh\)?', 'CONSUMO\s+(?:DO\s+)?M[ÊE]S'], '\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+');
        $dados->consumo = $consumo === null ? null : str_replace(',', '.', str_replace('.', '', $consumo));

        if (preg_match('/CHAVE\s+DE\s+ACESSO[^\d]{0,40}((?:\d[\s.]?){44})/iu', $t, $m)) {
            $dados->chaveNf3e = substr(preg_replace('/\D/', '', $m[1]) ?? '', 0, 44);
        }

        $dados->linhaDigitavel = $this->linhaDigitavel($t);
        $dados->pixCopiaCola = $this->pix($t);

        $bloco = $this->bloco($t, ['ITENS DA FATURA', 'DESCRIÇÃO DO FATURAMENTO', 'DISCRIMINAÇÃO'], ['TOTAL A PAGAR', "\nTOTAL", 'CHAVE DE ACESSO', 'INFORMAÇÕES']);

        if ($bloco !== null) {
            $dados->itens = $this->itens($bloco, [
                'consumo', 'energia', 'bandeira', 'ilum', 'cip', 'cosip', 'pis', 'cofins', 'icms',
                'multa', 'juros', 'tarifa', 'adicional', 'devolução', 'crédito', 'desconto', 'te ', 'tusd',
            ]);
        }

        return $dados;
    }
}

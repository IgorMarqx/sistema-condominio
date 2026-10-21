<?php

namespace App\Enums;

enum CategoriaLancamento: string
{
    case RateioEnergia = 'rateio_energia';
    case RateioAgua = 'rateio_agua';
    case ContribuicaoMensal = 'contribuicao_mensal';
    case Cobranca = 'cobranca';
    case PagamentoEnergisa = 'pagamento_energisa';
    case PagamentoCagepa = 'pagamento_cagepa';
    case PagamentoInternet = 'pagamento_internet';
    case Manutencao = 'manutencao';
    case TaxasBancarias = 'taxas_bancarias';
    case Transferencia = 'transferencia';
    case Estorno = 'estorno';
    case Outros = 'outros';

    public function rotulo(): string
    {
        return match ($this) {
            self::RateioEnergia => 'Rateio energia',
            self::RateioAgua => 'Rateio água',
            self::ContribuicaoMensal => 'Contribuição mensal',
            self::Cobranca => 'Cobrança do mês',
            self::PagamentoEnergisa => 'Pagamento Energisa',
            self::PagamentoCagepa => 'Pagamento Cagepa',
            self::PagamentoInternet => 'Pagamento internet',
            self::Manutencao => 'Manutenção',
            self::TaxasBancarias => 'Taxas bancárias',
            self::Transferencia => 'Transferência',
            self::Estorno => 'Estorno',
            self::Outros => 'Outros',
        };
    }

    /**
     * Categorias que o admin escolhe num lançamento manual.
     *
     * @return list<self>
     */
    public static function manuais(): array
    {
        return [
            self::RateioEnergia, self::RateioAgua, self::ContribuicaoMensal,
            self::PagamentoEnergisa, self::PagamentoCagepa, self::PagamentoInternet, self::Manutencao,
            self::TaxasBancarias, self::Outros,
        ];
    }
}

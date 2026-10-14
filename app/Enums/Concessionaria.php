<?php

namespace App\Enums;

enum Concessionaria: string
{
    case Energisa = 'energisa';
    case Cagepa = 'cagepa';

    public function rotulo(): string
    {
        return match ($this) {
            self::Energisa => 'Energisa',
            self::Cagepa => 'Cagepa',
        };
    }

    public function servico(): string
    {
        return match ($this) {
            self::Energisa => 'Energia',
            self::Cagepa => 'Água',
        };
    }

    public function unidadeMedida(): string
    {
        return match ($this) {
            self::Energisa => 'kWh',
            self::Cagepa => 'm³',
        };
    }

    public function categoriaPagamento(): CategoriaLancamento
    {
        return match ($this) {
            self::Energisa => CategoriaLancamento::PagamentoEnergisa,
            self::Cagepa => CategoriaLancamento::PagamentoCagepa,
        };
    }
}

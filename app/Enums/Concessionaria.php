<?php

namespace App\Enums;

enum Concessionaria: string
{
    case Energisa = 'energisa';
    case Cagepa = 'cagepa';
    case Internet = 'internet';

    public function rotulo(): string
    {
        return match ($this) {
            self::Energisa => 'Energisa',
            self::Cagepa => 'Cagepa',
            self::Internet => 'Internet',
        };
    }

    public function servico(): string
    {
        return match ($this) {
            self::Energisa => 'Energia',
            self::Cagepa => 'Água',
            self::Internet => 'Internet',
        };
    }

    /** Internet é mensalidade fixa: sem consumo, sem leitura e sem medidor de verdade. */
    public function unidadeMedida(): ?string
    {
        return match ($this) {
            self::Energisa => 'kWh',
            self::Cagepa => 'm³',
            self::Internet => null,
        };
    }

    public function temLeitura(): bool
    {
        return $this !== self::Internet;
    }

    /** Tipo do item da cobrança gerado por esta conta. */
    public function tipoItem(): string
    {
        return match ($this) {
            self::Energisa => 'energia',
            self::Cagepa => 'agua',
            self::Internet => 'internet',
        };
    }

    /** Ordem fixa entre as contas do mês: decide para quem vai o centavo do resto. */
    public function ordem(): int
    {
        return match ($this) {
            self::Energisa => 0,
            self::Cagepa => 1,
            self::Internet => 2,
        };
    }

    /** Letra da conta no txid do Pix (COND202610EAP302). */
    public function sigla(): string
    {
        return match ($this) {
            self::Energisa => 'E',
            self::Cagepa => 'A',
            self::Internet => 'I',
        };
    }

    public function categoriaPagamento(): CategoriaLancamento
    {
        return match ($this) {
            self::Energisa => CategoriaLancamento::PagamentoEnergisa,
            self::Cagepa => CategoriaLancamento::PagamentoCagepa,
            self::Internet => CategoriaLancamento::PagamentoInternet,
        };
    }
}

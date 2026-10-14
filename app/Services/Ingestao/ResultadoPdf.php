<?php

namespace App\Services\Ingestao;

use App\Models\Fatura;

/**
 * Resultado de um PDF: virou fatura, era duplicado ou precisa de revisão humana (com o motivo).
 */
final readonly class ResultadoPdf
{
    private function __construct(
        public string $situacao,
        public ?Fatura $fatura = null,
        public ?string $motivo = null,
    ) {}

    public static function fatura(Fatura $fatura): self
    {
        return new self('fatura', $fatura);
    }

    public static function duplicado(?Fatura $fatura): self
    {
        return new self('duplicado', $fatura, 'Este PDF já foi recebido antes.');
    }

    public static function revisao(string $motivo): self
    {
        return new self('revisao', motivo: $motivo);
    }

    public static function bloqueado(string $motivo): self
    {
        return new self('bloqueado', motivo: $motivo);
    }
}

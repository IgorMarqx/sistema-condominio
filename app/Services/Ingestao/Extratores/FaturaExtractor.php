<?php

namespace App\Services\Ingestao\Extratores;

use App\Services\Ingestao\DadosFatura;

interface FaturaExtractor
{
    public function nome(): string;

    /**
     * Versão gravada com a fatura: quando o extrator muda, dá para reprocessar o texto guardado.
     */
    public function versao(): string;

    public function supports(string $texto): bool;

    public function extract(string $texto): DadosFatura;
}

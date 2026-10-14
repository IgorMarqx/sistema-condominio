<?php

namespace App\Services\Ingestao;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Texto do PDF. Preferência: `pdftotext -layout` (poppler), que preserva as colunas da fatura.
 * Sem o binário (ambiente de desenvolvimento), cai no parser PHP.
 * PDF com senha: tenta as senhas cadastradas nos medidores com o qpdf.
 */
class LeitorPdf
{
    /**
     * @param  list<string>  $senhas
     */
    public function texto(string $pdf, array $senhas = []): string
    {
        if (! str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF')) {
            throw new RuntimeException('O arquivo não é um PDF.');
        }

        if ($this->protegido($pdf)) {
            $pdf = $this->abrir($pdf, $senhas);
        }

        $binario = $this->binario('pdftotext');

        return $binario ? $this->comPoppler($binario, $pdf) : $this->comParserPhp($pdf);
    }

    public function protegido(string $pdf): bool
    {
        return (bool) preg_match('/\/Encrypt\s*\d+\s+\d+\s+R|\/Encrypt\s*<</', $pdf);
    }

    /**
     * @param  list<string>  $senhas
     */
    private function abrir(string $pdf, array $senhas): string
    {
        $qpdf = $this->binario('qpdf');

        if (! $qpdf) {
            throw new PdfProtegido('PDF protegido por senha e o qpdf não está instalado.');
        }

        // Senha vazia primeiro: há PDFs com senha só de proprietário (abrem sem pedir nada).
        foreach (['', ...$senhas] as $senha) {
            $entrada = $this->temporario($pdf);
            $saida = $entrada.'.aberto.pdf';

            try {
                $processo = new Process([$qpdf, '--password='.$senha, '--decrypt', $entrada, $saida]);
                $processo->setTimeout(30)->run();

                // qpdf sai com 3 quando há avisos mas gerou o arquivo.
                if (in_array($processo->getExitCode(), [0, 3], true) && is_file($saida)) {
                    return (string) file_get_contents($saida);
                }
            } finally {
                @unlink($entrada);
                @unlink($saida);
            }
        }

        throw new PdfProtegido('PDF protegido por senha: nenhuma senha cadastrada abriu o arquivo.');
    }

    private function comPoppler(string $binario, string $pdf): string
    {
        $entrada = $this->temporario($pdf);

        try {
            $processo = new Process([$binario, '-layout', '-enc', 'UTF-8', $entrada, '-']);
            $processo->setTimeout(60)->mustRun();

            return $processo->getOutput();
        } finally {
            @unlink($entrada);
        }
    }

    private function comParserPhp(string $pdf): string
    {
        try {
            return (new Parser)->parseContent($pdf)->getText();
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'secured') || str_contains(strtolower($e->getMessage()), 'encrypt')) {
                throw new PdfProtegido('PDF protegido por senha.', previous: $e);
            }

            throw new RuntimeException('Não foi possível ler o PDF: '.$e->getMessage(), previous: $e);
        }
    }

    private function binario(string $nome): ?string
    {
        $configurado = (string) config('condominio.'.$nome, $nome);

        if (str_contains($configurado, '/')) {
            return is_executable($configurado) ? $configurado : null;
        }

        return (new ExecutableFinder)->find($configurado);
    }

    private function temporario(string $conteudo): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'fatura').'.pdf';
        file_put_contents($caminho, $conteudo);

        return $caminho;
    }
}

<?php

namespace App\Services\Arquivos;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PDFs de fatura, comprovantes e e-mails brutos. Em produção o disco é S3 (AWS_*, como no
 * sistema-ponto) com versionamento e Object Lock: nada é sobrescrito. Todo arquivo é guardado com o SHA-256 do conteúdo.
 */
class Armazenamento
{
    public function disco(): Filesystem
    {
        return Storage::disk(config('condominio.disco'));
    }

    /**
     * @return array{caminho: string, sha256: string, tamanho: int}
     */
    public function guardarConteudo(string $conteudo, string $pasta, string $extensao): array
    {
        $sha = hash('sha256', $conteudo);
        $caminho = trim($pasta, '/').'/'.now()->format('Y/m').'/'.$sha.'.'.ltrim($extensao, '.');

        if (! $this->disco()->exists($caminho) && ! $this->disco()->put($caminho, $conteudo)) {
            throw new RuntimeException('Não foi possível guardar o arquivo ('.$caminho.').');
        }

        return ['caminho' => $caminho, 'sha256' => $sha, 'tamanho' => strlen($conteudo)];
    }

    /**
     * @return array{caminho: string, sha256: string, tamanho: int}
     */
    public function guardarUpload(UploadedFile $arquivo, string $pasta): array
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->extension() ?: 'bin');

        return $this->guardarConteudo((string) file_get_contents($arquivo->getRealPath()), $pasta, $extensao);
    }

    public function ler(string $caminho): string
    {
        return (string) $this->disco()->get($caminho);
    }

    public function nomeSeguro(string $nome): string
    {
        $base = Str::limit(Str::slug(pathinfo($nome, PATHINFO_FILENAME)), 80, '');
        $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

        return ($base ?: 'arquivo').($extensao ? '.'.$extensao : '');
    }
}

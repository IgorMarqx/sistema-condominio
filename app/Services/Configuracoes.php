<?php

namespace App\Services;

use App\Models\Configuracao;
use Illuminate\Support\Facades\Cache;

/**
 * Parâmetros do condomínio editáveis pelo síndico. Os padrões valem até alguém mudar.
 */
class Configuracoes
{
    public const PADROES = [
        'nome_edificio' => 'Edifício',
        'endereco' => 'João Pessoa – PB',
        // Contribuição mensal ao caixa por unidade.
        'contribuicao_centavos' => 5000,
        // Vencimento interno = vencimento da concessionária − N dias.
        'dias_antecedencia' => 5,
        'pix_chave' => null,
        'pix_recebedor' => null,
        'pix_cidade' => 'JOAO PESSOA',
        'banco_titular' => null,
        'limite_anomalia_percentual' => 30,
    ];

    private const CACHE = 'configuracoes.todas';

    /**
     * @return array<string, mixed>
     */
    public function todas(): array
    {
        $salvas = Cache::rememberForever(self::CACHE, fn () => Configuracao::query()
            ->pluck('valor', 'chave')
            ->all());

        return array_replace(self::PADROES, $salvas);
    }

    public function get(string $chave, mixed $padrao = null): mixed
    {
        return $this->todas()[$chave] ?? $padrao;
    }

    public function int(string $chave): int
    {
        return (int) $this->get($chave);
    }

    public function string(string $chave): ?string
    {
        $valor = $this->get($chave);

        return $valor === null ? null : (string) $valor;
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed> valores anteriores das chaves alteradas
     */
    public function salvar(array $valores): array
    {
        $antes = [];
        $atuais = $this->todas();

        foreach ($valores as $chave => $valor) {
            if (! array_key_exists($chave, self::PADROES)) {
                continue;
            }

            if (($atuais[$chave] ?? null) !== $valor) {
                $antes[$chave] = $atuais[$chave] ?? null;
                Configuracao::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
            }
        }

        Cache::forget(self::CACHE);

        return $antes;
    }
}

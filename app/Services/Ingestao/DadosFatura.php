<?php

namespace App\Services\Ingestao;

use App\Enums\Concessionaria;
use Illuminate\Support\Carbon;

/**
 * O que um extrator conseguiu ler do PDF. Campo ausente fica null; a confiança é a fração dos
 * campos essenciais encontrados.
 */
final class DadosFatura
{
    /**
     * @param  list<array{descricao: string, valor_centavos: int}>  $itens
     * @param  list<string>  $avisos
     */
    public function __construct(
        public Concessionaria $concessionaria,
        public ?string $identificador = null,
        public ?Carbon $mesRef = null,
        public ?int $valorCentavos = null,
        public ?Carbon $vencimento = null,
        public ?Carbon $emissao = null,
        public ?string $consumo = null,
        public ?Carbon $leituraAnterior = null,
        public ?Carbon $leituraAtual = null,
        public ?Carbon $proximaLeitura = null,
        public ?string $chaveNf3e = null,
        public ?string $linhaDigitavel = null,
        public ?string $pixCopiaCola = null,
        public array $itens = [],
        public ?string $extrator = null,
        public ?string $versao = null,
        public array $avisos = [],
    ) {}

    public const ESSENCIAIS = ['identificador', 'mesRef', 'valorCentavos', 'vencimento'];

    public const DESEJAVEIS = ['consumo', 'leituraAnterior', 'leituraAtual', 'linhaDigitavel'];

    /**
     * Essenciais pesam 3, desejáveis 1. Faltar um essencial derruba a confiança abaixo de 0,8.
     */
    public function confianca(): float
    {
        $pontos = 0;
        $maximo = count(self::ESSENCIAIS) * 3 + count(self::DESEJAVEIS);

        foreach (self::ESSENCIAIS as $campo) {
            $pontos += $this->{$campo} !== null ? 3 : 0;
        }

        foreach (self::DESEJAVEIS as $campo) {
            $pontos += $this->{$campo} !== null ? 1 : 0;
        }

        return round($pontos / $maximo, 3);
    }

    /**
     * @return list<string>
     */
    public function essenciaisFaltando(): array
    {
        $nomes = [
            'identificador' => $this->concessionaria === Concessionaria::Energisa ? 'nº da UC' : 'matrícula',
            'mesRef' => 'competência',
            'valorCentavos' => 'valor total',
            'vencimento' => 'vencimento',
        ];

        return array_values(array_map(
            fn ($campo) => $nomes[$campo],
            array_filter(self::ESSENCIAIS, fn ($campo) => $this->{$campo} === null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function paraFatura(): array
    {
        return [
            'valor_centavos' => $this->valorCentavos,
            'vencimento' => $this->vencimento?->toDateString(),
            'emissao' => $this->emissao?->toDateString(),
            'consumo' => $this->consumo,
            'unidade_medida' => $this->concessionaria->unidadeMedida(),
            'leitura_anterior' => $this->leituraAnterior?->toDateString(),
            'leitura_atual' => $this->leituraAtual?->toDateString(),
            'proxima_leitura' => $this->proximaLeitura?->toDateString(),
            'chave_nf3e' => $this->chaveNf3e,
            'linha_digitavel' => $this->linhaDigitavel,
            'pix_copia_cola' => $this->pixCopiaCola,
            'itens_json' => $this->itens ?: null,
            'extrator' => $this->extrator,
            'extrator_versao' => $this->versao,
            'confianca' => $this->confianca(),
        ];
    }
}

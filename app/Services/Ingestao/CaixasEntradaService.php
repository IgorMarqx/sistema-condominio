<?php

namespace App\Services\Ingestao;

use App\Models\CaixaEntrada;
use App\Models\Unidade;
use App\Services\Auditoria\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Endereço exclusivo por dono: condominio-7qk2@… e ap302-x8vd@…. O sufixo aleatório evita
 * adivinhação; regenerar desativa o anterior (e-mail que chegar nele é descartado).
 */
class CaixasEntradaService
{
    public function __construct(private readonly Auditoria $auditoria) {}

    public function ativa(?Unidade $unidade): ?CaixaEntrada
    {
        return CaixaEntrada::query()
            ->where('dono_tipo', $unidade ? 'unidade' : 'condominio')
            ->where('unidade_id', $unidade?->id)
            ->where('ativo', true)
            ->latest('id')
            ->first();
    }

    public function garantir(?Unidade $unidade): CaixaEntrada
    {
        return $this->ativa($unidade) ?? $this->criar($unidade);
    }

    public function regenerar(?Unidade $unidade): CaixaEntrada
    {
        return DB::transaction(function () use ($unidade) {
            $anterior = $this->ativa($unidade);
            $anterior?->update(['ativo' => false, 'desativado_em' => now()]);
            $nova = $this->criar($unidade);

            $this->auditoria->registrar('caixa_entrada.regenerada', $nova, ['endereco' => $anterior?->endereco], ['endereco' => $nova->endereco]);

            return $nova;
        });
    }

    private function criar(?Unidade $unidade): CaixaEntrada
    {
        $prefixo = $unidade ? 'ap'.$unidade->numero : 'condominio';
        $dominio = (string) config('condominio.dominio_faturas');

        do {
            $endereco = $prefixo.'-'.Str::lower(Str::random(4)).'@'.$dominio;
        } while (CaixaEntrada::query()->where('endereco', $endereco)->exists());

        return CaixaEntrada::query()->create([
            'dono_tipo' => $unidade ? 'unidade' : 'condominio',
            'unidade_id' => $unidade?->id,
            'endereco' => $endereco,
            'ativo' => true,
        ]);
    }

    /**
     * @param  list<string>  $enderecos
     */
    public function localizar(array $enderecos): ?CaixaEntrada
    {
        $enderecos = array_map(fn ($e) => strtolower(trim($e, " <>\t")), $enderecos);

        return $enderecos === [] ? null : CaixaEntrada::query()->whereIn('endereco', $enderecos)->where('ativo', true)->first();
    }
}

<?php

namespace App\Services\Faturas;

use App\Models\Fatura;
use App\Support\Dinheiro;

/**
 * Verificações automáticas da fatura (tela de revisão). Falhou alguma → pendente_revisao com o motivo.
 * Nenhuma delas corrige valor: na dúvida a fatura vai para revisão, nunca entra errada em silêncio.
 */
class ValidadorFatura
{
    public const CONFIANCA_MINIMA = 0.8;

    /**
     * @return list<array{regra: string, ok: bool, mensagem: string}>
     */
    public function validar(Fatura $fatura): array
    {
        $resultado = [];

        $faltando = array_keys(array_filter([
            'valor total' => $fatura->valor_centavos === null,
            'vencimento' => $fatura->vencimento === null,
        ]));

        $resultado[] = $faltando
            ? ['regra' => 'campos', 'ok' => false, 'mensagem' => 'Faltam campos: '.implode(', ', $faltando).'.']
            : ['regra' => 'campos', 'ok' => true, 'mensagem' => 'Valor e vencimento encontrados'];

        $itens = $fatura->itens_json ?? [];

        if ($itens !== [] && $fatura->valor_centavos !== null) {
            $soma = array_sum(array_column($itens, 'valor_centavos'));
            $resultado[] = $soma === $fatura->valor_centavos
                ? ['regra' => 'soma_itens', 'ok' => true, 'mensagem' => 'Soma dos itens bate com o total']
                : ['regra' => 'soma_itens', 'ok' => false, 'mensagem' => 'Soma dos itens ('.Dinheiro::formatar($soma).') não bate com o total ('.Dinheiro::formatar($fatura->valor_centavos).').'];
        }

        if ($fatura->vencimento && $fatura->emissao) {
            $resultado[] = $fatura->vencimento->gt($fatura->emissao)
                ? ['regra' => 'datas', 'ok' => true, 'mensagem' => 'Vencimento posterior à emissão']
                : ['regra' => 'datas', 'ok' => false, 'mensagem' => 'Vencimento ('.$fatura->vencimento->format('d/m/Y').') não é depois da emissão ('.$fatura->emissao->format('d/m/Y').').'];
        }

        $outra = Fatura::query()
            ->where('competencia_id', $fatura->competencia_id)
            ->where('status', '!=', 'rejeitada')
            ->when($fatura->exists, fn ($q) => $q->whereKeyNot($fatura->id))
            ->exists();

        $resultado[] = $outra
            ? ['regra' => 'competencia', 'ok' => false, 'mensagem' => 'Já existe outra fatura nesta competência.']
            : ['regra' => 'competencia', 'ok' => true, 'mensagem' => 'Competência sem outra fatura'];

        if ($fatura->consumo !== null) {
            $media = $this->mediaConsumo($fatura);

            if ($media !== null && $media > 0) {
                $resultado[] = (float) $fatura->consumo <= 3 * $media
                    ? ['regra' => 'consumo', 'ok' => true, 'mensagem' => 'Consumo dentro da média dos últimos 6 meses']
                    : ['regra' => 'consumo', 'ok' => false, 'mensagem' => 'Consumo mais de 3× a média dos últimos 6 meses ('.number_format($media, 0, ',', '.').' '.$fatura->unidade_medida.').'];
            }
        }

        if ($fatura->confianca !== null && (float) $fatura->confianca < self::CONFIANCA_MINIMA) {
            $resultado[] = ['regra' => 'confianca', 'ok' => false, 'mensagem' => 'Leitura do PDF incompleta (confiança '.number_format((float) $fatura->confianca * 100, 0).'%).'];
        }

        return $resultado;
    }

    /**
     * @param  list<array{regra: string, ok: bool, mensagem: string}>  $validacoes
     */
    public static function motivo(array $validacoes): ?string
    {
        $falhas = array_column(array_filter($validacoes, fn ($v) => ! $v['ok']), 'mensagem');

        return $falhas ? implode(' ', $falhas) : null;
    }

    public function mediaConsumo(Fatura $fatura, int $meses = 6): ?float
    {
        $mesRef = $fatura->competencia_id ? $fatura->competencia->mes_ref : null;

        $valores = Fatura::query()
            ->where('medidor_id', $fatura->medidor_id)
            ->where('status', '!=', 'rejeitada')
            ->whereNotNull('consumo')
            ->when($fatura->exists, fn ($q) => $q->whereKeyNot($fatura->id))
            ->when($mesRef, fn ($q) => $q->whereHas('competencia', fn ($c) => $c->where('mes_ref', '<', $mesRef->toDateString())))
            ->latest('id')
            ->limit($meses)
            ->pluck('consumo');

        return $valores->isEmpty() ? null : (float) $valores->avg();
    }

    /**
     * Percentual acima (ou abaixo) da média; null sem histórico.
     */
    public function variacaoConsumo(Fatura $fatura): ?int
    {
        $media = $this->mediaConsumo($fatura);

        if ($media === null || $media <= 0 || $fatura->consumo === null) {
            return null;
        }

        return (int) round(((float) $fatura->consumo / $media - 1) * 100);
    }
}

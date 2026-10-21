<?php

namespace App\Http\Apresentacao;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use App\Support\Mes;

final class FaturaDados
{
    /**
     * @return array<string, mixed>
     */
    public static function resumo(Fatura $fatura, bool $comValores = true): array
    {
        $medidor = $fatura->medidor;
        $mes = $fatura->competencia->mes_ref;

        $base = [
            'id' => $fatura->id,
            'concessionaria' => $medidor->concessionaria->value,
            'concessionaria_rotulo' => $medidor->concessionaria->rotulo(),
            'servico' => $medidor->concessionaria->servico(),
            'mes' => $mes->format('Y-m'),
            'mes_rotulo' => Mes::rotulo($mes),
            'mes_nome' => Mes::nome($mes),
            'status' => $fatura->status->value,
            'status_rotulo' => $fatura->status->rotulo(),
            'origem' => $fatura->origem,
            'recebida_em' => $fatura->created_at->toIso8601String(),
            'tem_pdf' => $fatura->anexo_id !== null,
            // Foto da conta anexada no lugar do PDF: a tela mostra a imagem, não o leitor de PDF.
            'anexo_imagem' => str_starts_with((string) $fatura->anexo?->mime, 'image/'),
            'medidor' => [
                'id' => $medidor->id,
                'identificador' => $medidor->identificador,
                'condominio' => $medidor->isCondominio(),
                'unidade' => $medidor->unidade?->numero,
            ],
        ];

        if (! $comValores) {
            return $base;
        }

        return $base + [
            'valor' => $fatura->valor_centavos,
            'vencimento' => $fatura->vencimento?->toDateString(),
            'emissao' => $fatura->emissao?->toDateString(),
            'consumo' => $fatura->consumo !== null ? (float) $fatura->consumo : null,
            'unidade_medida' => $fatura->unidade_medida ?? $medidor->concessionaria->unidadeMedida(),
            'leitura_anterior' => $fatura->leitura_anterior?->toDateString(),
            'leitura_atual' => $fatura->leitura_atual?->toDateString(),
            'proxima_leitura' => $fatura->proxima_leitura?->toDateString(),
            'linha_digitavel' => $fatura->linha_digitavel,
            'pix_copia_cola' => $fatura->pix_copia_cola,
            'chave_nf3e' => $fatura->chave_nf3e,
            'paga_em' => $fatura->paga_em?->toDateString(),
            'rateada' => $fatura->relationLoaded('rateios') ? $fatura->rateios->isNotEmpty() : null,
            'pendente' => in_array($fatura->status, [StatusFatura::PendenteRevisao, StatusFatura::PendenteConfirmacao], true),
        ];
    }

    /**
     * Tela de revisão: tudo o que a extração trouxe, as verificações e a confiança.
     *
     * @return array<string, mixed>
     */
    public static function detalhe(Fatura $fatura): array
    {
        return self::resumo($fatura) + [
            'itens' => $fatura->itens_json ?? [],
            'validacoes' => $fatura->validacoes_json ?? [],
            'motivo_revisao' => $fatura->motivo_revisao,
            'confianca' => $fatura->confianca !== null ? (float) $fatura->confianca : null,
            'extrator' => $fatura->extrator ? $fatura->extrator.' '.$fatura->extrator_versao : null,
            'confirmada_em' => $fatura->confirmada_em?->toIso8601String(),
            'confirmada_por' => $fatura->confirmadaPor?->nome,
            'email' => $fatura->email ? [
                'remetente' => $fatura->email->remetente,
                'assunto' => $fatura->email->assunto,
                'spf' => $fatura->email->spf,
                'dkim' => $fatura->email->dkim,
            ] : null,
            'anexo_sha256' => $fatura->anexo?->sha256,
        ];
    }
}

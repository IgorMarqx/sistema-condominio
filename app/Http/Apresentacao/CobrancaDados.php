<?php

namespace App\Http\Apresentacao;

use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Support\Mes;

final class CobrancaDados
{
    /**
     * @return array<string, mixed>
     */
    public static function resumo(Cobranca $cobranca): array
    {
        return [
            'id' => $cobranca->id,
            'unidade' => $cobranca->unidade->numero,
            'mes' => $cobranca->mes_ref->format('Y-m'),
            'mes_rotulo' => Mes::rotulo($cobranca->mes_ref),
            'mes_nome' => Mes::nome($cobranca->mes_ref),
            'servico' => self::servico($cobranca),
            'valor' => $cobranca->valor_centavos,
            'vencimento' => $cobranca->vencimento->toDateString(),
            'vencida' => $cobranca->vencida(),
            'status' => $cobranca->status->value,
            'status_rotulo' => $cobranca->status->rotulo(),
        ];
    }

    /**
     * Do que é a cobrança: cada conta do condomínio gera a sua (as antigas juntavam energia e água).
     */
    public static function servico(Cobranca $cobranca): string
    {
        $tipos = $cobranca->itens->pluck('tipo');

        return match (true) {
            $tipos->contains('energia') && $tipos->contains('agua') => 'Energia e água',
            $tipos->contains('energia') => 'Energia',
            $tipos->contains('agua') => 'Água',
            default => 'Contribuição',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function detalhe(Cobranca $cobranca, ?string $pix): array
    {
        return self::resumo($cobranca) + [
            'itens' => $cobranca->itens->map(fn ($item) => [
                'tipo' => $item->tipo,
                'descricao' => $item->descricao,
                'valor' => $item->valor_centavos,
                'fatura_id' => $item->fatura_id,
            ])->values(),
            'pix' => $pix,
            'pagamentos' => $cobranca->pagamentos->sortByDesc('id')->map(fn (Pagamento $p) => self::pagamento($p))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pagamento(Pagamento $pagamento): array
    {
        return [
            'id' => $pagamento->id,
            'valor' => $pagamento->valor_centavos,
            'pago_em' => $pagamento->pago_em->toDateString(),
            'status' => $pagamento->status,
            'motivo_recusa' => $pagamento->motivo_recusa,
            'nome_informado' => $pagamento->nome_informado,
            'comprovante_nome' => $pagamento->comprovante_nome,
            'enviado_em' => $pagamento->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Unidade;

use App\Enums\Concessionaria;
use App\Http\Controllers\Controller;
use App\Models\EmailRecebido;
use App\Models\Fatura;
use App\Models\Unidade;
use App\Services\Ingestao\CaixasEntradaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Endereço exclusivo de recebimento, passo a passo e códigos de verificação (Gmail/concessionária).
 * Serve à unidade e, na gestão, ao endereço do condomínio.
 */
class ReceberFaturasController extends Controller
{
    public function show(Request $request, CaixasEntradaService $caixas): Response
    {
        $unidade = $request->user()->isUnidade() ? $request->user()->unidade : null;
        $caixa = $caixas->garantir($unidade);

        $emails = EmailRecebido::query()
            ->whereHas('caixaEntrada', fn ($q) => $q->where('dono_tipo', $unidade ? 'unidade' : 'condominio')->where('unidade_id', $unidade?->id))
            ->latest('id')
            ->limit(15)
            ->get();

        $verificacao = $emails->first(fn (EmailRecebido $e) => $e->tipo === 'verificacao' && $e->recebido_em->gt(now()->subDays(7)));

        return Inertia::render('receber-faturas', [
            'endereco' => $caixa->endereco,
            'condominio' => $unidade === null,
            'recebimento' => self::situacaoRecebimento($unidade),
            'verificacao' => $verificacao ? [
                'remetente' => $verificacao->remetente,
                'gmail' => $verificacao->remetente === 'forwarding-noreply@google.com',
                'codigo' => $verificacao->verificacao_codigo,
                'link' => $verificacao->verificacao_link,
                'recebido_em' => $verificacao->recebido_em->toIso8601String(),
            ] : null,
            'emails' => $emails->map(fn (EmailRecebido $e) => [
                'id' => $e->id,
                'remetente' => $e->remetente,
                'assunto' => $e->assunto,
                'tipo' => $e->tipo,
                'status' => $e->status,
                'erro' => $e->erro,
                'links' => $e->links ?? [],
                'recebido_em' => $e->recebido_em->toIso8601String(),
            ])->values(),
        ]);
    }

    public function regenerar(Request $request, CaixasEntradaService $caixas): RedirectResponse
    {
        $unidade = $request->user()->isUnidade() ? $request->user()->unidade : null;
        $nova = $caixas->regenerar($unidade);

        return back()->with('sucesso', 'Novo endereço: '.$nova->endereco.'. Atualize o cadastro na concessionária e no Gmail.');
    }

    /**
     * "Energisa ativa · Cagepa pendente": como cada fatura tem chegado.
     *
     * @return array<string, array{situacao: string, ultima: string|null}>
     */
    public static function situacaoRecebimento(?Unidade $unidade): array
    {
        $situacao = [];

        foreach (Concessionaria::cases() as $concessionaria) {
            $ultima = Fatura::query()
                ->whereHas('medidor', fn ($q) => $q->where('dono_tipo', $unidade ? 'unidade' : 'condominio')
                    ->where('unidade_id', $unidade?->id)
                    ->where('concessionaria', $concessionaria))
                ->latest('id')
                ->first(['id', 'origem', 'created_at']);

            $situacao[$concessionaria->value] = [
                'situacao' => match (true) {
                    $ultima === null => 'pendente',
                    $ultima->origem === 'email' && $ultima->created_at->gt(now()->subDays(45)) => 'ativo',
                    default => 'manual',
                },
                'ultima' => $ultima?->created_at->toIso8601String(),
            ];
        }

        return $situacao;
    }
}

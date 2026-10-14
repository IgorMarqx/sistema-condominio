<?php

namespace App\Http\Controllers;

use App\Models\Competencia;
use App\Models\Fatura;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Models\Pagamento;
use App\Models\User;
use App\Services\Arquivos\Armazenamento;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega de arquivos com a regra de quem pode ver cada um (LGPD: fatura individual e
 * comprovante de morador não são públicos para o prédio).
 */
class ArquivosController extends Controller
{
    public function __construct(private readonly Armazenamento $armazenamento) {}

    public function fatura(Request $request, Fatura $fatura): StreamedResponse
    {
        $user = $request->user();
        $medidor = $fatura->medidor;

        $pode = $medidor->isCondominio()
            || ($user->isUnidade() && $medidor->unidade_id === $user->unidade_id)
            || ($user->vePainelGestao() && $medidor->unidade?->autoriza_admin_ver_faturas);

        abort_unless($pode && $fatura->anexo, 404);

        return $this->entregar($fatura->anexo->s3_key, 'fatura-'.$medidor->concessionaria->value.'-'.$fatura->competencia->mes_ref->format('Y-m').'.pdf');
    }

    public function pagamento(Request $request, Pagamento $pagamento): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->vePainelGestao() || $pagamento->cobranca->unidade_id === $user->unidade_id, 404);

        return $this->entregar($pagamento->comprovante_s3, $pagamento->comprovante_nome);
    }

    public function lancamento(Request $request, LancamentoCaixa $lancamento): StreamedResponse
    {
        abort_unless($lancamento->comprovante_s3 !== null && $this->podeVerLancamento($request->user(), $lancamento), 404);

        $extensao = pathinfo($lancamento->comprovante_s3, PATHINFO_EXTENSION) ?: 'pdf';

        return $this->entregar($lancamento->comprovante_s3, 'comprovante-lancamento-'.$lancamento->id.'.'.$extensao);
    }

    public function prestacao(Fechamento $fechamento): StreamedResponse
    {
        abort_unless($fechamento->pdf_s3 !== null, 404);

        return $this->entregar($fechamento->pdf_s3, 'prestacao-de-contas-'.$fechamento->mes_ref->format('Y-m').'.pdf');
    }

    public function leitura(Request $request, Competencia $competencia): StreamedResponse
    {
        abort_unless($request->user()->vePainelGestao() && $competencia->leitura_foto_s3 !== null, 404);

        return $this->entregar($competencia->leitura_foto_s3, 'leitura-'.$competencia->id.'.'.pathinfo($competencia->leitura_foto_s3, PATHINFO_EXTENSION));
    }

    /**
     * Comprovante do Pix de um morador só para ele e para a gestão; o das contas do condomínio, para todos.
     */
    public static function podeVerLancamento(User $user, LancamentoCaixa $lancamento): bool
    {
        if ($user->vePainelGestao()) {
            return true;
        }

        $original = $lancamento->estornoDe ?? $lancamento;

        if ($original->origem_tipo !== 'pagamento') {
            return true;
        }

        return Pagamento::query()->whereKey($original->origem_id)
            ->whereHas('cobranca', fn ($q) => $q->where('unidade_id', $user->unidade_id))
            ->exists();
    }

    private function entregar(string $caminho, string $nome): StreamedResponse
    {
        $disco = $this->armazenamento->disco();
        abort_unless($disco->exists($caminho), 404);

        return $disco->response($caminho, $nome, [
            'Cache-Control' => 'private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}

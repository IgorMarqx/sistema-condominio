<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\Concessionaria;
use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Http\Apresentacao\CobrancaDados;
use App\Http\Apresentacao\FaturaDados;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Models\Pagamento;
use App\Models\Unidade;
use App\Services\Caixa\CaixaService;
use App\Services\Caixa\FechamentoService;
use App\Services\Cobrancas\PagamentoService;
use App\Support\Mes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request, CaixaService $caixa, PagamentoService $pagamentos, FechamentoService $fechamentos): Response
    {
        $hoje = Mes::ref(today());
        // Mês atual; sem cobrança nele ainda, o último mês que teve.
        $mes = Cobranca::query()->where('mes_ref', $hoje->toDateString())->exists()
            ? $hoje
            : (($ultimo = Cobranca::query()->max('mes_ref')) ? Mes::ref($ultimo) : null);

        $cobrancas = $mes
            ? Cobranca::query()->where('mes_ref', $mes->toDateString())->where('status', '!=', StatusCobranca::Cancelada)
                ->with(['unidade', 'itens', 'pagamentos'])->orderBy('id')->get()->groupBy('unidade_id')
            : collect();

        $emAberto = $cobrancas->flatten()
            // Comprovante enviado conta como "conferir", não como aberto (igual ao mockup).
            ->filter(fn (Cobranca $c) => $c->status === StatusCobranca::Aberta)
            ->sum(fn (Cobranca $c) => $pagamentos->emAberto($c));

        $faturasPendentes = Fatura::query()->doCondominio()
            ->whereIn('status', [StatusFatura::PendenteConfirmacao, StatusFatura::PendenteRevisao])
            ->with(['medidor', 'competencia'])
            ->latest('id')
            ->get();

        $comprovantes = Pagamento::query()->where('status', 'aguardando')->with('cobranca.unidade')->get();

        $proximoFechamento = $fechamentos->proximoMes();
        $podeFechar = $proximoFechamento && $fechamentos->previa($proximoFechamento)['pode_fechar'];
        $ocupados = Unidade::query()->where('ativa', true)->ordemFixa()->pluck('numero');

        return Inertia::render('gestao/painel', [
            'resumo' => [
                'recebidas' => $cobrancas->filter(fn (Collection $lista) => $lista->every(fn (Cobranca $c) => $c->status === StatusCobranca::Paga))->count(),
                'total' => $cobrancas->count(),
                'em_aberto' => $emAberto,
                'caixa' => $caixa->saldo(),
            ],
            'pendencias' => [
                'faturas' => $faturasPendentes->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
                'comprovantes' => [
                    'quantidade' => $comprovantes->count(),
                    'aptos' => $comprovantes->map(fn (Pagamento $p) => $p->cobranca->unidade->numero)->unique()->sort()->values(),
                    'mes' => $comprovantes->first()?->cobranca->mes_ref->format('Y-m'),
                ],
                'fechamento' => $podeFechar ? ['mes' => $proximoFechamento->format('Y-m'), 'mes_rotulo' => Mes::rotulo($proximoFechamento)] : null,
            ],
            'contas' => [
                'mes_nome' => Mes::nome($hoje),
                'ocupados' => $ocupados,
                'energisa' => $this->conta(Concessionaria::Energisa, $hoje),
                'cagepa' => $this->conta(Concessionaria::Cagepa, $hoje),
                'internet' => $this->conta(Concessionaria::Internet, $hoje),
            ],
            'cobrancas' => $mes ? [
                'mes' => $mes->format('Y-m'),
                'mes_nome' => Mes::nome($mes),
                'vencimento' => $cobrancas->flatten()->min('vencimento')?->toDateString(),
                'unidades' => Unidade::query()->where('ativa', true)->orderByDesc('andar')->orderBy('numero')->get()
                    ->map(function (Unidade $u) use ($cobrancas, $pagamentos) {
                        /** @var Collection<int, Cobranca> $lista */
                        $lista = $cobrancas->get($u->id, collect());

                        return [
                            'numero' => $u->numero,
                            'andar' => $u->andar,
                            'status' => $this->statusDaUnidade($lista),
                            'vencida' => $lista->contains(fn (Cobranca $c) => $c->vencida()),
                            'total' => (int) $lista->sum('valor_centavos'),
                            'cobrancas' => $lista->map(fn (Cobranca $c) => CobrancaDados::resumo($c) + [
                                'em_aberto' => $pagamentos->emAberto($c),
                                // Quais contas a cobrança cobre: o painel mostra energia, água e internet em linhas fixas.
                                'tipos' => $c->itens->pluck('tipo')->unique()->values(),
                            ])->values(),
                        ];
                    })->values(),
            ] : null,
        ]);
    }

    /**
     * @return array{medidor_id: int|null, unidade_medida: string|null, lancada: array{valor: int|null, consumo: string|null}|null}
     */
    private function conta(Concessionaria $concessionaria, Carbon $mes): array
    {
        $medidor = Medidor::query()->where('dono_tipo', 'condominio')->where('concessionaria', $concessionaria)
            ->where('ativo', true)->orderBy('id')->first();

        $fatura = $medidor
            ? Fatura::query()->where('medidor_id', $medidor->id)->where('status', StatusFatura::Confirmada)
                ->whereHas('competencia', fn ($q) => $q->where('mes_ref', $mes->toDateString()))->first()
            : null;

        return [
            'medidor_id' => $medidor?->id,
            'unidade_medida' => $concessionaria->unidadeMedida(),
            'lancada' => $fatura ? [
                'valor' => $fatura->valor_centavos,
                'consumo' => $fatura->consumo !== null ? rtrim(rtrim((string) $fatura->consumo, '0'), '.') : null,
            ] : null,
        ];
    }

    /**
     * Paga só quando todas as cobranças do mês (energia, água e internet) estão pagas.
     *
     * @param  Collection<int, Cobranca>  $lista
     */
    private function statusDaUnidade(Collection $lista): ?string
    {
        return match (true) {
            $lista->isEmpty() => null,
            $lista->every(fn (Cobranca $c) => $c->status === StatusCobranca::Paga) => StatusCobranca::Paga->value,
            $lista->contains(fn (Cobranca $c) => $c->status === StatusCobranca::AguardandoConfirmacao) => StatusCobranca::AguardandoConfirmacao->value,
            default => StatusCobranca::Aberta->value,
        };
    }
}

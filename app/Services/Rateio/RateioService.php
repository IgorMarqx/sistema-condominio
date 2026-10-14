<?php

namespace App\Services\Rateio;

use App\Enums\Concessionaria;
use App\Enums\StatusCobranca;
use App\Enums\StatusCompetencia;
use App\Enums\StatusFatura;
use App\Exceptions\RegraNegocio;
use App\Models\AjusteRateio;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Models\Rateio;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Services\Configuracoes;
use App\Services\Notificador;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RateioService
{
    public function __construct(
        private readonly Configuracoes $configuracoes,
        private readonly Auditoria $auditoria,
        private readonly Notificador $notificador,
    ) {}

    /**
     * Faturas do condomínio confirmadas e ainda fora de qualquer rateio.
     *
     * @return Collection<int, Fatura>
     */
    public function faturasDisponiveis(): Collection
    {
        return Fatura::query()
            ->doCondominio()
            ->where('status', StatusFatura::Confirmada)
            ->whereDoesntHave('rateios')
            ->with(['medidor', 'competencia'])
            ->orderBy('vencimento')
            ->get();
    }

    /**
     * Calcula sem gravar. Mesma conta que o rascunho e a confirmação usam.
     *
     * @param  Collection<int, Fatura>  $faturas
     * @return array{
     *     regra: string, total_faturas: int, contribuicao: int, total_geral: int, vencimento: string, resto: int,
     *     linhas: list<array{tipo: string, descricao: string, total: int, fatura_id: int|null}>,
     *     unidades: array<int, array{numero: string, itens: list<array{tipo: string, descricao: string, valor: int, fatura_id: int|null, ajuste_id?: int}>, total: int}>,
     *     recebem_resto: list<string>
     * }
     */
    public function calcular(Collection $faturas, ?Carbon $vencimento = null, ?string $regra = null, ?int $contribuicao = null): array
    {
        $regra ??= (string) $this->configuracoes->get('regra_rateio');
        $contribuicao ??= $this->configuracoes->int('contribuicao_centavos');

        $unidades = Unidade::query()->where('ativa', true)->ordemFixa()->get();
        $ids = array_values($unidades->map(fn (Unidade $u) => $u->id)->all());
        $numeros = $unidades->mapWithKeys(fn (Unidade $u) => [$u->id => $u->numero])->all();
        $fracoes = $unidades->mapWithKeys(fn (Unidade $u) => [$u->id => $u->fracao_ideal])->all();
        $n = count($ids);

        $calculadora = new CalculadoraRateio;
        $itens = [];
        $linhas = [];
        $resto = 0;
        $recebem = [];

        // Energia antes de água: a ordem decide para quem vai o centavo do resto.
        $ordenadas = $faturas->sortBy(fn (Fatura $f) => [$f->medidor->concessionaria === Concessionaria::Energisa ? 0 : 1, $f->id])->values();

        foreach ($ordenadas as $fatura) {
            $concessionaria = $fatura->medidor->concessionaria;
            $tipo = $concessionaria === Concessionaria::Energisa ? 'energia' : 'agua';
            $total = (int) $fatura->valor_centavos;
            $divisao = $calculadora->dividir($total, $ids, $regra, $fracoes);
            $resto += $divisao['resto'];

            foreach ($divisao['recebem_resto'] as $id) {
                $recebem[] = $numeros[$id];
            }

            $parte = $regra === 'fracao' ? 'fração ideal' : '1/'.$n;
            $descricao = $concessionaria->servico().' do condomínio ('.$parte.' de '.Dinheiro::formatar($total).')';
            $linhas[] = ['tipo' => $tipo, 'descricao' => $concessionaria->servico().' do condomínio · '.Mes::rotulo($fatura->competencia->mes_ref), 'total' => $total, 'fatura_id' => $fatura->id];

            foreach ($divisao['valores'] as $id => $valor) {
                $itens[$id][] = ['tipo' => $tipo, 'descricao' => $descricao, 'valor' => $valor, 'fatura_id' => $fatura->id];
            }
        }

        if ($contribuicao > 0) {
            $linhas[] = ['tipo' => 'contribuicao', 'descricao' => 'Contribuição ao caixa', 'total' => $contribuicao * $n, 'fatura_id' => null];

            foreach ($ids as $id) {
                $itens[$id][] = ['tipo' => 'contribuicao', 'descricao' => 'Contribuição ao caixa', 'valor' => $contribuicao, 'fatura_id' => null];
            }
        }

        $ajustes = AjusteRateio::query()->whereNull('cobranca_item_id')->whereIn('unidade_id', $ids)->orderBy('id')->get();

        foreach ($ajustes as $ajuste) {
            $itens[$ajuste->unidade_id][] = [
                'tipo' => 'ajuste',
                'descricao' => $ajuste->descricao,
                'valor' => $ajuste->valor_centavos,
                'fatura_id' => null,
                'ajuste_id' => $ajuste->id,
            ];
        }

        $porUnidade = [];

        foreach ($ids as $id) {
            $lista = $itens[$id] ?? [];
            $porUnidade[$id] = [
                'numero' => $numeros[$id],
                'itens' => $lista,
                // Crédito maior que a cobrança não vira valor negativo: a sobra fica para o mês seguinte à mão.
                'total' => max(0, array_sum(array_column($lista, 'valor'))),
            ];
        }

        $vencimento ??= $this->vencimentoSugerido($faturas);

        return [
            'regra' => $regra,
            'total_faturas' => (int) $faturas->sum('valor_centavos'),
            'contribuicao' => $contribuicao,
            'total_geral' => array_sum(array_column($porUnidade, 'total')),
            'vencimento' => $vencimento->toDateString(),
            'resto' => $resto,
            'linhas' => $linhas,
            'unidades' => $porUnidade,
            'recebem_resto' => $recebem,
        ];
    }

    /**
     * Vencimento interno = vencimento mais próximo das concessionárias − N dias (nunca no passado).
     *
     * @param  Collection<int, Fatura>  $faturas
     */
    public function vencimentoSugerido(Collection $faturas): Carbon
    {
        $menor = $faturas->pluck('vencimento')->filter()->min();
        $dias = $this->configuracoes->int('dias_antecedencia');
        $data = $menor ? Carbon::parse($menor)->subDays($dias) : today()->addDays(10);

        return $data->lt(today()) ? today()->addDays(3) : $data;
    }

    /**
     * @param  list<int>  $faturaIds
     */
    public function gerarRascunho(Carbon $mes, array $faturaIds, User $autor, ?Carbon $vencimento = null): Rateio
    {
        $mes = Mes::ref($mes);

        if (Rateio::query()->where('mes_ref', $mes)->where('status', 'confirmado')->exists()) {
            throw new RegraNegocio('O rateio de '.Mes::rotulo($mes).' já foi confirmado. Correções entram como ajuste no mês seguinte.');
        }

        $faturas = $this->faturasDisponiveis()->whereIn('id', $faturaIds)->values();

        if ($faturas->isEmpty()) {
            throw new RegraNegocio('Escolha ao menos uma fatura do condomínio confirmada.');
        }

        $calculo = $this->calcular($faturas, $vencimento);

        return DB::transaction(function () use ($mes, $faturas, $calculo, $autor) {
            Rateio::query()->where('mes_ref', $mes)->where('status', 'rascunho')->get()->each(function (Rateio $antigo) {
                $antigo->faturas()->detach();
                $antigo->delete();
            });

            $rateio = Rateio::query()->create([
                'mes_ref' => $mes,
                'regra' => $calculo['regra'],
                'total_centavos' => $calculo['total_faturas'],
                'contribuicao_centavos' => $calculo['contribuicao'],
                'resto_centavos' => $calculo['resto'],
                'vencimento' => $calculo['vencimento'],
                'detalhe_json' => $calculo,
                'status' => 'rascunho',
                'criado_por' => $autor->id,
            ]);

            $rateio->faturas()->attach($faturas->pluck('id'));

            $this->auditoria->registrar('rateio.rascunho', $rateio, null, [
                'mes' => $mes->format('Y-m'),
                'faturas' => $faturas->pluck('id')->all(),
                'total_geral' => $calculo['total_geral'],
            ]);

            return $rateio;
        });
    }

    /**
     * Confirma o rascunho: recalcula, cria as cobranças e trava o rateio.
     */
    public function confirmar(Rateio $rateio, User $autor): Rateio
    {
        if ($rateio->isConfirmado()) {
            throw new RegraNegocio('Este rateio já foi confirmado.');
        }

        return DB::transaction(function () use ($rateio, $autor) {
            $rateio = Rateio::query()->whereKey($rateio->id)->lockForUpdate()->firstOrFail();
            $faturas = $rateio->faturas()->with(['medidor', 'competencia'])->get();

            if ($faturas->contains(fn (Fatura $f) => $f->status !== StatusFatura::Confirmada)) {
                throw new RegraNegocio('Uma das faturas deixou de estar confirmada. Gere o rateio de novo.');
            }

            $calculo = $this->calcular($faturas, $rateio->vencimento, $rateio->regra, $rateio->contribuicao_centavos);

            foreach ($calculo['unidades'] as $unidadeId => $dados) {
                $cobranca = Cobranca::query()->create([
                    'rateio_id' => $rateio->id,
                    'unidade_id' => $unidadeId,
                    'mes_ref' => $rateio->mes_ref,
                    'valor_centavos' => $dados['total'],
                    'vencimento' => $rateio->vencimento,
                    'status' => $dados['total'] > 0 ? StatusCobranca::Aberta : StatusCobranca::Paga,
                    'pix_txid' => 'COND'.$rateio->mes_ref->format('Ym').'AP'.$dados['numero'],
                ]);

                foreach ($dados['itens'] as $item) {
                    $criado = $cobranca->itens()->create([
                        'tipo' => $item['tipo'],
                        'descricao' => $item['descricao'],
                        'valor_centavos' => $item['valor'],
                        'fatura_id' => $item['fatura_id'],
                    ]);

                    if (isset($item['ajuste_id'])) {
                        AjusteRateio::query()->whereKey($item['ajuste_id'])->update(['cobranca_item_id' => $criado->id]);
                    }
                }
            }

            $rateio->update([
                'status' => 'confirmado',
                'detalhe_json' => $calculo,
                'resto_centavos' => $calculo['resto'],
                'confirmado_por' => $autor->id,
                'confirmado_em' => now(),
            ]);

            foreach ($faturas as $fatura) {
                $fatura->competencia->avancarPara(StatusCompetencia::Rateada);
            }

            $this->auditoria->registrar('rateio.confirmado', $rateio, null, [
                'mes' => $rateio->mes_ref->format('Y-m'),
                'vencimento' => $rateio->vencimento->toDateString(),
                'cobrancas' => collect($calculo['unidades'])->mapWithKeys(fn ($u) => [$u['numero'] => $u['total']])->all(),
                'recebem_resto' => $calculo['recebem_resto'],
            ]);

            foreach ($calculo['unidades'] as $unidadeId => $dados) {
                $this->notificador->unidade(
                    $unidadeId,
                    'cobranca_gerada',
                    'Cobrança de '.Mes::nome($rateio->mes_ref).' disponível',
                    Dinheiro::formatar($dados['total']).' · vence '.$rateio->vencimento->format('d/m'),
                    '/cobranca',
                    'cobranca:'.$rateio->id.':'.$unidadeId,
                );
            }

            return $rateio->refresh();
        });
    }

    public function descartar(Rateio $rateio): void
    {
        if ($rateio->isConfirmado()) {
            throw new RegraNegocio('Rateio confirmado não pode ser descartado; use um ajuste no mês seguinte.');
        }

        DB::transaction(function () use ($rateio) {
            $rateio->faturas()->detach();
            $this->auditoria->registrar('rateio.descartado', $rateio, ['mes' => $rateio->mes_ref->format('Y-m')]);
            $rateio->delete();
        });
    }

    public function criarAjuste(Unidade $unidade, int $valorCentavos, string $descricao, string $motivo, User $autor, ?Rateio $origem = null): AjusteRateio
    {
        if ($valorCentavos === 0) {
            throw new RegraNegocio('O ajuste precisa de um valor diferente de zero.');
        }

        return DB::transaction(function () use ($unidade, $valorCentavos, $descricao, $motivo, $autor, $origem) {
            $ajuste = AjusteRateio::query()->create([
                'rateio_origem_id' => $origem?->id,
                'unidade_id' => $unidade->id,
                'descricao' => $descricao,
                'valor_centavos' => $valorCentavos,
                'motivo' => $motivo,
                'criado_por' => $autor->id,
            ]);

            $this->auditoria->registrar('rateio.ajuste', $ajuste, null, [
                'unidade' => $unidade->numero,
                'valor_centavos' => $valorCentavos,
                'descricao' => $descricao,
                'motivo' => $motivo,
            ]);

            return $ajuste;
        });
    }
}

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
     * Calcula sem gravar. É a mesma conta que o ratear() grava.
     *
     * @param  Collection<int, Fatura>  $faturas
     * @return array{
     *     regra: string, total_faturas: int, contribuicao: int, total_geral: int, vencimento: string, resto: int,
     *     linhas: list<array{tipo: string, descricao: string, total: int, fatura_id: int|null}>,
     *     unidades: array<int, array{numero: string, itens: list<array{tipo: string, descricao: string, valor: int, fatura_id: int|null, ajuste_id?: int}>, total: int}>,
     *     recebem_resto: list<string>
     * }
     */
    public function calcular(Collection $faturas, ?Carbon $vencimento = null, string $regra = 'igual', ?int $contribuicao = null, bool $comAjustes = true): array
    {
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

        // Sem ajustes: whereIn vazio não traz nenhum.
        $ajustes = AjusteRateio::query()->whereNull('cobranca_item_id')->whereIn('unidade_id', $comAjustes ? $ids : [])->orderBy('id')->get();

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
     * Cada conta do condomínio vira o próprio rateio, já confirmado: divide igual entre as unidades
     * ativas, cria as cobranças e avisa as unidades. A contribuição ao caixa e os ajustes pendentes
     * entram só no primeiro rateio do mês.
     */
    public function ratear(Fatura $fatura, User $autor): Rateio
    {
        $fatura->loadMissing(['medidor', 'competencia']);

        if (! $fatura->isCondominio() || $fatura->status !== StatusFatura::Confirmada) {
            throw new RegraNegocio('Só conta do condomínio confirmada gera cobrança.');
        }

        if ($fatura->rateios()->exists()) {
            throw new RegraNegocio('Esta conta já gerou as cobranças.');
        }

        $mes = Mes::ref($fatura->competencia->mes_ref);

        return DB::transaction(function () use ($fatura, $mes, $autor) {
            $primeiroDoMes = ! Rateio::query()->where('mes_ref', $mes->toDateString())->lockForUpdate()->exists();
            $calculo = $this->calcular(collect([$fatura]), null, 'igual', $primeiroDoMes ? null : 0, $primeiroDoMes);

            $rateio = Rateio::query()->create([
                'mes_ref' => $mes,
                'regra' => 'igual',
                'total_centavos' => $calculo['total_faturas'],
                'contribuicao_centavos' => $calculo['contribuicao'],
                'resto_centavos' => $calculo['resto'],
                'vencimento' => $calculo['vencimento'],
                'detalhe_json' => $calculo,
                'status' => 'confirmado',
                'criado_por' => $autor->id,
                'confirmado_por' => $autor->id,
                'confirmado_em' => now(),
            ]);

            $rateio->faturas()->attach($fatura->id);
            $sigla = $fatura->medidor->concessionaria === Concessionaria::Energisa ? 'E' : 'A';

            foreach ($calculo['unidades'] as $unidadeId => $dados) {
                $cobranca = Cobranca::query()->create([
                    'rateio_id' => $rateio->id,
                    'unidade_id' => $unidadeId,
                    'mes_ref' => $mes,
                    'valor_centavos' => $dados['total'],
                    'vencimento' => $rateio->vencimento,
                    'status' => $dados['total'] > 0 ? StatusCobranca::Aberta : StatusCobranca::Paga,
                    'pix_txid' => 'COND'.$mes->format('Ym').$sigla.'AP'.$dados['numero'],
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

            $fatura->competencia->avancarPara(StatusCompetencia::Rateada);
            $servico = $fatura->medidor->concessionaria->servico();

            $this->auditoria->registrar('rateio.confirmado', $rateio, null, [
                'mes' => $mes->format('Y-m'),
                'fatura' => $fatura->id,
                'servico' => $servico,
                'vencimento' => $rateio->vencimento->toDateString(),
                'cobrancas' => collect($calculo['unidades'])->mapWithKeys(fn ($u) => [$u['numero'] => $u['total']])->all(),
                'recebem_resto' => $calculo['recebem_resto'],
            ]);

            foreach ($calculo['unidades'] as $unidadeId => $dados) {
                $this->notificador->unidade(
                    $unidadeId,
                    'cobranca_gerada',
                    'Cobrança de '.mb_strtolower($servico).' de '.Mes::nome($mes).' disponível',
                    Dinheiro::formatar($dados['total']).' · vence '.$rateio->vencimento->format('d/m'),
                    '/cobranca',
                    'cobranca:'.$rateio->id.':'.$unidadeId,
                );
            }

            return $rateio->refresh();
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

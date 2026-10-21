<?php

namespace App\Services\Caixa;

use App\Enums\CategoriaLancamento;
use App\Exceptions\RegraNegocio;
use App\Models\ContaCaixa;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Models\User;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Livro-caixa append-only. O saldo é sempre calculado; nada aqui altera um lançamento gravado.
 */
class CaixaService
{
    public function __construct(
        private readonly Auditoria $auditoria,
        private readonly Armazenamento $armazenamento,
    ) {}

    /**
     * @param  UploadedFile|array{caminho: string, sha256: string}|null  $comprovante
     */
    public function lancar(
        ContaCaixa $conta,
        Carbon $data,
        string $tipo,
        int $valorCentavos,
        CategoriaLancamento $categoria,
        string $descricao,
        User $autor,
        UploadedFile|array|null $comprovante,
        string $origemTipo = 'manual',
        ?int $origemId = null,
        ?string $grupo = null,
    ): LancamentoCaixa {
        if (! in_array($tipo, ['entrada', 'saida'], true)) {
            throw new RegraNegocio('Tipo de lançamento inválido.');
        }

        if ($valorCentavos <= 0) {
            throw new RegraNegocio('O valor do lançamento precisa ser maior que zero.');
        }

        // Exceção única: o recebimento de cobrança que o síndico marca como pago no painel.
        if ($comprovante === null && $origemTipo !== 'pagamento') {
            throw new RegraNegocio('Todo lançamento precisa de comprovante.');
        }

        $this->garantirMesAberto($data);

        $arquivo = $comprovante instanceof UploadedFile
            ? $this->armazenamento->guardarUpload($comprovante, 'comprovantes')
            : $comprovante;

        return DB::transaction(function () use ($conta, $data, $tipo, $valorCentavos, $categoria, $descricao, $autor, $arquivo, $origemTipo, $origemId, $grupo) {
            $lancamento = LancamentoCaixa::query()->create([
                'conta_id' => $conta->id,
                'data' => $data->toDateString(),
                'tipo' => $tipo,
                'valor_centavos' => $valorCentavos,
                'categoria' => $categoria,
                'descricao' => $descricao,
                'comprovante_s3' => $arquivo['caminho'] ?? null,
                'comprovante_sha256' => $arquivo['sha256'] ?? null,
                'origem_tipo' => $origemTipo,
                'origem_id' => $origemId,
                'grupo' => $grupo,
                'autor_id' => $autor->id,
            ]);

            $this->auditoria->registrar('caixa.lancamento', $lancamento, null, $this->resumo($lancamento));

            return $lancamento;
        });
    }

    /**
     * Estorno = lançamento inverso que referencia o original. O original fica intocado.
     */
    public function estornar(LancamentoCaixa $original, string $motivo, User $autor, ?Carbon $data = null): LancamentoCaixa
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RegraNegocio('O estorno exige um motivo.');
        }

        if ($original->estorno_de_id !== null) {
            throw new RegraNegocio('Um estorno não pode ser estornado.');
        }

        $data ??= today();
        $this->garantirMesAberto($data);

        return DB::transaction(function () use ($original, $motivo, $autor, $data) {
            // Trava o original: dois estornos simultâneos esbarrariam no UNIQUE, mas a mensagem fica melhor assim.
            LancamentoCaixa::query()->whereKey($original->id)->lockForUpdate()->first();

            if (LancamentoCaixa::query()->where('estorno_de_id', $original->id)->exists()) {
                throw new RegraNegocio('Este lançamento já foi estornado.');
            }

            $estorno = LancamentoCaixa::query()->create([
                'conta_id' => $original->conta_id,
                'data' => $data->toDateString(),
                'tipo' => $original->tipo === 'entrada' ? 'saida' : 'entrada',
                'valor_centavos' => $original->valor_centavos,
                'categoria' => CategoriaLancamento::Estorno,
                'descricao' => 'Estorno · '.$original->descricao,
                'comprovante_s3' => $original->comprovante_s3,
                'comprovante_sha256' => $original->comprovante_sha256,
                'origem_tipo' => 'estorno',
                'origem_id' => $original->id,
                'estorno_de_id' => $original->id,
                'motivo' => $motivo,
                'autor_id' => $autor->id,
            ]);

            $this->auditoria->registrar('caixa.estorno', $estorno, $this->resumo($original), $this->resumo($estorno) + ['motivo' => $motivo]);

            return $estorno;
        });
    }

    /**
     * Transferência entre contas: saída numa, entrada na outra, ligadas pelo mesmo grupo.
     *
     * @param  UploadedFile|array{caminho: string, sha256: string}  $comprovante
     * @return array{0: LancamentoCaixa, 1: LancamentoCaixa}
     */
    public function transferir(ContaCaixa $origem, ContaCaixa $destino, int $valorCentavos, Carbon $data, string $descricao, User $autor, UploadedFile|array $comprovante): array
    {
        if ($origem->is($destino)) {
            throw new RegraNegocio('Escolha contas diferentes para a transferência.');
        }

        $arquivo = $comprovante instanceof UploadedFile
            ? $this->armazenamento->guardarUpload($comprovante, 'comprovantes')
            : $comprovante;

        $grupo = (string) Str::uuid();

        return DB::transaction(fn () => [
            $this->lancar($origem, $data, 'saida', $valorCentavos, CategoriaLancamento::Transferencia, $descricao.' → '.$destino->nome, $autor, $arquivo, 'transferencia', null, $grupo),
            $this->lancar($destino, $data, 'entrada', $valorCentavos, CategoriaLancamento::Transferencia, $descricao.' ← '.$origem->nome, $autor, $arquivo, 'transferencia', null, $grupo),
        ]);
    }

    public function saldo(?ContaCaixa $conta = null, ?Carbon $ate = null): int
    {
        return (int) LancamentoCaixa::query()
            ->when($conta, fn ($q) => $q->where('conta_id', $conta->id))
            ->when($ate, fn ($q) => $q->where('data', '<=', $ate->toDateString()))
            ->sum(DB::raw("CASE WHEN tipo = 'entrada' THEN valor_centavos ELSE -valor_centavos END"));
    }

    /**
     * @return array<string, int> saldo por slug de conta
     */
    public function saldosPorConta(?Carbon $ate = null): array
    {
        return ContaCaixa::query()->orderBy('id')->get()
            ->mapWithKeys(fn (ContaCaixa $conta) => [$conta->slug => $this->saldo($conta, $ate)])
            ->all();
    }

    /**
     * @return array{entradas: int, saidas: int}
     */
    public function movimentoDoMes(Carbon $mes): array
    {
        $linha = LancamentoCaixa::query()
            ->whereBetween('data', [$mes->copy()->startOfMonth()->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            // Transferência entre contas não é entrada nem saída do condomínio, e um lançamento
            // estornado se anula com o estorno: nenhum dos dois entra no movimento do mês.
            ->where('categoria', '!=', CategoriaLancamento::Transferencia->value)
            ->whereNull('estorno_de_id')
            ->whereDoesntHave('estornadoPor')
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN valor_centavos ELSE 0 END) AS entradas")
            ->selectRaw("SUM(CASE WHEN tipo = 'saida' THEN valor_centavos ELSE 0 END) AS saidas")
            ->first();

        return ['entradas' => (int) ($linha->entradas ?? 0), 'saidas' => (int) ($linha->saidas ?? 0)];
    }

    /**
     * Saldo ao fim de cada um dos últimos N meses (gráfico de evolução).
     *
     * @return list<array{mes: string, saldo: int, entradas: int, saidas: int}>
     */
    public function evolucao(int $meses = 12): array
    {
        $serie = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = today()->startOfMonth()->subMonthsNoOverflow($i);
            $movimento = $this->movimentoDoMes($mes);
            $serie[] = [
                'mes' => $mes->format('Y-m'),
                'saldo' => $this->saldo(null, $mes->copy()->endOfMonth()),
                ...$movimento,
            ];
        }

        return $serie;
    }

    public function garantirMesAberto(Carbon $data): void
    {
        $travado = Fechamento::travadoAte();

        if ($travado && $data->lte($travado)) {
            throw new RegraNegocio('O mês de '.$data->translatedFormat('F/Y').' já foi fechado. Lance com data posterior a '.$travado->format('d/m/Y').'.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resumo(LancamentoCaixa $lancamento): array
    {
        return [
            'conta_id' => $lancamento->conta_id,
            'data' => $lancamento->data->toDateString(),
            'tipo' => $lancamento->tipo,
            'valor_centavos' => $lancamento->valor_centavos,
            'categoria' => $lancamento->categoria->value,
            'descricao' => $lancamento->descricao,
            'comprovante_sha256' => $lancamento->comprovante_sha256,
            'origem' => $lancamento->origem_tipo.($lancamento->origem_id ? '#'.$lancamento->origem_id : ''),
        ];
    }
}

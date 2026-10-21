<?php

namespace App\Http\Controllers\Unidade;

use App\Enums\Concessionaria;
use App\Exceptions\RegraNegocio;
use App\Http\Apresentacao\FaturaDados;
use App\Http\Controllers\Controller;
use App\Http\Requests\CamposFaturaRequest;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Services\Auditoria\Auditoria;
use App\Services\Configuracoes;
use App\Services\Consultas;
use App\Services\Faturas\FaturaService;
use App\Services\Faturas\ValidadorFatura;
use App\Services\Ingestao\ProcessadorPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas individuais de luz e água. Só a própria unidade vê valores e consumo.
 */
class MinhasContasController extends Controller
{
    public function index(Request $request, Consultas $consultas, ValidadorFatura $validador): Response
    {
        $unidade = $request->user()->unidade;
        $medidores = $unidade->medidores()->where('ativo', true)->get();
        $servicos = [];

        foreach (Concessionaria::cases() as $concessionaria) {
            $medidor = $medidores->firstWhere('concessionaria', $concessionaria);

            if (! $medidor) {
                $servicos[$concessionaria->value] = null;

                continue;
            }

            $ultima = Fatura::query()->where('medidor_id', $medidor->id)->where('status', '!=', 'rejeitada')
                ->with(['medidor', 'competencia', 'email'])
                ->get()->sortByDesc(fn (Fatura $f) => [$f->competencia->mes_ref, $f->id])->first();

            $servicos[$concessionaria->value] = [
                'medidor' => ['id' => $medidor->id, 'identificador' => $medidor->identificador],
                'ultima' => $ultima ? FaturaDados::detalhe($ultima) : null,
                'variacao' => $ultima ? $validador->variacaoConsumo($ultima) : null,
                'historico' => $consultas->historicoConsumo($medidor),
            ];
        }

        $historico = Fatura::query()->daUnidade($unidade->id)->where('status', '!=', 'rejeitada')
            ->with(['medidor', 'competencia'])
            ->get()->sortByDesc(fn (Fatura $f) => [$f->competencia->mes_ref, $f->id])->values();

        return Inertia::render('unidade/minhas-contas', [
            'servicos' => $servicos,
            'historico' => $historico->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
            'medidores' => $medidores->map(fn (Medidor $m) => [
                'id' => $m->id,
                'concessionaria' => $m->concessionaria->value,
                'rotulo' => $m->concessionaria->rotulo().' · '.$m->identificador,
                'tem_senha_pdf' => $m->senha_pdf !== null,
            ])->values(),
            'autoriza_admin' => $unidade->autoriza_admin_ver_faturas,
            'limite_anomalia' => (int) app(Configuracoes::class)->get('limite_anomalia_percentual'),
        ]);
    }

    public function upload(CamposFaturaRequest $request, ProcessadorPdf $processador): RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'medidor_id' => ['nullable', 'integer'],
        ], [], ['arquivo' => 'PDF da fatura']);

        $unidade = $request->user()->unidade;
        $medidor = $request->filled('medidor_id')
            ? $unidade->medidores()->whereKey($request->integer('medidor_id'))->firstOrFail()
            : null;

        $resultado = $processador->processar(
            (string) file_get_contents($request->file('arquivo')->getRealPath()),
            $request->file('arquivo')->getClientOriginalName(),
            $unidade,
            null,
            $medidor,
            $request->campos(),
            $request->user()->id,
        );

        return match ($resultado->situacao) {
            'fatura' => back()->with('sucesso', 'Fatura de '.$resultado->fatura->medidor->concessionaria->servico().' registrada.'),
            'duplicado' => back()->with('erro', 'Este PDF já tinha sido enviado.'),
            default => back()->with('erro', (string) $resultado->motivo),
        };
    }

    public function corrigir(CamposFaturaRequest $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $this->garantirDona($request, $fatura);
        $faturas->corrigir($fatura, $request->campos(), (string) ($request->input('motivo') ?: 'Correção pela unidade'), $request->user());

        return back()->with('sucesso', 'Fatura corrigida.');
    }

    public function aceitar(Request $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $this->garantirDona($request, $fatura);
        $faturas->aceitarIndividual($fatura, $request->user());

        return back()->with('sucesso', 'Fatura conferida.');
    }

    public function paga(Request $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $this->garantirDona($request, $fatura);
        $dados = $request->validate(['paga_em' => ['required', 'date', 'before_or_equal:today']]);
        $faturas->marcarPaga($fatura, Carbon::parse($dados['paga_em']));

        return back()->with('sucesso', 'Marcada como paga.');
    }

    public function senhaPdf(Request $request, Medidor $medidor, Auditoria $auditoria): RedirectResponse
    {
        abort_unless($medidor->unidade_id === $request->user()->unidade_id, 404);
        $dados = $request->validate(['senha_pdf' => ['nullable', 'string', 'max:60']]);
        $medidor->update(['senha_pdf' => $dados['senha_pdf'] ?: null]);
        $auditoria->registrar('medidor.senha_pdf', $medidor, null, ['definida' => (bool) $dados['senha_pdf']]);

        return back()->with('sucesso', 'Senha do PDF salva.');
    }

    public function privacidade(Request $request, Auditoria $auditoria): RedirectResponse
    {
        $dados = $request->validate(['autoriza' => ['required', 'boolean']]);
        $unidade = $request->user()->unidade;
        $antes = $unidade->autoriza_admin_ver_faturas;
        $unidade->update(['autoriza_admin_ver_faturas' => $dados['autoriza']]);
        $auditoria->registrar('unidade.privacidade', $unidade, ['autoriza_admin_ver_faturas' => $antes], ['autoriza_admin_ver_faturas' => $dados['autoriza']]);

        return back()->with('sucesso', $dados['autoriza'] ? 'O síndico pode ver valores e consumo.' : 'O síndico vê só se a fatura chegou.');
    }

    public function excluirHistorico(Request $request, FaturaService $faturas): RedirectResponse
    {
        $request->validate(['confirmacao' => ['required', 'in:EXCLUIR']], ['confirmacao.in' => 'Digite EXCLUIR para confirmar.']);
        $total = $faturas->excluirHistoricoIndividual($request->user()->unidade);

        return back()->with('sucesso', $total.' fatura(s) individual(is) excluída(s).');
    }

    private function garantirDona(Request $request, Fatura $fatura): void
    {
        if ($fatura->medidor->isCondominio() || $fatura->medidor->unidade_id !== $request->user()->unidade_id) {
            throw new RegraNegocio('Fatura não encontrada.');
        }
    }
}

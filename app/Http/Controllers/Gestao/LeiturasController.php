<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\StatusCompetencia;
use App\Http\Controllers\Controller;
use App\Models\Competencia;
use App\Models\Medidor;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Faturas\CompetenciaService;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendário de leituras: data prevista de cada medidor no mês e o status da competência.
 */
class LeiturasController extends Controller
{
    public function index(Request $request, CompetenciaService $competencias): Response
    {
        $mes = $request->filled('mes') ? Mes::ref((string) $request->input('mes')) : today()->startOfMonth();

        $medidores = Medidor::query()->where('ativo', true)->with('unidade')
            ->orderByRaw("FIELD(dono_tipo, 'condominio', 'unidade')")
            ->orderBy('unidade_id')->orderBy('concessionaria')
            ->get();

        // Ver o calendário já garante as competências do mês (mesmo efeito do job das 06:00).
        $linhas = $medidores->map(function (Medidor $medidor) use ($competencias, $mes) {
            $competencia = $competencias->garantir($medidor, $mes);
            $competencia->loadMissing('faturaAtual');

            return [
                'competencia_id' => $competencia->id,
                'medidor' => [
                    'id' => $medidor->id,
                    'concessionaria' => $medidor->concessionaria->value,
                    'servico' => $medidor->concessionaria->servico(),
                    'identificador' => $medidor->identificador,
                    'dono' => $medidor->isCondominio() ? 'Condomínio' : 'Apto '.$medidor->unidade?->numero,
                    'condominio' => $medidor->isCondominio(),
                ],
                'leitura_prevista' => $competencia->leitura_prevista?->toDateString(),
                'origem' => $competencia->leitura_prevista_origem,
                'leitura_realizada' => $competencia->leitura_realizada?->toDateString(),
                'tem_foto' => $competencia->leitura_foto_s3 !== null,
                'status' => $competencia->status->value,
                'status_rotulo' => $competencia->status->rotulo(),
                'fatura_id' => $medidor->isCondominio() ? $competencia->faturaAtual?->id : null,
                'atrasada' => $competencia->leitura_prevista !== null
                    && $competencia->leitura_prevista->lte(today()->subDays(7))
                    && in_array($competencia->status, [StatusCompetencia::Prevista, StatusCompetencia::LeituraRealizada], true),
            ];
        });

        return Inertia::render('gestao/leituras', [
            'mes' => $mes->format('Y-m'),
            'mes_rotulo' => Mes::rotulo($mes),
            'linhas' => $linhas->values(),
        ]);
    }

    public function update(Request $request, Competencia $competencia, Armazenamento $armazenamento, Auditoria $auditoria): RedirectResponse
    {
        $dados = $request->validate([
            'leitura_prevista' => ['nullable', 'date'],
            'leitura_realizada' => ['nullable', 'date', 'before_or_equal:today'],
            'foto' => ['nullable', 'image', 'max:10240'],
        ]);

        $antes = $competencia->only(['leitura_prevista', 'leitura_realizada']);
        $mudancas = [];

        if (filled($dados['leitura_prevista'] ?? null)) {
            $mudancas['leitura_prevista'] = $dados['leitura_prevista'];
            $mudancas['leitura_prevista_origem'] = 'manual';
        }

        if (filled($dados['leitura_realizada'] ?? null)) {
            $mudancas['leitura_realizada'] = $dados['leitura_realizada'];
        }

        if ($request->hasFile('foto')) {
            $mudancas['leitura_foto_s3'] = $armazenamento->guardarUpload($request->file('foto'), 'leituras')['caminho'];
        }

        $competencia->update($mudancas);

        if (isset($mudancas['leitura_realizada'])) {
            $competencia->avancarPara(StatusCompetencia::LeituraRealizada);
        }

        $auditoria->registrar('competencia.leitura', $competencia, array_map(fn ($v) => $v?->toDateString(), $antes), array_diff_key($mudancas, ['leitura_foto_s3' => true]) + ['foto' => $request->hasFile('foto')]);

        return back()->with('sucesso', 'Leitura atualizada.');
    }
}

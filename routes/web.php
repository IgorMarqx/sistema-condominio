<?php

use App\Http\Controllers\ArquivosController;
use App\Http\Controllers\Auth\AparelhoController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\Gestao;
use App\Http\Controllers\Unidade;
use App\Http\Controllers\Unidade\AvisosController;
use App\Http\Controllers\Unidade\ReceberFaturasController;
use App\Http\Controllers\Webhooks\EmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Entrada de e-mails (fora do CSRF; autenticados por assinatura SNS ou token).
Route::post('webhooks/email/sns', [EmailController::class, 'sns'])->middleware('throttle:120,1')->name('webhooks.email.sns');
Route::post('webhooks/email/bruto/{token}', [EmailController::class, 'bruto'])->middleware('throttle:120,1')->name('webhooks.email.bruto');

Route::middleware('guest')->group(function () {
    Route::get('entrar/gestao', fn () => Inertia::render('auth/entrar-gestao'))->name('login.gestao');
    Route::post('aparelho/esquecer', [AparelhoController::class, 'esquecer'])->name('aparelho.esquecer');
});

Route::middleware('auth')->group(function () {
    Route::get('trocar-senha', [SenhaController::class, 'edit'])->name('senha.trocar');
    Route::put('trocar-senha', [SenhaController::class, 'update'])->middleware('throttle:6,1')->name('senha.atualizar');

    Route::post('aparelho/biometria', [AparelhoController::class, 'biometria'])->name('aparelho.biometria');

    Route::get('conta', [ContaController::class, 'show'])->name('conta');
    Route::put('conta/email-avisos', [ContaController::class, 'emailAvisos'])->name('conta.email-avisos');
    Route::put('conta/nome', [ContaController::class, 'nome'])->name('conta.nome');

    Route::get('avisos', [AvisosController::class, 'index'])->name('avisos');
    Route::post('avisos/lidas', [AvisosController::class, 'lidas'])->name('avisos.lidas');
    Route::get('avisos/{aviso}', [AvisosController::class, 'abrir'])->name('avisos.abrir');

    // Caixa e contas do condomínio: transparência para todas as unidades.
    Route::get('caixa', [CaixaController::class, 'index'])->name('caixa');
    Route::get('caixa/extrato.csv', [CaixaController::class, 'csv'])->name('caixa.csv');
    Route::get('caixa/extrato.pdf', [CaixaController::class, 'pdf'])->name('caixa.pdf');
    Route::get('condominio', Unidade\CondominioController::class)->name('condominio');

    Route::prefix('arquivos')->name('arquivos.')->group(function () {
        Route::get('fatura/{fatura}', [ArquivosController::class, 'fatura'])->name('fatura');
        Route::get('pagamento/{pagamento}', [ArquivosController::class, 'pagamento'])->name('pagamento');
        Route::get('lancamento/{lancamento}', [ArquivosController::class, 'lancamento'])->name('lancamento');
        Route::get('prestacao/{fechamento}', [ArquivosController::class, 'prestacao'])->name('prestacao');
        Route::get('leitura/{competencia}', [ArquivosController::class, 'leitura'])->name('leitura');
    });

    // Área da unidade.
    Route::middleware('unidade')->group(function () {
        Route::get('/', Unidade\InicioController::class)->name('inicio');

        Route::get('cobranca/{cobranca?}', [Unidade\CobrancaController::class, 'show'])->name('cobranca');
        Route::post('cobranca/{cobranca}/comprovante', [Unidade\CobrancaController::class, 'comprovante'])->name('cobranca.comprovante');

        Route::get('minhas-contas', [Unidade\MinhasContasController::class, 'index'])->name('minhas-contas');
        Route::post('minhas-contas/upload', [Unidade\MinhasContasController::class, 'upload'])->name('minhas-contas.upload');
        Route::patch('minhas-contas/faturas/{fatura}', [Unidade\MinhasContasController::class, 'corrigir'])->name('minhas-contas.corrigir');
        Route::post('minhas-contas/faturas/{fatura}/aceitar', [Unidade\MinhasContasController::class, 'aceitar'])->name('minhas-contas.aceitar');
        Route::post('minhas-contas/faturas/{fatura}/paga', [Unidade\MinhasContasController::class, 'paga'])->name('minhas-contas.paga');
        Route::put('minhas-contas/medidores/{medidor}/senha-pdf', [Unidade\MinhasContasController::class, 'senhaPdf'])->name('minhas-contas.senha-pdf');
        Route::put('minhas-contas/privacidade', [Unidade\MinhasContasController::class, 'privacidade'])->name('minhas-contas.privacidade');
        Route::delete('minhas-contas/historico', [Unidade\MinhasContasController::class, 'excluirHistorico'])->name('minhas-contas.excluir-historico');

        Route::get('receber-faturas', [ReceberFaturasController::class, 'show'])->name('receber-faturas');
        Route::post('receber-faturas/regenerar', [ReceberFaturasController::class, 'regenerar'])->name('receber-faturas.regenerar');
    });

    // Gestão: síndico (com 2FA) e conselho (somente leitura).
    Route::middleware('gestao')->prefix('gestao')->name('gestao.')->group(function () {
        Route::get('/', Gestao\PainelController::class)->name('painel');
        Route::get('seguranca', [ContaController::class, 'show'])->name('seguranca');

        Route::get('faturas', [Gestao\FaturasController::class, 'index'])->name('faturas');
        Route::post('faturas/upload', [Gestao\FaturasController::class, 'upload'])->name('faturas.upload');
        Route::get('faturas/{fatura}', [Gestao\FaturasController::class, 'show'])->name('faturas.show');
        Route::post('faturas/{fatura}/confirmar', [Gestao\FaturasController::class, 'confirmar'])->name('faturas.confirmar');
        Route::patch('faturas/{fatura}', [Gestao\FaturasController::class, 'corrigir'])->name('faturas.corrigir');
        Route::post('faturas/{fatura}/rejeitar', [Gestao\FaturasController::class, 'rejeitar'])->name('faturas.rejeitar');
        Route::post('faturas/{fatura}/pagar', [Gestao\FaturasController::class, 'pagar'])->name('faturas.pagar');
        Route::post('emails/{email}/reprocessar', [Gestao\FaturasController::class, 'reprocessar'])->name('emails.reprocessar');
        Route::post('emails/{email}/descartar', [Gestao\FaturasController::class, 'descartarEmail'])->name('emails.descartar');

        Route::get('receber-faturas', [ReceberFaturasController::class, 'show'])->name('receber-faturas');
        Route::post('receber-faturas/regenerar', [ReceberFaturasController::class, 'regenerar'])->name('receber-faturas.regenerar');

        Route::get('leituras', [Gestao\LeiturasController::class, 'index'])->name('leituras');
        Route::post('competencias/{competencia}', [Gestao\LeiturasController::class, 'update'])->name('competencias.update');

        Route::get('rateio', [Gestao\RateioController::class, 'show'])->name('rateio');
        Route::post('rateio', [Gestao\RateioController::class, 'store'])->name('rateio.store');
        Route::post('rateio/{rateio}/confirmar', [Gestao\RateioController::class, 'confirmar'])->name('rateio.confirmar');
        Route::delete('rateio/{rateio}', [Gestao\RateioController::class, 'destroy'])->name('rateio.destroy');
        Route::post('ajustes', [Gestao\RateioController::class, 'ajuste'])->name('ajustes.store');

        Route::get('cobrancas', [Gestao\CobrancasController::class, 'index'])->name('cobrancas');
        Route::post('pagamentos/{pagamento}/confirmar', [Gestao\CobrancasController::class, 'confirmar'])->name('pagamentos.confirmar');
        Route::post('pagamentos/{pagamento}/recusar', [Gestao\CobrancasController::class, 'recusar'])->name('pagamentos.recusar');
        Route::post('cobrancas/{cobranca}/recebimento', [Gestao\CobrancasController::class, 'recebimento'])->name('cobrancas.recebimento');

        Route::get('caixa', [CaixaController::class, 'index'])->name('caixa');
        Route::post('caixa/lancamentos', [Gestao\LancamentosController::class, 'store'])->name('lancamentos.store');
        Route::post('caixa/lancamentos/{lancamento}/estornar', [Gestao\LancamentosController::class, 'estornar'])->name('lancamentos.estornar');
        Route::post('caixa/transferencias', [Gestao\LancamentosController::class, 'transferir'])->name('transferencias.store');

        Route::get('fechamento', [Gestao\FechamentoController::class, 'show'])->name('fechamento');
        Route::post('fechamento', [Gestao\FechamentoController::class, 'store'])->name('fechamento.store');

        Route::get('unidades', [Gestao\UnidadesController::class, 'index'])->name('unidades');
        Route::post('unidades/{unidade}/senha', [Gestao\UnidadesController::class, 'senha'])->name('unidades.senha');
        Route::patch('unidades/{unidade}', [Gestao\UnidadesController::class, 'update'])->name('unidades.update');

        Route::get('medidores', [Gestao\MedidoresController::class, 'index'])->name('medidores');
        Route::post('medidores', [Gestao\MedidoresController::class, 'store'])->name('medidores.store');
        Route::patch('medidores/{medidor}', [Gestao\MedidoresController::class, 'update'])->name('medidores.update');

        Route::get('auditoria', [Gestao\AuditoriaController::class, 'index'])->name('auditoria');
        Route::post('auditoria/verificar', [Gestao\AuditoriaController::class, 'verificar'])->name('auditoria.verificar');

        Route::get('configuracoes', [Gestao\ConfiguracoesController::class, 'edit'])->name('configuracoes');
        Route::put('configuracoes', [Gestao\ConfiguracoesController::class, 'update'])->name('configuracoes.update');
    });
});

Route::get('.well-known/passkey-endpoints', fn () => response()->json([
    'enroll' => route('conta'),
    'manage' => route('conta'),
]))->name('well-known.passkeys');

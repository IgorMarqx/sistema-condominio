<?php

namespace Database\Seeders;

use App\Enums\CategoriaLancamento;
use App\Enums\Concessionaria;
use App\Models\ContaCaixa;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Models\Notificacao;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Caixa\CaixaService;
use App\Services\Caixa\FechamentoService;
use App\Services\Cobrancas\PagamentoService;
use App\Services\Configuracoes;
use App\Services\Faturas\FaturaService;
use App\Services\Ingestao\DadosFatura;
use App\Services\Ingestao\ProcessadorPdf;
use App\Services\Rateio\RateioService;
use App\Support\Mes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

/**
 * Cenário de demonstração: outubro em andamento (água lançada no painel, luz importada para
 * conferir), setembro fechado, apto 302 com água acima da média. Só para ambiente local.
 *
 * Acessos: apartamentos 101…403 e senha "password"; síndico sindico@condominio.local / "password"
 * com 2FA (o segredo é impresso no fim); conselho conselho@condominio.local / "password".
 */
class DemoSeeder extends Seeder
{
    private Carbon $mes;

    private Carbon $hoje;

    public function run(): void
    {
        $this->hoje = today();
        $this->mes = $this->hoje->copy()->startOfMonth();
        $anterior = $this->mes->copy()->subMonthNoOverflow();

        app(Configuracoes::class)->salvar([
            'nome_edificio' => 'Edifício Exemplo',
            'endereco' => 'Rua Exemplo, 100 · João Pessoa – PB',
            'pix_chave' => 'caixa@condominio.exemplo',
            'pix_recebedor' => 'Condominio Edificio Exemplo',
            'pix_cidade' => 'JOAO PESSOA',
            'banco_titular' => 'Conta no CPF do síndico (registrado em ata de 01/09)',
        ]);

        [$admin, $segredo] = $this->acessos();

        $this->medidores();

        // Histórico do condomínio: cinco meses confirmados e pagos.
        $energia = [5 => [38972, 476], 4 => [40110, 490], 3 => [39540, 483], 2 => [38972, 471], 1 => [42510, 521]];
        $agua = [5 => [21890, 24], 4 => [22340, 25], 3 => [21120, 23], 2 => [23050, 26], 1 => [22551, 25]];
        $faturasAnterior = [];
        $rateios = app(RateioService::class);

        foreach ([5, 4, 3, 2, 1] as $atras) {
            $mes = $this->mes->copy()->subMonthsNoOverflow($atras);
            Carbon::setTestNow($mes->copy()->addDays(11)->setTime(9, 0));
            $e = $this->faturaHistorica($this->medidor('condominio', Concessionaria::Energisa), $mes, $energia[$atras][0], $energia[$atras][1], 11, 22, $admin);
            $a = $this->faturaHistorica($this->medidor('condominio', Concessionaria::Cagepa), $mes, $agua[$atras][0], $agua[$atras][1], 18, 25, $admin);

            // Meses antes do sistema (histórico importado): cada conta cobrada no próprio mês e paga fora do caixa.
            $rateios->ratear($e, $admin)->cobrancas()->update(['status' => 'paga']);
            $rateios->ratear($a, $admin)->cobrancas()->update(['status' => 'paga']);

            if ($atras === 1) {
                $faturasAnterior = [$e, $a];
            } else {
                $e->update(['paga_em' => $mes->copy()->day(20)]);
                $a->update(['paga_em' => $mes->copy()->day(22)]);
            }
        }

        // Saldo inicial do caixa no mês anterior (o mês que fica fechado).
        Carbon::setTestNow($anterior->copy()->addDay()->setTime(10, 0));
        Auth::setUser($admin);
        $caixa = app(CaixaService::class);
        $comprovante = $this->arquivo('saldo-inicial.pdf', 'Extrato bancário de abertura');
        $caixa->lancar(ContaCaixa::corrente(), $anterior->copy(), 'entrada', 190324, CategoriaLancamento::Outros, 'Saldo inicial · conta corrente', $admin, $comprovante);
        $caixa->lancar(ContaCaixa::reserva(), $anterior->copy(), 'entrada', 150000, CategoriaLancamento::Outros, 'Saldo inicial · fundo de reserva', $admin, $this->arquivo('saldo-reserva.pdf', 'Extrato da reserva'));

        // Outubro: a conta da Cagepa lançada no painel (valor e m³), já dividida entre as 12 unidades.
        Carbon::setTestNow($this->mes->copy()->addDays(1)->setTime(19, 30));
        $rateio = $rateios->ratear(app(FaturaService::class)->lancarManual(Concessionaria::Cagepa, $this->mes, 23810, '28', $admin), $admin);

        // Fecha o mês anterior com o banco conferindo.
        Carbon::setTestNow($this->mes->copy()->addDay()->setTime(20, 0));
        app(FechamentoService::class)->fechar($anterior, ['corrente' => 190324, 'reserva' => 150000], null, $admin);

        // Pagamentos: 7 confirmados, 2 aguardando conferência, 3 em aberto.
        $pagamentos = app(PagamentoService::class);
        $pagas = ['101' => 4, '202' => 5, '203' => 6, '301' => 6, '303' => 9, '401' => 10, '403' => 11];
        $conferir = ['102' => 12, '402' => 12];

        foreach ($rateio->cobrancas()->with('unidade')->get() as $cobranca) {
            $numero = $cobranca->unidade->numero;
            $dia = $pagas[$numero] ?? $conferir[$numero] ?? null;

            if ($dia === null) {
                continue;
            }

            $quando = $this->mes->copy()->addDays($dia - 1)->setTime(12, 10);
            Carbon::setTestNow($quando);
            $morador = User::query()->where('login', $numero)->first();
            Auth::setUser($morador);
            $pagamento = $pagamentos->enviarComprovante($cobranca, $this->arquivo('pix-'.$numero.'.pdf', 'Comprovante Pix apto '.$numero), $quando, $morador, $numero === '102' ? 'Carlos' : 'Maria');

            if (isset($pagas[$numero])) {
                Carbon::setTestNow($quando->copy()->addHours(6));
                Auth::setUser($admin);
                $pagamentos->confirmar($pagamento, $admin);
            }
        }

        // Contas de setembro pagas pelo caixa; um lançamento duplicado e o estorno dele.
        Auth::setUser($admin);
        Carbon::setTestNow($this->mes->copy()->addDays(4)->setTime(15, 0));
        $duplicado = $caixa->lancar(ContaCaixa::corrente(), $this->mes->copy()->addDays(4), 'entrada', 10422, CategoriaLancamento::Cobranca, 'Cobrança · apto 101 · '.Mes::nome($this->mes).' (lançado em dobro)', $admin, $this->arquivo('dup.pdf', 'Comprovante repetido'));
        $caixa->estornar($duplicado, 'Lançamento duplicado: o pagamento do apto 101 já tinha entrado.', $admin, $this->mes->copy()->addDays(4));

        Carbon::setTestNow($this->mes->copy()->addDays(7)->setTime(11, 0));
        $faturas = app(FaturaService::class);
        $faturas->pagarCondominio($faturasAnterior[0]->refresh(), $this->mes->copy()->addDays(7), $this->arquivo('energisa-paga.pdf', 'Comprovante Energisa'), $admin);
        $faturas->pagarCondominio($faturasAnterior[1]->refresh(), $this->mes->copy()->addDays(9), $this->arquivo('cagepa-paga.pdf', 'Comprovante Cagepa'), $admin);

        // Conta individual do 302: histórico de luz e água.
        $morador = User::query()->where('login', '302')->first();
        Auth::setUser($morador);
        $luz302 = [5 => [17210, 190], 4 => [18350, 205], 3 => [17790, 198], 2 => [19480, 220], 1 => [20120, 230]];
        $agua302 = [5 => [7810, 13], 4 => [7420, 12], 3 => [7810, 13], 2 => [7420, 12], 1 => [7810, 13]];

        foreach ([5, 4, 3, 2, 1] as $atras) {
            $mes = $this->mes->copy()->subMonthsNoOverflow($atras);
            Carbon::setTestNow($mes->copy()->addDays(13)->setTime(7, 12));
            $f = $this->faturaHistorica($this->medidor('302', Concessionaria::Energisa), $mes, $luz302[$atras][0], $luz302[$atras][1], 12, 20, null);
            $faturas->marcarPaga($f, $mes->copy()->addDays(18));
            $f = $this->faturaHistorica($this->medidor('302', Concessionaria::Cagepa), $mes, $agua302[$atras][0], $agua302[$atras][1], 8, 25, null);
            $faturas->marcarPaga($f, $mes->copy()->addDays(20));
        }

        // Água de outubro do 302: enviada à mão no dia 9, 18 m³ (acima da média), já paga.
        Carbon::setTestNow($this->mes->copy()->addDays(8)->setTime(18, 40));
        $aguaOut = $this->faturaHistorica($this->medidor('302', Concessionaria::Cagepa), $this->mes, 10940, 18, 8, 25, null);
        $faturas->marcarPaga($aguaOut, $this->mes->copy()->addDays(9));

        // Hoje, 07:12: o 302 envia o PDF da conta de luz; 07:40, o síndico importa a da Energisa do
        // condomínio, que fica para conferir (ao confirmar, vira a cobrança de energia de outubro).
        Carbon::setTestNow($this->local(7, 12));
        $this->importarPdf($this->medidor('302', Concessionaria::Energisa), $morador, $this->textoEnergisa('5/7654321-0', $this->mes, 18640, 214, 12, 20, 'APARTAMENTO 302'));
        Carbon::setTestNow($this->local(7, 40));
        $this->importarPdf($this->medidor('condominio', Concessionaria::Energisa), $admin, $this->textoEnergisa('5/1234567-8', $this->mes, 41257, 512, 11, 22, 'CONDOMINIO EDIFICIO EXEMPLO'));

        Carbon::setTestNow();
        Auth::forgetUser();

        // Avisos antigos já lidos.
        Notificacao::query()->where('created_at', '<', $this->hoje->copy()->subDays(3))->update(['lida_em' => now()]);

        $this->command->info('Demo pronta. Síndico: sindico@condominio.local / password · 2FA segredo: '.$segredo);
        $this->command->info('Unidades: 101…403 / password · conselho@condominio.local / password');
    }

    /**
     * Hora de Brasília de hoje, em UTC (como o app grava).
     */
    private function local(int $hora, int $minuto): Carbon
    {
        return Carbon::parse($this->hoje->toDateString().' '.sprintf('%02d:%02d', $hora, $minuto), 'America/Sao_Paulo')->utc();
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function acessos(): array
    {
        $segredo = (new Google2FA)->generateSecretKey();

        $admin = User::query()->updateOrCreate(['login' => 'sindico@condominio.local'], [
            'nome' => 'Igor Marques',
            'email' => 'sindico@condominio.local',
            'tipo' => 'admin',
            'password' => 'password',
            'deve_trocar_senha' => false,
            'ativo' => true,
        ]);
        $admin->forceFill([
            'two_factor_secret' => encrypt($segredo),
            'two_factor_recovery_codes' => encrypt(json_encode(['codigo-recuperacao-1', 'codigo-recuperacao-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        User::query()->updateOrCreate(['login' => 'conselho@condominio.local'], [
            'nome' => 'Ana (conselho)',
            'email' => 'conselho@condominio.local',
            'tipo' => 'conselho',
            'password' => 'password',
            'deve_trocar_senha' => false,
            'ativo' => true,
        ]);

        foreach (Unidade::query()->get() as $unidade) {
            User::query()->updateOrCreate(['login' => $unidade->numero], [
                'nome' => 'Apartamento '.$unidade->numero,
                'tipo' => 'unidade',
                'unidade_id' => $unidade->id,
                'password' => 'password',
                'deve_trocar_senha' => false,
                'ativo' => true,
            ]);
        }

        return [$admin, $segredo];
    }

    private function medidores(): void
    {
        $u302 = Unidade::query()->where('numero', '302')->firstOrFail();

        foreach ([
            ['condominio', null, Concessionaria::Energisa, '5/1234567-8', 'Áreas comuns, bomba e iluminação'],
            ['condominio', null, Concessionaria::Cagepa, '1234567-8', 'Medidor geral do condomínio'],
            ['unidade', $u302->id, Concessionaria::Energisa, '5/7654321-0', null],
            ['unidade', $u302->id, Concessionaria::Cagepa, '7654321-0', null],
        ] as [$dono, $unidade, $concessionaria, $identificador, $descricao]) {
            Medidor::query()->firstOrCreate(
                ['concessionaria' => $concessionaria, 'identificador' => $identificador],
                ['dono_tipo' => $dono, 'unidade_id' => $unidade, 'descricao' => $descricao, 'ativo' => true],
            );
        }
    }

    private function medidor(string $dono, Concessionaria $concessionaria): Medidor
    {
        return Medidor::query()
            ->where('concessionaria', $concessionaria)
            ->when($dono === 'condominio', fn ($q) => $q->where('dono_tipo', 'condominio'))
            ->when($dono !== 'condominio', fn ($q) => $q->whereHas('unidade', fn ($u) => $u->where('numero', $dono)))
            ->firstOrFail();
    }

    private function faturaHistorica(Medidor $medidor, Carbon $mes, int $valor, int $consumo, int $diaLeitura, int $diaVencimento, ?User $confirmar): Fatura
    {
        $dados = new DadosFatura(
            $medidor->concessionaria,
            identificador: $medidor->identificador,
            mesRef: $mes->copy(),
            valorCentavos: $valor,
            vencimento: $mes->copy()->day($diaVencimento),
            emissao: $mes->copy()->day($diaLeitura + 1),
            consumo: (string) $consumo,
            leituraAnterior: $mes->copy()->subMonthNoOverflow()->day($diaLeitura - 1),
            leituraAtual: $mes->copy()->day($diaLeitura - 1),
            proximaLeitura: $mes->copy()->addMonthNoOverflow()->day($diaLeitura - 1),
            linhaDigitavel: '83640000'.str_pad((string) $valor, 7, '0', STR_PAD_LEFT).'-1 00330000000-5 10012345678-2 0'.$mes->format('Ym').'1234-5',
            extrator: 'demo',
            versao: '0',
        );

        $fatura = app(FaturaService::class)->registrar($medidor, $dados, $mes, null, null, 'upload');

        if ($confirmar && $medidor->isCondominio()) {
            Auth::setUser($confirmar);
            $fatura = app(FaturaService::class)->confirmar($fatura, $confirmar);
        } elseif (! $medidor->isCondominio() && $fatura->status->value === 'pendente_revisao') {
            $fatura = app(FaturaService::class)->aceitarIndividual($fatura, User::query()->where('unidade_id', $medidor->unidade_id)->first());
        }

        return $fatura;
    }

    private function textoEnergisa(string $uc, Carbon $mes, int $total, int $kwh, int $diaLeitura, int $diaVencimento, string $titular): string
    {
        $ilum = (int) round($total * 0.065);
        $icms = (int) round($total * 0.12);
        $bandeira = (int) round($total * 0.02);
        $consumo = $total - $ilum - $icms - $bandeira;
        $r = fn (int $c) => number_format($c / 100, 2, ',', '.');

        return implode("\n", [
            'ENERGISA PARAÍBA - DISTRIBUIDORA DE ENERGIA S.A.',
            'CNPJ 09.095.183/0001-40',
            'NOTA FISCAL/CONTA DE ENERGIA ELÉTRICA ELETRÔNICA',
            $titular,
            'Data de emissão: '.$mes->copy()->day($diaLeitura + 1)->format('d/m/Y'),
            'Nº da UC: '.$uc,
            'Referência: '.strtoupper(Mes::curto($mes)).'/'.$mes->year,
            'Vencimento: '.$mes->copy()->day($diaVencimento)->format('d/m/Y'),
            'Total a pagar: R$ '.$r($total),
            'Leitura anterior: '.$mes->copy()->subMonthNoOverflow()->day($diaLeitura - 1)->format('d/m/Y'),
            'Leitura atual: '.$mes->copy()->day($diaLeitura - 1)->format('d/m/Y'),
            'Próxima leitura: '.$mes->copy()->addMonthNoOverflow()->day($diaLeitura - 1)->format('d/m/Y'),
            'Consumo (kWh): '.$kwh,
            'ITENS DA FATURA',
            'Consumo em kWh '.$r($consumo),
            'Adicional Bandeira Amarela '.$r($bandeira),
            'Contrib. Ilum. Pública '.$r($ilum),
            'ICMS '.$r($icms),
            'TOTAL A PAGAR '.$r($total),
            '',
            'Linha digitável: 83640000001-8 86400033000-5 10012345678-2 0'.$mes->format('Ym').'1234-5',
        ]);
    }

    private function importarPdf(Medidor $medidor, User $autor, string $texto): void
    {
        Auth::setUser($autor);
        $pdf = Pdf::loadHTML('<pre style="font-family: DejaVu Sans Mono; font-size: 9px">'.e($texto).'</pre>')->output();
        app(ProcessadorPdf::class)->processar($pdf, 'fatura.pdf', $medidor->unidade, null, $medidor, [], $autor->id);
    }

    private function arquivo(string $nome, string $texto): UploadedFile
    {
        $pdf = Pdf::loadHTML('<h3 style="font-family: DejaVu Sans">'.e($texto).'</h3><p style="font-family: DejaVu Sans">Documento de demonstração gerado em '.now()->format('d/m/Y H:i').'.</p>')->output();
        $caminho = tempnam(sys_get_temp_dir(), 'demo').'.pdf';
        file_put_contents($caminho, $pdf);

        return new UploadedFile($caminho, $nome, 'application/pdf', null, true);
    }
}

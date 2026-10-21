@php
    $r = fn (int $c) => \App\Support\Dinheiro::formatar($c);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Prestação de contas · {{ $mes }}</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #13254f; margin: 0; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #f5d04c; }
    .muted { color: #6b7590; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7590; border-bottom: 1px solid #d9deea; padding: 4px 3px; }
    td { padding: 4px 3px; border-bottom: 1px solid #eef1f6; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .pos { color: #15803d; }
    .neg { color: #b91c1c; }
    .total td { font-weight: bold; border-top: 1px solid #13254f; }
    .caixa { background: #13254f; color: #fff; padding: 12px 14px; border-radius: 6px; }
    .ok { color: #15803d; font-weight: bold; }
    .alerta { color: #b45309; font-weight: bold; }
    .hash { font-family: DejaVu Sans Mono, monospace; font-size: 8px; word-break: break-all; }
    .riscado { text-decoration: line-through; color: #9aa3b8; }
</style>
</head>
<body>
    <div class="caixa">
        <h1>Prestação de contas · {{ $mes }}</h1>
        <div>{{ $edificio }} · {{ $endereco }}</div>
    </div>

    <h2>Resumo</h2>
    <table>
        <tr><td>Saldo no início do mês</td><td class="num">{{ $r($saldoInicial) }}</td></tr>
        <tr><td>Entradas</td><td class="num pos">+ {{ $r($movimento['entradas']) }}</td></tr>
        <tr><td>Saídas</td><td class="num neg">− {{ $r($movimento['saidas']) }}</td></tr>
        <tr class="total"><td>Saldo calculado no fim do mês</td><td class="num">{{ $r($fechamento->saldo_calculado_centavos) }}</td></tr>
    </table>

    <h2>Conciliação bancária</h2>
    <table>
        <tr><th>Conta</th><th class="num">Calculado</th><th class="num">Extrato do banco</th><th class="num">Diferença</th></tr>
        @foreach ($fechamento->contas_json as $conta)
            <tr>
                <td>{{ $conta['nome'] }}</td>
                <td class="num">{{ $r($conta['calculado']) }}</td>
                <td class="num">{{ $r($conta['banco']) }}</td>
                <td class="num">{{ $r($conta['banco'] - $conta['calculado']) }}</td>
            </tr>
        @endforeach
    </table>
    <p class="{{ $fechamento->diferenca_centavos === 0 ? 'ok' : 'alerta' }}">
        {{ $fechamento->diferenca_centavos === 0 ? 'Saldo do banco confere com o calculado.' : 'Diferença de '.$r($fechamento->diferenca_centavos).': '.$fechamento->observacao }}
    </p>

    <h2>Faturas do condomínio</h2>
    @if ($faturas->isEmpty())
        <p class="muted">Nenhuma fatura do condomínio nesta competência.</p>
    @else
        <table>
            <tr><th>Concessionária</th><th>Leitura</th><th>Consumo</th><th>Vencimento</th><th class="num">Valor</th><th>SHA-256 do PDF</th></tr>
            @foreach ($faturas as $f)
                <tr>
                    <td>{{ $f->medidor->concessionaria->rotulo() }} · {{ $f->medidor->identificador }}</td>
                    <td>{{ $f->leitura_anterior?->format('d/m') }} → {{ $f->leitura_atual?->format('d/m') }}</td>
                    <td>{{ $f->consumo !== null ? rtrim(rtrim(number_format((float) $f->consumo, 3, ',', '.'), '0'), ',') : '—' }} {{ $f->unidade_medida }}</td>
                    <td>{{ $f->vencimento?->format('d/m/Y') }}</td>
                    <td class="num">{{ $f->valor_centavos !== null ? $r($f->valor_centavos) : '—' }}</td>
                    <td class="hash">{{ $f->anexo?->sha256 ? substr($f->anexo->sha256, 0, 16).'…' : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Rateio e recebimentos por unidade</h2>
    @if ($rateios->isEmpty())
        <p class="muted">Nenhum rateio confirmado para este mês.</p>
    @else
        <p class="muted">Divisão igual entre as unidades ativas · vencimento {{ $rateios->min('vencimento')->format('d/m/Y') }} · resto de {{ $rateios->sum('resto_centavos') }} centavo(s) distribuído na ordem fixa.</p>
        <table>
            <tr><th>Unidade</th><th>Conta</th><th class="num">Cobrado</th><th class="num">Recebido</th><th>Situação</th></tr>
            @foreach ($cobrancas as $c)
                @php $recebido = (int) $c->pagamentos->sum('valor_centavos'); @endphp
                <tr>
                    <td>{{ $c->unidade->numero }}</td>
                    <td>{{ \App\Http\Apresentacao\CobrancaDados::servico($c) }}</td>
                    <td class="num">{{ $r($c->valor_centavos) }}</td>
                    <td class="num">{{ $r($recebido) }}</td>
                    <td>{{ $c->status->rotulo() }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td></td>
                <td class="num">{{ $r((int) $cobrancas->sum('valor_centavos')) }}</td>
                <td class="num">{{ $r((int) $cobrancas->sum(fn ($c) => $c->pagamentos->sum('valor_centavos'))) }}</td>
                <td></td>
            </tr>
        </table>
    @endif

    <h2>Extrato do mês</h2>
    <table>
        <tr><th>Data</th><th>Conta</th><th>Descrição</th><th>Categoria</th><th class="num">Valor</th></tr>
        @forelse ($lancamentos as $l)
            <tr>
                <td>{{ $l->data->format('d/m') }}</td>
                <td>{{ $l->conta->nome }}</td>
                <td class="{{ $l->estornadoPor ? 'riscado' : '' }}">{{ $l->descricao }}@if ($l->motivo) <span class="muted">— {{ $l->motivo }}</span>@endif</td>
                <td>{{ $l->categoria->rotulo() }}</td>
                <td class="num {{ $l->tipo === 'entrada' ? 'pos' : 'neg' }}">{{ $l->tipo === 'entrada' ? '+' : '−' }} {{ $r($l->valor_centavos) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Sem lançamentos.</td></tr>
        @endforelse
    </table>

    <h2>Integridade</h2>
    <p>Hash do último registro de auditoria no fechamento:</p>
    <p class="hash">{{ $fechamento->hash_auditoria }}</p>
    <p class="muted">Fechado em {{ $fechamento->fechado_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} por {{ $fechamento->fechadoPor?->nome }}. A verificação da cadeia de auditoria no sistema recalcula este hash.</p>
</body>
</html>

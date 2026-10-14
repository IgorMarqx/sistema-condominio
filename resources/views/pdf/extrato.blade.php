<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Extrato do caixa</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #13254f; }
    h1 { font-size: 16px; margin: 0; }
    .muted { color: #6b7590; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7590; border-bottom: 1px solid #d9deea; padding: 4px 3px; }
    td { padding: 4px 3px; border-bottom: 1px solid #eef1f6; }
    .num { text-align: right; white-space: nowrap; }
    .pos { color: #15803d; } .neg { color: #b91c1c; }
    .riscado { text-decoration: line-through; color: #9aa3b8; }
    .topo { background: #13254f; color: #fff; padding: 10px 12px; border-radius: 6px; }
</style>
</head>
<body>
    <div class="topo">
        <h1>Extrato do caixa · {{ $edificio }}</h1>
        <div>{{ $periodo }}</div>
    </div>
    <table>
        <tr><td>Saldo inicial</td><td class="num">{{ $formatar($saldoInicial) }}</td></tr>
        @foreach ($saldos as $slug => $valor)
            <tr><td class="muted">{{ $contas[$slug] ?? $slug }} (fim do período)</td><td class="num muted">{{ $formatar($valor) }}</td></tr>
        @endforeach
        <tr><td><strong>Saldo final</strong></td><td class="num"><strong>{{ $formatar($saldoFinal) }}</strong></td></tr>
    </table>
    <table>
        <tr><th>Nº</th><th>Data</th><th>Conta</th><th>Descrição</th><th>Categoria</th><th class="num">Valor</th></tr>
        @forelse ($lancamentos as $l)
            <tr>
                <td>{{ $l->id }}</td>
                <td>{{ $l->data->format('d/m/Y') }}</td>
                <td>{{ $l->conta->nome }}</td>
                <td class="{{ $l->estornadoPor ? 'riscado' : '' }}">{{ $l->descricao }}@if ($l->estorno_de_id) <span class="muted">(ref. nº {{ $l->estorno_de_id }}: {{ $l->motivo }})</span>@endif</td>
                <td>{{ $l->categoria->rotulo() }}</td>
                <td class="num {{ $l->tipo === 'entrada' ? 'pos' : 'neg' }}">{{ $l->tipo === 'entrada' ? '+' : '−' }} {{ $formatar($l->valor_centavos) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Sem lançamentos no período.</td></tr>
        @endforelse
    </table>
    <p class="muted">Gerado em {{ $gerado }}. Lançamentos não são editados nem apagados: correções aparecem como estorno.</p>
</body>
</html>

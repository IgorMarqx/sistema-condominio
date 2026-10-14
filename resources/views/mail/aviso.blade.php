<x-mail::message>
# {{ $titulo }}

@if ($mensagem)
{{ $mensagem }}
@endif

@if ($link)
<x-mail::button :url="$link">
Abrir no sistema
</x-mail::button>
@endif

{{ app(\App\Services\Configuracoes::class)->get('nome_edificio') }}
</x-mail::message>

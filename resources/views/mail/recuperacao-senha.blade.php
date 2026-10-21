<x-mail::message>
# Recuperar senha

Recebemos um pedido para criar uma nova senha de acesso ao condomínio.

<x-mail::button :url="$link">
Criar nova senha
</x-mail::button>

O link vale por {{ $validade }} minutos e só pode ser usado uma vez.
Se você não fez esse pedido, ignore esta mensagem.

{{ config('app.name') }}
</x-mail::message>

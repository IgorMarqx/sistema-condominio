<?php

namespace App\Services;

use App\Exceptions\RegraNegocio;
use App\Mail\RecuperacaoSenhaMail;
use App\Models\User;
use App\Services\Auditoria\Ator;
use App\Services\Auditoria\Auditoria;
use App\Support\ContatoRecuperacao;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class RecuperacaoSenhaService
{
    public function __construct(private WhatsappService $whatsapp, private Auditoria $auditoria) {}

    /** @return array{email: string|null, whatsapp: string|null} */
    public function canais(string $identificador): array
    {
        $user = $this->usuario($identificador);

        return $user ? ContatoRecuperacao::canais($user) : ['email' => null, 'whatsapp' => null];
    }

    /** @return array{destino: string, validade_minutos: int} */
    public function enviar(string $identificador, string $canal): array
    {
        $user = $this->usuario($identificador);
        $destino = $user ? (ContatoRecuperacao::canais($user)[$canal] ?? null) : null;

        if (! $user || ! $destino || ! in_array($canal, ['email', 'whatsapp'], true)) {
            throw new RegraNegocio('Não há contato disponível para esse canal. Confira os dados ou procure o síndico.');
        }

        $broker = $this->broker();

        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            throw new RegraNegocio('Você acabou de pedir um link. Aguarde um minuto para pedir de novo.');
        }

        $token = $broker->createToken($user);
        $validade = (int) config('auth.passwords.users.expire', 60);
        $link = rtrim((string) config('app.url'), '/').route('password.reset', ['token' => $token, 'login' => $user->login], false);

        try {
            if ($canal === 'email') {
                Mail::to((string) ContatoRecuperacao::email($user))->send(new RecuperacaoSenhaMail($link, $validade));
            } else {
                $this->whatsapp->enviarTexto((string) $user->telefone,
                    'Recebemos um pedido para recuperar sua senha de acesso ao condomínio.'."\n\n"
                    .'Crie uma nova senha: '.$link."\n\n"
                    ."O link vale por {$validade} minutos. Se não foi você, ignore esta mensagem.");
            }
        } catch (Throwable) {
            $broker->deleteToken($user);
            // A resposta do provedor pode conter o link secreto; não registrar o payload.
            $this->auditoria->registrar('acesso.recuperacao_falhou', $user, null, ['canal' => $canal], Ator::sistema());
            throw new RegraNegocio('Não conseguimos enviar por esse canal agora. Tente a outra opção ou procure o síndico.');
        }

        $this->auditoria->registrar('acesso.recuperacao_enviada', $user, null, ['canal' => $canal], Ator::sistema());

        return ['destino' => $destino, 'validade_minutos' => $validade];
    }

    public function redefinir(string $login, string $token, string $password): void
    {
        $dados = compact('login', 'token', 'password');
        DB::transaction(function () use ($dados) {
            // Serializa o consumo do link para impedir duas redefinições com o mesmo token.
            User::query()->where('login', $dados['login'])->lockForUpdate()->first();
            $status = $this->broker()->reset([...$dados, 'ativo' => true], function (User $user, string $password) {
                if ($user->isUnidade() && ! $user->unidade?->ativa) {
                    throw new RegraNegocio('Este acesso está indisponível. Procure o síndico.');
                }

                $user->forceFill([
                    'password' => $password,
                    'deve_trocar_senha' => false,
                    'senha_definida_em' => now(),
                    'remember_token' => Str::random(60),
                ])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->passkeys()->delete();
                $this->auditoria->registrar('acesso.senha_redefinida', $user, null, ['origem' => 'recuperacao'], Ator::sistema());
                event(new PasswordReset($user));
            });

            if ($status !== Password::PASSWORD_RESET) {
                throw new RegraNegocio('Este link é inválido ou expirou. Peça um novo link para recuperar a senha.');
            }
        });
    }

    public function salvarContatos(User $user, ?string $email, ?string $telefone): void
    {
        DB::transaction(function () use ($user, $email, $telefone) {
            $user->update(['email_recuperacao' => $email, 'telefone' => ContatoRecuperacao::celular($telefone)]);
            $this->broker()->deleteToken($user);
            $this->auditoria->registrar('acesso.contatos_recuperacao', $user, null, [
                'email_definido' => $email !== null,
                'whatsapp_definido' => $user->telefone !== null,
            ]);
        });
    }

    private function usuario(string $identificador): ?User
    {
        $identificador = Str::lower(trim($identificador));
        $cpf = preg_replace('/\D/', '', $identificador);

        return User::query()->where('ativo', true)
            ->where(fn ($query) => $query->whereNull('unidade_id')->orWhereHas('unidade', fn ($unidade) => $unidade->where('ativa', true)))
            ->where(function ($query) use ($identificador, $cpf) {
                $query->where('login', $identificador);

                if (strlen($cpf ?? '') === 11) {
                    $query->orWhere('cpf', $cpf);
                }
            })->first();
    }

    private function broker(): PasswordBroker
    {
        $broker = Password::broker();

        if (! $broker instanceof PasswordBroker) {
            throw new \LogicException('Broker de senhas incompatível.');
        }

        return $broker;
    }
}

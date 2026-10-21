import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint, LockKeyhole, Mail } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import TelaEntrada, {
    CabecalhoEntrada,
} from '@/components/condominio/tela-entrada';
import { traduzirErroPasskey } from '@/lib/passkeys';

/**
 * Login nominal do síndico e do conselho: e-mail + senha (o 2FA vem na tela seguinte) ou
 * biometria cadastrada, que já vale como segundo fator e entra direto.
 */
export default function EntrarGestao() {
    const biometria = usePasskeyVerify({
        onSuccess: (resposta) => router.visit(resposta.redirect ?? '/'),
    });
    const form = useForm({
        login: '',
        password: '',
        remember: false as boolean,
    });

    return (
        <>
            <Head title="Acesso da gestão" />
            <TelaEntrada>
                <CabecalhoEntrada
                    titulo="Acesso da gestão"
                    descricao="Síndico e conselho entram com o próprio e-mail. Com senha, o síndico confirma o código do autenticador; com biometria, entra direto."
                    voltar={{ href: '/login', rotulo: 'Apartamentos' }}
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/login', {
                            onFinish: () => form.reset('password'),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-6 pb-8 md:max-w-none md:px-8"
                >
                    <Campo rotulo="E-mail" erro={form.errors.login}>
                        <Entrada
                            type="email"
                            autoComplete="username"
                            placeholder="voce@email.com"
                            value={form.data.login}
                            onChange={(e) =>
                                form.setData('login', e.target.value)
                            }
                            icone={<Mail />}
                        />
                    </Campo>
                    <Campo rotulo="Senha" erro={form.errors.password}>
                        <Entrada
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                        disabled={!form.data.login || !form.data.password}
                    >
                        Entrar
                    </Botao>
                    {biometria.isSupported && (
                        <>
                            <div className="flex items-center gap-3 text-xs font-semibold text-suave">
                                <span className="h-px flex-1 bg-linha" />
                                ou
                                <span className="h-px flex-1 bg-linha" />
                            </div>
                            <Botao
                                type="button"
                                tom="branco"
                                tamanho="lg"
                                largo
                                carregando={biometria.isLoading}
                                onClick={() => void biometria.verify()}
                            >
                                {!biometria.isLoading && <Fingerprint />}
                                {biometria.isLoading
                                    ? 'Entrando…'
                                    : 'Entrar com biometria'}
                            </Botao>
                            {biometria.error && (
                                <p className="text-center text-xs text-red-600">
                                    {traduzirErroPasskey(biometria.error)}
                                </p>
                            )}
                        </>
                    )}
                    <Link
                        href="/recuperar-senha"
                        className="block text-center text-sm font-semibold text-marinho-700"
                    >
                        Esqueci a senha
                    </Link>
                </form>
            </TelaEntrada>
        </>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { LockKeyhole, Mail } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import TelaEntrada, {
    CabecalhoEntrada,
} from '@/components/condominio/tela-entrada';

/**
 * Login nominal do síndico e do conselho (e-mail + senha; o 2FA vem na tela seguinte).
 */
export default function EntrarGestao() {
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
                    descricao="Síndico e conselho entram com o próprio e-mail. O síndico confirma com o código do aplicativo autenticador."
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

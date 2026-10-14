import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, LockKeyhole, Mail } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';

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
            <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
                <header className="topo-marinho area-segura-topo rounded-b-[28px] px-6 pt-6 pb-7 text-white">
                    <Link
                        href="/login"
                        className="mb-5 inline-flex items-center gap-1 text-sm font-semibold text-white/80"
                    >
                        <ChevronLeft className="size-4" /> Apartamentos
                    </Link>
                    <LogoPredio tamanho="md" />
                    <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                        Acesso da gestão
                    </h1>
                    <p className="mt-1 text-sm text-white/75">
                        Síndico e conselho entram com o próprio e-mail. O
                        síndico confirma com o código do aplicativo
                        autenticador.
                    </p>
                </header>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/login', {
                            onFinish: () => form.reset('password'),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-6 pb-8"
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
                </form>
            </div>
        </>
    );
}

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, LockKeyhole } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';

/**
 * Primeiro acesso: troca obrigatória da senha entregue pelo síndico. Depois, troca voluntária.
 */
export default function TrocarSenha({
    primeiro_acesso,
    regras,
}: {
    primeiro_acesso: boolean;
    regras: string;
}) {
    const { auth } = usePage().props;
    const form = useForm({
        senha_atual: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <>
            <Head title="Trocar senha" />
            <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
                <header className="topo-marinho area-segura-topo rounded-b-[28px] px-6 pt-6 pb-7 text-white">
                    {!primeiro_acesso && (
                        <Link
                            href="/conta"
                            className="mb-5 inline-flex items-center gap-1 text-sm font-semibold text-white/80"
                        >
                            <ChevronLeft className="size-4" /> Minha conta
                        </Link>
                    )}
                    <LogoPredio tamanho="md" />
                    <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                        {primeiro_acesso
                            ? 'Crie a senha do apartamento'
                            : 'Trocar senha'}
                    </h1>
                    <p className="mt-1 text-sm text-white/75">
                        {primeiro_acesso
                            ? `A senha entregue pelo síndico vale só para este primeiro acesso${auth.user?.tipo === 'unidade' ? '. Combine a nova com quem mora no ' + auth.user.login : ''}.`
                            : 'Quem usa o mesmo acesso vai precisar da senha nova.'}
                    </p>
                </header>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/trocar-senha', {
                            onFinish: () => form.reset(),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-6 pb-8"
                >
                    <Campo
                        rotulo={
                            primeiro_acesso ? 'Senha recebida' : 'Senha atual'
                        }
                        erro={form.errors.senha_atual}
                    >
                        <Entrada
                            type="password"
                            autoComplete="current-password"
                            value={form.data.senha_atual}
                            onChange={(e) =>
                                form.setData('senha_atual', e.target.value)
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    <Campo
                        rotulo="Nova senha"
                        erro={form.errors.password}
                        ajuda="Ao menos 8 caracteres, com letras e números."
                    >
                        <Entrada
                            type="password"
                            autoComplete="new-password"
                            passwordrules={regras}
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    <Campo
                        rotulo="Repita a nova senha"
                        erro={form.errors.password_confirmation}
                    >
                        <Entrada
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                    >
                        Salvar senha
                    </Botao>
                    {primeiro_acesso && (
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="w-full text-center text-sm font-semibold text-suave"
                        >
                            Sair
                        </button>
                    )}
                </form>
            </div>
        </>
    );
}

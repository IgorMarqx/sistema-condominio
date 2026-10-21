import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import TelaEntrada, {
    CabecalhoEntrada,
} from '@/components/condominio/tela-entrada';

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
            <TelaEntrada>
                <CabecalhoEntrada
                    titulo={
                        primeiro_acesso
                            ? 'Crie a senha do apartamento'
                            : 'Trocar senha'
                    }
                    descricao={
                        primeiro_acesso
                            ? `A senha entregue pelo síndico vale só para este primeiro acesso${auth.user?.tipo === 'unidade' ? '. Combine a nova com quem mora no ' + auth.user.login : ''}.`
                            : 'Quem usa o mesmo acesso vai precisar da senha nova.'
                    }
                    voltar={
                        primeiro_acesso
                            ? undefined
                            : { href: '/conta', rotulo: 'Minha conta' }
                    }
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/trocar-senha', {
                            onFinish: () => form.reset(),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-6 pb-8 md:max-w-none md:px-8"
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
            </TelaEntrada>
        </>
    );
}

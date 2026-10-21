import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import TelaEntrada, {
    CabecalhoEntrada,
} from '@/components/condominio/tela-entrada';

export default function RedefinirSenha({
    token,
    login,
    regras,
}: {
    token: string;
    login: string;
    regras: string;
}) {
    const form = useForm({
        token,
        login,
        password: '',
        password_confirmation: '',
    });
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Criar nova senha" />
            <TelaEntrada>
                <CabecalhoEntrada
                    titulo="Criar nova senha"
                    descricao="Escolha uma senha com ao menos 8 caracteres, letras e números."
                />
                <form
                    className="space-y-5 px-6 pt-6 pb-8 md:px-8"
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        form.post('/redefinir-senha', {
                            onFinish: () =>
                                form.reset('password', 'password_confirmation'),
                        });
                    }}
                >
                    {flash.erro && (
                        <div
                            role="alert"
                            className="space-y-2 rounded-xl bg-red-50 p-4 text-sm text-red-700"
                        >
                            <p>{flash.erro}</p>
                            <Link
                                href="/recuperar-senha"
                                className="font-bold underline"
                            >
                                Pedir um novo link
                            </Link>
                        </div>
                    )}
                    <Campo
                        rotulo="Nova senha"
                        htmlFor="password"
                        erro={form.errors.password}
                    >
                        <Entrada
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            passwordrules={regras}
                            required
                            value={form.data.password}
                            onChange={(evento) =>
                                form.setData('password', evento.target.value)
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    <Campo
                        rotulo="Repita a nova senha"
                        htmlFor="password_confirmation"
                        erro={form.errors.password_confirmation}
                    >
                        <Entrada
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(evento) =>
                                form.setData(
                                    'password_confirmation',
                                    evento.target.value,
                                )
                            }
                            icone={<LockKeyhole />}
                        />
                    </Campo>
                    {(form.errors.token || form.errors.login) && (
                        <p role="alert" className="text-sm text-red-600">
                            {form.errors.token ?? form.errors.login}{' '}
                            <Link href="/recuperar-senha" className="underline">
                                Pedir um novo link
                            </Link>
                        </p>
                    )}
                    <p className="text-xs leading-5 text-suave">
                        A nova senha encerra as sessões anteriores e desativa a
                        biometria cadastrada. A verificação em duas etapas
                        continua ativa.
                    </p>
                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                    >
                        Salvar nova senha
                    </Botao>
                    <Link
                        href="/login"
                        className="block text-center text-sm font-semibold text-marinho-700"
                    >
                        Voltar ao login
                    </Link>
                </form>
            </TelaEntrada>
        </>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';

export default function ConfirmarSenha() {
    const form = useForm({ password: '' });

    return (
        <>
            <Head title="Confirmar senha" />
            <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
                <header className="topo-marinho area-segura-topo rounded-b-[28px] px-6 pt-8 pb-7 text-white">
                    <LogoPredio tamanho="md" />
                    <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                        Confirme a senha
                    </h1>
                    <p className="mt-1 text-sm text-white/75">
                        Esta é uma área protegida. Confirme a senha para
                        continuar.
                    </p>
                </header>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/user/confirm-password', {
                            onFinish: () => form.reset(),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-8"
                >
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
                        disabled={!form.data.password}
                    >
                        Confirmar
                    </Botao>
                </form>
            </div>
        </>
    );
}

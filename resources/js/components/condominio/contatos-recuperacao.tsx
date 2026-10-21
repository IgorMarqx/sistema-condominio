import { useForm } from '@inertiajs/react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Cartao from '@/components/condominio/cartao';

export type ContatosRecuperacaoProps = {
    email_recuperacao: string | null;
    telefone: string | null;
};

export default function ContatosRecuperacao({
    contatos,
}: {
    contatos: ContatosRecuperacaoProps;
}) {
    const form = useForm({
        email_recuperacao: contatos.email_recuperacao ?? '',
        telefone: contatos.telefone ?? '',
        senha_atual: '',
    });
    return (
        <Cartao>
            <form
                className="space-y-4"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    form.put('/conta/recuperacao', {
                        preserveScroll: true,
                        onFinish: () => form.reset('senha_atual'),
                    });
                }}
            >
                <p className="text-sm leading-6 text-suave">
                    Escolha onde receber o link caso esqueça a senha. O celular
                    deve ter WhatsApp. No acesso compartilhado, esses contatos
                    podem recuperar a senha do apartamento.
                </p>
                <Campo
                    rotulo="E-mail de recuperação"
                    htmlFor="email_recuperacao"
                    erro={form.errors.email_recuperacao}
                >
                    <Entrada
                        id="email_recuperacao"
                        type="email"
                        autoComplete="email"
                        maxLength={120}
                        value={form.data.email_recuperacao}
                        onChange={(evento) =>
                            form.setData(
                                'email_recuperacao',
                                evento.target.value,
                            )
                        }
                    />
                </Campo>
                <Campo
                    rotulo="WhatsApp com DDD"
                    htmlFor="telefone"
                    erro={form.errors.telefone}
                >
                    <Entrada
                        id="telefone"
                        type="tel"
                        autoComplete="tel"
                        maxLength={30}
                        placeholder="(83) 99999-9999"
                        value={form.data.telefone}
                        onChange={(evento) =>
                            form.setData('telefone', evento.target.value)
                        }
                    />
                </Campo>
                <Campo
                    rotulo="Confirme sua senha atual"
                    htmlFor="senha_contatos"
                    erro={form.errors.senha_atual}
                >
                    <Entrada
                        id="senha_contatos"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={form.data.senha_atual}
                        onChange={(evento) =>
                            form.setData('senha_atual', evento.target.value)
                        }
                    />
                </Campo>
                <Botao type="submit" carregando={form.processing}>
                    Salvar contatos
                </Botao>
            </form>
        </Cartao>
    );
}

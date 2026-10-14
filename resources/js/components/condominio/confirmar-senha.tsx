import { LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import { postJson } from '@/lib/http';
import Botao from './botao';
import { Campo, Entrada } from './campo';
import Folha from './folha';

/**
 * Antes de cadastrar biometria ou mexer no 2FA, o Fortify exige a senha confirmada há pouco.
 * `exigir(acao)` pergunta a senha só se precisar e depois executa a ação.
 */
export function useSenhaConfirmada() {
    const [pendente, setPendente] = useState<null | (() => void)>(null);

    const exigir = async (acao: () => void) => {
        const resposta = await fetch('/user/confirmed-password-status', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((r) => r.json() as Promise<{ confirmed: boolean }>)
            .catch(() => ({ confirmed: false }));

        if (resposta.confirmed) {
            acao();

            return;
        }

        setPendente(() => acao);
    };

    const folha = (
        <FolhaSenha
            aberta={pendente !== null}
            aoFechar={() => setPendente(null)}
            aoConfirmar={() => {
                const acao = pendente;
                setPendente(null);
                acao?.();
            }}
        />
    );

    return { exigir, folha };
}

function FolhaSenha({
    aberta,
    aoFechar,
    aoConfirmar,
}: {
    aberta: boolean;
    aoFechar: () => void;
    aoConfirmar: () => void;
}) {
    const [senha, setSenha] = useState('');
    const [erro, setErro] = useState<string | null>(null);
    const [enviando, setEnviando] = useState(false);

    const confirmar = async () => {
        setEnviando(true);
        setErro(null);

        try {
            await postJson('/user/confirm-password', { password: senha });
            setSenha('');
            aoConfirmar();
        } catch (e) {
            setErro(e instanceof Error ? e.message : 'Senha incorreta.');
        } finally {
            setEnviando(false);
        }
    };

    return (
        <Folha
            aberta={aberta}
            aoMudar={(v) => !v && aoFechar()}
            titulo="Confirme a senha"
            descricao="Por segurança, confirme a senha antes de continuar."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={enviando}
                    disabled={!senha}
                    onClick={() => void confirmar()}
                >
                    Confirmar
                </Botao>
            }
        >
            <form
                className="pb-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    void confirmar();
                }}
            >
                <Campo rotulo="Senha" erro={erro ?? undefined}>
                    <Entrada
                        type="password"
                        autoComplete="current-password"
                        value={senha}
                        onChange={(e) => setSenha(e.target.value)}
                        icone={<LockKeyhole />}
                    />
                </Campo>
            </form>
        </Folha>
    );
}

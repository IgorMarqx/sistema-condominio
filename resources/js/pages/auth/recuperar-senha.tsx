import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, MessageCircle, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';
import TelaEntrada from '@/components/condominio/tela-entrada';
import { postJson } from '@/lib/http';

type Canal = 'email' | 'whatsapp';
type Canais = Record<Canal, string | null>;
type Enviado = { destino: string; validade_minutos: number; canal: Canal };

export default function RecuperarSenha() {
    const { edificio } = usePage().props;
    const [identificador, setIdentificador] = useState('');
    const [canais, setCanais] = useState<Canais | null>(null);
    const [enviado, setEnviado] = useState<Enviado | null>(null);
    const [processando, setProcessando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);
    const [espera, setEspera] = useState(0);

    useEffect(() => {
        if (espera <= 0) return;
        const timer = window.setTimeout(
            () => setEspera((valor) => valor - 1),
            1000,
        );
        return () => window.clearTimeout(timer);
    }, [espera]);

    async function consultar(evento: FormEvent) {
        evento.preventDefault();
        setProcessando(true);
        setErro(null);
        try {
            const resposta = await postJson<{ canais: Canais }>(
                '/recuperar-senha/canais',
                { identificador },
            );
            setCanais(resposta.canais);
        } catch (falha) {
            setErro(
                falha instanceof Error
                    ? falha.message
                    : 'Não foi possível consultar. Tente de novo.',
            );
        } finally {
            setProcessando(false);
        }
    }

    async function enviar(canal: Canal) {
        setProcessando(true);
        setErro(null);
        try {
            const resposta = await postJson<Omit<Enviado, 'canal'>>(
                '/recuperar-senha/enviar',
                { identificador, canal },
            );
            setEnviado({ ...resposta, canal });
            setEspera(60);
        } catch (falha) {
            setErro(
                falha instanceof Error
                    ? falha.message
                    : 'Não foi possível enviar. Tente de novo.',
            );
        } finally {
            setProcessando(false);
        }
    }

    const semCanal = canais !== null && !canais.email && !canais.whatsapp;

    return (
        <>
            <Head title="Recuperar senha" />
            <TelaEntrada>
                <header className="flex flex-col items-center px-6 pt-10 text-center md:px-8">
                    <LogoPredio tamanho="lg" />
                    <p className="mt-4 text-[11px] font-bold tracking-[0.2em] text-marinho-900 uppercase">
                        {edificio}
                    </p>
                    <h1 className="mt-7 text-2xl font-extrabold text-texto">
                        {enviado ? 'Link enviado' : 'Recuperar senha'}
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-suave">
                        {enviado
                            ? `Abra o link enviado para ${enviado.destino}. Ele vale por ${enviado.validade_minutos} minutos.`
                            : canais
                              ? 'Como você quer receber o link?'
                              : 'Informe seu CPF, e-mail de acesso ou apartamento para criar uma nova senha.'}
                    </p>
                </header>
                <div className="space-y-4 px-6 pt-6 pb-9 md:px-8">
                    {!canais && (
                        <form
                            onSubmit={(evento) => void consultar(evento)}
                            className="space-y-5"
                        >
                            <Campo
                                rotulo="CPF, e-mail ou apartamento"
                                htmlFor="identificador"
                            >
                                <Entrada
                                    id="identificador"
                                    name="identificador"
                                    autoComplete="username"
                                    maxLength={120}
                                    required
                                    value={identificador}
                                    onChange={(evento) =>
                                        setIdentificador(evento.target.value)
                                    }
                                    icone={<Search />}
                                    placeholder="Informe seu acesso"
                                />
                            </Campo>
                            <Botao
                                type="submit"
                                largo
                                tamanho="lg"
                                carregando={processando}
                                disabled={!identificador.trim()}
                            >
                                Continuar
                            </Botao>
                        </form>
                    )}
                    {canais && !enviado && !semCanal && (
                        <div className="space-y-3">
                            {(['email', 'whatsapp'] as const)
                                .filter((canal) => canais[canal])
                                .map((canal) => {
                                    const Icone =
                                        canal === 'email'
                                            ? Mail
                                            : MessageCircle;
                                    return (
                                        <button
                                            key={canal}
                                            type="button"
                                            disabled={processando}
                                            onClick={() => void enviar(canal)}
                                            className="flex min-h-20 w-full items-center gap-3 rounded-xl border border-linha bg-white p-4 text-left shadow-sm transition hover:bg-marinho-50 focus-visible:ring-4 focus-visible:ring-marinho-600/25 disabled:opacity-50"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-marinho-900 text-ouro-500">
                                                <Icone className="size-5" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block text-base font-bold text-texto">
                                                    Enviar por{' '}
                                                    {canal === 'email'
                                                        ? 'e-mail'
                                                        : 'WhatsApp'}
                                                </span>
                                                <span className="block truncate text-sm text-suave">
                                                    {canais[canal]}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            {processando && (
                                <p
                                    role="status"
                                    className="text-center text-sm text-suave"
                                >
                                    Enviando o link…
                                </p>
                            )}
                        </div>
                    )}
                    {semCanal && (
                        <p className="rounded-xl bg-marinho-50 p-4 text-sm leading-6 text-marinho-900">
                            Não encontramos um contato disponível para esses
                            dados. Confira o acesso informado ou procure o
                            síndico para recuperar a senha.
                        </p>
                    )}
                    {enviado && (
                        <div className="space-y-3">
                            <p className="text-sm text-suave">
                                Se não encontrar o e-mail, confira também o
                                spam.
                            </p>
                            <Botao
                                tom="branco"
                                largo
                                carregando={processando}
                                disabled={espera > 0}
                                onClick={() => void enviar(enviado.canal)}
                            >
                                {espera > 0
                                    ? `Enviar de novo em ${espera}s`
                                    : 'Não recebi, enviar de novo'}
                            </Botao>
                            <button
                                type="button"
                                disabled={processando}
                                onClick={() => {
                                    setEnviado(null);
                                    setErro(null);
                                }}
                                className="w-full text-sm font-semibold text-marinho-700"
                            >
                                Escolher outra forma de envio
                            </button>
                        </div>
                    )}
                    {erro && (
                        <p
                            role="alert"
                            className="text-center text-sm text-red-600"
                        >
                            {erro}
                        </p>
                    )}
                    {canais && !enviado && (
                        <button
                            type="button"
                            disabled={processando}
                            onClick={() => {
                                setCanais(null);
                                setErro(null);
                                setIdentificador('');
                            }}
                            className="w-full py-1 text-sm font-semibold text-marinho-700"
                        >
                            Informar outros dados
                        </button>
                    )}
                    <p className="pt-5 text-center text-sm text-suave">
                        Lembrou a senha?{' '}
                        <Link
                            href="/login"
                            className="font-semibold text-marinho-700 underline underline-offset-4"
                        >
                            Voltar ao login
                        </Link>
                    </p>
                </div>
            </TelaEntrada>
        </>
    );
}

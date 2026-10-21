import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint, LockKeyhole, ShieldCheck, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';
import TelaEntrada, {
    CabecalhoEntrada,
} from '@/components/condominio/tela-entrada';
import { Checkbox } from '@/components/ui/checkbox';
import { traduzirErroPasskey } from '@/lib/passkeys';
import { cn } from '@/lib/utils';

type Props = {
    edificio: string;
    apartamentos: string[][];
    lembrado: { apto: string; andar: number; nome: string | null } | null;
    entradaPorBiometria: boolean;
    biometriaGestao: { nome: string; papel: string } | null;
    status?: string;
};

type Tela = 'biometria' | 'biometria-gestao' | 'bemvindo' | 'grade';

export default function Entrar({
    edificio,
    apartamentos,
    lembrado,
    entradaPorBiometria,
    biometriaGestao,
}: Props) {
    const [tela, setTela] = useState<Tela>(
        biometriaGestao
            ? 'biometria-gestao'
            : entradaPorBiometria && lembrado
              ? 'biometria'
              : lembrado
                ? 'bemvindo'
                : 'grade',
    );

    const trocarApartamento = () => {
        router.post(
            '/aparelho/esquecer',
            {},
            { onSuccess: () => setTela('grade') },
        );
    };

    return (
        <>
            <Head title="Entrar" />
            {tela === 'biometria-gestao' && biometriaGestao && (
                <TelaBiometria
                    edificio={edificio}
                    titulo={`Olá, ${biometriaGestao.nome}`}
                    apoio={`${biometriaGestao.papel} · sem código do autenticador`}
                    // Com senha, o síndico confirma o 2FA na tela seguinte.
                    aoUsarSenha={() => router.visit('/entrar/gestao')}
                    trocar={{ pergunta: 'Não é você?', acao: 'Trocar conta' }}
                    aoTrocar={() =>
                        router.post(
                            '/aparelho/esquecer',
                            { gestao: true },
                            {
                                onSuccess: () =>
                                    setTela(lembrado ? 'bemvindo' : 'grade'),
                            },
                        )
                    }
                />
            )}
            {tela === 'biometria' && lembrado && (
                <TelaBiometria
                    edificio={edificio}
                    titulo={`Apartamento ${lembrado.apto}`}
                    aoUsarSenha={() => setTela('bemvindo')}
                    trocar={{
                        pergunta: `Não é o ${lembrado.apto}?`,
                        acao: 'Trocar apartamento',
                    }}
                    aoTrocar={trocarApartamento}
                />
            )}
            {tela === 'bemvindo' && lembrado && (
                <TelaBemVindo
                    edificio={edificio}
                    lembrado={lembrado}
                    aoTrocar={trocarApartamento}
                />
            )}
            {tela === 'grade' && <TelaGrade apartamentos={apartamentos} />}
        </>
    );
}

function usarBiometria(aoFalhar?: () => void) {
    return usePasskeyVerify({
        onSuccess: (resposta) => router.visit(resposta.redirect ?? '/'),
        onError: () => aoFalhar?.(),
    });
}

/**
 * Mockup 1: só o prédio, quem entra e o botão de biometria. O prompt abre sozinho uma vez.
 * Serve ao apartamento e à gestão (pela biometria a gestão entra sem o código do 2FA).
 */
function TelaBiometria({
    edificio,
    titulo,
    apoio,
    aoUsarSenha,
    trocar,
    aoTrocar,
}: {
    edificio: string;
    titulo: string;
    apoio?: string;
    aoUsarSenha: () => void;
    trocar: { pergunta: string; acao: string };
    aoTrocar: () => void;
}) {
    const { verify, isLoading, isSupported, error } = usarBiometria();
    const disparado = useRef(false);

    // Abre o prompt sozinho, uma vez: na montagem ou, se o app abriu em segundo plano (PWA
    // acordando), assim que a tela ficar visível. O botão fica para tentar de novo.
    useEffect(() => {
        if (!isSupported) {
            return;
        }

        const disparar = () => {
            if (!disparado.current && document.visibilityState === 'visible') {
                disparado.current = true;
                void verify();
            }
        };

        disparar();
        document.addEventListener('visibilitychange', disparar);

        return () => document.removeEventListener('visibilitychange', disparar);
    }, [isSupported, verify]);

    return (
        <div className="topo-marinho arcos fixed inset-0 flex flex-col overflow-y-auto text-white">
            <div className="area-segura-topo flex flex-1 flex-col items-center justify-center px-8 text-center">
                <LogoPredio tamanho="xl" halo className="mb-7" />
                <p className="text-[11px] font-bold tracking-[0.18em] text-ouro-300 uppercase">
                    {edificio}
                </p>
                <h1 className="mt-1 text-3xl font-extrabold tracking-tight">
                    {titulo}
                </h1>
                {apoio && (
                    <p className="mt-1.5 text-sm text-white/70">{apoio}</p>
                )}

                <Botao
                    tom="vidro"
                    tamanho="lg"
                    largo
                    className="mt-10 max-w-xs"
                    carregando={isLoading}
                    onClick={() => void verify()}
                >
                    {!isLoading && <Fingerprint />}
                    {isLoading ? 'Entrando…' : 'Entrar com biometria'}
                </Botao>
                {error && (
                    <p className="mt-3 max-w-xs text-xs text-red-200">
                        {traduzirErroPasskey(error)}
                    </p>
                )}
                {!isSupported && (
                    <p className="mt-3 max-w-xs text-xs text-white/70">
                        Este navegador não aceita biometria. Entre com a senha.
                    </p>
                )}
            </div>
            <div className="area-segura-baixo flex flex-col items-center gap-3 px-8 pb-6">
                <button
                    type="button"
                    onClick={aoUsarSenha}
                    disabled={isLoading}
                    className="text-[15px] font-bold disabled:opacity-50"
                >
                    Entrar com senha
                </button>
                <button
                    type="button"
                    onClick={aoTrocar}
                    className="text-xs text-white/70"
                >
                    {trocar.pergunta}{' '}
                    <span className="font-semibold text-white underline-offset-2 hover:underline">
                        {trocar.acao}
                    </span>
                </button>
            </div>
        </div>
    );
}

/**
 * Mockup 2: aparelho lembrado; confirma a senha ou usa a biometria.
 */
function TelaBemVindo({
    edificio,
    lembrado,
    aoTrocar,
}: {
    edificio: string;
    lembrado: NonNullable<Props['lembrado']>;
    aoTrocar: () => void;
}) {
    const form = useForm({
        login: lembrado.apto,
        password: '',
        remember: true as boolean,
        lembrar: true as boolean,
        nome: lembrado.nome ?? '',
    });
    const [mostrar, setMostrar] = useState(false);
    const { verify, isLoading, isSupported, error } = usarBiometria();

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((dados) => ({ ...dados, remember: dados.lembrar }));
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <TelaEntrada>
            <div className="area-segura-topo flex flex-1 flex-col px-6 pt-10 md:px-8 md:pb-8">
                <div className="flex flex-col items-center text-center">
                    <div className="rounded-full bg-marinho-50 p-3">
                        <LogoPredio tamanho="lg" />
                    </div>
                    <h1 className="mt-5 text-[28px] leading-tight font-extrabold tracking-tight text-texto">
                        Bem-vindo de volta
                    </h1>
                    <p className="mt-1 text-sm text-suave">
                        Confirme a senha do apartamento ou use a biometria.
                    </p>
                </div>

                <form
                    onSubmit={enviar}
                    className="mx-auto mt-7 w-full max-w-sm space-y-4 md:max-w-none"
                >
                    <div className="flex items-center gap-3 rounded-2xl border border-linha bg-[#f7f8fb] p-3">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-marinho-900 text-sm font-extrabold text-ouro-500">
                            {lembrado.apto}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-extrabold text-texto">
                                Apartamento {lembrado.apto}
                            </p>
                            <p className="truncate text-xs text-suave">
                                {edificio} · {lembrado.andar}º andar
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={aoTrocar}
                            className="flex items-center gap-1 text-xs font-bold text-marinho-700"
                        >
                            Trocar <X className="size-3.5" />
                        </button>
                    </div>

                    <Campo
                        rotulo="Senha"
                        erro={form.errors.login ?? form.errors.password}
                        htmlFor="senha"
                    >
                        <Entrada
                            id="senha"
                            type={mostrar ? 'text' : 'password'}
                            autoComplete="current-password"
                            placeholder="Digite a senha do apartamento"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            icone={<LockKeyhole />}
                            aria-invalid={!!form.errors.login}
                            sufixo={
                                <button
                                    type="button"
                                    onClick={() => setMostrar((m) => !m)}
                                    className="text-xs font-bold text-marinho-700"
                                >
                                    {mostrar ? 'Ocultar' : 'Mostrar'}
                                </button>
                            }
                        />
                    </Campo>

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm font-semibold text-texto">
                            <Checkbox
                                checked={form.data.lembrar}
                                onCheckedChange={(v) =>
                                    form.setData('lembrar', v === true)
                                }
                                className="size-5 rounded-md border-marinho-900 data-[state=checked]:bg-marinho-900"
                            />
                            Lembrar deste aparelho
                        </label>
                        <Link
                            href="/recuperar-senha"
                            className="text-sm font-semibold text-marinho-700"
                        >
                            Esqueci a senha
                        </Link>
                    </div>

                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                        disabled={!form.data.password}
                    >
                        Entrar
                    </Botao>

                    {isSupported && (
                        <>
                            <Botao
                                type="button"
                                tom="branco"
                                tamanho="lg"
                                largo
                                carregando={isLoading}
                                onClick={() => void verify()}
                            >
                                {!isLoading && <Fingerprint />}
                                Entrar com biometria
                            </Botao>
                            {error && (
                                <p className="text-center text-xs text-red-600">
                                    {traduzirErroPasskey(error)}
                                </p>
                            )}
                        </>
                    )}

                    <p className="hidden items-center justify-center gap-1.5 pt-1 text-xs text-suave md:flex">
                        <ShieldCheck className="size-3.5" />
                        Recupere sua senha por e-mail ou WhatsApp.
                    </p>
                </form>
            </div>

            <div className="area-segura-baixo arcos relative mt-8 bg-gradient-to-b from-white via-[#c7d0e4] to-marinho-800 px-6 pt-14 pb-5 md:hidden">
                <p className="flex items-center justify-center gap-2 text-xs font-semibold text-white">
                    <ShieldCheck className="size-4" />
                    Recupere sua senha por e-mail ou WhatsApp.
                </p>
            </div>
        </TelaEntrada>
    );
}

/**
 * Mockup 3: primeira vez neste aparelho. Escolhe o apartamento, a senha e (opcional) o nome.
 */
function TelaGrade({ apartamentos }: { apartamentos: string[][] }) {
    const form = useForm({
        login: '',
        password: '',
        nome: '',
        remember: true as boolean,
        lembrar: true as boolean,
    });

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <TelaEntrada>
            <CabecalhoEntrada
                titulo="Qual é o seu apartamento?"
                descricao="O acesso é por unidade. Use a senha que o síndico entregou."
            />

            <form
                onSubmit={enviar}
                className="mx-auto w-full max-w-sm flex-1 space-y-5 px-6 pt-6 pb-6 md:max-w-none md:px-8 md:pb-8"
            >
                <Campo rotulo="Apartamento" erro={form.errors.login}>
                    <div
                        className="grid grid-cols-3 gap-2.5"
                        role="radiogroup"
                        aria-label="Apartamento"
                    >
                        {apartamentos.flat().map((numero) => {
                            const escolhido = form.data.login === numero;

                            return (
                                <button
                                    key={numero}
                                    type="button"
                                    role="radio"
                                    aria-checked={escolhido}
                                    onClick={() =>
                                        form.setData('login', numero)
                                    }
                                    className={cn(
                                        'h-12 rounded-xl border text-[15px] font-bold transition active:scale-95',
                                        escolhido
                                            ? 'border-marinho-900 bg-marinho-900 text-ouro-500 shadow-md'
                                            : 'border-linha bg-[#f7f8fb] text-texto hover:border-marinho-100',
                                    )}
                                >
                                    {numero}
                                </button>
                            );
                        })}
                    </div>
                </Campo>

                <Campo rotulo="Senha" erro={form.errors.password}>
                    <Entrada
                        type="password"
                        autoComplete="current-password"
                        placeholder="Senha do apartamento"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        icone={<LockKeyhole />}
                    />
                </Campo>

                <Campo
                    rotulo="Quem está acessando?"
                    detalhe="(opcional)"
                    ajuda="Esse nome aparece no histórico de ações do apartamento."
                >
                    <Entrada
                        placeholder="Ex.: Maria"
                        maxLength={60}
                        autoComplete="given-name"
                        value={form.data.nome}
                        onChange={(e) => form.setData('nome', e.target.value)}
                    />
                </Campo>

                <Botao
                    type="submit"
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={!form.data.login || !form.data.password}
                >
                    {form.data.login
                        ? `Entrar no ${form.data.login}`
                        : 'Escolha o apartamento'}
                </Botao>

                <div className="flex items-center justify-between pt-1 text-sm">
                    <Link
                        href="/recuperar-senha"
                        className="font-semibold text-marinho-700"
                    >
                        Esqueci a senha
                    </Link>
                    <Link
                        href="/entrar/gestao"
                        className="font-semibold text-suave"
                    >
                        Acesso do síndico
                    </Link>
                </div>
            </form>
        </TelaEntrada>
    );
}

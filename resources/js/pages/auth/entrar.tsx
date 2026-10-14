import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint, LockKeyhole, ShieldCheck, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import LogoPredio from '@/components/condominio/logo-predio';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type Props = {
    edificio: string;
    apartamentos: string[][];
    lembrado: { apto: string; andar: number; nome: string | null } | null;
    entradaPorBiometria: boolean;
    status?: string;
};

type Tela = 'biometria' | 'bemvindo' | 'grade';

export default function Entrar({
    edificio,
    apartamentos,
    lembrado,
    entradaPorBiometria,
}: Props) {
    const [tela, setTela] = useState<Tela>(
        entradaPorBiometria && lembrado
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
            {tela === 'biometria' && lembrado && (
                <TelaBiometria
                    edificio={edificio}
                    apto={lembrado.apto}
                    aoUsarSenha={() => setTela('bemvindo')}
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
 * Mockup 1: só o prédio, o apartamento e o botão de biometria. O prompt abre sozinho uma vez.
 */
function TelaBiometria({
    edificio,
    apto,
    aoUsarSenha,
    aoTrocar,
}: {
    edificio: string;
    apto: string;
    aoUsarSenha: () => void;
    aoTrocar: () => void;
}) {
    const { verify, isLoading, isSupported, error } = usarBiometria();
    const disparado = useRef(false);

    useEffect(() => {
        if (
            isSupported &&
            !disparado.current &&
            document.visibilityState === 'visible'
        ) {
            disparado.current = true;
            void verify();
        }
    }, [isSupported, verify]);

    return (
        <div className="topo-marinho arcos fixed inset-0 flex flex-col overflow-y-auto text-white">
            <div className="area-segura-topo flex flex-1 flex-col items-center justify-center px-8 text-center">
                <LogoPredio tamanho="xl" halo className="mb-7" />
                <p className="text-[11px] font-bold tracking-[0.18em] text-ouro-300 uppercase">
                    {edificio}
                </p>
                <h1 className="mt-1 text-3xl font-extrabold tracking-tight">
                    Apartamento {apto}
                </h1>

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
                    Não é o {apto}?{' '}
                    <span className="font-semibold text-white underline-offset-2 hover:underline">
                        Trocar apartamento
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
    const [esqueci, setEsqueci] = useState(false);
    const { verify, isLoading, isSupported, error } = usarBiometria();

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((dados) => ({ ...dados, remember: dados.lembrar }));
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
            <div className="area-segura-topo flex flex-1 flex-col px-6 pt-10">
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
                    className="mx-auto mt-7 w-full max-w-sm space-y-4"
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
                        <button
                            type="button"
                            onClick={() => setEsqueci(true)}
                            className="text-sm font-semibold text-marinho-700"
                        >
                            Esqueci a senha
                        </button>
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
                </form>
            </div>

            <div className="area-segura-baixo arcos relative mt-8 bg-gradient-to-b from-white via-[#c7d0e4] to-marinho-800 px-6 pt-14 pb-5">
                <p className="flex items-center justify-center gap-2 text-xs font-semibold text-white">
                    <ShieldCheck className="size-4" />
                    Senha esquecida? Peça uma nova ao síndico.
                </p>
            </div>

            <AjudaSenha aberta={esqueci} aoMudar={setEsqueci} />
        </div>
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
    const [esqueci, setEsqueci] = useState(false);

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
            <header className="topo-marinho area-segura-topo rounded-b-[28px] px-6 pt-8 pb-7 text-white">
                <LogoPredio tamanho="md" />
                <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                    Qual é o seu apartamento?
                </h1>
                <p className="mt-1 text-sm text-white/75">
                    O acesso é por unidade. Use a senha que o síndico entregou.
                </p>
            </header>

            <form
                onSubmit={enviar}
                className="mx-auto w-full max-w-sm flex-1 space-y-5 px-6 pt-6 pb-6"
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
                    <button
                        type="button"
                        onClick={() => setEsqueci(true)}
                        className="font-semibold text-marinho-700"
                    >
                        Esqueci a senha
                    </button>
                    <Link
                        href="/entrar/gestao"
                        className="font-semibold text-suave"
                    >
                        Acesso do síndico
                    </Link>
                </div>
            </form>

            <AjudaSenha aberta={esqueci} aoMudar={setEsqueci} />
        </div>
    );
}

function AjudaSenha({
    aberta,
    aoMudar,
}: {
    aberta: boolean;
    aoMudar: (v: boolean) => void;
}) {
    return (
        <Folha aberta={aberta} aoMudar={aoMudar} titulo="Esqueceu a senha?">
            <div className="space-y-3 pb-2 text-sm text-suave">
                <p>
                    A senha é do apartamento e só o{' '}
                    <strong className="text-texto">síndico</strong> gera uma
                    nova. Peça a ele pessoalmente ou por mensagem: você recebe
                    uma senha provisória e escolhe a sua no primeiro acesso.
                </p>
                <p>
                    Por segurança, a senha nova desativa a biometria cadastrada
                    antes. É só ativar de novo em “Minha conta”.
                </p>
            </div>
        </Folha>
    );
}

function traduzirErroPasskey(erro: string): string {
    if (/cancel|abort|NotAllowed|timed out|not allowed/i.test(erro)) {
        return 'Biometria cancelada. Toque de novo ou entre com a senha.';
    }

    if (/Unable to sign in|Invalid/i.test(erro)) {
        return 'Esta biometria não está mais válida. Entre com a senha e ative de novo.';
    }

    return erro;
}

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import {
    ChevronRight,
    Fingerprint,
    KeyRound,
    LogOut,
    Mail,
    ShieldCheck,
    Smartphone,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Botao from '@/components/condominio/botao';
import BotaoCopiar from '@/components/condominio/botao-copiar';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import ContatosRecuperacao from '@/components/condominio/contatos-recuperacao';
import type { ContatosRecuperacaoProps } from '@/components/condominio/contatos-recuperacao';
import { Campo, Entrada } from '@/components/condominio/campo';
import { useSenhaConfirmada } from '@/components/condominio/confirmar-senha';
import Folha from '@/components/condominio/folha';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import { CartaoAplicativo } from '@/components/condominio/instalar-app';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { quando } from '@/lib/formato';
import { postJson } from '@/lib/http';
import type { Passkey } from '@/types';

type Props = {
    biometria_aparelho: boolean;
    passkeys: Passkey[];
    dois_fatores: { ativo: boolean; confirmado: boolean; obrigatorio: boolean };
    email_avisos: string | null;
    nome_informado: string | null;
    contatos_recuperacao: ContatosRecuperacaoProps;
};

export default function Conta(props: Props) {
    const { auth } = usePage().props;
    const user = auth.user!;
    const unidade = user.tipo === 'unidade';
    const senha = useSenhaConfirmada();

    return (
        <>
            <Head title="Minha conta" />
            <TopoTela
                voltar={unidade ? '/' : '/gestao'}
                contexto={
                    unidade
                        ? `Apartamento ${user.login}`
                        : user.tipo === 'admin'
                          ? 'Síndico'
                          : 'Conselho'
                }
                titulo={unidade ? 'Minha conta' : 'Segurança da conta'}
            />

            <div className="space-y-5 px-4">
                {user.tipo === 'admin' && !props.dois_fatores.confirmado && (
                    <div className="rounded-2xl border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800">
                        <p className="font-extrabold">
                            Ative a verificação em duas etapas
                        </p>
                        <p className="mt-0.5 text-xs">
                            A gestão do condomínio só abre com o código do
                            aplicativo autenticador.
                        </p>
                    </div>
                )}

                {unidade && <QuemUsa nome={props.nome_informado} />}

                <section>
                    <TituloSecao>Aplicativo</TituloSecao>
                    <CartaoAplicativo />
                </section>

                <section>
                    <TituloSecao>Biometria</TituloSecao>
                    <Biometria
                        passkeys={props.passkeys}
                        aparelho={props.biometria_aparelho}
                        exigirSenha={senha.exigir}
                    />
                </section>

                {!unidade && (
                    <section>
                        <TituloSecao>Verificação em duas etapas</TituloSecao>
                        <DoisFatores
                            estado={props.dois_fatores}
                            exigirSenha={senha.exigir}
                        />
                    </section>
                )}

                {unidade && (
                    <section>
                        <TituloSecao>Avisos por e-mail</TituloSecao>
                        <EmailAvisos atual={props.email_avisos} />
                    </section>
                )}

                <section>
                    <TituloSecao>Recuperação de senha</TituloSecao>
                    <ContatosRecuperacao
                        contatos={props.contatos_recuperacao}
                    />
                </section>

                <Cartao className="divide-y divide-linha py-1">
                    <Link
                        href="/trocar-senha"
                        className="flex items-center gap-3 py-3"
                    >
                        <KeyRound className="size-[18px] text-marinho-700" />
                        <span className="flex-1 text-sm font-bold">
                            Trocar senha
                        </span>
                        <ChevronRight className="size-4 text-suave" />
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="flex w-full items-center gap-3 py-3 text-left"
                    >
                        <LogOut className="size-[18px] text-red-600" />
                        <span className="flex-1 text-sm font-bold text-red-600">
                            Sair
                        </span>
                    </button>
                </Cartao>
            </div>

            {senha.folha}
        </>
    );
}

function QuemUsa({ nome }: { nome: string | null }) {
    const form = useForm({ nome: nome ?? '' });

    return (
        <section>
            <TituloSecao>Quem está usando</TituloSecao>
            <Cartao>
                <form
                    className="flex items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/conta/nome', { preserveScroll: true });
                    }}
                >
                    <Campo
                        rotulo="Seu nome"
                        detalhe="(aparece no histórico do apartamento)"
                        className="flex-1"
                    >
                        <Entrada
                            icone={<UserRound />}
                            placeholder="Ex.: Maria"
                            maxLength={60}
                            value={form.data.nome}
                            onChange={(e) =>
                                form.setData('nome', e.target.value)
                            }
                        />
                    </Campo>
                    <Botao
                        type="submit"
                        tom="marinho"
                        className="h-12"
                        carregando={form.processing}
                    >
                        Salvar
                    </Botao>
                </form>
            </Cartao>
        </section>
    );
}

function Biometria({
    passkeys,
    aparelho,
    exigirSenha,
}: {
    passkeys: Passkey[];
    aparelho: boolean;
    exigirSenha: (acao: () => void) => void;
}) {
    const { register, isLoading, isSupported, error } = usePasskeyRegister({
        onSuccess: async () => {
            await postJson('/aparelho/biometria').catch(() => undefined);
            toast.success('Biometria ativada neste aparelho.');
            router.reload();
        },
    });

    const nomeAparelho = () => {
        const ua = navigator.userAgent;
        const sistema = /iPhone/.test(ua)
            ? 'iPhone'
            : /iPad/.test(ua)
              ? 'iPad'
              : /Android/.test(ua)
                ? 'Android'
                : /Mac/.test(ua)
                  ? 'Mac'
                  : /Windows/.test(ua)
                    ? 'Windows'
                    : 'Aparelho';

        return `${sistema} · ${new Date().toLocaleDateString('pt-BR')}`;
    };

    return (
        <Cartao className="space-y-3">
            <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-xl bg-marinho-50 text-marinho-700">
                    <Fingerprint className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-extrabold">
                        Entrar com digital ou Face ID
                    </p>
                    <p className="text-xs text-suave">
                        {aparelho
                            ? 'Ativa neste aparelho.'
                            : 'Na próxima entrada, é só confirmar com a biometria.'}
                    </p>
                </div>
                {aparelho && <Selo tom="pago">Ativa</Selo>}
            </div>

            {passkeys.length > 0 && (
                <ul className="divide-y divide-linha rounded-xl border border-linha px-3">
                    {passkeys.map((p) => (
                        <li
                            key={p.id}
                            className="flex items-center gap-3 py-2.5"
                        >
                            <Smartphone className="size-4 text-suave" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-bold">
                                    {p.name}
                                </p>
                                <p className="text-[11px] text-suave">
                                    {p.authenticator
                                        ? `${p.authenticator} · `
                                        : ''}
                                    {p.usada_em
                                        ? `usada ${quando(p.usada_em)}`
                                        : `criada ${quando(p.criada_em)}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                aria-label="Remover biometria"
                                onClick={() =>
                                    exigirSenha(() =>
                                        router.delete(
                                            `/user/passkeys/${p.id}`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    toast.success(
                                                        'Biometria removida.',
                                                    ),
                                            },
                                        ),
                                    )
                                }
                                className="flex size-8 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {isSupported ? (
                <Botao
                    tom={aparelho ? 'branco' : 'ouro'}
                    largo
                    carregando={isLoading}
                    onClick={() =>
                        exigirSenha(() => void register(nomeAparelho()))
                    }
                >
                    <Fingerprint />{' '}
                    {aparelho
                        ? 'Cadastrar outra biometria'
                        : 'Ativar biometria neste aparelho'}
                </Botao>
            ) : (
                <p className="text-xs text-suave">
                    Este navegador não aceita biometria (passkey).
                </p>
            )}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </Cartao>
    );
}

function DoisFatores({
    estado,
    exigirSenha,
}: {
    estado: Props['dois_fatores'];
    exigirSenha: (acao: () => void) => void;
}) {
    const {
        qrCodeSvg,
        manualSetupKey,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        clearTwoFactorAuthData,
    } = useTwoFactorAuth();
    const [configurando, setConfigurando] = useState(false);
    const [codigos, setCodigos] = useState(false);
    const form = useForm({ code: '' });

    const iniciar = () =>
        exigirSenha(() =>
            router.post(
                '/user/two-factor-authentication',
                {},
                {
                    preserveScroll: true,
                    onSuccess: async () => {
                        await fetchSetupData();
                        setConfigurando(true);
                    },
                },
            ),
        );

    if (estado.confirmado) {
        return (
            <Cartao className="space-y-3">
                <div className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <ShieldCheck className="size-5" />
                    </span>
                    <div className="flex-1">
                        <p className="text-sm font-extrabold">Ativa</p>
                        <p className="text-xs text-suave">
                            O código do aplicativo é pedido em toda entrada.
                        </p>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <Botao
                        tom="branco"
                        onClick={() =>
                            exigirSenha(async () => {
                                await fetchRecoveryCodes();
                                setCodigos(true);
                            })
                        }
                    >
                        Códigos de recuperação
                    </Botao>
                    {!estado.obrigatorio && (
                        <Botao
                            tom="branco"
                            className="text-red-600"
                            onClick={() =>
                                exigirSenha(() =>
                                    router.delete(
                                        '/user/two-factor-authentication',
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                clearTwoFactorAuthData(),
                                        },
                                    ),
                                )
                            }
                        >
                            Desativar
                        </Botao>
                    )}
                </div>

                <Folha
                    aberta={codigos}
                    aoMudar={setCodigos}
                    titulo="Códigos de recuperação"
                    descricao="Cada código vale uma vez, se o celular sumir. Guarde fora do celular."
                >
                    <div className="space-y-3 pb-2">
                        <ul className="grid grid-cols-2 gap-2 rounded-xl bg-marinho-50 p-3 font-mono text-sm">
                            {recoveryCodesList.map((c) => (
                                <li key={c}>{c}</li>
                            ))}
                        </ul>
                        <BotaoCopiar
                            texto={recoveryCodesList.join('\n')}
                            rotulo="Copiar todos"
                            tom="branco"
                            largo
                        />
                        <Botao
                            tom="fantasma"
                            largo
                            onClick={() =>
                                router.post(
                                    '/user/two-factor-recovery-codes',
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            void fetchRecoveryCodes(),
                                    },
                                )
                            }
                        >
                            Gerar códigos novos
                        </Botao>
                    </div>
                </Folha>
            </Cartao>
        );
    }

    return (
        <Cartao className="space-y-3">
            <p className="text-sm text-suave">
                Use um aplicativo autenticador (Google Authenticator, Microsoft
                Authenticator, 1Password…) para gerar o código de 6 dígitos.
            </p>
            <Botao largo onClick={iniciar}>
                <ShieldCheck /> Ativar verificação em duas etapas
            </Botao>

            <Folha
                aberta={configurando}
                aoMudar={setConfigurando}
                titulo="Ativar duas etapas"
                descricao="1. Leia o QR Code no aplicativo. 2. Digite o código que aparecer."
                rodape={
                    <Botao
                        tamanho="lg"
                        largo
                        disabled={form.data.code.length < 6}
                        carregando={form.processing}
                        onClick={() =>
                            form.post(
                                '/user/confirmed-two-factor-authentication',
                                {
                                    preserveScroll: true,
                                    errorBag: 'confirmTwoFactorAuthentication',
                                    onSuccess: () => {
                                        setConfigurando(false);
                                        toast.success(
                                            'Verificação em duas etapas ativada.',
                                        );
                                    },
                                },
                            )
                        }
                    >
                        Confirmar código
                    </Botao>
                }
            >
                <div className="space-y-4 pb-2">
                    {qrCodeSvg && (
                        <div
                            className="mx-auto w-52 rounded-xl border border-linha bg-white p-3 [&_svg]:size-full"
                            dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                        />
                    )}
                    {manualSetupKey && (
                        <div className="flex items-center gap-2 rounded-xl bg-marinho-50 px-3 py-2">
                            <span className="flex-1 font-mono text-xs break-all">
                                {manualSetupKey}
                            </span>
                            <BotaoCopiar
                                texto={manualSetupKey}
                                rotulo="Copiar"
                                tamanho="sm"
                                tom="branco"
                            />
                        </div>
                    )}
                    <div className="flex flex-col items-center gap-1">
                        <InputOTP
                            maxLength={6}
                            pattern={REGEXP_ONLY_DIGITS}
                            value={form.data.code}
                            onChange={(v) => form.setData('code', v)}
                            inputMode="numeric"
                        >
                            <InputOTPGroup>
                                {Array.from({ length: 6 }, (_, i) => (
                                    <InputOTPSlot
                                        key={i}
                                        index={i}
                                        className="size-11 text-lg font-bold"
                                    />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                        {form.errors.code && (
                            <p className="text-xs font-semibold text-red-600">
                                {form.errors.code}
                            </p>
                        )}
                    </div>
                </div>
            </Folha>
        </Cartao>
    );
}

function EmailAvisos({ atual }: { atual: string | null }) {
    const form = useForm({ email_avisos: atual ?? '' });

    return (
        <Cartao>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/conta/email-avisos', { preserveScroll: true });
                }}
            >
                <Campo
                    rotulo="E-mail"
                    detalhe="(opcional)"
                    erro={form.errors.email_avisos}
                    ajuda="Cobrança, comprovante confirmado, fatura que chegou. Sem e-mail, os avisos ficam só aqui."
                >
                    <Entrada
                        type="email"
                        icone={<Mail />}
                        placeholder="voce@email.com"
                        value={form.data.email_avisos}
                        onChange={(e) =>
                            form.setData('email_avisos', e.target.value)
                        }
                    />
                </Campo>
                <Botao
                    type="submit"
                    tom="marinho"
                    largo
                    carregando={form.processing}
                >
                    Salvar
                </Botao>
            </form>
        </Cartao>
    );
}

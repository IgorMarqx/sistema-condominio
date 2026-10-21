import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fingerprint, KeyRound, Plus, Printer } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import BotaoCopiar from '@/components/condominio/botao-copiar';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import { dataHora, quando } from '@/lib/formato';

type Unidade = {
    id: number;
    numero: string;
    andar: number;
    fracao_ideal: number | null;
    ativa: boolean;
    medidores: number;
    acesso: {
        deve_trocar_senha: boolean;
        ultimo_acesso_em: string | null;
        biometria: number;
        ativo: boolean;
    } | null;
};

type Props = {
    unidades: Unidade[];
    soma_fracoes: number;
    acessos: {
        id: number;
        quem: string;
        metodo: string;
        ip: string | null;
        aparelho: string;
        quando: string;
    }[];
};

export default function Unidades({ unidades, soma_fracoes, acessos }: Props) {
    const { auth, flash } = usePage().props;
    const [redefinir, setRedefinir] = useState<Unidade | null>(null);
    const [editar, setEditar] = useState<Unidade | null>(null);
    const [nova, setNova] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const senha = flash.senha_gerada;

    return (
        <>
            <Head title="Unidades e senhas" />
            <TopoTela
                titulo="Unidades e senhas"
                contexto={`${unidades.length} apartamentos`}
                acao={
                    auth.user?.pode_editar && (
                        <Botao
                            tamanho="sm"
                            tom="marinho"
                            onClick={() => setNova(true)}
                        >
                            <Plus /> Adicionar
                            <span className="-ml-1.5 hidden sm:inline">
                                unidade
                            </span>
                        </Botao>
                    )
                }
            />

            <div className="space-y-4 px-4 lg:px-0">
                {senha && (
                    <div className="rounded-2xl border-2 border-ouro-500 bg-ouro-50 p-4">
                        <p className="text-sm font-extrabold">
                            Senha nova do apartamento {senha.apto}
                        </p>
                        <p className="mt-0.5 text-xs text-suave">
                            Aparece só agora. Entregue em papel ou mensagem; o
                            morador troca no primeiro acesso.
                        </p>
                        <p className="mt-3 rounded-xl bg-white px-3 py-3 text-center font-mono text-xl font-bold tracking-wider ring-1 ring-ouro-300">
                            {senha.senha}
                        </p>
                        <div className="mt-3 grid grid-cols-2 gap-2">
                            <BotaoCopiar
                                texto={`Apartamento ${senha.apto}\nSenha provisória: ${senha.senha}`}
                                rotulo="Copiar"
                                tom="marinho"
                            />
                            <Botao tom="branco" onClick={() => window.print()}>
                                <Printer /> Imprimir
                            </Botao>
                        </div>
                    </div>
                )}

                <Cartao className="divide-y divide-linha py-1">
                    {unidades.map((u) => (
                        <div
                            key={u.id}
                            className="flex items-center gap-3 py-3"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-marinho-900 text-sm font-extrabold text-ouro-500">
                                {u.numero}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {!u.acesso ? (
                                        <Selo tom="neutro">Sem acesso</Selo>
                                    ) : u.acesso.deve_trocar_senha ? (
                                        <Selo tom="aberto">
                                            Aguardando 1º acesso
                                        </Selo>
                                    ) : (
                                        <Selo tom="pago">Ativo</Selo>
                                    )}
                                    {!!u.acesso?.biometria && (
                                        <Selo tom="conferir">
                                            <Fingerprint className="size-3" />{' '}
                                            {u.acesso.biometria}
                                        </Selo>
                                    )}
                                    {!u.ativa && (
                                        <Selo tom="atrasado">Inativa</Selo>
                                    )}
                                </div>
                                <p className="mt-0.5 truncate text-xs text-suave">
                                    {u.acesso?.ultimo_acesso_em
                                        ? `Último acesso ${quando(u.acesso.ultimo_acesso_em)}`
                                        : 'Nunca acessou'}
                                    {u.fracao_ideal !== null &&
                                        ` · fração ${u.fracao_ideal.toLocaleString('pt-BR')}%`}
                                    {` · ${u.medidores} medidor${u.medidores === 1 ? '' : 'es'}`}
                                </p>
                            </div>
                            {auth.user?.pode_editar && (
                                <div className="flex shrink-0 gap-1">
                                    <Botao
                                        tamanho="sm"
                                        tom="branco"
                                        onClick={() => setEditar(u)}
                                    >
                                        Editar
                                    </Botao>
                                    <Botao
                                        tamanho="sm"
                                        tom="branco"
                                        onClick={() => setRedefinir(u)}
                                        aria-label={`Nova senha do ${u.numero}`}
                                    >
                                        <KeyRound />
                                    </Botao>
                                </div>
                            )}
                        </div>
                    ))}
                </Cartao>
                <p className="px-1 text-xs text-suave">
                    Soma das frações ideais:{' '}
                    {soma_fracoes.toLocaleString('pt-BR')}% (precisa ser 100%
                    para usar o rateio por fração).
                </p>

                <section>
                    <TituloSecao>Últimos acessos</TituloSecao>
                    <Cartao className="divide-y divide-linha py-1">
                        {acessos.map((a) => (
                            <div
                                key={a.id}
                                className="flex items-center justify-between gap-3 py-2.5"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold">
                                        {a.quem}
                                    </p>
                                    <p className="truncate text-xs text-suave">
                                        {a.aparelho} · {a.ip}
                                    </p>
                                </div>
                                <div className="shrink-0 text-right">
                                    <Selo
                                        tom={
                                            a.metodo === 'biometria'
                                                ? 'conferir'
                                                : 'neutro'
                                        }
                                    >
                                        {a.metodo}
                                    </Selo>
                                    <p className="mt-0.5 text-[11px] text-suave">
                                        {dataHora(a.quando)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </Cartao>
                </section>
            </div>

            <Folha
                aberta={redefinir !== null}
                aoMudar={(v) => !v && setRedefinir(null)}
                titulo={`Nova senha para o ${redefinir?.numero ?? ''}`}
                descricao="Gera 16 caracteres, encerra as sessões abertas e remove a biometria cadastrada. Fica registrado na auditoria."
                rodape={
                    <Botao
                        tamanho="lg"
                        largo
                        carregando={enviando}
                        onClick={() =>
                            redefinir &&
                            router.post(
                                `/gestao/unidades/${redefinir.id}/senha`,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setEnviando(true),
                                    onFinish: () => {
                                        setEnviando(false);
                                        setRedefinir(null);
                                    },
                                },
                            )
                        }
                    >
                        <KeyRound /> Gerar senha
                    </Botao>
                }
            >
                <p className="pb-2 text-sm text-suave">
                    Use quando o morador esquecer a senha ou na entrega do
                    apartamento.
                </p>
            </Folha>

            <NovaUnidade aberta={nova} aoFechar={() => setNova(false)} />

            {editar && (
                <EditarUnidade
                    key={editar.id}
                    unidade={editar}
                    aoFechar={() => setEditar(null)}
                />
            )}
        </>
    );
}

function EditarUnidade({
    unidade,
    aoFechar,
}: {
    unidade: Unidade;
    aoFechar: () => void;
}) {
    const [fracao, setFracao] = useState(
        unidade.fracao_ideal !== null ? String(unidade.fracao_ideal) : '',
    );
    const [ativa, setAtiva] = useState(unidade.ativa);
    const [enviando, setEnviando] = useState(false);

    return (
        <Folha
            aberta
            aoMudar={(v) => !v && aoFechar()}
            titulo={`Apartamento ${unidade.numero}`}
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={enviando}
                    onClick={() =>
                        router.patch(
                            `/gestao/unidades/${unidade.id}`,
                            {
                                fracao_ideal:
                                    fracao === ''
                                        ? null
                                        : fracao.replace(',', '.'),
                                ativa,
                            },
                            {
                                preserveScroll: true,
                                onStart: () => setEnviando(true),
                                onFinish: () => setEnviando(false),
                                onSuccess: aoFechar,
                            },
                        )
                    }
                >
                    Salvar
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <Campo
                    rotulo="Fração ideal (%)"
                    ajuda="Só é usada se a regra do rateio for por fração."
                >
                    <Entrada
                        inputMode="decimal"
                        value={fracao}
                        onChange={(e) => setFracao(e.target.value)}
                        placeholder="8,3333"
                    />
                </Campo>
                <label className="flex items-center gap-3 text-sm font-bold">
                    <input
                        type="checkbox"
                        className="size-5 accent-marinho-900"
                        checked={ativa}
                        onChange={(e) => setAtiva(e.target.checked)}
                    />
                    Unidade ativa (entra no rateio)
                </label>
            </div>
        </Folha>
    );
}

function NovaUnidade({
    aberta,
    aoFechar,
}: {
    aberta: boolean;
    aoFechar: () => void;
}) {
    const form = useForm({ numero: '', fracao_ideal: '' });

    const fechar = () => {
        form.reset();
        form.clearErrors();
        aoFechar();
    };

    return (
        <Folha
            aberta={aberta}
            aoMudar={(v) => !v && fechar()}
            titulo="Adicionar unidade"
            descricao="Entra ativa no rateio e na tela de login. A senha provisória aparece uma vez só."
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    disabled={form.data.numero.length < 3}
                    onClick={() => {
                        form.transform((dados) => ({
                            numero: dados.numero,
                            fracao_ideal:
                                dados.fracao_ideal === ''
                                    ? null
                                    : dados.fracao_ideal.replace(',', '.'),
                        }));
                        form.post('/gestao/unidades', {
                            preserveScroll: true,
                            onSuccess: fechar,
                        });
                    }}
                >
                    <Plus /> Adicionar e gerar senha
                </Botao>
            }
        >
            <div className="space-y-4 pb-2">
                <Campo
                    rotulo="Número do apartamento"
                    htmlFor="numero-unidade"
                    erro={form.errors.numero}
                    ajuda="O andar sai do número: 104 fica no 1º andar."
                >
                    <Entrada
                        id="numero-unidade"
                        inputMode="numeric"
                        maxLength={4}
                        placeholder="104"
                        value={form.data.numero}
                        onChange={(e) =>
                            form.setData(
                                'numero',
                                e.target.value.replace(/\D/g, ''),
                            )
                        }
                    />
                </Campo>
                <Campo
                    rotulo="Fração ideal (%)"
                    detalhe="(opcional)"
                    htmlFor="fracao-unidade"
                    erro={form.errors.fracao_ideal}
                >
                    <Entrada
                        id="fracao-unidade"
                        inputMode="decimal"
                        placeholder="8,3333"
                        value={form.data.fracao_ideal}
                        onChange={(e) =>
                            form.setData('fracao_ideal', e.target.value)
                        }
                    />
                </Campo>
            </div>
        </Folha>
    );
}

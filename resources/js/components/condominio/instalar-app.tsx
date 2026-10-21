import {
    Download,
    EllipsisVertical,
    MonitorDown,
    Share,
    SquarePlus,
} from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao from '@/components/condominio/cartao';
import Folha from '@/components/condominio/folha';
import LogoPredio from '@/components/condominio/logo-predio';
import Selo from '@/components/condominio/selo';
import {
    assinarInstalacao,
    estadoInstalacao,
    estadoInstalacaoServidor,
    pedirInstalacao,
} from '@/lib/pwa';

type Sistema = 'ios' | 'android' | 'computador';

/**
 * Estado da instalação e a ação de instalar: pedido nativo quando o navegador oferece; senão,
 * a folha com os passos do iPhone, do Android ou do computador. `folha` precisa ser renderizada.
 */
export function useInstalarApp() {
    const estado = useSyncExternalStore(
        assinarInstalacao,
        estadoInstalacao,
        estadoInstalacaoServidor,
    );
    const [passos, setPassos] = useState<Sistema | null>(null);

    function instalar(): void {
        if (estado.nativo) {
            void pedirInstalacao();

            return;
        }

        setPassos(
            estado.ios ? 'ios' : estado.android ? 'android' : 'computador',
        );
    }

    return {
        ...estado,
        instalar,
        folha: (
            <PassosInstalacao
                sistema={passos}
                embutido={estado.embutido}
                aoFechar={() => setPassos(null)}
            />
        ),
    };
}

/**
 * Botão fixo de instalar (Configurações e Minha conta): funciona mesmo quando o convite não
 * aparece, porque sem o pedido nativo do navegador mostra os passos à mão.
 */
export function CartaoAplicativo() {
    const { instalado, instalar, folha } = useInstalarApp();

    return (
        <Cartao className="space-y-3">
            <div className="flex items-center gap-3">
                <LogoPredio tamanho="sm" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-extrabold">Aplicativo</p>
                    <p className="text-xs text-suave">
                        {instalado
                            ? 'Instalado neste aparelho.'
                            : 'Abra o condomínio direto da tela inicial, como um app.'}
                    </p>
                </div>
                {instalado && <Selo tom="pago">Instalado</Selo>}
            </div>
            {!instalado && (
                <Botao type="button" tom="marinho" largo onClick={instalar}>
                    <Download /> Baixar aplicativo
                </Botao>
            )}
            {folha}
        </Cartao>
    );
}

const TITULOS: Record<Sistema, { titulo: string; descricao: string }> = {
    ios: {
        titulo: 'Adicione à tela de início',
        descricao: 'No iPhone ou iPad, a instalação é feita pelo Safari.',
    },
    android: {
        titulo: 'Instale pelo Chrome',
        descricao: 'O Android instala o app pelo menu do navegador.',
    },
    computador: {
        titulo: 'Instale no computador',
        descricao: 'Chrome e Edge instalam o app pela barra de endereço.',
    },
};

function PassosInstalacao({
    sistema,
    embutido,
    aoFechar,
}: {
    sistema: Sistema | null;
    embutido: boolean;
    aoFechar: () => void;
}) {
    // Mantém o conteúdo enquanto a folha anima ao fechar.
    const [ultimo, setUltimo] = useState<Sistema>('android');

    if (sistema && sistema !== ultimo) {
        setUltimo(sistema);
    }

    const atual = sistema ?? ultimo;
    const icone = 'mx-0.5 inline size-4 align-text-bottom text-marinho-700';

    return (
        <Folha
            aberta={sistema !== null}
            aoMudar={(aberta) => !aberta && aoFechar()}
            titulo={TITULOS[atual].titulo}
            descricao={TITULOS[atual].descricao}
            rodape={
                <Botao
                    type="button"
                    tom="marinho"
                    largo
                    onClick={aoFechar}
                    className="mb-1"
                >
                    Entendi
                </Botao>
            }
        >
            {embutido && (
                <p className="mb-3 rounded-xl bg-orange-50 px-3 py-2.5 text-xs leading-5 text-orange-800">
                    Você abriu o link dentro de outro app. Abra no{' '}
                    {atual === 'ios' ? 'Safari' : 'Chrome'} (menu “Abrir no
                    navegador”) para instalar.
                </p>
            )}
            <ol className="space-y-3 pb-2 text-sm leading-6 text-texto">
                {atual === 'ios' && (
                    <>
                        <Passo numero={1}>
                            Toque em Compartilhar <Share className={icone} /> na
                            barra do Safari.
                        </Passo>
                        <Passo numero={2}>
                            Escolha “Adicionar à Tela de Início”{' '}
                            <SquarePlus className={icone} />.
                        </Passo>
                        <Passo numero={3}>Confirme em “Adicionar”.</Passo>
                    </>
                )}
                {atual === 'android' && (
                    <>
                        <Passo numero={1}>
                            Toque no menu <EllipsisVertical className={icone} />{' '}
                            no canto de cima do Chrome.
                        </Passo>
                        <Passo numero={2}>
                            Escolha “Instalar app” ou “Adicionar à tela
                            inicial”.
                        </Passo>
                        <Passo numero={3}>Confirme em “Instalar”.</Passo>
                    </>
                )}
                {atual === 'computador' && (
                    <>
                        <Passo numero={1}>
                            Clique no ícone de instalar{' '}
                            <MonitorDown className={icone} /> no fim da barra de
                            endereço.
                        </Passo>
                        <Passo numero={2}>
                            Sem o ícone, abra o menu{' '}
                            <EllipsisVertical className={icone} /> →
                            “Transmitir, salvar e compartilhar” → “Instalar
                            página como app”.
                        </Passo>
                        <Passo numero={3}>Confirme em “Instalar”.</Passo>
                    </>
                )}
            </ol>
        </Folha>
    );
}

function Passo({ numero, children }: { numero: number; children: ReactNode }) {
    return (
        <li className="flex gap-3">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-ouro-500 text-xs font-extrabold text-marinho-900">
                {numero}
            </span>
            <span>{children}</span>
        </li>
    );
}

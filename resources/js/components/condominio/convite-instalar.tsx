import { Download, X } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import { useInstalarApp } from '@/components/condominio/instalar-app';
import LogoPredio from '@/components/condominio/logo-predio';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const CHAVE_FECHADO = 'convite-instalar-fechado';

function foiFechado(): boolean {
    try {
        return localStorage.getItem(CHAVE_FECHADO) === '1';
    } catch {
        return false;
    }
}

function guardarFechado(): void {
    try {
        localStorage.setItem(CHAVE_FECHADO, '1');
    } catch {
        // Navegação privada bloqueia o storage: o convite fica fechado só até recarregar.
    }
}

/**
 * Chamado nas telas de entrada: o próximo login volta a mostrar o cartão inteiro.
 */
export function reabrirConviteInstalar(): void {
    try {
        localStorage.removeItem(CHAVE_FECHADO);
    } catch {
        // Sem storage não há o que limpar.
    }
}

/**
 * Convite para instalar o app: cartão com X logo depois do login; fechado, vira um botão
 * redondo de download no canto, acima da barra inferior. Some quando já está instalado ou
 * quando o navegador não oferece instalação (nem pedido nativo, nem iPhone). Fora disso, o
 * cartão "Aplicativo" das Configurações e da Minha conta mostra os passos à mão.
 */
export default function ConviteInstalar() {
    const { instalado, nativo, ios, instalar, folha } = useInstalarApp();
    const [fechado, setFechado] = useState(foiFechado);

    if (instalado || (!nativo && !ios)) {
        return null;
    }

    function fechar(): void {
        guardarFechado();
        setFechado(true);
    }

    return (
        <>
            {fechado ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={instalar}
                            aria-label="Instalar aplicativo"
                            className="fixed right-4 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-40 flex size-12 items-center justify-center rounded-full bg-marinho-900 text-ouro-500 shadow-[0_10px_24px_-8px_rgb(19_37_79/0.6)] transition outline-none hover:bg-marinho-800 focus-visible:ring-4 focus-visible:ring-marinho-600/25 active:scale-95 md:right-6 md:bottom-6"
                        >
                            <Download className="size-5" strokeWidth={2.4} />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="left">
                        Instalar aplicativo
                    </TooltipContent>
                </Tooltip>
            ) : (
                <aside className="fixed right-4 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] left-4 z-40 mx-auto max-w-sm animate-in rounded-3xl border border-linha bg-white p-4 shadow-[0_14px_34px_-10px_rgb(19_37_79/0.35)] fade-in-0 slide-in-from-bottom-4 md:right-6 md:bottom-28 md:left-auto md:w-96">
                    <button
                        type="button"
                        onClick={fechar}
                        aria-label="Fechar convite de instalação"
                        className="absolute top-3 right-3 flex size-8 items-center justify-center rounded-full bg-marinho-50 text-texto hover:bg-marinho-100"
                    >
                        <X className="size-4" />
                    </button>

                    <div className="flex items-center gap-3 pr-9">
                        <LogoPredio tamanho="sm" />
                        <div className="min-w-0">
                            <h2 className="text-[15px] font-extrabold tracking-tight text-texto">
                                Instale o aplicativo
                            </h2>
                            <p className="mt-0.5 text-[13px] leading-5 text-suave">
                                Abra as contas e a cobrança do mês direto da
                                tela inicial.
                            </p>
                        </div>
                    </div>

                    <Botao
                        type="button"
                        tom="marinho"
                        tamanho="sm"
                        largo
                        onClick={instalar}
                        className="mt-3.5"
                    >
                        <Download />
                        Instalar aplicativo
                    </Botao>
                </aside>
            )}

            {folha}
        </>
    );
}

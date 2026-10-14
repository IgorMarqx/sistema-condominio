import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Folha que sobe de baixo no celular e vira janela centralizada no computador.
 * Sem foco automático no toque (o teclado abriria por cima da folha).
 */
export default function Folha({
    aberta,
    aoMudar,
    titulo,
    descricao,
    children,
    rodape,
    larga = false,
}: {
    aberta: boolean;
    aoMudar: (aberta: boolean) => void;
    titulo: ReactNode;
    descricao?: ReactNode;
    children: ReactNode;
    rodape?: ReactNode;
    larga?: boolean;
}) {
    return (
        <DialogPrimitive.Root open={aberta} onOpenChange={aoMudar}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-marinho-950/50 backdrop-blur-[2px] data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    onOpenAutoFocus={(e) => {
                        if (!window.matchMedia('(pointer: fine)').matches) {
                            e.preventDefault();
                        }
                    }}
                    className={cn(
                        'fixed inset-x-0 bottom-0 z-50 flex max-h-[92svh] flex-col rounded-t-3xl bg-white shadow-2xl outline-none',
                        'data-[state=closed]:animate-out data-[state=closed]:slide-out-to-bottom data-[state=open]:animate-in data-[state=open]:slide-in-from-bottom',
                        'sm:inset-x-auto sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:w-full sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-3xl',
                        'sm:data-[state=closed]:slide-out-to-bottom-0 sm:data-[state=closed]:zoom-out-95 sm:data-[state=open]:slide-in-from-bottom-0 sm:data-[state=open]:zoom-in-95',
                        larga ? 'sm:max-w-2xl' : 'sm:max-w-md',
                    )}
                >
                    <div className="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-linha sm:hidden" />
                    <div className="flex shrink-0 items-start gap-3 px-5 pt-4 pb-2">
                        <div className="min-w-0 flex-1">
                            <DialogPrimitive.Title className="text-lg font-extrabold tracking-tight text-texto">
                                {titulo}
                            </DialogPrimitive.Title>
                            {descricao ? (
                                <DialogPrimitive.Description className="mt-0.5 text-sm text-suave">
                                    {descricao}
                                </DialogPrimitive.Description>
                            ) : (
                                <DialogPrimitive.Description className="sr-only">
                                    {typeof titulo === 'string'
                                        ? titulo
                                        : 'Detalhes'}
                                </DialogPrimitive.Description>
                            )}
                        </div>
                        <DialogPrimitive.Close
                            className="flex size-9 shrink-0 items-center justify-center rounded-full bg-marinho-50 text-texto hover:bg-marinho-100"
                            aria-label="Fechar"
                        >
                            <X className="size-4" />
                        </DialogPrimitive.Close>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-4">
                        {children}
                    </div>
                    {rodape && (
                        <div className="area-segura-baixo shrink-0 border-t border-linha px-5 pt-3">
                            {rodape}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

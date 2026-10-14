import { FileUp, Paperclip, X } from 'lucide-react';
import { useRef } from 'react';
import { cn } from '@/lib/utils';

/**
 * Área de "Enviar comprovante · Foto ou PDF": abre câmera/galeria no celular.
 */
export default function SeletorArquivo({
    arquivo,
    aoEscolher,
    titulo = 'Escolher arquivo',
    apoio = 'Foto ou PDF',
    aceitar = 'application/pdf,image/*',
    erro,
}: {
    arquivo: File | null;
    aoEscolher: (arquivo: File | null) => void;
    titulo?: string;
    apoio?: string;
    aceitar?: string;
    erro?: string;
}) {
    const entrada = useRef<HTMLInputElement>(null);

    return (
        <div>
            <input
                ref={entrada}
                type="file"
                accept={aceitar}
                className="hidden"
                onChange={(e) => aoEscolher(e.target.files?.[0] ?? null)}
            />
            {arquivo ? (
                <div className="flex items-center gap-3 rounded-xl border border-linha bg-marinho-50 px-3.5 py-3">
                    <Paperclip className="size-4 shrink-0 text-suave" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold">
                            {arquivo.name}
                        </p>
                        <p className="text-xs text-suave">
                            {(arquivo.size / 1024).toFixed(0)} KB
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            aoEscolher(null);

                            if (entrada.current) {
                                entrada.current.value = '';
                            }
                        }}
                        className="flex size-8 items-center justify-center rounded-full hover:bg-white"
                        aria-label="Remover arquivo"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => entrada.current?.click()}
                    className={cn(
                        'flex w-full items-center gap-3 rounded-xl border border-dashed border-[#c9d3ea] bg-[#f7f8fb] px-3.5 py-3.5 text-left hover:bg-marinho-50',
                        erro && 'border-red-400',
                    )}
                >
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white text-marinho-700 shadow-xs">
                        <FileUp className="size-5" />
                    </span>
                    <span>
                        <span className="block text-sm font-bold">
                            {titulo}
                        </span>
                        <span className="block text-xs text-suave">
                            {apoio}
                        </span>
                    </span>
                </button>
            )}
            {erro && (
                <p className="mt-1 text-xs font-semibold text-red-600">
                    {erro}
                </p>
            )}
        </div>
    );
}

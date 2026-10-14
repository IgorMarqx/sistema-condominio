import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Botao, { type BotaoProps } from './botao';

async function copiarTexto(texto: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(texto);

        return true;
    } catch {
        // Fallback para navegador sem Clipboard API (HTTP local, webview antiga).
        const area = document.createElement('textarea');
        area.value = texto;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();

        return ok;
    }
}

export default function BotaoCopiar({
    texto,
    rotulo,
    mensagem = 'Copiado!',
    ...props
}: Omit<BotaoProps, 'onClick'> & {
    texto: string;
    rotulo: string;
    mensagem?: string;
}) {
    const [copiado, setCopiado] = useState(false);

    return (
        <Botao
            type="button"
            onClick={async () => {
                if (await copiarTexto(texto)) {
                    setCopiado(true);
                    toast.success(mensagem);
                    window.setTimeout(() => setCopiado(false), 2000);
                } else {
                    toast.error(
                        'Não foi possível copiar. Selecione o texto e copie à mão.',
                    );
                }
            }}
            {...props}
        >
            {copiado ? <Check /> : <Copy />}
            {copiado ? 'Copiado' : rotulo}
        </Botao>
    );
}

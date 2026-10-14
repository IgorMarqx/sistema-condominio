import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Mensagens de sucesso/erro que o servidor manda na sessão viram toast.
 */
export function useFlash(): void {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.sucesso) {
            toast.success(flash.sucesso);
        }

        if (flash?.erro) {
            toast.error(flash.erro);
        }
    }, [flash]);
}

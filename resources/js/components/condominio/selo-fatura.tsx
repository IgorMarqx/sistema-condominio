import type { Fatura } from '@/types';
import Selo from './selo';

/**
 * Situação de uma fatura do ponto de vista de quem lê: conferir, rateada, paga, a pagar.
 */
export default function SeloFatura({
    fatura,
    individual = false,
}: {
    fatura: Fatura;
    individual?: boolean;
}) {
    if (fatura.status === 'pendente_revisao') {
        return <Selo tom="alerta">Revisar</Selo>;
    }

    if (fatura.status === 'pendente_confirmacao') {
        return <Selo tom="conferir">Conferir</Selo>;
    }

    if (fatura.status === 'rejeitada') {
        return <Selo tom="atrasado">Rejeitada</Selo>;
    }

    if (fatura.paga_em) {
        return <Selo tom="pago">Paga</Selo>;
    }

    if (!individual && fatura.rateada) {
        return <Selo tom="marinho">Rateada</Selo>;
    }

    return <Selo tom="aberto">A pagar</Selo>;
}

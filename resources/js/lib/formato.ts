const reais = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const fuso = 'America/Sao_Paulo';

/** 10422 → "104,22" */
export function valor(centavos: number | null | undefined): string {
    if (centavos === null || centavos === undefined) {
        return '—';
    }

    return reais.format(centavos / 100);
}

/** 10422 → "R$ 104,22" */
export function dinheiro(centavos: number | null | undefined): string {
    if (centavos === null || centavos === undefined) {
        return '—';
    }

    const texto = reais.format(Math.abs(centavos) / 100);

    return (centavos < 0 ? '−R$ ' : 'R$ ') + texto;
}

/** Com sinal explícito: "+ 104,23" / "− 425,10". */
export function comSinal(centavos: number): string {
    return (
        (centavos < 0 ? '− ' : '+ ') + reais.format(Math.abs(centavos) / 100)
    );
}

/** Separa "R$", reais e centavos para o número grande dos cartões. */
export function partesDinheiro(centavos: number): {
    inteiro: string;
    centavos: string;
} {
    const [inteiro, decimal] = reais.format(centavos / 100).split(',');

    return { inteiro, centavos: decimal ?? '00' };
}

function paraData(iso: string): Date {
    // Data pura ("2026-10-15") é dia de calendário: sem conversão de fuso.
    return /^\d{4}-\d{2}-\d{2}$/.test(iso)
        ? new Date(`${iso}T12:00:00`)
        : new Date(iso);
}

/** "2026-10-15" → "15/10" */
export function diaMes(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return paraData(iso).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        timeZone: /^\d{4}-\d{2}-\d{2}$/.test(iso) ? undefined : fuso,
    });
}

/** "2026-10-15" → "15/10/2026" */
export function data(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return paraData(iso).toLocaleDateString('pt-BR', {
        timeZone: /^\d{4}-\d{2}-\d{2}$/.test(iso) ? undefined : fuso,
    });
}

/** Data e hora local: "13/10 07:12". */
export function dataHora(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return `${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', timeZone: fuso })} ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: fuso })}`;
}

export function hora(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: fuso,
    });
}

function diaLocal(d: Date): string {
    return d.toLocaleDateString('en-CA', { timeZone: fuso });
}

/** "hoje", "ontem", ou "02/10". */
export function quando(iso: string): string {
    const d = new Date(iso);
    const hoje = new Date();
    const ontem = new Date(Date.now() - 86_400_000);

    if (diaLocal(d) === diaLocal(hoje)) {
        return 'hoje';
    }

    if (diaLocal(d) === diaLocal(ontem)) {
        return 'ontem';
    }

    return diaMes(iso);
}

/** "Recebida por e-mail hoje, 07:12" */
export function quandoComHora(iso: string): string {
    const q = quando(iso);

    return q === 'hoje' || q === 'ontem' ? `${q}, ${hora(iso)}` : q;
}

/** Dias até a data (negativo = passou). */
export function diasAte(iso: string): number {
    const alvo = paraData(iso);
    const hoje = new Date(`${diaLocal(new Date())}T12:00:00`);

    return Math.round((alvo.getTime() - hoje.getTime()) / 86_400_000);
}

export function numero(valor: number | null | undefined, casas = 0): string {
    if (valor === null || valor === undefined) {
        return '—';
    }

    return valor.toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: casas,
    });
}

const MESES = [
    'janeiro',
    'fevereiro',
    'março',
    'abril',
    'maio',
    'junho',
    'julho',
    'agosto',
    'setembro',
    'outubro',
    'novembro',
    'dezembro',
];

/** "2026-10" → "outubro" */
export function nomeMes(mes: string): string {
    return MESES[Number(mes.slice(5, 7)) - 1] ?? mes;
}

/** "2026-10" → "Outubro/2026" */
export function rotuloMes(mes: string): string {
    const nome = nomeMes(mes);

    return `${nome.charAt(0).toUpperCase()}${nome.slice(1)}/${mes.slice(0, 4)}`;
}

/** Mês anterior/seguinte de "2026-10". */
export function somarMes(mes: string, delta: number): string {
    const d = new Date(
        Number(mes.slice(0, 4)),
        Number(mes.slice(5, 7)) - 1 + delta,
        1,
    );

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function hojeIso(): string {
    return diaLocal(new Date());
}

/** "+R$ 450,00" / "−R$ 12,30" */
export function dinheiroSinal(centavos: number): string {
    return (
        (centavos < 0 ? '−' : '+') +
        'R$ ' +
        reais.format(Math.abs(centavos) / 100)
    );
}

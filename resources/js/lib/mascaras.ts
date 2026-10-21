import { valor } from '@/lib/formato';

/** Digitação em centavos: "123456" → "1.234,56". */
export function mascararMoeda(texto: string): string {
    const digitos = texto.replace(/\D/g, '');

    return digitos ? valor(Number(digitos)) : '';
}

/** Número positivo com vírgula decimal, preservando o separador durante a digitação. */
export function mascararDecimal(texto: string, casas: number): string {
    const normalizado = texto.includes(',')
        ? texto.replace(/\./g, '')
        : texto.replace('.', ',');
    const [inteiro, ...fracao] = normalizado.replace(/[^\d,]/g, '').split(',');
    const digitos = inteiro.replace(/^0+(?=\d)/, '');

    if (fracao.length === 0) {
        return digitos;
    }

    return `${digitos || '0'},${fracao.join('').slice(0, casas)}`;
}

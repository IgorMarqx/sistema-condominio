import { Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Bell,
    CalendarDays,
    CircleAlert,
    Droplet,
    FileText,
    Inbox,
    KeyRound,
    ReceiptText,
    TriangleAlert,
    Zap,
} from 'lucide-react';
import { quando } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { Aviso } from '@/types';

const icones: Record<string, { icone: typeof Bell; cor: string }> = {
    fatura_individual: { icone: Zap, cor: 'bg-ouro-100 text-[#b7791f]' },
    fatura_condominio: { icone: FileText, cor: 'bg-ouro-100 text-[#b7791f]' },
    prestacao_contas: {
        icone: FileText,
        cor: 'bg-marinho-50 text-marinho-700',
    },
    cobranca_gerada: {
        icone: ReceiptText,
        cor: 'bg-marinho-50 text-marinho-700',
    },
    cobranca_vencendo: {
        icone: CalendarDays,
        cor: 'bg-ouro-100 text-[#8a6100]',
    },
    cobranca_vencida: { icone: TriangleAlert, cor: 'bg-red-100 text-red-600' },
    pagamento_confirmado: {
        icone: BadgeCheck,
        cor: 'bg-emerald-100 text-emerald-700',
    },
    pagamento_recusado: { icone: CircleAlert, cor: 'bg-red-100 text-red-600' },
    comprovante_enviado: { icone: ReceiptText, cor: 'bg-sky-100 text-sky-700' },
    anomalia_consumo: {
        icone: TriangleAlert,
        cor: 'bg-orange-100 text-orange-600',
    },
    verificacao_email: { icone: KeyRound, cor: 'bg-ouro-100 text-[#8a6100]' },
    email_revisao: { icone: Inbox, cor: 'bg-orange-100 text-orange-600' },
    fatura_atrasada: {
        icone: CalendarDays,
        cor: 'bg-orange-100 text-orange-600',
    },
    lembrete_leitura: {
        icone: CalendarDays,
        cor: 'bg-marinho-50 text-marinho-700',
    },
};

export default function AvisoLinha({ aviso }: { aviso: Aviso }) {
    const agua =
        aviso.concessionaria === 'cagepa' &&
        ['fatura_individual', 'fatura_condominio'].includes(aviso.tipo);
    const { icone: Icone, cor } = agua
        ? { icone: Droplet, cor: 'bg-sky-100 text-sky-600' }
        : (icones[aviso.tipo] ?? {
              icone: Bell,
              cor: 'bg-marinho-50 text-marinho-700',
          });

    return (
        <Link
            href={`/avisos/${aviso.id}`}
            className="flex items-start gap-3 py-3 active:opacity-70"
        >
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    cor,
                )}
            >
                <Icone className="size-[18px]" />
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block truncate text-sm text-texto',
                        aviso.lida ? 'font-semibold' : 'font-extrabold',
                    )}
                >
                    {aviso.titulo}
                </span>
                {aviso.mensagem && (
                    <span className="block truncate text-xs text-suave">
                        {aviso.mensagem}
                    </span>
                )}
            </span>
            <span className="flex shrink-0 flex-col items-end gap-1 pt-0.5">
                <span className="text-[11px] font-semibold text-suave">
                    {quando(aviso.criado_em)}
                </span>
                {!aviso.lida && (
                    <span className="size-2 rounded-full bg-ouro-500" />
                )}
            </span>
        </Link>
    );
}

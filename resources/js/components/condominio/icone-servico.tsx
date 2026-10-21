import { Droplet, Wifi, Zap } from 'lucide-react';
import type { ConcessionariaId } from '@/types';
import { cn } from '@/lib/utils';

const ESTILOS: Record<ConcessionariaId, { icone: typeof Zap; cor: string }> = {
    energisa: { icone: Zap, cor: 'bg-ouro-100 text-[#b7791f]' },
    cagepa: { icone: Droplet, cor: 'bg-sky-100 text-sky-600' },
    internet: { icone: Wifi, cor: 'bg-violet-100 text-violet-600' },
};

export default function IconeServico({
    concessionaria,
    className,
}: {
    concessionaria: ConcessionariaId;
    className?: string;
}) {
    const { icone: Icone, cor } = ESTILOS[concessionaria];

    return (
        <div
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                cor,
                className,
            )}
        >
            <Icone className="size-[18px]" strokeWidth={2.2} />
        </div>
    );
}

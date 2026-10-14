import { Droplet, Zap } from 'lucide-react';
import type { ConcessionariaId } from '@/types';
import { cn } from '@/lib/utils';

export default function IconeServico({
    concessionaria,
    className,
}: {
    concessionaria: ConcessionariaId;
    className?: string;
}) {
    const energia = concessionaria === 'energisa';
    const Icone = energia ? Zap : Droplet;

    return (
        <div
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                energia
                    ? 'bg-ouro-100 text-[#b7791f]'
                    : 'bg-sky-100 text-sky-600',
                className,
            )}
        >
            <Icone className="size-[18px]" strokeWidth={2.2} />
        </div>
    );
}

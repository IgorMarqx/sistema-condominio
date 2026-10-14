import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function Vazio({
    icone: Icone,
    titulo,
    children,
    className,
}: {
    icone: LucideIcon;
    titulo: string;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center px-6 py-10 text-center',
                className,
            )}
        >
            <div className="mb-3 flex size-14 items-center justify-center rounded-2xl bg-marinho-50 text-suave">
                <Icone className="size-6" />
            </div>
            <p className="font-bold text-texto">{titulo}</p>
            {children && (
                <div className="mt-1 max-w-xs text-sm text-suave">
                    {children}
                </div>
            )}
        </div>
    );
}

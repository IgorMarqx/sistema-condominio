import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const variantes = cva(
    'inline-flex items-center justify-center gap-2 rounded-full font-bold whitespace-nowrap transition outline-none focus-visible:ring-4 focus-visible:ring-marinho-600/25 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-55 [&_svg]:size-[18px] [&_svg]:shrink-0',
    {
        variants: {
            tom: {
                ouro: 'bg-ouro-500 text-marinho-900 shadow-[0_6px_16px_-8px_rgb(245_207_60/0.9)] hover:bg-ouro-400',
                marinho: 'bg-marinho-900 text-white hover:bg-marinho-800',
                branco: 'border border-linha bg-white text-texto shadow-xs hover:bg-marinho-50',
                vidro: 'border border-white/20 bg-white/10 text-white backdrop-blur hover:bg-white/15',
                perigo: 'bg-red-600 text-white hover:bg-red-700',
                fantasma: 'text-texto hover:bg-marinho-50',
            },
            tamanho: {
                lg: 'h-13 px-6 text-[15px]',
                md: 'h-11 px-5 text-sm',
                sm: 'h-9 px-3.5 text-[13px]',
            },
            largo: { true: 'w-full' },
        },
        defaultVariants: { tom: 'ouro', tamanho: 'md' },
    },
);

export type BotaoProps = ComponentProps<'button'> &
    VariantProps<typeof variantes> & {
        asChild?: boolean;
        carregando?: boolean;
    };

export default function Botao({
    className,
    tom,
    tamanho,
    largo,
    asChild,
    carregando,
    children,
    disabled,
    ...props
}: BotaoProps) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            className={cn(variantes({ tom, tamanho, largo }), className)}
            disabled={disabled || carregando}
            {...props}
        >
            {asChild ? (
                children
            ) : (
                <>
                    {carregando && <Loader2 className="animate-spin" />}
                    {children}
                </>
            )}
        </Comp>
    );
}

export { variantes as variantesBotao };

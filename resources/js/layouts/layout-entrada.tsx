import { useFlash } from '@/hooks/use-flash';
import type { LayoutProps } from '@/types';

export default function LayoutEntrada({ children }: LayoutProps) {
    useFlash();

    return <>{children}</>;
}

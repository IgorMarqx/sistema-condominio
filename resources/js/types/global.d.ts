import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            edificio: string;
            auth: Auth;
            avisos_nao_lidos: number;
            flash: {
                sucesso: string | null;
                erro: string | null;
                senha_gerada: { apto: string; senha: string } | null;
            };
            [key: string]: unknown;
        };
    }
}

export type TipoUsuario = 'admin' | 'unidade' | 'conselho';

export type Usuario = {
    id: number;
    nome: string;
    login: string;
    tipo: TipoUsuario;
    unidade: { id: number; numero: string; andar: number } | null;
    dois_fatores: boolean;
    pode_editar: boolean;
};

export type Auth = {
    user: Usuario | null;
    nome_informado: string | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    criada_em: string;
    usada_em: string | null;
};

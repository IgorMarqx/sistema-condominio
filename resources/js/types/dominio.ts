export type ConcessionariaId = 'energisa' | 'cagepa';

export type StatusFatura =
    | 'pendente_revisao'
    | 'pendente_confirmacao'
    | 'processada'
    | 'confirmada'
    | 'rejeitada';

export type StatusCobranca =
    | 'aberta'
    | 'aguardando_confirmacao'
    | 'paga'
    | 'cancelada';

export type Fatura = {
    id: number;
    concessionaria: ConcessionariaId;
    concessionaria_rotulo: string;
    servico: string;
    mes: string;
    mes_rotulo: string;
    mes_nome: string;
    status: StatusFatura;
    status_rotulo: string;
    origem: 'email' | 'upload';
    recebida_em: string;
    tem_pdf: boolean;
    medidor: {
        id: number;
        identificador: string;
        condominio: boolean;
        unidade: string | null;
    };
    valor?: number | null;
    vencimento?: string | null;
    emissao?: string | null;
    consumo?: number | null;
    unidade_medida?: string;
    leitura_anterior?: string | null;
    leitura_atual?: string | null;
    proxima_leitura?: string | null;
    linha_digitavel?: string | null;
    pix_copia_cola?: string | null;
    chave_nf3e?: string | null;
    paga_em?: string | null;
    rateada?: boolean | null;
    pendente?: boolean;
};

export type Validacao = { regra: string; ok: boolean; mensagem: string };

export type FaturaDetalhe = Fatura & {
    itens: { descricao: string; valor_centavos: number }[];
    validacoes: Validacao[];
    motivo_revisao: string | null;
    confianca: number | null;
    extrator: string | null;
    confirmada_em: string | null;
    confirmada_por: string | null;
    email: {
        remetente: string | null;
        assunto: string | null;
        spf: string | null;
        dkim: string | null;
    } | null;
    anexo_sha256: string | null;
};

export type PontoConsumo = {
    mes: string;
    rotulo: string;
    consumo: number | null;
    valor: number | null;
    fatura_id: number | null;
};

export type Pagamento = {
    id: number;
    valor: number;
    pago_em: string;
    status: 'aguardando' | 'confirmado' | 'recusado';
    motivo_recusa: string | null;
    nome_informado: string | null;
    comprovante_nome: string;
    enviado_em: string;
};

export type Cobranca = {
    id: number;
    unidade: string;
    mes: string;
    mes_rotulo: string;
    mes_nome: string;
    servico: string;
    valor: number;
    vencimento: string;
    vencida: boolean;
    status: StatusCobranca;
    status_rotulo: string;
};

export type CobrancaDetalhe = Cobranca & {
    itens: {
        tipo: 'energia' | 'agua' | 'contribuicao' | 'ajuste';
        descricao: string;
        valor: number;
        fatura_id: number | null;
    }[];
    pix: string | null;
    pagamentos: Pagamento[];
    em_aberto?: number;
};

export type Lancamento = {
    id: number;
    data: string;
    conta: string;
    conta_nome: string;
    tipo: 'entrada' | 'saida';
    valor: number;
    categoria: string;
    categoria_rotulo: string;
    descricao: string;
    origem: string;
    tem_comprovante: boolean;
    estorno_de_id: number | null;
    estornado: boolean | null;
    motivo: string | null;
    autor: string | null;
    criado_em: string | null;
    pode_ver_comprovante?: boolean;
};

export type MesCaixa = {
    mes: string;
    mes_rotulo: string;
    ano: number;
    entradas: number;
    saidas: number;
    fechado: boolean;
    lancamentos: Lancamento[];
};

export type Aviso = {
    id: number;
    tipo: string;
    titulo: string;
    mensagem: string | null;
    url: string | null;
    lida: boolean;
    criado_em: string;
    concessionaria?: ConcessionariaId | null;
};

export type LeituraPrevista = {
    concessionaria: ConcessionariaId;
    servico: string;
    data: string;
    origem: string | null;
};

import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import ExtratoCaixa from '@/components/condominio/extrato-caixa';
import TopoCaixa, {
    type DadosTopoCaixa,
} from '@/components/condominio/topo-caixa';
import Selo from '@/components/condominio/selo';
import type { MesCaixa } from '@/types';

type Props = DadosTopoCaixa & {
    meses: MesCaixa[];
    prestacoes: {
        id: number;
        mes: string;
        mes_rotulo: string;
        diferenca: number;
        tem_pdf: boolean;
    }[];
};

export default function Caixa(props: Props) {
    return (
        <>
            <Head title="Caixa" />
            <TopoCaixa
                saldo={props.saldo}
                contas={props.contas}
                ultimo_fechamento={props.ultimo_fechamento}
            />

            <div className="mt-4 space-y-6 px-4">
                <ExtratoCaixa meses={props.meses} base="/caixa" />

                {props.prestacoes.length > 0 && (
                    <section>
                        <TituloSecao>Prestações de contas</TituloSecao>
                        <Cartao className="divide-y divide-linha py-1">
                            {props.prestacoes.map((p) => (
                                <a
                                    key={p.id}
                                    href={`/arquivos/prestacao/${p.id}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 py-3"
                                >
                                    <span className="flex size-9 items-center justify-center rounded-xl bg-marinho-50 text-marinho-700">
                                        <FileText className="size-4" />
                                    </span>
                                    <span className="flex-1 text-sm font-bold">
                                        {p.mes_rotulo}
                                    </span>
                                    <Selo
                                        tom={
                                            p.diferenca === 0
                                                ? 'pago'
                                                : 'alerta'
                                        }
                                    >
                                        {p.diferenca === 0
                                            ? 'Conciliado'
                                            : 'Com diferença'}
                                    </Selo>
                                </a>
                            ))}
                        </Cartao>
                    </section>
                )}
            </div>
        </>
    );
}

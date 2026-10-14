import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import AvisoLinha from '@/components/condominio/aviso-linha';
import Cartao from '@/components/condominio/cartao';
import TopoTela from '@/components/condominio/topo-tela';
import Vazio from '@/components/condominio/vazio';
import type { Aviso } from '@/types';

export default function Avisos({ avisos }: { avisos: Aviso[] }) {
    const naoLidos = avisos.some((a) => !a.lida);

    return (
        <>
            <Head title="Avisos" />
            <TopoTela
                voltar="/"
                titulo="Avisos"
                acao={
                    naoLidos && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    '/avisos/lidas',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="flex items-center gap-1 rounded-full border border-linha bg-white px-3 py-1.5 text-xs font-bold"
                        >
                            <CheckCheck className="size-3.5" /> Marcar lidos
                        </button>
                    )
                }
            />
            <div className="px-4">
                <Cartao className="divide-y divide-linha py-1">
                    {avisos.length === 0 ? (
                        <Vazio icone={Bell} titulo="Nenhum aviso" />
                    ) : (
                        avisos.map((a) => <AvisoLinha key={a.id} aviso={a} />)
                    )}
                </Cartao>
            </div>
        </>
    );
}

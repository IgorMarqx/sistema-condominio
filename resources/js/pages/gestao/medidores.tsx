import { Head, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Plus } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import Cartao, { TituloSecao } from '@/components/condominio/cartao';
import { Campo, Entrada, Escolha } from '@/components/condominio/campo';
import Folha from '@/components/condominio/folha';
import IconeServico from '@/components/condominio/icone-servico';
import Selo from '@/components/condominio/selo';
import TopoTela from '@/components/condominio/topo-tela';
import type { ConcessionariaId } from '@/types';

type Medidor = {
    id: number;
    dono_tipo: 'condominio' | 'unidade';
    unidade: string | null;
    concessionaria: ConcessionariaId;
    concessionaria_rotulo: string;
    identificador: string;
    descricao: string | null;
    tem_senha_pdf: boolean;
    ativo: boolean;
};

export default function Medidores({
    medidores,
    unidades,
}: {
    medidores: Medidor[];
    unidades: string[];
}) {
    const { auth } = usePage().props;
    const [editando, setEditando] = useState<Medidor | 'novo' | null>(null);
    const doCondominio = medidores.filter((m) => m.dono_tipo === 'condominio');
    const dasUnidades = medidores.filter((m) => m.dono_tipo === 'unidade');

    return (
        <>
            <Head title="Medidores" />
            <TopoTela
                titulo="Medidores"
                contexto="Nº da UC (Energisa) e matrícula (Cagepa)"
                acao={
                    auth.user?.pode_editar && (
                        <Botao tamanho="sm" onClick={() => setEditando('novo')}>
                            <Plus /> Novo
                        </Botao>
                    )
                }
            />

            <div className="space-y-4 px-4 lg:px-0">
                <Lista
                    titulo="Condomínio"
                    medidores={doCondominio}
                    aoEditar={auth.user?.pode_editar ? setEditando : undefined}
                />
                <Lista
                    titulo="Unidades"
                    acao="a 1ª fatura de cada unidade cadastra o medidor dela"
                    medidores={dasUnidades}
                    aoEditar={auth.user?.pode_editar ? setEditando : undefined}
                />
            </div>

            {editando && (
                <FormMedidor
                    key={editando === 'novo' ? 'novo' : editando.id}
                    medidor={editando === 'novo' ? null : editando}
                    unidades={unidades}
                    aoFechar={() => setEditando(null)}
                />
            )}
        </>
    );
}

function Lista({
    titulo,
    acao,
    medidores,
    aoEditar,
}: {
    titulo: string;
    acao?: string;
    medidores: Medidor[];
    aoEditar?: (m: Medidor) => void;
}) {
    return (
        <section>
            <TituloSecao acao={acao}>{titulo}</TituloSecao>
            <Cartao className="divide-y divide-linha py-1">
                {medidores.length === 0 && (
                    <p className="py-5 text-center text-sm text-suave">
                        Nenhum medidor.
                    </p>
                )}
                {medidores.map((m) => (
                    <button
                        key={m.id}
                        type="button"
                        disabled={!aoEditar}
                        onClick={() => aoEditar?.(m)}
                        className="flex w-full items-center gap-3 py-3 text-left"
                    >
                        <IconeServico concessionaria={m.concessionaria} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-extrabold">
                                {m.dono_tipo === 'condominio'
                                    ? m.concessionaria_rotulo
                                    : `Apto ${m.unidade} · ${m.concessionaria_rotulo}`}
                            </p>
                            <p className="truncate font-mono text-xs text-suave">
                                {m.identificador}
                            </p>
                            {m.descricao && (
                                <p className="truncate text-xs text-suave">
                                    {m.descricao}
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-1">
                            {!m.ativo && <Selo tom="neutro">Inativo</Selo>}
                            {m.tem_senha_pdf && (
                                <Selo tom="conferir">
                                    <KeyRound className="size-3" /> senha PDF
                                </Selo>
                            )}
                        </div>
                    </button>
                ))}
            </Cartao>
        </section>
    );
}

function FormMedidor({
    medidor,
    unidades,
    aoFechar,
}: {
    medidor: Medidor | null;
    unidades: string[];
    aoFechar: () => void;
}) {
    const form = useForm({
        dono_tipo: medidor?.dono_tipo ?? 'condominio',
        unidade: medidor?.unidade ?? unidades[0] ?? '',
        concessionaria: medidor?.concessionaria ?? 'energisa',
        identificador: medidor?.identificador ?? '',
        descricao: medidor?.descricao ?? '',
        senha_pdf: '',
        remover_senha: false as boolean,
        ativo: medidor?.ativo ?? true,
    });

    const salvar = () => {
        const opcoes = { preserveScroll: true, onSuccess: aoFechar };

        if (medidor) {
            form.patch(`/gestao/medidores/${medidor.id}`, opcoes);
        } else {
            form.post('/gestao/medidores', opcoes);
        }
    };

    return (
        <Folha
            aberta
            aoMudar={(v) => !v && aoFechar()}
            titulo={medidor ? 'Editar medidor' : 'Novo medidor'}
            rodape={
                <Botao
                    tamanho="lg"
                    largo
                    carregando={form.processing}
                    onClick={salvar}
                >
                    Salvar
                </Botao>
            }
        >
            <div className="grid grid-cols-2 gap-3 pb-2">
                <Campo rotulo="Dono" erro={form.errors.dono_tipo}>
                    <Escolha
                        value={form.data.dono_tipo}
                        onChange={(e) =>
                            form.setData(
                                'dono_tipo',
                                e.target.value as 'condominio' | 'unidade',
                            )
                        }
                    >
                        <option value="condominio">Condomínio</option>
                        <option value="unidade">Unidade</option>
                    </Escolha>
                </Campo>
                {form.data.dono_tipo === 'unidade' ? (
                    <Campo rotulo="Apartamento" erro={form.errors.unidade}>
                        <Escolha
                            value={form.data.unidade}
                            onChange={(e) =>
                                form.setData('unidade', e.target.value)
                            }
                        >
                            {unidades.map((u) => (
                                <option key={u} value={u}>
                                    {u}
                                </option>
                            ))}
                        </Escolha>
                    </Campo>
                ) : (
                    <span />
                )}
                <Campo
                    rotulo="Concessionária"
                    erro={form.errors.concessionaria}
                >
                    <Escolha
                        value={form.data.concessionaria}
                        onChange={(e) =>
                            form.setData(
                                'concessionaria',
                                e.target.value as ConcessionariaId,
                            )
                        }
                    >
                        <option value="energisa">Energisa (energia)</option>
                        <option value="cagepa">Cagepa (água)</option>
                    </Escolha>
                </Campo>
                <Campo
                    rotulo={
                        form.data.concessionaria === 'energisa'
                            ? 'Nº da UC'
                            : 'Matrícula'
                    }
                    erro={form.errors.identificador}
                >
                    <Entrada
                        value={form.data.identificador}
                        onChange={(e) =>
                            form.setData('identificador', e.target.value)
                        }
                        placeholder={
                            form.data.concessionaria === 'energisa'
                                ? '5/1234567-8'
                                : '1234567-8'
                        }
                    />
                </Campo>
                <Campo
                    rotulo="Descrição"
                    detalhe="(opcional)"
                    className="col-span-2"
                    erro={form.errors.descricao}
                >
                    <Entrada
                        value={form.data.descricao}
                        onChange={(e) =>
                            form.setData('descricao', e.target.value)
                        }
                        placeholder="Ex.: bomba e iluminação"
                    />
                </Campo>
                <Campo
                    rotulo="Senha do PDF"
                    detalhe="(se a fatura vier protegida)"
                    className="col-span-2"
                    ajuda={
                        medidor?.tem_senha_pdf
                            ? 'Já existe uma senha guardada (cifrada). Deixe vazio para manter.'
                            : 'Guardada cifrada; usada só para abrir o PDF.'
                    }
                    erro={form.errors.senha_pdf}
                >
                    <Entrada
                        type="password"
                        autoComplete="off"
                        value={form.data.senha_pdf}
                        onChange={(e) =>
                            form.setData('senha_pdf', e.target.value)
                        }
                    />
                </Campo>
                {medidor?.tem_senha_pdf && (
                    <label className="col-span-2 flex items-center gap-2 text-sm font-semibold">
                        <input
                            type="checkbox"
                            className="size-4 accent-marinho-900"
                            checked={form.data.remover_senha}
                            onChange={(e) =>
                                form.setData('remover_senha', e.target.checked)
                            }
                        />
                        Remover a senha guardada
                    </label>
                )}
                <label className="col-span-2 flex items-center gap-2 text-sm font-semibold">
                    <input
                        type="checkbox"
                        className="size-4 accent-marinho-900"
                        checked={form.data.ativo}
                        onChange={(e) =>
                            form.setData('ativo', e.target.checked)
                        }
                    />
                    Ativo
                </label>
            </div>
        </Folha>
    );
}

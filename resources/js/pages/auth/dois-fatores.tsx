import { Head, useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import Botao from '@/components/condominio/botao';
import { Campo, Entrada } from '@/components/condominio/campo';
import LogoPredio from '@/components/condominio/logo-predio';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';

export default function DoisFatores() {
    const [recuperacao, setRecuperacao] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    return (
        <>
            <Head title="Verificação em duas etapas" />
            <div className="fixed inset-0 flex flex-col overflow-y-auto bg-white">
                <header className="topo-marinho area-segura-topo rounded-b-[28px] px-6 pt-8 pb-7 text-white">
                    <LogoPredio tamanho="md" />
                    <h1 className="mt-4 text-[26px] leading-tight font-extrabold tracking-tight">
                        Verificação em duas etapas
                    </h1>
                    <p className="mt-1 text-sm text-white/75">
                        {recuperacao
                            ? 'Digite um dos códigos de recuperação guardados.'
                            : 'Digite o código de 6 dígitos do aplicativo autenticador.'}
                    </p>
                </header>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) =>
                            recuperacao
                                ? { recovery_code: d.recovery_code }
                                : { code: d.code },
                        );
                        form.post('/two-factor-challenge', {
                            onError: () => form.reset(),
                        });
                    }}
                    className="mx-auto w-full max-w-sm space-y-5 px-6 pt-8 pb-8"
                >
                    {recuperacao ? (
                        <Campo
                            rotulo="Código de recuperação"
                            erro={form.errors.recovery_code}
                        >
                            <Entrada
                                icone={<KeyRound />}
                                autoComplete="one-time-code"
                                value={form.data.recovery_code}
                                onChange={(e) =>
                                    form.setData(
                                        'recovery_code',
                                        e.target.value,
                                    )
                                }
                            />
                        </Campo>
                    ) : (
                        <div className="flex flex-col items-center gap-2">
                            <InputOTP
                                maxLength={6}
                                pattern={REGEXP_ONLY_DIGITS}
                                value={form.data.code}
                                onChange={(v) => form.setData('code', v)}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                            >
                                <InputOTPGroup>
                                    {Array.from({ length: 6 }, (_, i) => (
                                        <InputOTPSlot
                                            key={i}
                                            index={i}
                                            className="size-12 text-lg font-bold"
                                        />
                                    ))}
                                </InputOTPGroup>
                            </InputOTP>
                            {form.errors.code && (
                                <p className="text-xs font-semibold text-red-600">
                                    {form.errors.code}
                                </p>
                            )}
                        </div>
                    )}

                    <Botao
                        type="submit"
                        tamanho="lg"
                        largo
                        carregando={form.processing}
                        disabled={
                            recuperacao
                                ? !form.data.recovery_code
                                : form.data.code.length < 6
                        }
                    >
                        <ShieldCheck /> Confirmar
                    </Botao>

                    <button
                        type="button"
                        onClick={() => {
                            setRecuperacao((r) => !r);
                            form.clearErrors();
                        }}
                        className="w-full text-center text-sm font-semibold text-marinho-700"
                    >
                        {recuperacao
                            ? 'Usar o código do aplicativo'
                            : 'Usar um código de recuperação'}
                    </button>
                </form>
            </div>
        </>
    );
}

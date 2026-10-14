/**
 * fetch com o token CSRF do Laravel (cookie XSRF-TOKEN), para chamadas JSON fora do Inertia
 * (confirmar senha antes de cadastrar biometria, marcar o aparelho).
 */
export async function postJson<T = unknown>(
    url: string,
    corpo: Record<string, unknown> = {},
): Promise<T> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    const resposta = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(corpo),
    });

    if (!resposta.ok) {
        const dados = (await resposta.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const primeiro = dados.errors
            ? Object.values(dados.errors)[0]?.[0]
            : undefined;

        throw new Error(
            primeiro ?? dados.message ?? 'Não foi possível concluir.',
        );
    }

    return (await resposta.json().catch(() => ({}))) as T;
}

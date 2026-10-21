/** Mensagem da biometria (passkey) em português, para as telas de entrada. */
export function traduzirErroPasskey(erro: string): string {
    if (/cancel|abort|NotAllowed|timed out|not allowed/i.test(erro)) {
        return 'Biometria cancelada. Toque de novo ou entre com a senha.';
    }

    if (/Unable to sign in|Invalid/i.test(erro)) {
        return 'Esta biometria não está mais válida. Entre com a senha e ative de novo.';
    }

    return erro;
}

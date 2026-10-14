<?php

namespace App\Services\Ingestao;

use App\Models\EmailRecebido;
use App\Services\Arquivos\Armazenamento;
use App\Services\Notificador;

/**
 * Etapas 3 a 10: classifica o e-mail e manda cada PDF para o ProcessadorPdf.
 */
class ProcessadorEmail
{
    private const REMETENTE_GMAIL = 'forwarding-noreply@google.com';

    public function __construct(
        private readonly Armazenamento $armazenamento,
        private readonly ProcessadorPdf $pdfs,
        private readonly Notificador $notificador,
    ) {}

    public function processar(EmailRecebido $email): EmailRecebido
    {
        if (! in_array($email->status, ['recebido', 'erro'], true)) {
            return $email;
        }

        $mensagem = MensagemEmail::ler($this->armazenamento->ler($email->s3_eml));
        $caixa = $email->caixaEntrada;
        $dono = $caixa?->unidade;

        if ($this->ehVerificacao($email, $mensagem)) {
            return $this->registrarVerificacao($email, $mensagem);
        }

        $limite = (int) config('condominio.anexo_max_bytes');
        $pdfs = array_values(array_filter($mensagem->pdfs(), fn ($p) => $p['tamanho'] > 0 && $p['tamanho'] <= $limite));

        if ($pdfs === []) {
            $grandes = count($mensagem->pdfs()) > 0;
            $email->update([
                'tipo' => 'sem_anexo',
                'status' => 'revisao',
                'links' => $mensagem->links() ?: null,
                'erro' => $grandes ? 'PDF acima de 10 MB.' : 'E-mail sem PDF. Os links do corpo foram registrados e não foram abertos.',
                'processado_em' => now(),
            ]);

            $this->avisarRevisao($email, $email->erro);

            return $email;
        }

        $email->tipo = 'fatura';
        $resultados = [];

        foreach ($pdfs as $pdf) {
            $resultados[] = $this->pdfs->processar($pdf['conteudo'], $pdf['nome'], $dono, $email);
        }

        $situacoes = array_map(fn (ResultadoPdf $r) => $r->situacao, $resultados);
        $motivos = array_values(array_filter(array_map(fn (ResultadoPdf $r) => $r->situacao === 'fatura' ? null : $r->motivo, $resultados)));

        $email->status = match (true) {
            in_array('fatura', $situacoes, true) => 'processado',
            array_unique($situacoes) === ['duplicado'] => 'duplicado',
            default => 'revisao',
        };
        $email->erro = $motivos ? implode(' ', array_unique($motivos)) : null;
        $email->processado_em = now();
        $email->save();

        if ($email->status === 'revisao') {
            $this->avisarRevisao($email, (string) $email->erro);
        }

        return $email;
    }

    private function ehVerificacao(EmailRecebido $email, MensagemEmail $mensagem): bool
    {
        $remetente = (string) $email->remetente;
        $assunto = mb_strtolower((string) $email->assunto);

        if ($remetente === self::REMETENTE_GMAIL || str_contains($assunto, 'gmail forwarding confirmation') || str_contains($assunto, 'confirmação de encaminhamento')) {
            return true;
        }

        // Concessionária pedindo para confirmar o e-mail cadastrado na fatura digital.
        $daConcessionaria = (bool) preg_match('/energisa|cagepa/i', $remetente);

        return $daConcessionaria
            && $mensagem->pdfs() === []
            && (bool) preg_match('/confirm|verific|valida|ativ[ae]|cadastr/u', $assunto);
    }

    private function registrarVerificacao(EmailRecebido $email, MensagemEmail $mensagem): EmailRecebido
    {
        $texto = ($email->assunto ?? '')."\n".$mensagem->texto();
        $codigo = null;

        if (preg_match('/(?:c[oó]digo de confirma[cç][aã]o|confirmation code)\s*:?\s*(\d{6,12})/iu', $texto, $m)
            || preg_match('/\(#(\d{6,12})\)/', $texto, $m)) {
            $codigo = $m[1];
        }

        $link = collect($mensagem->links())->first(fn (string $l) => str_contains($l, 'mail-settings.google.com') || str_contains($l, 'mail.google.com'))
            ?? collect($mensagem->links())->first(fn (string $l) => (bool) preg_match('/confirm|verific|valida|ativ/i', $l));

        $email->update([
            'tipo' => 'verificacao',
            'status' => 'processado',
            'verificacao_codigo' => $codigo,
            'verificacao_link' => $link,
            'processado_em' => now(),
        ]);

        $titulo = $email->remetente === self::REMETENTE_GMAIL ? 'Confirmação do Gmail recebida' : 'Confirmação de e-mail recebida';
        $mensagemAviso = $codigo ? 'Código: '.$codigo : 'Abra a tela "Receber faturas" para ver o link.';
        $unidadeId = $email->caixaEntrada?->unidade_id;

        if ($unidadeId) {
            $this->notificador->unidade($unidadeId, 'verificacao_email', $titulo, $mensagemAviso, '/receber-faturas', 'verificacao:'.$email->id);
        } else {
            $this->notificador->admin('verificacao_email', $titulo.' (condomínio)', $mensagemAviso, '/gestao/receber-faturas', 'verificacao:'.$email->id);
        }

        return $email;
    }

    private function avisarRevisao(EmailRecebido $email, string $motivo): void
    {
        $unidadeId = $email->caixaEntrada?->unidade_id;

        if ($unidadeId) {
            $this->notificador->unidade($unidadeId, 'email_revisao', 'Um e-mail recebido precisa de atenção', $motivo, '/receber-faturas', 'email-revisao:'.$email->id);

            return;
        }

        $this->notificador->admin('email_revisao', 'E-mail do condomínio precisa de revisão', $motivo, '/gestao/faturas', 'email-revisao:'.$email->id);
    }
}

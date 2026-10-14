<?php

return [
    // Disco dos documentos (PDFs, comprovantes, .eml). Em produção: "s3".
    'disco' => env('CONDOMINIO_DISCO', 'local'),

    // Domínio que recebe as faturas: ap302-x8vd@<dominio>.
    'dominio_faturas' => env('FATURAS_DOMINIO', 'faturas.localhost'),

    // Webhook genérico de e-mail bruto (Mailgun "store and forward", Cloudflare Email Worker...).
    'webhook_email_token' => env('FATURAS_WEBHOOK_TOKEN'),

    // SES → SNS: tópico aceito (ARN). Vazio = recusa notificações SNS.
    'sns_topic_arn' => env('FATURAS_SNS_TOPIC_ARN'),

    // Bucket onde o SES grava o .eml bruto (pode ser o mesmo dos documentos).
    'ses_bucket_disco' => env('FATURAS_SES_DISCO', 's3'),

    'anexo_max_bytes' => 10 * 1024 * 1024,

    // Binários do poppler/qpdf. Sem eles, cai no parser PHP (menos fiel ao layout).
    'pdftotext' => env('PDFTOTEXT_BIN', 'pdftotext'),
    'qpdf' => env('QPDF_BIN', 'qpdf'),

    // Backups cifrados do banco (retenção 90 dias).
    'disco_backup' => env('CONDOMINIO_DISCO_BACKUP', env('CONDOMINIO_DISCO', 'local')),
];

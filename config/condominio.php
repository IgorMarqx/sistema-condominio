<?php

return [
    // Disco dos documentos (PDFs, comprovantes). Com AWS_BUCKET preenchido vai para o S3, com as
    // mesmas variáveis AWS_* do sistema-ponto; CONDOMINIO_DISCO força outro disco.
    'disco' => env('CONDOMINIO_DISCO', env('AWS_BUCKET') ? 's3' : 'local'),

    'anexo_max_bytes' => 10 * 1024 * 1024,

    // Binários do poppler/qpdf. Sem eles, cai no parser PHP (menos fiel ao layout).
    'pdftotext' => env('PDFTOTEXT_BIN', 'pdftotext'),
    'qpdf' => env('QPDF_BIN', 'qpdf'),

    // Backups cifrados do banco (retenção 90 dias).
    'disco_backup' => env('CONDOMINIO_DISCO_BACKUP', env('CONDOMINIO_DISCO', env('AWS_BUCKET') ? 's3' : 'local')),
];

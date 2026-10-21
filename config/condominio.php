<?php

return [
    // Disco dos documentos (PDFs, comprovantes). Em produção: "s3".
    'disco' => env('CONDOMINIO_DISCO', 'local'),

    'anexo_max_bytes' => 10 * 1024 * 1024,

    // Binários do poppler/qpdf. Sem eles, cai no parser PHP (menos fiel ao layout).
    'pdftotext' => env('PDFTOTEXT_BIN', 'pdftotext'),
    'qpdf' => env('QPDF_BIN', 'qpdf'),

    // Backups cifrados do banco (retenção 90 dias).
    'disco_backup' => env('CONDOMINIO_DISCO_BACKUP', env('CONDOMINIO_DISCO', 'local')),
];

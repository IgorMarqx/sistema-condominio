<?php

namespace App\Jobs;

use App\Models\EmailRecebido;
use App\Services\Ingestao\ProcessadorEmail;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessarEmailRecebido implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $emailId)
    {
        $this->onQueue('ingestao');
    }

    public function uniqueId(): string
    {
        return (string) $this->emailId;
    }

    public function handle(ProcessadorEmail $processador): void
    {
        $email = EmailRecebido::query()->find($this->emailId);

        if ($email) {
            $processador->processar($email);
        }
    }

    public function failed(Throwable $e): void
    {
        EmailRecebido::query()->whereKey($this->emailId)->update([
            'status' => 'erro',
            'erro' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}

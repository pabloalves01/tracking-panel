<?php

namespace App\Jobs;

use App\Services\Auth\VerificationCodeService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Roda depois da resposta HTTP para o tempo da consulta não revelar se a conta existe.
 * O código fica só na memória deste processo, não no log.
 */
class DeliverVerificationCode
{
    use Dispatchable;

    public function __construct(
        public int $codeId,
        public string $plain,
        public ?string $token,
    ) {}

    public function handle(VerificationCodeService $codes): void
    {
        $codes->deliver($this->codeId, $this->plain, $this->token);
    }
}

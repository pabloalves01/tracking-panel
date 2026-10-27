<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendVerificationCodeRequest;
use App\Services\Auth\VerificationCodeService;

class VerificationCodeController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    public function store(SendVerificationCodeRequest $request)
    {
        $result = $this->codes->issue(
            $request->validated('email'),
            $request->validated('purpose'),
            $request->input('token'),
        );

        return response()->json([
            'message' => $this->message($result),
            'sent' => $result['sent'],
            'account_exists' => $result['account_exists'],
            'retry_after' => $result['retry_after'],
        ]);
    }

    /**
     * @param  array{sent: bool, retry_after: int, account_exists: bool}  $result
     */
    private function message(array $result): string
    {
        if ($result['account_exists']) {
            return 'Este e-mail já tem conta. Entre para aceitar o convite.';
        }

        if (! $result['sent']) {
            return $this->codes->waitMessage($result['retry_after']);
        }

        return 'Enviamos um código para este e-mail.';
    }
}

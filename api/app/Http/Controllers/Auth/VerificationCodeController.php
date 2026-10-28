<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendVerificationCodeRequest;
use App\Services\Auth\VerificationCodeService;

class VerificationCodeController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    /** A resposta não diz se a conta existe: isso só depois do código. */
    public function store(SendVerificationCodeRequest $request)
    {
        $result = $this->codes->issue(
            $request->validated('email'),
            $request->validated('purpose'),
        );

        return response()->json([
            'message' => $result['sent']
                ? 'Enviamos um código para este e-mail.'
                : $this->codes->waitMessage($result['retry_after']),
            'sent' => $result['sent'],
            'retry_after' => $result['retry_after'],
        ]);
    }
}

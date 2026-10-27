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
        $retryAfter = $this->codes->issue(
            $request->validated('email'),
            $request->validated('purpose'),
            $request->input('token'),
        );

        return response()->json([
            'message' => 'Se este e-mail puder receber o código, ele foi enviado.',
            'retry_after' => $retryAfter,
        ]);
    }
}

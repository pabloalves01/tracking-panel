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
            'message' => 'Enviamos um código para este e-mail.',
            'retry_after' => $retryAfter,
        ]);
    }
}

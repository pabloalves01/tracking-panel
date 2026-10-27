<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Auth\VerificationCodeService;
use App\Support\Text;

class PasswordResetController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    public function forgot(ForgotPasswordRequest $request)
    {
        $result = $this->codes->issue(
            $request->validated('email'),
            VerificationCode::PASSWORD_RESET,
        );

        return response()->json([
            'message' => $result['sent']
                ? 'Se este e-mail puder receber o código, ele foi enviado.'
                : $this->codes->waitMessage($result['retry_after']),
            'sent' => $result['sent'],
            'retry_after' => $result['retry_after'],
        ]);
    }

    public function reset(ResetPasswordRequest $request)
    {
        $email = Text::email($request->validated('email'));
        $this->codes->consume($email, VerificationCode::PASSWORD_RESET, $request->validated('code'));

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            abort(422, 'Código inválido');
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Senha atualizada. Entre com a nova senha.',
        ]);
    }
}

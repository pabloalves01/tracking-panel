<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Auth\VerificationCodeService;
use App\Support\Text;
use DomainException;
use Illuminate\Support\Facades\DB;

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

        // O código é consumido dentro da transação que troca a senha: se
        // algo falhar depois, ele não foi gasto.
        DB::transaction(function () use ($request, $email) {
            $this->codes->consume($email, VerificationCode::PASSWORD_RESET, $request->validated('code'));

            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if (! $user) {
                throw new DomainException(VerificationCodeService::PUBLIC_REJECTION);
            }

            $user->forceFill([
                'password' => $request->validated('password'),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            // Trocar a senha encerra as outras sessões: se alguém entrou com
            // a senha antiga, o acesso dele cai aqui.
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Senha atualizada. Entre com a nova senha.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckAccountCodeRequest;
use App\Http\Requests\Auth\RegisterAccountRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Access\InvitationService;
use App\Services\Auth\AccountRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * Criar conta. O código chega por `POST /auth/verification-codes` com a
 * finalidade `account_register`.
 */
class AccountRegistrationController extends Controller
{
    public function __construct(
        private readonly AccountRegistrationService $registration,
        private readonly InvitationService $invitations,
    ) {}

    /** Confere o código e diz se a conta já existe. */
    public function check(CheckAccountCodeRequest $request): JsonResponse
    {
        $exists = $this->registration->check(
            $request->validated('email'),
            $request->validated('code'),
        );

        return response()->json([
            'account_exists' => $exists,
            'message' => $exists
                ? 'Este e-mail já tem uma conta. Entre com a sua senha.'
                : 'Código confirmado.',
        ]);
    }

    /**
     * Cria a conta e devolve a sessão, sem organização.
     *
     * A resposta tem a forma do login, para o client tratar "acabei de
     * entrar" de um jeito só.
     */
    public function store(RegisterAccountRequest $request): JsonResponse
    {
        $user = $this->registration->register(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('name'),
            $request->validated('password'),
        );

        $token = $user->createToken('web');
        $user->withAccessToken($token->accessToken);

        return response()->json([
            'message' => 'Conta criada',
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
            'organizations' => [],
            'invitations' => $this->invitations->pendingFor($user),
        ], 201);
    }
}

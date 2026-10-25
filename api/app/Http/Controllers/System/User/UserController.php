<?php

namespace App\Http\Controllers\System\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\User\LookupUserRequest;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Busca uma conta pelo e-mail completo, para vincular à empresa.
     * Devolve só nome e e-mail; não existe listagem de usuários.
     */
    public function lookup(LookupUserRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            abort(404, 'Nenhuma conta encontrada com este e-mail. Peça para a pessoa criar uma conta na Logiv');
        }

        return response()->json([
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}

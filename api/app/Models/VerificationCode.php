<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Código de seis dígitos enviado por e-mail.
 *
 * Eram três finalidades — cadastro de posto, cadastro por convite e
 * recuperação de senha — e as duas primeiras criavam conta junto com a
 * organização. Agora conta é uma coisa só: o código de cadastro serve para
 * criar a conta, venha a pessoa de um convite ou do cadastro de posto, e a
 * organização é criada ou aceita depois, já autenticada.
 */
class VerificationCode extends Model
{
    public const ACCOUNT_REGISTER = 'account_register';

    public const PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'email',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'invalidated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}

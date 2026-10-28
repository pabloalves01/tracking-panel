<?php

namespace App\Exceptions;

use DomainException;

/**
 * Pediram para criar uma conta com um e-mail que já tem uma.
 *
 * Só sobe depois de o código do e-mail ter sido conferido — antes disso o
 * sistema não diz se a conta existe. Acontece quando a conta passa a existir
 * entre a conferência e o envio do formulário: um convite aceito em outra
 * aba, um segundo clique. Vira 409 com `code: account_exists`, e a tela troca
 * o formulário de cadastro pela entrada com senha.
 *
 * A senha da conta que já existe nunca é tocada.
 */
class AccountAlreadyExists extends DomainException
{
    public function __construct()
    {
        parent::__construct('Este e-mail já tem uma conta. Entre com a sua senha.');
    }
}

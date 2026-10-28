<?php

namespace App\Exceptions;

use DomainException;

/**
 * O que o usuário mandou não serve, e ele conserta digitando de novo:
 * um código de verificação errado, expirado ou já usado, um CNPJ malformado.
 *
 * Vira 422, o mesmo status da validação do Laravel, porque é a mesma coisa
 * do ponto de vista de quem chama — só que a regra mora no serviço, não na
 * FormRequest. Fica separada do `DomainException` cru, que vira 409 e quer
 * dizer outra coisa: o pedido está certo, o estado é que não permite.
 */
class RejectedInput extends DomainException {}

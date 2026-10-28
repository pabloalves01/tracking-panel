<?php

namespace App\Exceptions;

use DomainException;

/**
 * O recurso pedido não existe — um token de convite que nunca existiu, um
 * membro que não é desta organização.
 *
 * Vira 404. Importa para quem procura token por força bruta: um 409 com
 * "não encontrado ou já utilizado" dizia que a rota existe e devolvia uma
 * mensagem diferente do 404 de rota inexistente.
 */
class NotFound extends DomainException {}

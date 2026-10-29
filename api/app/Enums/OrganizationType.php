<?php

namespace App\Enums;

/**
 * Os tipos de organização.
 *
 * Aberto de propósito: quando o fornecedor ganhar painel próprio, ele é um
 * `case` novo aqui — não uma tabela, nem uma terceira cópia das rotas de
 * usuários e convites.
 */
enum OrganizationType: string
{
    case Company = 'company';
    case FuelStation = 'fuel_station';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::FuelStation => 'Posto',
        };
    }

    /** Para onde o usuário vai ao entrar nesta organização. */
    public function homePath(): string
    {
        return match ($this) {
            self::Company => '/app',
            self::FuelStation => '/posto',
        };
    }
}

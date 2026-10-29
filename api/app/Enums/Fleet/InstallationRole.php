<?php

namespace App\Enums\Fleet;

/**
 * A função do rastreador no veículo. O principal é a posição oficial do
 * veículo; o reserva cobre quando o principal falha ou é violado.
 */
enum InstallationRole: string
{
    case Primary = 'primary';
    case Backup = 'backup';
}

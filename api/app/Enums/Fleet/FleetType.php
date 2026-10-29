<?php

namespace App\Enums\Fleet;

/**
 * De quem é o veículo na operação: da empresa, de um agregado (motorista
 * dono que trabalha só para ela) ou de um terceiro contratado por frete.
 */
enum FleetType: string
{
    case Own = 'own';
    case Aggregated = 'aggregated';
    case ThirdParty = 'third_party';
}

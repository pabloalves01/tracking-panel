<?php

namespace App\Enums\Fleet;

/**
 * O grupo do veículo, como no CTB: automotor (tem propulsão própria —
 * cavalo, caminhão, carro, moto) ou implemento (reboque, semirreboque,
 * dolly). No engate, o automotor puxa e o implemento é puxado.
 *
 * Não é coluna de `vehicles`: sai do tipo (`vehicle_types.kind`).
 */
enum VehicleKind: string
{
    case MotorVehicle = 'motor_vehicle';
    case Trailer = 'trailer';
}

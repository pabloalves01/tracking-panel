<?php

namespace App\Enums;

/**
 * O catálogo de papéis de uma entidade.
 *
 * Adicionar um papel é adicionar um caso aqui. Só precisa de tabela de
 * detalhe o papel que tiver dados próprios — hoje, apenas o motorista.
 */
enum EntityRole: string
{
    case Driver = 'driver';
    case VehicleOwner = 'vehicle_owner';
    case VehicleResponsible = 'vehicle_responsible';
    case Employee = 'employee';
    case Supplier = 'supplier';
    case Customer = 'customer';
    case Carrier = 'carrier';

    /**
     * Como o papel aparece para o usuário, no singular.
     */
    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Motorista',
            self::VehicleOwner => 'Proprietário',
            self::VehicleResponsible => 'Responsável',
            self::Employee => 'Funcionário',
            self::Supplier => 'Fornecedor',
            self::Customer => 'Cliente',
            self::Carrier => 'Transportadora',
        };
    }

    /**
     * O gênero gramatical, para montar mensagens sem concordância errada
     * ("Cliente desativado", "Transportadora desativada").
     */
    public function isFeminine(): bool
    {
        return $this === self::Carrier;
    }

    /**
     * O segmento usado na URL do client.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Driver => 'motoristas',
            self::VehicleOwner => 'proprietarios',
            self::VehicleResponsible => 'responsaveis',
            self::Employee => 'funcionarios',
            self::Supplier => 'fornecedores',
            self::Customer => 'clientes',
            self::Carrier => 'transportadoras',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

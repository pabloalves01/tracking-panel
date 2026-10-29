<?php

namespace App\Enums;

use App\Models\Registry\Carrier;
use App\Models\Registry\Customer;
use App\Models\Registry\Driver;
use App\Models\Registry\Employee;
use App\Models\Registry\Supplier;
use App\Models\Registry\VehicleOwner;
use App\Models\Registry\VehicleResponsible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * O catálogo de papéis de uma entidade.
 *
 * Cada papel tem tabela, model, controller e rotas próprios. O enum é o que
 * os liga: é por ele que a entidade sabe quais papéis procurar e que a
 * tela geral filtra por papel.
 *
 * Papel novo: um caso aqui, a migration, o model (`use IsEntityRole`), a
 * request, o resource, o controller e a linha em `routes/api.php`.
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
     * O model da tabela do papel.
     *
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Driver => Driver::class,
            self::VehicleOwner => VehicleOwner::class,
            self::VehicleResponsible => VehicleResponsible::class,
            self::Employee => Employee::class,
            self::Supplier => Supplier::class,
            self::Customer => Customer::class,
            self::Carrier => Carrier::class,
        };
    }

    /**
     * O nome da relação em `Entity` — `$entity->vehicleOwner`.
     */
    public function relation(): string
    {
        return Str::camel($this->value);
    }

    /**
     * As relações de todos os papéis, para carregar de uma vez.
     *
     * @return array<int, string>
     */
    public static function relations(): array
    {
        return array_map(fn (self $role) => $role->relation(), self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

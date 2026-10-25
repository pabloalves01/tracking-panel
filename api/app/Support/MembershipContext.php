<?php

namespace App\Support;

use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Empresa ou posto onde ficam os membros e os convites.
 * As regras são as mesmas nos dois; muda só a tabela e a coluna.
 */
class MembershipContext
{
    public function __construct(public string $type, public int $id) {}

    public static function company(int $id): self
    {
        return new self('company', $id);
    }

    public static function station(int $id): self
    {
        return new self('fuel_station', $id);
    }

    /**
     * Contexto do token atual. `ensure.company` ou `ensure.station` já conferiu o vínculo.
     */
    public static function current(): self
    {
        $token = request()->user()->currentAccessToken();

        return $token->company_id
            ? self::company($token->company_id)
            : self::station($token->fuel_station_id);
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function column(): string
    {
        return $this->isCompany() ? 'company_id' : 'fuel_station_id';
    }

    /**
     * Vínculos deste contexto (company_user ou fuel_station_user).
     */
    public function members(): Builder
    {
        $model = $this->isCompany() ? CompanyUser::class : FuelStationUser::class;

        return $model::query()->where($this->column(), $this->id);
    }

    /**
     * Colunas de contexto do token: uma preenchida e a outra nula.
     *
     * @return array{company_id: ?int, fuel_station_id: ?int}
     */
    public function tokenColumns(): array
    {
        return [
            'company_id' => $this->isCompany() ? $this->id : null,
            'fuel_station_id' => $this->isCompany() ? null : $this->id,
        ];
    }

    /** "da empresa" ou "do posto". */
    public function ofLabel(): string
    {
        return $this->isCompany() ? 'da empresa' : 'do posto';
    }

    /** "A empresa" ou "O posto". */
    public function theLabel(): string
    {
        return $this->isCompany() ? 'A empresa' : 'O posto';
    }
}

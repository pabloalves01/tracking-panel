<?php

namespace App\Providers;

use App\Models\CompanyFuelStation;
use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use App\Policies\CompanyFuelStationPolicy;
use App\Policies\MembershipPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use MatanYadaev\EloquentSpatial\EloquentSpatial;
use MatanYadaev\EloquentSpatial\Enums\Srid;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && config('mail.default') === 'log') {
            throw new RuntimeException('MAIL_MAILER=log não pode ser usado em produção: códigos e tokens iriam para o log.');
        }

        // Set the default SRID to WGS84 (EPSG:4326)
        EloquentSpatial::setDefaultSrid(Srid::WGS84);

        Gate::policy(CompanyFuelStation::class, CompanyFuelStationPolicy::class);
        Gate::policy(CompanyUser::class, MembershipPolicy::class);
        Gate::policy(FuelStationUser::class, MembershipPolicy::class);
    }
}

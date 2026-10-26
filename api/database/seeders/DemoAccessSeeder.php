<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Membership;
use Illuminate\Database\Seeder;

/**
 * Contas de demonstração. A senha de todas é mesquita2016.
 *
 * - logiv@logiv.com.br: administrador do sistema
 * - ana@melo.test: proprietária da Transportes Melo e membro do Posto Exemplo
 * - bruno@melo.test: membro da Transportes Melo
 * - carla@posto.test: proprietária do Posto Exemplo
 * - eduardo@posto.test: proprietário do Posto Norte (pedido de vínculo pendente)
 * - diego@melo.test: convite pendente de membro. O link é /auth/convite/convite-demo-diego
 */
class DemoAccessSeeder extends Seeder
{
    public function run(): void
    {
        $ana = $this->user('Ana Melo', 'ana@melo.test');
        $bruno = $this->user('Bruno Melo', 'bruno@melo.test');
        $carla = $this->user('Carla Posto', 'carla@posto.test');
        $eduardo = $this->user('Eduardo Norte', 'eduardo@posto.test');

        $company = Company::query()->create([
            'legal_name' => 'Transportes Melo LTDA',
            'trade_name' => 'Transportes Melo',
            'cnpj' => '00000000000191',
            'status' => 'active',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $ana->id,
            'role' => Membership::OWNER,
            'status' => Membership::ACTIVE,
        ]);
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $bruno->id,
            'role' => Membership::MEMBER,
            'status' => Membership::ACTIVE,
        ]);

        $exemplo = $this->station('19131243000197', 'POSTO EXEMPLO LTDA', 'Posto Exemplo');
        $norte = $this->station('34028316000103', 'POSTO NORTE LTDA', 'Posto Norte');

        FuelStationUser::query()->create([
            'fuel_station_id' => $exemplo->id,
            'user_id' => $carla->id,
            'role' => Membership::OWNER,
            'status' => Membership::ACTIVE,
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $exemplo->id,
            'user_id' => $ana->id,
            'role' => Membership::MEMBER,
            'status' => Membership::ACTIVE,
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $norte->id,
            'user_id' => $eduardo->id,
            'role' => Membership::OWNER,
            'status' => Membership::ACTIVE,
        ]);

        CompanyFuelStation::query()->create([
            'company_id' => $company->id,
            'fuel_station_id' => $exemplo->id,
            'status' => 'active',
        ]);
        CompanyFuelStation::query()->create([
            'company_id' => $company->id,
            'fuel_station_id' => $norte->id,
            'status' => 'request_sent',
        ]);

        Invitation::query()->create([
            'company_id' => $company->id,
            'email' => 'diego@melo.test',
            'role' => Membership::MEMBER,
            'status' => Invitation::PENDING,
            'token_hash' => hash('sha256', 'convite-demo-diego'),
            'expires_at' => now()->addDays(Invitation::DAYS),
            'invited_by' => $ana->id,
        ]);
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'mesquita2016',
            'email_verified_at' => now(),
        ]);
    }

    private function station(string $cnpj, string $corporateName, string $tradeName): FuelStation
    {
        return FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => $corporateName,
            'trade_name' => $tradeName,
            'status' => 'active',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\FuelStationAgreement;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contas de demonstração. A senha de todas é mesquita2016.
 *
 * Cobre todos os caminhos que a tela de escolher organização precisa
 * mostrar: uma organização só, várias organizações, convite pendente e
 * conta que ainda não existe.
 *
 * | Conta                 | Situação                                            |
 * |-----------------------|-----------------------------------------------------|
 * | logiv@logiv.com.br    | admin do sistema, sem organização                   |
 * | ana@melo.test         | Transportes Melo e Posto Exemplo — dois contextos   |
 * | bruno@melo.test       | só a Transportes Melo — entra direto                |
 * | carla@posto.test      | só o Posto Exemplo — entra direto                   |
 * | eduardo@posto.test    | Posto Norte, com pedido de convênio pendente        |
 * | diego@melo.test       | sem conta; convite pendente em /auth/convite/convite-demo-diego |
 */
class DemoAccessSeeder extends Seeder
{
    private const PASSWORD = 'mesquita2016';

    private const DIEGO_TOKEN = 'convite-demo-diego';

    public function run(): void
    {
        $ana = $this->user('Ana Melo', 'ana@melo.test');
        $bruno = $this->user('Bruno Melo', 'bruno@melo.test');
        $carla = $this->user('Carla Posto', 'carla@posto.test');
        $eduardo = $this->user('Eduardo Norte', 'eduardo@posto.test');

        $melo = $this->organization(OrganizationType::Company, '00000000000191', 'Transportes Melo LTDA', 'Transportes Melo');
        $exemplo = $this->organization(OrganizationType::FuelStation, '11222333000181', 'Posto Exemplo LTDA', 'Posto Exemplo');
        $norte = $this->organization(OrganizationType::FuelStation, '19131243000197', 'Posto Norte LTDA', 'Posto Norte');

        // Ana tem dois contextos: cai no seletor de organização.
        $this->member($melo, $ana);
        $this->member($exemplo, $ana);

        // Bruno e Carla têm um só: entram direto.
        $this->member($melo, $bruno);
        $this->member($exemplo, $carla);
        $this->member($norte, $eduardo);

        // Um convênio ativo e um pedido esperando resposta.
        $this->link($melo, $exemplo, FuelStationAgreement::ACTIVE);
        $this->link($melo, $norte, FuelStationAgreement::REQUEST_SENT);

        // Convite para quem ainda não tem conta. O token é fixo para dar
        // para abrir o link direto, sem procurar no log.
        Invitation::query()->create([
            'organization_id' => $melo->getKey(),
            'email' => 'diego@melo.test',
            'token_hash' => hash('sha256', self::DIEGO_TOKEN),
            'status' => Invitation::PENDING,
            'expires_at' => now()->addDays(Invitation::DAYS),
            'invited_by' => $ana->getKey(),
        ]);

        $this->command?->info('Convite de demonstração: /auth/convite/'.self::DIEGO_TOKEN);
    }

    private function user(string $name, string $email): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function organization(
        OrganizationType $type,
        string $document,
        string $legalName,
        string $tradeName,
    ): Organization {
        return Organization::query()->create([
            'type' => $type,
            'document' => $document,
            'legal_name' => $legalName,
            'trade_name' => $tradeName,
            'status' => Organization::ACTIVE,
        ]);
    }

    private function member(Organization $organization, User $user): Membership
    {
        return Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);
    }

    private function link(Organization $company, Organization $fuelStation, string $status): FuelStationAgreement
    {
        return FuelStationAgreement::query()->create([
            'company_id' => $company->getKey(),
            'fuel_station_id' => $fuelStation->getKey(),
            'status' => $status,
        ]);
    }
}

<?php

namespace Tests\Support;

use App\Enums\OrganizationType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

/**
 * Monta uma conta dentro de uma organização e autentica o teste nela.
 *
 * Antes cada arquivo de teste repetia o mesmo `setUp`: criava a empresa na
 * mão, anexava o usuário com um papel, forjava o token e ainda registrava
 * um `CurrentCompany` no container. Com `organizations` + `memberships` e
 * a organização gravada no próprio token, sobra só isto.
 */
trait ActsAsMember
{
    protected ?Organization $organization = null;

    protected ?User $member = null;

    protected function actingAsMemberOf(
        ?Organization $organization = null,
        ?User $user = null,
        string $status = Membership::ACTIVE,
    ): static {
        $this->organization = $organization ?? Organization::factory()->company()->create();
        $this->member = $user ?? User::factory()->create();

        Membership::query()->updateOrCreate(
            ['organization_id' => $this->organization->getKey(), 'user_id' => $this->member->getKey()],
            ['status' => $status],
        );

        return $this->enter($this->member, $this->organization);
    }

    protected function actingAsFuelStationMemberOf(?Organization $fuelStation = null, ?User $user = null): static
    {
        return $this->actingAsMemberOf(
            $fuelStation ?? Organization::factory()->fuelStation()->create(),
            $user,
        );
    }

    /** O admin do sistema entra sem organização: é assim que ele existe. */
    protected function actingAsSystemAdmin(?User $user = null): static
    {
        $admin = $user ?? User::factory()->systemAdmin()->create();

        return $this->enter($admin, null);
    }

    /**
     * Emite o token já com a organização escolhida — o mesmo que o
     * `POST /auth/organization` faz.
     */
    protected function enter(User $user, ?Organization $organization): static
    {
        // As requisições de um teste compartilham a mesma instância da
        // aplicação, e o guard do Sanctum guarda o usuário que resolveu.
        // Sem isto, trocar de identidade no meio do teste não troca nada:
        // o header muda e a resposta continua vindo como o primeiro usuário.
        $this->app['auth']->forgetGuards();

        $token = $user->createToken('web');

        if ($organization) {
            $token->accessToken->forceFill([
                'organization_id' => $organization->getKey(),
            ])->save();
        }

        $this->withToken($token->plainTextToken);

        return $this;
    }

    protected function otherCompany(): Organization
    {
        return Organization::factory()->company()->create();
    }

    protected function fuelStation(): Organization
    {
        return Organization::factory()->state(['type' => OrganizationType::FuelStation])->create();
    }
}

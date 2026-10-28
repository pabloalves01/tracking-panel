<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * O CNPJ é gerado com os dígitos verificadores certos: a validação do
     * sistema é real, e um documento aleatório seria recusado.
     */
    public function definition(): array
    {
        return [
            'type' => OrganizationType::Company,
            'document' => self::cnpj(),
            'legal_name' => mb_strtoupper($this->faker->company()).' LTDA',
            'trade_name' => $this->faker->company(),
            'status' => Organization::ACTIVE,
        ];
    }

    public function company(): static
    {
        return $this->state(['type' => OrganizationType::Company]);
    }

    public function fuelStation(): static
    {
        return $this->state(['type' => OrganizationType::FuelStation]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => Organization::INACTIVE]);
    }

    /** Um CNPJ sintético, mas com os dois dígitos verificadores corretos. */
    public static function cnpj(): string
    {
        $digits = '';

        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum += (int) $digits[$index] * $weight;
            }

            $rest = $sum % 11;
            $digits .= $rest < 2 ? 0 : 11 - $rest;
        }

        return $digits;
    }
}

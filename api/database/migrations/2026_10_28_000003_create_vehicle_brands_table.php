<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de veículo. As globais (`company_id` nulo) são mantidas pela
 * LogIV; a empresa acrescenta a que faltar, visível só para ela. Marca de
 * automotor e de implemento são listas diferentes — Scania não faz
 * semirreboque, Randon não faz cavalo.
 */
return new class extends Migration
{
    private const BRANDS = [
        'motor_vehicle' => [
            'Agrale', 'Chevrolet', 'DAF', 'Fiat', 'Ford', 'Honda', 'Hyundai', 'Iveco', 'JAC',
            'Mercedes-Benz', 'Renault', 'Scania', 'Toyota', 'Volkswagen', 'Volvo', 'Yamaha',
        ],
        'trailer' => [
            'Facchini', 'Guerra', 'Krone', 'Librelato', 'Noma', 'Pastre', 'Randon', 'Rodofort',
            'Rossetti', 'Schiffer',
        ],
    ];

    public function up(): void
    {
        Schema::create('vehicle_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('organizations');
            $table->string('kind', 20); // motor_vehicle | trailer
            $table->string('name', 60);
            $table->timestamps();

            $table->index(['kind', 'company_id']);
        });

        $rows = [];

        foreach (self::BRANDS as $kind => $names) {
            foreach ($names as $name) {
                $rows[] = ['kind' => $kind, 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        DB::table('vehicle_brands')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_brands');
    }
};

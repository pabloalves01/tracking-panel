<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de veículo, catálogo global mantido pela LogIV. O grupo (`kind`) e
 * a categoria saem do tipo: o veículo não guarda nenhum dos dois, e a tela
 * não tem como escolher um tipo que contradiga o grupo.
 *
 * `has_body`: leva carga numa carroceria (o cavalo e o dolly não). Sem ela,
 * não há carroceria, lotação nem volume. `can_tow`: puxa implemento (o
 * cavalo e o caminhão sim; a van e o carro não). Sem isso, não há CMT nem
 * implementos engatados.
 *
 * `mdfe_code` é o tipo de rodado do MDF-e (`tpRod`), só onde a
 * equivalência é direta. Implemento não tem `tpRod` no MDF-e.
 */
return new class extends Migration
{
    private const TYPES = [
        // slug, nome, grupo, categoria, tpRod, carroceria, puxa
        ['truck_tractor', 'Cavalo mecânico', 'motor_vehicle', 'heavy_commercial', '03', false, true],
        ['truck', 'Caminhão truck', 'motor_vehicle', 'heavy_commercial', '01', true, true],
        ['toco', 'Caminhão toco', 'motor_vehicle', 'heavy_commercial', '02', true, true],
        ['bitruck', 'Caminhão bitruck', 'motor_vehicle', 'heavy_commercial', null, true, true],
        ['van', 'Van', 'motor_vehicle', 'light_commercial', '04', true, false],
        ['utility', 'Utilitário', 'motor_vehicle', 'light_commercial', '05', true, false],
        ['car', 'Automóvel', 'motor_vehicle', 'passenger', null, false, false],
        ['motorcycle', 'Motocicleta', 'motor_vehicle', 'passenger', null, false, false],
        ['semi_trailer', 'Semirreboque', 'trailer', 'heavy_commercial', null, true, false],
        ['trailer', 'Reboque', 'trailer', 'heavy_commercial', null, true, false],
        ['dolly', 'Dolly', 'trailer', 'heavy_commercial', null, false, false],
    ];

    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            $table->string('kind', 20); // motor_vehicle | trailer
            $table->string('category', 20); // passenger | light_commercial | heavy_commercial
            $table->string('mdfe_code', 2)->nullable();
            $table->boolean('has_body');
            $table->boolean('can_tow');
            $table->timestamps();
        });

        DB::table('vehicle_types')->insert(array_map(fn (array $type) => [
            'slug' => $type[0],
            'name' => $type[1],
            'kind' => $type[2],
            'category' => $type[3],
            'mdfe_code' => $type[4],
            'has_body' => $type[5],
            'can_tow' => $type[6],
            'created_at' => now(),
            'updated_at' => now(),
        ], self::TYPES));
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};

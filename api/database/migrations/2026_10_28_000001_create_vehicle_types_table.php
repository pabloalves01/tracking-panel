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
 * `mdfe_code` é o tipo de rodado do MDF-e (`tpRod`), só onde a
 * equivalência é direta. Implemento não tem `tpRod` no MDF-e.
 */
return new class extends Migration
{
    private const TYPES = [
        // slug, nome, grupo, categoria, tpRod
        ['truck_tractor', 'Cavalo mecânico', 'motor_vehicle', 'heavy_commercial', '03'],
        ['truck', 'Caminhão truck', 'motor_vehicle', 'heavy_commercial', '01'],
        ['toco', 'Caminhão toco', 'motor_vehicle', 'heavy_commercial', '02'],
        ['bitruck', 'Caminhão bitruck', 'motor_vehicle', 'heavy_commercial', null],
        ['van', 'Van', 'motor_vehicle', 'light_commercial', '04'],
        ['utility', 'Utilitário', 'motor_vehicle', 'light_commercial', '05'],
        ['car', 'Automóvel', 'motor_vehicle', 'passenger', null],
        ['motorcycle', 'Motocicleta', 'motor_vehicle', 'passenger', null],
        ['semi_trailer', 'Semirreboque', 'trailer', 'heavy_commercial', null],
        ['trailer', 'Reboque', 'trailer', 'heavy_commercial', null],
        ['dolly', 'Dolly', 'trailer', 'heavy_commercial', null],
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
            $table->timestamps();
        });

        DB::table('vehicle_types')->insert(array_map(fn (array $type) => [
            'slug' => $type[0],
            'name' => $type[1],
            'kind' => $type[2],
            'category' => $type[3],
            'mdfe_code' => $type[4],
            'created_at' => now(),
            'updated_at' => now(),
        ], self::TYPES));
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};

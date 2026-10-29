<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carrocerias, catálogo global mantido pela LogIV. É classificação
 * separada do tipo: um truck pode ser baú ou graneleiro.
 *
 * `mdfe_code` é o tipo de carroceria do MDF-e (`tpCar`), só onde a
 * equivalência é direta. Veículo sem carroceria (cavalo mecânico) fica sem
 * `body_type_id` — no MDF-e, `00`.
 */
return new class extends Migration
{
    private const TYPES = [
        // slug, nome, tpCar
        ['open', 'Aberta', '01'],
        ['closed', 'Baú / fechada', '02'],
        ['refrigerated', 'Baú frigorífico', '02'],
        ['bulk', 'Graneleira', '03'],
        ['container', 'Porta-contêiner', '04'],
        ['sider', 'Sider', '05'],
        ['tank', 'Tanque', null],
        ['dump', 'Basculante', null],
        ['flatbed', 'Prancha', null],
        ['car_carrier', 'Cegonha', null],
        ['livestock', 'Boiadeira', null],
    ];

    public function up(): void
    {
        Schema::create('body_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            $table->string('mdfe_code', 2)->nullable();
            $table->timestamps();
        });

        DB::table('body_types')->insert(array_map(fn (array $type) => [
            'slug' => $type[0],
            'name' => $type[1],
            'mdfe_code' => $type[2],
            'created_at' => now(),
            'updated_at' => now(),
        ], self::TYPES));
    }

    public function down(): void
    {
        Schema::dropIfExists('body_types');
    }
};

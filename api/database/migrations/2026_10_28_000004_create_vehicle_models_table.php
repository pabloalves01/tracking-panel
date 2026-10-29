<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos, de uma marca. Mesma regra das marcas: os globais são da LogIV,
 * e a empresa acrescenta o que faltar.
 *
 * Nasce com os cavalos e caminhões mais comuns. Implemento não tem lista:
 * o "modelo" dele varia por fabricante e configuração, e a empresa cadastra
 * os que usa.
 */
return new class extends Migration
{
    private const MODELS = [
        'DAF' => ['CF 410', 'XF 480', 'XF 530'],
        'Iveco' => ['S-Way 480', 'S-Way 540', 'Tector 240E28', 'Daily 35-150'],
        'Mercedes-Benz' => ['Actros 2548', 'Actros 2651', 'Axor 2544', 'Atego 2430', 'Accelo 1016', 'Sprinter 416'],
        'Scania' => ['G 410', 'P 320', 'R 450', 'R 500', 'R 540', 'S 500'],
        'Volkswagen' => ['Constellation 24.280', 'Constellation 25.460', 'Meteor 28.460', 'Delivery 11.180'],
        'Volvo' => ['FH 460', 'FH 500', 'FH 540', 'FM 380', 'VM 270', 'VM 330'],
    ];

    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('organizations');
            $table->foreignId('vehicle_brand_id')->constrained('vehicle_brands');
            $table->string('name', 80);
            $table->timestamps();

            $table->index(['vehicle_brand_id', 'company_id']);
        });

        $brands = DB::table('vehicle_brands')->whereNull('company_id')->pluck('id', 'name');
        $rows = [];

        foreach (self::MODELS as $brand => $names) {
            foreach ($names as $name) {
                $rows[] = ['vehicle_brand_id' => $brands[$brand], 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        DB::table('vehicle_models')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};

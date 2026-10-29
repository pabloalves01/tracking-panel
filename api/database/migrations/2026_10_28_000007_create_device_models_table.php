<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de modelos de rastreador, o mesmo para todas as empresas e
 * mantido pela LogIV. É tabela, e não enum, porque o modelo vai carregar
 * comportamento: protocolo, porta no Traccar e comandos aceitos.
 */
return new class extends Migration
{
    private const MODELS = [
        ['Suntech', 'ST310U'],
        ['Suntech', 'ST340UR'],
        ['Suntech', 'ST4315'],
        ['Suntech', 'ST8310'],
        ['Iter', 'ITR155'],
        ['X3Tech', 'XT40'],
        ['X3Tech', 'NT40'],
        ['Allcom', 'N4'],
    ];

    public function up(): void
    {
        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer', 60);
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['manufacturer', 'name']);
        });

        DB::table('device_models')->insert(array_map(fn (array $model) => [
            'manufacturer' => $model[0],
            'name' => $model[1],
            'created_at' => now(),
            'updated_at' => now(),
        ], self::MODELS));
    }

    public function down(): void
    {
        Schema::dropIfExists('device_models');
    }
};

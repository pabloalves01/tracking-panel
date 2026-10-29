<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Implemento engatado na tração. Desengatar preenche `uncoupled_at` em vez
 * de apagar: a composição de uma viagem antiga continua consultável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_couplings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('traction_vehicle_id')->constrained('vehicles');
            $table->foreignId('trailer_vehicle_id')->constrained('vehicles');
            $table->unsignedTinyInteger('position'); // 1ª, 2ª, 3ª carreta
            $table->timestamp('coupled_at');
            $table->timestamp('uncoupled_at')->nullable();
            $table->timestamps();

            $table->index(['traction_vehicle_id', 'uncoupled_at']);
        });

        // Um implemento engatado em uma tração por vez, e uma posição por
        // implemento em cada tração.
        DB::statement(
            'CREATE UNIQUE INDEX vehicle_couplings_trailer_unique
             ON vehicle_couplings (trailer_vehicle_id) WHERE uncoupled_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX vehicle_couplings_position_unique
             ON vehicle_couplings (traction_vehicle_id, position) WHERE uncoupled_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_couplings');
    }
};

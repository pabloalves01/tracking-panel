<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreadores. Existem sem veículo — em estoque, ou retirados de um
 * caminhão — e são instalados por `device_installations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('device_model_id')->constrained('device_models');
            $table->string('imei', 20); // só dígitos
            // O identificador que o rastreador manda para o Traccar, quando
            // não é o IMEI (alguns protocolos usam o número de série). Nulo:
            // o IMEI.
            $table->string('tracking_id', 30)->nullable();
            $table->string('sim_number', 13)->nullable(); // número do chip, com DDD
            $table->string('sim_iccid', 22)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        // O IMEI é único no sistema, não por empresa: é o `uniqueId` do
        // device no Traccar, que é um servidor só para todas as empresas.
        DB::statement(
            'CREATE UNIQUE INDEX devices_imei_unique
             ON devices (imei) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX devices_tracking_id_unique
             ON devices (tracking_id) WHERE deleted_at IS NULL AND tracking_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};

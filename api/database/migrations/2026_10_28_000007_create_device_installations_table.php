<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreador instalado em veículo. Retirar preenche `removed_at`: as
 * posições antigas continuam ligadas ao veículo em que ele estava.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->string('role', 20); // primary | backup
            $table->timestamp('installed_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'removed_at']);
        });

        // Um rastreador em um veículo por vez, e um principal por veículo.
        DB::statement(
            'CREATE UNIQUE INDEX device_installations_device_unique
             ON device_installations (device_id) WHERE removed_at IS NULL'
        );
        DB::statement(
            "CREATE UNIQUE INDEX device_installations_primary_unique
             ON device_installations (vehicle_id) WHERE removed_at IS NULL AND role = 'primary'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('device_installations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O vínculo entre uma empresa e um posto conveniado.
 *
 * Substitui `company_fuel_station`, e sem a coluna `cnpj` que existia lá: o
 * documento é do posto, e guardá-lo no vínculo permitia que os dois
 * divergissem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_supply_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('station_id')->constrained('organizations');

            // request_sent | active | rejected | disconnected
            $table->string('status', 20)->default('request_sent');

            $table->timestamps();

            $table->unique(['company_id', 'station_id']);
            $table->index(['station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_supply_links');
    }
};

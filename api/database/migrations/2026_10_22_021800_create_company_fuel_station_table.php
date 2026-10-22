<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedido de vínculo da empresa com um posto já cadastrado, feito pelo CNPJ.
     */
    public function up(): void
    {
        Schema::create('company_fuel_station', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('fuel_station_id')->constrained('fuel_stations');
            $table->string('cnpj', 14);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['company_id', 'cnpj']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_fuel_station');
    }
};

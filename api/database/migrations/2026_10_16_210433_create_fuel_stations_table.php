<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posto global. Os dados da Receita nascem travados no cadastro.
     */
    public function up(): void
    {
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('cnpj', 14)->unique();
            $table->string('corporate_name');
            $table->string('trade_name')->nullable();
            $table->string('receita_status');
            $table->timestamp('receita_checked_at')->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->foreignId('state_id')->nullable()->constrained('states');
            $table->foreignId('city_id')->nullable()->constrained('cities');
            $table->string('pix_key_type')->nullable();
            $table->string('pix_key')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stations');
    }
};

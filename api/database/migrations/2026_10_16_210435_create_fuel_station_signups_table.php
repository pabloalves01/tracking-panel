<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro pendente. O posto e o usuário só nascem depois do código.
     */
    public function up(): void
    {
        Schema::create('fuel_station_signups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('cnpj', 14);
            $table->string('email');
            $table->string('name');
            $table->string('password');
            $table->json('receita_payload');
            $table->string('code_hash');
            $table->timestamp('code_expires_at');
            $table->timestamps();

            $table->index('cnpj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_station_signups');
    }
};

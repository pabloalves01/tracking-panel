<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convite para entrar numa empresa ou num posto. Só uma das duas colunas
     * fica preenchida. O link leva o token; aqui fica só o sha256 dele.
     * Status: pending, accepted ou canceled. Expirado é pending com expires_at no passado.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->foreignId('fuel_station_id')->nullable()->constrained('fuel_stations');
            $table->string('email')->index();
            $table->string('role');
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

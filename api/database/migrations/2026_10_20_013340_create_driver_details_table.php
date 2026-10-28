<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O único papel com dados próprios hoje. Cliente, transportadora e
 * funcionário não tinham nenhuma coluna além de `status`; proprietário,
 * responsável e fornecedor só tinham a chave PIX, que subiu para a entidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('license_number', 30);
            $table->string('license_category', 10);
            $table->date('license_expires_on')->nullable();
            $table->string('license_issued_state', 2)->nullable();
            $table->string('residence_proof_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_details');
    }
};

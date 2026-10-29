<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transportadora. Mesmo esqueleto de `drivers`; as colunas próprias entram aqui
 * conforme o papel ganhar dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            // Registro na ANTT. É do transportador — TAC, ETC ou CTC —, não
            // do veículo: o veículo é que fica registrado no RNTRC dele.
            $table->string('rntrc', 12)->nullable();
            $table->string('antt_category', 10)->nullable(); // tac | etc | ctc
            $table->date('rntrc_expires_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};

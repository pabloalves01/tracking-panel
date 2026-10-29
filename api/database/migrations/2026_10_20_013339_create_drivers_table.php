<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motorista. Todo papel segue este esqueleto:
 *
 * - `entity_id` aponta para a pessoa em `entities`, onde ficam nome,
 *   documento, endereço e contato. Aqui fica só o que é deste papel.
 * - `entity_id` é único e a exclusão é lógica: a pessoa tem no máximo uma
 *   linha por papel, para sempre. Cadastrar de novo quem foi excluído
 *   restaura a mesma linha, e o que a operação gravar apontando para este
 *   `id` continua ligado a ela.
 * - `company_id` repete o da entidade para o escopo por empresa funcionar
 *   direto na tabela — sem join — e para os módulos que vierem filtrarem
 *   por ele.
 * - `status` inativa sem excluir: some das escolhas novas, mas o histórico
 *   continua apontando para cá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->string('license_number', 30);
            $table->string('license_category', 10);
            $table->date('license_expires_on')->nullable();
            $table->string('license_issued_state', 2)->nullable();
            $table->string('residence_proof_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'license_expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

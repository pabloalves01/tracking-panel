<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os papéis que uma entidade exerce. O catálogo de papéis é o enum
 * `App\Enums\EntityRole`, não uma tabela: papéis governam comportamento
 * (motorista tem CNH, fornecedor recebe por PIX), então são do sistema e
 * não do usuário. Um papel novo é um valor no enum, sem migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();

            $table->unique(['entity_id', 'role']);
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_roles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O vínculo entre uma conta e uma organização.
 *
 * Substitui `company_user` e `fuel_station_user`, que tinham as mesmas
 * colunas e regras quase iguais — mas comportamentos de remoção diferentes:
 * soft delete na empresa, delete físico no posto. Com uma tabela só, a
 * assimetria deixa de existir.
 *
 * Não há coluna de papel. Por enquanto todo membro tem o mesmo acesso;
 * quando houver papéis, eles entram aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

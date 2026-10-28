<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A organização é aquilo a que um usuário pertence e dentro do que ele
 * trabalha: hoje uma empresa ou um posto, amanhã um fornecedor com painel
 * próprio.
 *
 * Empresa e posto eram duas tabelas com os mesmos campos sob nomes
 * diferentes (`legal_name` e `corporate_name` para razão social), cada uma
 * com sua tabela de usuários, seu middleware e sua cópia das rotas. O que
 * difere entre elas é o domínio — a empresa tem operações, o posto tem
 * abastecimentos — e não o acesso.
 *
 * `type` é aberto de propósito: um valor novo não pede migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('type', 30); // company | fuel_station
            $table->string('document', 14)->unique(); // CNPJ, só dígitos
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->string('postal_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('street_number', 30)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->foreignId('state_id')->nullable()->constrained('states');
            $table->foreignId('city_id')->nullable()->constrained('cities');

            // Campos de um tipo só. São dois; uma tabela de perfil para cada
            // um custaria mais do que a coluna nula.
            $table->string('logo_path')->nullable(); // empresa
            $table->timestamp('receita_checked_at')->nullable(); // posto

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A entidade é a pessoa física ou jurídica que a empresa cadastra uma vez.
 * Os papéis que ela exerce — motorista, cliente, fornecedor — vivem em
 * `entity_roles`, e os dados próprios de um papel, quando existem, em uma
 * tabela de detalhe own (hoje só `driver_details`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');

            $table->string('kind', 20); // individual | organization
            $table->string('document', 14); // somente dígitos: CPF (11) ou CNPJ (14)

            // Quando preenchida, este registro local é a mesma parte do mundo
            // real que uma organização com conta e painel próprios — hoje o
            // posto, amanhã o fornecedor e a transportadora. Nula é o caso
            // comum: um registro que só esta empresa conhece.
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->string('name');
            $table->string('trade_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('emergency_phone_type', 20)->nullable();

            $table->string('national_id', 30)->nullable(); // RG
            $table->string('state_registration', 30)->nullable(); // IE

            // A chave PIX é da pessoa, não do papel: fornecedor, proprietário
            // e responsável compartilhavam a mesma coluna em três tabelas.
            $table->string('pix_key_type', 20)->nullable();
            $table->string('pix_key')->nullable();

            $table->string('postal_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('street_number', 30)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->foreignId('state_id')->nullable()->constrained('states');
            $table->foreignId('city_id')->nullable()->constrained('cities');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });

        // Índice único parcial: um documento por empresa entre as entidades
        // vivas. Um `unique` comum somado ao soft delete faria uma entidade
        // apagada bloquear o documento para sempre.
        DB::statement(
            'CREATE UNIQUE INDEX entities_company_document_unique
             ON entities (company_id, document) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};

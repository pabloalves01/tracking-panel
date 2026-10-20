<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('common_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('person_type'); // PF ou PJ
            $table->string('cpf_cnpj')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // todo:: adicionar campos de estado, endereço, cidade, cep, etc..
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'cpf_cnpj']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_entities');
    }
};

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
            $table->string('cpf_cnpj');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('rg')->nullable();
            $table->string('ie')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('emergency_phone_type')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('street')->nullable();
            $table->string('street_number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities');
            $table->foreignId('state_id')->nullable()->constrained('states');
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

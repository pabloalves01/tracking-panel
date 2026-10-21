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
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('entity_id')->constrained('common_entities');
            $table->string('login')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('pix_key_type')->nullable();
            $table->string('pix_key')->nullable();
            $table->decimal('diesel_s10_price', 12, 4)->nullable();
            $table->decimal('diesel_s500_price', 12, 4)->nullable();
            $table->decimal('gasoline_price', 12, 4)->nullable();
            $table->decimal('additive_gasoline_price', 12, 4)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_stations');
    }
};

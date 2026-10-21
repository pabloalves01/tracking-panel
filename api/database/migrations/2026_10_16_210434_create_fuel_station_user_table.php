<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo do usuário com o posto.
     * Por enquanto cada posto tem um único usuário, o owner.
     */
    public function up(): void
    {
        Schema::create('fuel_station_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fuel_station_id')->constrained('fuel_stations');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role');
            $table->string('status', 50)->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('fuel_station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_station_user');
    }
};

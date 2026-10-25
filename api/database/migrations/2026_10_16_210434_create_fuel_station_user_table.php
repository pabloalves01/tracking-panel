<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo do usuário com o posto. Papel `admin` ou `member`, como na empresa.
     */
    public function up(): void
    {
        Schema::create('fuel_station_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_station_id')->constrained('fuel_stations');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['fuel_station_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_station_user');
    }
};

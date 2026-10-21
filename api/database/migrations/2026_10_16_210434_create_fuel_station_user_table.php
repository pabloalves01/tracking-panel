<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo do usuário com o posto. Um posto tem um único owner.
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

            $table->unique(['fuel_station_id', 'user_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement("CREATE UNIQUE INDEX fuel_station_user_one_owner ON fuel_station_user (fuel_station_id) WHERE role = 'owner' AND deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_station_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O posto deixa de ter um único usuário. O par posto+usuário continua único.
     */
    public function up(): void
    {
        Schema::table('fuel_station_user', function (Blueprint $table) {
            $table->dropUnique(['fuel_station_id']);
            $table->string('status')->default('active')->after('role');
            $table->unique(['fuel_station_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fuel_station_user', function (Blueprint $table) {
            $table->dropUnique(['fuel_station_id', 'user_id']);
            $table->dropColumn('status');
            $table->unique('fuel_station_id');
        });
    }
};

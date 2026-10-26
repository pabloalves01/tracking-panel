<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * O CNPJ deixa de ser coluna do vínculo. A identidade é empresa + posto.
     */
    public function up(): void
    {
        Schema::table('company_fuel_station', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'cnpj']);
            $table->dropColumn('cnpj');
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unique(['company_id', 'fuel_station_id']);
        });

        DB::table('company_fuel_station')->whereNull('uuid')->orderBy('id')->lazy()->each(function (object $link) {
            DB::table('company_fuel_station')->where('id', $link->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('company_fuel_station', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'fuel_station_id']);
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
            $table->string('cnpj', 14)->nullable();
            $table->unique(['company_id', 'cnpj']);
        });
    }
};

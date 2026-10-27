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

    /**
     * O CNPJ volta a partir do posto vinculado. Sem o posto, a coluna fica vazia:
     * o valor original da coluna removida não existe mais.
     */
    public function down(): void
    {
        Schema::table('company_fuel_station', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('
                update company_fuel_station as link
                set cnpj = station.cnpj
                from fuel_stations as station
                where station.id = link.fuel_station_id
            ');
        } else {
            DB::statement('
                update company_fuel_station
                set cnpj = (
                    select cnpj from fuel_stations
                    where fuel_stations.id = company_fuel_station.fuel_station_id
                )
            ');
        }

        Schema::table('company_fuel_station', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'fuel_station_id']);
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
            $table->unique(['company_id', 'cnpj']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas que já foram criadas antes do status entrar na migration original.
     *
     * @var list<string>
     */
    private array $tables = [
        'companies',
        'suppliers',
        'employees',
        'drivers',
        'customers',
        'carriers',
        'fuel_stations',
        'vehicles',
        'vehicle_owners',
        'vehicle_responsibles',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'status')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('status', 50)->default('active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'status')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};

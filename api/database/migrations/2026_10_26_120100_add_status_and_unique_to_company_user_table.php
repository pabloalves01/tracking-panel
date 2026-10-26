<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma pessoa, um vínculo por empresa. O status guarda ativo ou suspenso.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('status')->default('active')->after('role');
            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'user_id']);
            $table->dropColumn('status');
        });
    }
};

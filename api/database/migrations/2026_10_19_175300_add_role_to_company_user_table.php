<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Papel do usuário naquela empresa. O primeiro acesso nasce como admin.
     */
    public function up(): void
    {
        if (Schema::hasColumn('company_user', 'role')) {
            return;
        }

        Schema::table('company_user', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

<?php

use App\Services\Access\CompanyOwnershipRepair;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Empresas antigas podem ter ficado sem proprietário, com o administrador da Logiv no vínculo.
     */
    public function up(): void
    {
        app(CompanyOwnershipRepair::class)->run();
    }

    public function down(): void
    {
        // Irreversível: o papel anterior e o vínculo do administrador da Logiv não são restaurados.
    }
};

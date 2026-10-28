<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies');
            // Proprietário e responsável são entidades com o papel
            // correspondente em `entity_roles`.
            $table->foreignId('vehicle_owner_id')->constrained('entities');
            $table->foreignId('vehicle_responsible_id')->constrained('entities');
            $table->string('plate');
            $table->string('status', 50)->default('active');
            // todo:: chassi, renavam, validade crlv, 
            // chave pix do veiculo, is_frota, tracker_id (criar tabela vehicle_trackers ou trackers)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Veículos da empresa — automotor e implemento na mesma tabela, porque têm
 * os mesmos documentos. O grupo sai do tipo. Ver
 * `specs/fleet-vehicles-devices.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('organizations');
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types');
            $table->foreignId('body_type_id')->nullable()->constrained('body_types');
            $table->string('fleet_type', 20); // own | aggregated | third_party

            $table->string('plate', 7); // sem traço, maiúsculas
            $table->string('plate_state', 2); // UF de licenciamento
            $table->string('renavam', 11);
            $table->string('chassis', 17);
            $table->date('registration_expires_on'); // validade do CRLV

            $table->string('brand', 60);
            $table->string('model', 80);
            $table->string('color', 40)->nullable();
            $table->unsignedSmallInteger('manufacture_year');
            $table->unsignedSmallInteger('model_year');

            // Valores do CRLV — os nominais, homologados. O peso de cada
            // carga transportada é da viagem, não daqui.
            $table->unsignedTinyInteger('axle_count');
            $table->unsignedInteger('tare_weight_kg');
            $table->unsignedInteger('load_capacity_kg')->nullable(); // lotação
            $table->unsignedInteger('gross_weight_kg')->nullable(); // PBT
            $table->unsignedInteger('max_traction_weight_kg')->nullable(); // CMT, só automotor
            $table->decimal('volume_capacity_m3', 8, 2)->nullable();

            // Proprietário legal (CRLV); responsável, a quem vão as receitas
            // e as despesas do veículo (fretes, adiantamentos, descontos); e
            // o transportador no RNTRC quando não é a própria empresa — o
            // proprietário que é TAC, ou o arrendatário.
            $table->foreignId('owner_id')->constrained('vehicle_owners');
            $table->foreignId('responsible_id')->constrained('vehicle_responsibles');
            $table->foreignId('carrier_id')->nullable()->constrained('carriers');

            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX vehicles_company_plate_unique
             ON vehicles (company_id, plate) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

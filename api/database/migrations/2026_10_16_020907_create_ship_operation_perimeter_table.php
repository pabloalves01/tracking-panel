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
        Schema::create('ship_operations_perimeter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_operation_id')->constrained('ship_operations');
            $table->geography('perimeter', 'polygon', 4326);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations_perimeter');
    }
};

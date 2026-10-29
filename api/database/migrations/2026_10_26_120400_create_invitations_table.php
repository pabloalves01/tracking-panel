<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convite para entrar numa organização.
 *
 * Com `organizations`, some o `xor` entre `company_id` e `fuel_station_id`
 * que exigia um CHECK no PostgreSQL e triggers equivalentes no sqlite: é
 * uma chave estrangeira só.
 *
 * O token nunca é guardado em claro — só o sha256 dele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();

            // pending | accepted | declined | revoked | expired
            $table->string('status', 20)->default('pending');

            $table->timestamp('expires_at');

            // Quando saiu do `pending`. Separado de `updated_at`, que também
            // se mexe num reenvio, e é o que data a aceitação no histórico.
            $table->timestamp('responded_at')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['email', 'status']);
        });

        // Um convite pendente por e-mail e organização. Parcial, porque os
        // recusados e aceitos do passado não podem bloquear um convite novo.
        DB::statement(
            "CREATE UNIQUE INDEX invitations_pending_unique
             ON invitations (organization_id, email) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

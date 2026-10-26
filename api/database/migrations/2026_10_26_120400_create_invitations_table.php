<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convite de uma empresa ou de um posto. Nunca os dois, nunca nenhum.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->foreignId('fuel_station_id')->nullable()->constrained('fuel_stations');
            $table->string('email');
            $table->string('role');
            $table->string('status')->default('pending');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table invitations add constraint invitations_one_context check ((company_id is not null and fuel_station_id is null) or (company_id is null and fuel_station_id is not null))');
        }

        if ($driver === 'sqlite') {
            DB::unprepared('
                create trigger invitations_one_context_insert
                before insert on invitations
                when not (
                    (new.company_id is not null and new.fuel_station_id is null)
                    or (new.company_id is null and new.fuel_station_id is not null)
                )
                begin
                    select raise(abort, \'convite precisa de empresa ou posto\');
                end
            ');
            DB::unprepared('
                create trigger invitations_one_context_update
                before update on invitations
                when not (
                    (new.company_id is not null and new.fuel_station_id is null)
                    or (new.company_id is null and new.fuel_station_id is not null)
                )
                begin
                    select raise(abort, \'convite precisa de empresa ou posto\');
                end
            ');
        }

        DB::statement("create unique index invitations_company_email_pending on invitations (company_id, email) where status = 'pending' and company_id is not null");
        DB::statement("create unique index invitations_station_email_pending on invitations (fuel_station_id, email) where status = 'pending' and fuel_station_id is not null");
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

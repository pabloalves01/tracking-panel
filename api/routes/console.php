<?php

use App\Models\FuelStationSignup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cadastro abandonado some no dia seguinte ao vencimento do código.
Schedule::call(function () {
    FuelStationSignup::query()
        ->where('code_expires_at', '<', now()->subDay())
        ->delete();
})->daily();

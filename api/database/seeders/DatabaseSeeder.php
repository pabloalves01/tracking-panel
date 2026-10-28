<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // O admin do sistema acessa a gestão sem pertencer a empresa alguma.
        //
        // A identidade é fixa e impressa ao final: vinha do UserFactory, com
        // e-mail aleatório, e não havia como entrar sem ir ler o banco.
        $email = (string) env('SYSTEM_ADMIN_EMAIL', 'logiv@logiv.com.br');
        $password = (string) env('SYSTEM_ADMIN_PASSWORD', 'logiv@2026');

        $admin = User::query()->firstOrNew(['email' => $email]);

        $admin->forceFill([
            'name' => env('SYSTEM_ADMIN_NAME', 'Administrador LogIV'),
            'password' => $password,
            'email_verified_at' => now(),
            'is_system_admin' => true,
        ])->save();

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(DemoAccessSeeder::class);

        $this->command?->newLine();
        $this->command?->info("Admin do sistema: {$email} / {$password}");
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Administrador do sistema (id 1): acessa a gestão sem precisar de empresa.
        // Identidade (nome/email/senha) vem do UserFactory; aqui só marcamos a flag.
        User::factory()->create([
            'is_system_admin' => true,
        ]);
    }
}

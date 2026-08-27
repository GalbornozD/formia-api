<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
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
        $empresaUno = Company::factory()->create(['legal_name' => 'OCA Global Chile']);
        $empresaDos = Company::factory()->create(['legal_name' => 'OCA Global Perú']);

        // Rol global master: pertenece a dos empresas y tiene acceso total a
        // ambas (y a cualquier otra) — ejercita el flujo de selección/cambio
        // de empresa.
        $admin = User::factory()->create([
            'email' => 'admin@formia.test',
            'first_name' => 'Admin',
            'last_name' => 'Formia',
            'role_id' => Role::MASTER,
        ]);
        $admin->empresas()->attach($empresaUno->id, ['permission' => 15, 'status' => true]);
        $admin->empresas()->attach($empresaDos->id, ['permission' => 15, 'status' => true]);

        // Rol global administrador: gestiona usuarios pero solo dentro de su
        // empresa. Una sola empresa: ejercita la auto-selección al login.
        $inspector = User::factory()->create([
            'email' => 'inspector@formia.test',
            'first_name' => 'Inspector',
            'last_name' => 'Formia',
            'role_id' => Role::ADMINISTRADOR,
        ]);
        $inspector->empresas()->attach($empresaUno->id, ['permission' => 15, 'status' => true]);
    }
}

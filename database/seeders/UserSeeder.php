<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios de prueba con contraseña "password": nunca en producción
        if (app()->isProduction()) {
            $this->command?->warn('UserSeeder omitido en producción. Cree el admin con: php artisan make:filament-user');

            return;
        }

        $users = [
        [
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'role' => 'admin',
        ],
        [
            'name' => 'Cliente',
            'email' => 'cliente@cliente.com',
            'role' => 'cliente',
        ],
        [
            'name' => 'Evaluador',
            'email' => 'evaluador@ivs.com',
            'role' => 'evaluador',
        ],
        [
            'name' => 'Revisor',
            'email' => 'revisor@ivs.com',
            'role' => 'revisor',
        ],
        ];


        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                'name' => $data['name'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                ]
            );


            if (! $user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }
        }
    }
}
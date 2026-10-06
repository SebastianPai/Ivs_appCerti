<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
        'admin',
        'cliente',
        'evaluador',
        'revisor',
        ];


        foreach ($roles as $role) {
            Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
            ]);
        }
    }
}
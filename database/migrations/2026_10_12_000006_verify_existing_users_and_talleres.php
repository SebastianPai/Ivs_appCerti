<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desde ahora todo usuario nuevo debe confirmar su correo antes de entrar y todo cliente es taller.
 * Los usuarios que ya existían quedan con el correo confirmado para que nadie pierda el acceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $clientes = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'cliente')
            ->pluck('model_has_roles.model_id');

        DB::table('users')->whereIn('id', $clientes)->update(['is_taller' => true]);
    }

    public function down(): void
    {
        // No se revierte: no hay forma de saber quién estaba sin confirmar
    }
};

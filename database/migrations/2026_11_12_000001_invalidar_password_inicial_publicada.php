<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * La contraseña inicial de los usuarios importados estuvo escrita en el código, y el repositorio
 * es público. Quien aún no la cambió (debe_cambiar_password) pasa a tener la que el servidor
 * define en PASSWORD_INICIAL_USUARIOS; si no está definida, una al azar que nadie conoce
 * (entonces se entra con «¿Olvidó su contraseña?» o el admin le asigna una).
 */
return new class extends Migration
{
    public function up(): void
    {
        $nueva = (string) config('auth.password_inicial');

        DB::table('users')->where('debe_cambiar_password', true)->orderBy('id')->each(function ($user) use ($nueva) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make(strlen($nueva) >= 8 ? $nueva : Str::random(40)),
                'remember_token' => null,
            ]);
        });

        // Sesiones abiertas con la contraseña anterior
        DB::table('sessions')->whereIn('user_id', DB::table('users')->where('debe_cambiar_password', true)->pluck('id'))->delete();
    }

    public function down(): void
    {
        // No se puede (ni se debe) restaurar la contraseña publicada.
    }
};

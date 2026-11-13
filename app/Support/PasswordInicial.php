<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * La contraseña inicial de los usuarios importados estuvo escrita en el código, y el repositorio
 * es público. En cuanto el servidor tenga PASSWORD_INICIAL_USUARIOS, quien todavía no cambió la
 * suya pasa a entrar con esa (y la debe cambiar al entrar). Se hace una sola vez.
 *
 * Mientras no esté configurada no se toca nada (para no dejar a nadie sin acceso) y los
 * administradores ven un aviso.
 */
class PasswordInicial
{
    public const AJUSTE = 'password_inicial_rotada';

    public static function configurada(): bool
    {
        return strlen((string) config('auth.password_inicial')) >= 8;
    }

    public static function yaRotada(): bool
    {
        return Cache::rememberForever(self::AJUSTE, fn () => SystemSetting::enabled(self::AJUSTE));
    }

    /** Cuentas que siguen con la contraseña inicial. */
    public static function pendientes(): int
    {
        return User::where('debe_cambiar_password', true)->count();
    }

    public static function rotarSiCorresponde(): void
    {
        if (! self::configurada() || self::yaRotada()) {
            return;
        }

        $hash = Hash::make((string) config('auth.password_inicial'));
        $ids = User::where('debe_cambiar_password', true)->pluck('id');

        DB::transaction(function () use ($hash, $ids) {
            User::whereKey($ids)->update(['password' => $hash, 'remember_token' => null]);
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            SystemSetting::put(self::AJUSTE, true);
        });

        Cache::forever(self::AJUSTE, true);
    }

    /** Hay que avisar: no está configurada, nunca se rotó y quedan cuentas con la contraseña publicada. */
    public static function hayQueAvisar(): bool
    {
        return ! self::configurada() && ! self::yaRotada() && self::pendientes() > 0;
    }
}

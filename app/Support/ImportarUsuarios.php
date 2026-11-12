<?php

namespace App\Support;

use App\Models\Actividad;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trae los usuarios de la plataforma anterior desde el archivo que se armó con sus datos
 * (Administrar Usuarios › «Importar usuarios»). El archivo no va en el repositorio, porque
 * tiene datos personales.
 *
 * Por cada usuario: nombre, correo, ubicación, dirección, roles, acceso, servicios del taller,
 * fechas del registro de Industria y Comercio, inventario y talleres asignados (evaluador o
 * externo). Si el usuario ya existe (mismo correo o mismo nombre) se actualiza en lugar de
 * duplicarlo, conservando sus solicitudes.
 *
 * Los usuarios nuevos, y los que estaban con un correo de prueba (.test), quedan con la
 * contraseña inicial y deben cambiarla al entrar. A quien ya tenía su correo real no se le
 * toca la contraseña. Se puede correr varias veces: el resultado es el mismo.
 */
class ImportarUsuarios
{
    /**
     * Contraseña con la que entran los usuarios importados. Va en el .env (PASSWORD_INICIAL_USUARIOS)
     * y nunca en el código: el repositorio es público.
     */
    public static function passwordInicial(): string
    {
        $password = (string) config('auth.password_inicial');

        if (strlen($password) < 8) {
            throw new \RuntimeException('Falta PASSWORD_INICIAL_USUARIOS en el .env del servidor (mínimo 8 caracteres).');
        }

        return $password;
    }

    /** @return array{creados: int, actualizados: int, omitidos: list<string>} */
    public static function desde(array $archivo): array
    {
        self::passwordInicial(); // falla antes de tocar nada si no está configurada

        $usuarios = $archivo['usuarios'] ?? null;
        if (! is_array($usuarios) || $usuarios === []) {
            throw new \InvalidArgumentException('El archivo no trae usuarios de la plataforma anterior.');
        }

        $resumen = ['creados' => 0, 'actualizados' => 0, 'omitidos' => []];
        $servicios = ServiceType::pluck('id', 'nombre')->mapWithKeys(fn ($id, $nombre) => [mb_strtoupper(trim($nombre)) => $id]);

        DB::transaction(function () use ($usuarios, $servicios, &$resumen) {
            /** @var array<string, User> $porIdOriginal */
            $porIdOriginal = [];

            foreach ($usuarios as $u) {
                $email = mb_strtolower(trim($u['email'] ?? ''));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || blank($u['nombre'] ?? null)) {
                    $resumen['omitidos'][] = ($u['nombre'] ?? '¿sin nombre?').': correo no válido';
                    continue;
                }

                [$user, $porCorreo] = self::buscar($email, $u['nombre']);

                if ($user && ! $porCorreo && User::where('email', $email)->whereKeyNot($user->id)->exists()) {
                    $resumen['omitidos'][] = "{$u['nombre']}: el correo {$email} ya lo usa otro usuario";
                    continue;
                }

                $nuevo = $user === null;
                // El correo de prueba (.test) se cambia por el real; el que ya era real se respeta
                $contrasenaInicial = $nuevo || str_ends_with((string) $user->email, '.test');
                $user ??= new User;

                $user->fill(array_filter([
                    // Una cuenta que ya existía con su correo real conserva el nombre que tiene
                    'name' => $porCorreo ? $user->name : trim($u['nombre']),
                    'email' => $email,
                    'department' => $u['departamento'] ?? null,
                    'city' => $u['ciudad'] ?? null,
                    'direccion' => $u['direccion'] ?? null,
                    'fecha_radicado' => $u['registro']['expedicion'] ?? null,
                    'fecha_vencimiento' => $u['registro']['vencimiento'] ?? null,
                ], fn ($v) => filled($v)));
                $user->is_taller = $porCorreo ? ($user->is_taller || ($u['es_taller'] ?? false)) : (bool) ($u['es_taller'] ?? false);
                // Nadie se queda por fuera por la importación: la cuenta real activa sigue activa
                $user->activo = $porCorreo ? ($user->activo || ($u['activo'] ?? true)) : (bool) ($u['activo'] ?? true);

                if ($contrasenaInicial) {
                    $user->password = self::passwordInicial();
                    $user->debe_cambiar_password = true;
                }

                // Ya entraban a la plataforma anterior con este correo
                $user->email_verified_at ??= now();
                $user->save();

                if ($roles = array_values(array_intersect($u['roles'] ?? [], array_keys(User::ROLES)))) {
                    // A la cuenta real que ya existía solo se le suman los roles del original
                    $porCorreo ? $user->assignRole($roles) : $user->syncRoles($roles);
                }

                if (isset($u['servicios'])) {
                    $user->servicios()->sync(collect($u['servicios'])
                        ->map(fn ($nombre) => $servicios[mb_strtoupper(trim($nombre))] ?? null)
                        ->filter()->unique()->values()->all());
                }

                if (! empty($u['inventario'])) {
                    $user->inventario()->updateOrCreate([], collect($u['inventario'])->only(\App\Models\InventarioUsuario::CAMPOS)->all());
                }

                $porIdOriginal[(string) $u['id_original']] = $user;
                $nuevo ? $resumen['creados']++ : $resumen['actualizados']++;
            }

            // Con todos creados, los talleres que atiende cada evaluador o que exporta cada externo
            foreach ($usuarios as $u) {
                $user = $porIdOriginal[(string) ($u['id_original'] ?? '')] ?? null;
                if (! $user || ! isset($u['talleres']) || ! $user->hasAnyRole(['evaluador', 'externo'])) {
                    continue;
                }

                $user->clientesAsignados()->sync(collect($u['talleres'])
                    ->map(fn ($id) => $porIdOriginal[(string) $id] ?? null)
                    ->filter(fn (?User $t) => $t?->hasRole('cliente'))
                    ->map->getKey()->unique()->values()->all());
            }
        });

        Actividad::registrar('usuario', "Usuarios de la plataforma anterior importados: {$resumen['creados']} nuevos, {$resumen['actualizados']} actualizados");

        return $resumen;
    }

    /** @return array{0: ?User, 1: bool} el usuario y si se encontró por el correo */
    private static function buscar(string $email, string $nombre): array
    {
        if ($user = User::whereRaw('LOWER(email) = ?', [$email])->first()) {
            return [$user, true];
        }

        // Por nombre, sin tildes ni mayúsculas, y solo si hay uno
        $clave = fn (?string $n) => Str::of(Str::ascii((string) $n))->lower()->squish()->toString();
        $coinciden = User::all(['id', 'name', 'email'])->filter(fn (User $x) => $clave($x->name) === $clave($nombre));

        return [$coinciden->count() === 1 ? User::find($coinciden->first()->id) : null, false];
    }
}

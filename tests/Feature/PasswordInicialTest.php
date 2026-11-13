<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordInicialTest extends TestCase
{
    public function test_la_migracion_cambia_la_contrasena_de_quien_no_la_ha_cambiado(): void
    {
        $pendiente = $this->usuario('cliente', ['password' => 'clave-vieja-123']);
        $pendiente->forceFill(['debe_cambiar_password' => true])->save();
        $activo = $this->usuario('cliente', ['password' => 'clave-propia-456']);

        $migracion = require database_path('migrations/2026_11_12_000001_invalidar_password_inicial_publicada.php');

        // Con PASSWORD_INICIAL_USUARIOS definida, queda con esa
        $migracion->up();
        $this->assertFalse(Hash::check('clave-vieja-123', $pendiente->fresh()->password));
        $this->assertTrue(Hash::check(config('auth.password_inicial'), $pendiente->fresh()->password));
        $this->assertTrue(Hash::check('clave-propia-456', $activo->fresh()->password), 'a quien ya la cambió no se le toca');
        $this->assertTrue($pendiente->fresh()->debe_cambiar_password);

        // Sin definir, queda una al azar
        config(['auth.password_inicial' => null]);
        $migracion->up();
        $this->assertFalse(Hash::check('clave-de-pruebas-1', $pendiente->fresh()->password));
    }

    public function test_importar_usuarios_exige_la_contrasena_inicial_en_el_env(): void
    {
        config(['auth.password_inicial' => null]);

        $this->expectExceptionMessage('PASSWORD_INICIAL_USUARIOS');
        \App\Support\ImportarUsuarios::desde(['usuarios' => []]);
    }
}

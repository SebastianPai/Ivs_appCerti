<?php

namespace Tests\Feature;

use App\Support\PasswordInicial;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordInicialTest extends TestCase
{
    public function test_sin_configurar_no_se_toca_nada_y_se_avisa_al_admin(): void
    {
        config(['auth.password_inicial' => null]);
        $pendiente = $this->usuario('cliente', ['password' => 'clave-vieja-123']);
        $pendiente->forceFill(['debe_cambiar_password' => true])->save();

        PasswordInicial::rotarSiCorresponde();

        $this->assertTrue(Hash::check('clave-vieja-123', $pendiente->fresh()->password), 'nadie queda sin acceso');
        $this->assertTrue(PasswordInicial::hayQueAvisar());
        $this->actingAs($this->usuario('admin'))->get('/ivs')->assertOk();
        $this->assertTrue(session('aviso_password_inicial'));
    }

    public function test_configurada_cambia_una_sola_vez_la_de_quien_no_la_ha_cambiado(): void
    {
        $pendiente = $this->usuario('cliente', ['password' => 'clave-vieja-123']);
        $pendiente->forceFill(['debe_cambiar_password' => true])->save();
        $activo = $this->usuario('cliente', ['password' => 'clave-propia-456']);

        PasswordInicial::rotarSiCorresponde();

        $this->assertTrue(Hash::check(config('auth.password_inicial'), $pendiente->fresh()->password));
        $this->assertTrue(Hash::check('clave-propia-456', $activo->fresh()->password), 'a quien ya la cambió no se le toca');
        $this->assertTrue($pendiente->fresh()->debe_cambiar_password);
        $this->assertFalse(PasswordInicial::hayQueAvisar());

        // Una sola vez: un usuario importado después conserva la que le dio la importación
        $nuevo = $this->usuario('cliente', ['password' => 'otra-clave-789']);
        $nuevo->forceFill(['debe_cambiar_password' => true])->save();
        Cache::forget(PasswordInicial::AJUSTE);
        PasswordInicial::rotarSiCorresponde();
        $this->assertTrue(Hash::check('otra-clave-789', $nuevo->fresh()->password));
    }

    public function test_importar_usuarios_exige_la_contrasena_inicial_en_el_env(): void
    {
        config(['auth.password_inicial' => null]);

        $this->expectExceptionMessage('PASSWORD_INICIAL_USUARIOS');
        \App\Support\ImportarUsuarios::desde(['usuarios' => []]);
    }
}

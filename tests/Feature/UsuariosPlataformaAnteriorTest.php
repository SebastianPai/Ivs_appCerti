<?php

namespace Tests\Feature;

use App\Exports\Exportables;
use App\Filament\Pages\CambiarPassword;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\ImportarUsuarios;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsuariosPlataformaAnteriorTest extends TestCase
{
    private function archivo(): array
    {
        return ['usuarios' => [
            ['id_original' => '19', 'nombre' => 'TALLER UNO SAS', 'email' => 'Taller.Uno@Example.com', 'roles' => ['cliente'], 'activo' => true,
                'es_taller' => true, 'departamento' => 'Bogotá', 'ciudad' => 'Bogotá D.C.', 'direccion' => 'Calle 1 # 2-3',
                'servicios' => ['REVISION ANUAL', 'INICIAL', 'NO EXISTE'], 'registro' => ['expedicion' => '2026-01-21', 'vencimiento' => '2027-01-21']],
            ['id_original' => '20', 'nombre' => 'Taller Dos', 'email' => 'dos@example.com', 'roles' => ['cliente'], 'activo' => true, 'es_taller' => true],
            ['id_original' => '24', 'nombre' => 'Luz Inspectora', 'email' => 'luz@example.com', 'roles' => ['evaluador'], 'activo' => true,
                'talleres' => ['19', '20'], 'inventario' => ['flexometro_serial' => 'FLX-003', 'flexometro_fecha_entrega' => '2024-01-01']],
            ['id_original' => '33', 'nombre' => 'Externo', 'email' => 'externo@example.com', 'roles' => ['externo'], 'activo' => true, 'talleres' => ['20']],
            ['id_original' => '25', 'nombre' => 'Sin Acceso', 'email' => 'sin@example.com', 'roles' => ['evaluador'], 'activo' => false],
            ['id_original' => '99', 'nombre' => 'Correo malo', 'email' => 'no-es-correo', 'roles' => ['cliente']],
        ]];
    }

    public function test_importa_usuarios_con_roles_accesos_y_asignaciones_sin_duplicar(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        // Ya existía con un correo de prueba y con su propia solicitud: se actualiza, no se duplica
        $previo = $this->usuario('cliente', ['name' => 'Taller Uno S.A.S', 'email' => 'taller.uno.sas@cliente.test']);
        $previo->forceFill(['name' => 'taller uno sas'])->save();
        // Cuenta real que ya existía: conserva nombre, contraseña y roles
        $real = $this->usuario('cliente', ['name' => 'Mi cuenta', 'email' => 'dos@example.com', 'password' => 'mi-clave-123']);

        $resumen = ImportarUsuarios::desde($this->archivo());

        $this->assertSame(3, $resumen['creados']);
        $this->assertSame(2, $resumen['actualizados']);
        $this->assertCount(1, $resumen['omitidos']);

        $uno = $previo->fresh();
        $this->assertSame('TALLER UNO SAS', $uno->name);
        $this->assertSame('taller.uno@example.com', $uno->email);
        $this->assertTrue($uno->debe_cambiar_password);
        $this->assertTrue(Hash::check(ImportarUsuarios::PASSWORD_INICIAL, $uno->password));
        $this->assertSame('Bogotá D.C.', $uno->city);
        $this->assertSame('Calle 1 # 2-3', $uno->direccion);
        $this->assertSame('2027-01-21', $uno->fecha_vencimiento->toDateString());
        $this->assertEqualsCanonicalizing(['REVISION ANUAL', 'INICIAL'], $uno->servicios()->pluck('nombre')->all());

        $real->refresh();
        $this->assertSame('Mi cuenta', $real->name);
        $this->assertFalse($real->debe_cambiar_password);
        $this->assertTrue(Hash::check('mi-clave-123', $real->password));

        $luz = User::where('email', 'luz@example.com')->firstOrFail();
        $this->assertTrue($luz->hasRole('evaluador'));
        $this->assertNotNull($luz->email_verified_at);
        $this->assertEqualsCanonicalizing([$uno->id, $real->id], $luz->clientesAsignados()->pluck('users.id')->all());
        $this->assertSame('FLX-003', $luz->inventario->flexometro_serial);

        $this->assertFalse(User::where('email', 'sin@example.com')->firstOrFail()->activo);

        // Se puede repetir: nadie se duplica
        ImportarUsuarios::desde($this->archivo());
        $this->assertSame(1, User::where('email', 'luz@example.com')->count());
        $this->assertSame(2, $luz->clientesAsignados()->count());
    }

    public function test_quien_entra_con_la_contrasena_inicial_debe_cambiarla(): void
    {
        $user = $this->usuario('evaluador', ['password' => ImportarUsuarios::PASSWORD_INICIAL]);
        $user->forceFill(['debe_cambiar_password' => true])->save();
        $this->actingAs($user);

        $this->get('/ivs')->assertRedirect(CambiarPassword::getUrl());
        $this->get(CambiarPassword::getUrl())->assertOk();

        // La misma contraseña inicial no sirve
        Livewire::test(CambiarPassword::class)
            ->fillForm(['password' => ImportarUsuarios::PASSWORD_INICIAL, 'password_confirmation' => ImportarUsuarios::PASSWORD_INICIAL])
            ->call('guardar')
            ->assertHasErrors(['data.password']);

        Livewire::test(CambiarPassword::class)
            ->fillForm(['password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->call('guardar')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->debe_cambiar_password);
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        // Con la nueva ya no lo manda a cambiarla
        $this->withSession(['password_hash_web' => $user->password])->get('/ivs')->assertOk();
    }

    public function test_fuera_del_horario_el_evaluador_no_abre_solicitudes(): void
    {
        $taller = $this->usuario('cliente');
        $evaluador = $this->usuario('evaluador');
        $evaluador->clientesAsignados()->attach($taller);
        $solicitud = $this->solicitud($taller);
        $verificacion = \App\Filament\Resources\Evaluador\Solicituds\Pages\VerificacionPrevia::class;
        $this->actingAs($evaluador);

        // Lunes a viernes de 8 a 17
        \App\Models\SystemSetting::put('restriccion_horario_inspector', true, ['desde' => '08:00', 'hasta' => '17:00', 'dias' => [1, 2, 3, 4, 5]]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 10:00')); // lunes
        Livewire::test($verificacion, ['record' => $solicitud->id])->assertNoRedirect();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 18:30')); // lunes, tarde
        Livewire::test($verificacion, ['record' => $solicitud->id])
            ->assertRedirect()
            ->assertNotified('Fuera de horario');

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 10:00')); // domingo
        $this->assertTrue(\App\Support\HorarioEvaluador::fueraDeHorario($evaluador));

        // Apagado (como hoy en la plataforma anterior), entra a cualquier hora
        \App\Models\SystemSetting::put('restriccion_horario_inspector', false, ['desde' => '08:00', 'hasta' => '17:00', 'dias' => [1]]);
        Livewire::test($verificacion, ['record' => $solicitud->id])->assertNoRedirect();
    }

    public function test_inventario_y_talleres_desde_la_fila_del_usuario(): void
    {
        $admin = $this->usuario('admin');
        $evaluador = $this->usuario('evaluador');
        $externo = $this->usuario('externo');
        $taller = $this->usuario('cliente');
        $otro = $this->usuario('cliente');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('inventario', $evaluador, [
                'tiene_movil1' => '1', 'movil1_marca' => 'Samsung', 'movil1_modelo' => 'A15', 'movil1_serial' => 'S1', 'movil1_tiene_cargador' => '1',
                'tiene_movil2' => '0',
                'flexometro_serial' => 'FLX-9', 'flexometro_fecha_entrega' => '2026-01-10',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Inventario guardado');
        $inv = $evaluador->fresh()->inventario;
        $this->assertTrue($inv->tiene_movil1);
        $this->assertSame('Samsung', $inv->movil1_marca);
        $this->assertTrue($inv->movil1_tiene_cargador);
        $this->assertFalse($inv->tiene_movil2);
        $this->assertSame('FLX-9', $inv->flexometro_serial);

        Livewire::test(ListUsers::class)
            ->callTableAction('asignarTalleres', $evaluador, ['talleres' => [$taller->id]])
            ->assertNotified('Talleres actualizados');
        $this->assertSame([$evaluador->id], $taller->evaluadores()->pluck('users.id')->all());

        // El externo solo exporta los talleres que tiene asignados
        Livewire::test(ListUsers::class)->callTableAction('asignarTalleres', $externo, ['talleres' => [$otro->id]]);
        Livewire::test(ListUsers::class)->assertTableActionHidden('asignarTalleres', $taller);

        foreach ([$taller, $otro] as $t) {
            $this->solicitud($t)->forceFill(['estado' => \App\Enums\EstadoSolicitud::Aprobada->value, 'fecha_aprobacion' => now()])->save();
        }
        $filtros = ['desde' => now()->toDateString(), 'hasta' => now()->toDateString()];
        $this->assertCount(1, Exportables::filas('externo', $filtros, $externo));
        $this->assertCount(2, Exportables::filas('externo', $filtros, $admin));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Pages\Contactenos;
use App\Filament\Resources\Contactenos\ContactenosResource;
use App\Filament\Resources\Contactenos\Pages\ManageContactenos;
use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\MensajeContacto;
use App\Models\ServiceType;
use App\Models\SystemSetting;
use Database\Seeders\ServiceTypeSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class ServiciosAccesoYContactenosTest extends TestCase
{
    public function test_el_taller_solo_puede_pedir_los_servicios_que_tiene_asignados(): void
    {
        $this->seed(ServiceTypeSeeder::class);
        SystemSetting::put('servicios_por_taller', true);
        $anual = ServiceType::where('nombre', 'REVISION ANUAL')->firstOrFail();
        $inicial = ServiceType::where('nombre', 'INICIAL')->firstOrFail();
        $taller = $this->usuario('cliente');

        // Recién creado no tiene ninguno, como en la plataforma anterior
        $this->assertSame([], $taller->serviciosDisponibles()->pluck('id')->all());
        $this->actingAs($taller);
        Livewire::test(CreateSolicitud::class)->assertSee('No tienes permisos a servicios');

        // El admin le asigna uno desde Administrar Usuarios
        $this->flushSession();
        $this->actingAs($this->usuario('admin'));
        Livewire::test(ListUsers::class)
            ->callTableAction('asignarServicios', $taller, ['servicios' => [$anual->id]])
            ->assertNotified('Servicios actualizados');
        $this->assertSame([$anual->id], $taller->serviciosDisponibles()->pluck('id')->all());

        // El taller ve ese y no puede colar otro
        $this->flushSession();
        $this->actingAs($taller);
        Livewire::test(CreateSolicitud::class)
            ->assertDontSee('No tienes permisos a servicios')
            ->fillForm(['service_type_id' => $inicial->id])
            ->call('create')
            ->assertHasFormErrors(['service_type_id' => 'in']);
        Livewire::test(CreateSolicitud::class)
            ->fillForm(['service_type_id' => $anual->id])
            ->call('create')
            ->assertHasNoFormErrors(['service_type_id']);

        // Con la regla apagada, todos los activos
        SystemSetting::put('servicios_por_taller', false);
        $this->assertSame(ServiceType::where('activo', true)->count(), $taller->serviciosDisponibles()->count());
    }

    public function test_una_cuenta_sin_acceso_no_entra_pero_conserva_su_rol(): void
    {
        $admin = $this->usuario('admin');
        $evaluador = $this->usuario('evaluador');

        $this->actingAs($evaluador);
        $this->get('/ivs')->assertOk();

        $this->flushSession();
        $this->actingAs($admin);
        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('cambiarAcceso', $admin) // no se quita el acceso a sí mismo
            ->callTableAction('cambiarAcceso', $evaluador)
            ->assertNotified('Cuenta sin acceso');

        $evaluador->refresh();
        $this->assertFalse($evaluador->activo);
        $this->assertTrue($evaluador->hasRole('evaluador'));
        $this->assertFalse($evaluador->canAccessPanel(filament()->getCurrentPanel()));

        // Si tenía la sesión abierta: el panel lo rechaza y la inspección sin conexión lo saca
        $this->flushSession();
        $this->actingAs($evaluador);
        $this->get('/ivs')->assertForbidden();
        $this->get(route('campo.index'))->assertRedirect(route('access.restricted'));
        $this->assertGuest();

        $this->actingAs($admin);
        Livewire::test(ListUsers::class)->callTableAction('cambiarAcceso', $evaluador)->assertNotified('Acceso devuelto');
        $this->assertTrue($evaluador->fresh()->canAccessPanel(filament()->getCurrentPanel()));
    }

    public function test_contactenos_llega_a_la_bandeja_del_admin_y_se_marca_gestionado(): void
    {
        $admin = $this->usuario('admin');
        $taller = $this->usuario('cliente');

        $this->actingAs($taller);
        $this->get(Contactenos::getUrl())->assertOk();
        $this->assertFalse(ContactenosResource::canViewAny());

        Livewire::test(Contactenos::class)
            ->fillForm(['asunto' => '', 'mensaje' => 'corto'])
            ->call('enviar')
            ->assertHasFormErrors(['asunto', 'mensaje'])
            ->fillForm(['asunto' => 'No puedo descargar el certificado', 'mensaje' => 'El botón de descarga no responde desde ayer.'])
            ->call('enviar')
            ->assertHasNoFormErrors()
            ->assertNotified('Buen trabajo!');

        $mensaje = MensajeContacto::sole();
        $this->assertSame([$taller->id, $taller->name, $taller->email], [$mensaje->user_id, $mensaje->nombre, $mensaje->email]);
        $this->assertSame(1, $admin->notifications()->count());

        $this->flushSession();
        $this->actingAs($admin);
        $this->get(ContactenosResource::getUrl())->assertOk();
        $this->assertSame('1', ContactenosResource::getNavigationBadge());

        Livewire::test(ManageContactenos::class)
            ->assertCanSeeTableRecords([$mensaje])
            ->callTableAction('gestionar', $mensaje)
            ->assertNotified('Mensaje gestionado')
            ->assertCanNotSeeTableRecords([$mensaje])
            ->set('activeTab', 'gestionadas')
            ->assertCanSeeTableRecords([$mensaje]);

        $this->assertSame($admin->id, $mensaje->fresh()->gestionado_por);
        $this->assertNull(ContactenosResource::getNavigationBadge());
    }
}

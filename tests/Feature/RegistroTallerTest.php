<?php

namespace Tests\Feature;

use App\Filament\Pages\RegistroTaller;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\RegistrosTallerPendientes;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RegistroTallerTest extends TestCase
{
    /** Cliente recién creado: sin fecha de registro, es decir, vencido. */
    private function tallerSinRegistro(array $attrs = []): User
    {
        return $this->usuario('cliente', ['fecha_vencimiento' => null, ...$attrs]);
    }

    public function test_todo_cliente_con_el_registro_vencido_solo_entra_a_cargarlo(): void
    {
        // No hace falta ninguna marca aparte: todo cliente es taller
        $taller = $this->tallerSinRegistro();
        $this->actingAs($taller);

        $this->assertTrue($taller->debeActualizarRegistro());
        $this->get('/ivs')->assertRedirect(RegistroTaller::getUrl());
        $this->get(SolicitudResource::getUrl('create'))->assertRedirect(RegistroTaller::getUrl());
        $this->get(RegistroTaller::getUrl())->assertOk()->assertSee('El registro de Industria y Comercio está vencido');

        $taller->update(['fecha_radicado' => now()->subYears(2), 'fecha_vencimiento' => now()->subYear()]);
        $this->get('/ivs')->assertRedirect(RegistroTaller::getUrl());

        // Vigente hasta hoy inclusive
        $taller->update(['fecha_radicado' => now()->subYear(), 'fecha_vencimiento' => now()]);
        $this->get('/ivs')->assertOk();
        $this->get(SolicitudResource::getUrl('create'))->assertOk();
    }

    public function test_no_aplica_a_otros_roles_ni_con_la_regla_apagada(): void
    {
        $this->actingAs($this->usuario('evaluador'));
        $this->get('/ivs')->assertOk();
        $this->assertFalse(RegistroTaller::canAccess());

        SystemSetting::put('registro_taller_required', false);
        $this->flushSession();
        $this->actingAs($this->tallerSinRegistro());
        $this->get('/ivs')->assertOk();
    }

    public function test_el_taller_carga_el_documento_y_el_admin_le_asigna_la_fecha_desde_inicio(): void
    {
        Storage::fake('local');
        $admin = $this->usuario('admin');
        $taller = $this->usuario('cliente', ['fecha_radicado' => '2024-01-01', 'fecha_vencimiento' => '2025-01-01']);
        $vigente = $this->usuario('cliente', ['fecha_vencimiento' => now()->addMonth()]);

        $this->actingAs($taller);
        Livewire::test(RegistroTaller::class)
            ->fillForm(['archivo' => UploadedFile::fake()->create('registro.pdf', 100, 'application/pdf')])
            ->call('guardar')
            ->assertHasNoFormErrors()
            ->assertNotified('Buen trabajo!');

        $taller->refresh();
        $this->assertNotNull($taller->camara_comercio);
        Storage::disk('local')->assertExists($taller->camara_comercio);
        $this->assertNull($taller->fecha_vencimiento, 'el documento nuevo deja sin efecto la fecha anterior');
        $this->assertTrue($taller->debeActualizarRegistro(), 'sigue sin entrar hasta que el admin asigne la fecha');
        $this->assertSame(1, $admin->notifications()->count());

        // Inicio del admin: aparece el pendiente y no el vigente
        $this->flushSession();
        $this->actingAs($admin);
        $this->assertTrue(RegistrosTallerPendientes::canView());
        $this->get('/ivs')->assertOk()->assertSee('Talleres pendientes de fecha de registro');

        Livewire::test(RegistrosTallerPendientes::class)
            ->assertCanSeeTableRecords([$taller])
            ->assertCanNotSeeTableRecords([$vigente])
            ->callTableAction('asignar', $taller, ['fecha_radicado' => now()->subMonth()->toDateString()])
            ->assertHasNoTableActionErrors();

        $taller->refresh();
        $this->assertSame(now()->subMonth()->addYear()->toDateString(), $taller->fecha_vencimiento->toDateString());
        $this->assertFalse($taller->debeActualizarRegistro());
        $this->assertFalse(RegistrosTallerPendientes::canView(), 'sin pendientes, la lista desaparece del inicio');

        $this->flushSession();
        $this->actingAs($taller);
        $this->get('/ivs')->assertOk();
    }

    public function test_los_demas_roles_no_ven_la_lista_de_pendientes(): void
    {
        $this->tallerSinRegistro();

        foreach (['cliente', 'evaluador', 'revisor'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->assertFalse(RegistrosTallerPendientes::canView());
        }

        $this->assertSame(1, User::conRegistroPendiente()->count());
    }

    public function test_un_usuario_nuevo_no_entra_hasta_confirmar_su_correo(): void
    {
        Notification::fake();
        $admin = $this->usuario('admin');
        $this->actingAs($admin);

        // El admin lo crea: le sale el correo de confirmación y queda como taller sin registro
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Taller Recién Creado',
                'email' => 'nuevo@taller.test',
                'role' => 'cliente',
                'password' => 'clave-segura-1',
                'department' => 'Antioquia',
                'city' => 'Medellín',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = User::where('email', 'nuevo@taller.test')->sole();
        Notification::assertSentTo($nuevo, VerifyEmail::class);
        $this->assertFalse($nuevo->hasVerifiedEmail());
        $this->assertTrue($nuevo->esTaller());

        // Paso 1: sin confirmar el correo solo ve el aviso para confirmarlo
        $this->flushSession();
        $this->actingAs($nuevo);
        $this->get('/ivs')->assertRedirect(filament()->getEmailVerificationPromptUrl());
        $this->get(SolicitudResource::getUrl('create'))->assertRedirect(filament()->getEmailVerificationPromptUrl());

        // El admin puede darlo por confirmado si ya lo comprobó por otro medio
        $this->flushSession();
        $this->actingAs($admin);
        Livewire::test(ListUsers::class)->callTableAction('confirmarCorreo', $nuevo)->assertNotified('Correo confirmado');
        $this->assertTrue($nuevo->fresh()->hasVerifiedEmail());

        // Paso 2: con el correo confirmado todavía falta el registro de Industria y Comercio
        $this->flushSession();
        $this->actingAs($nuevo->fresh());
        $this->get('/ivs')->assertRedirect(RegistroTaller::getUrl());
    }
}

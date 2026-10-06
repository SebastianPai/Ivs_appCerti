<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorSolicitudResource;
use App\Filament\Resources\Solicituds\Schemas\SolicitudForm;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\Solicitud;
use App\Support\Correo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CreateSolicitud extends CreateRecord
{
    use HasWizard {
        getWizardComponent as wizardBase;
    }

    protected static string $resource = SolicitudResource::class;

    protected static bool $canCreateAnother = false;

    /** Se muestra el botón "Empezar de cero" cuando se recuperó un borrador. */
    public bool $hayBorrador = false;

    public function getSteps(): array
    {
        return SolicitudForm::steps();
    }

    /** El paso actual queda en la URL: al recargar se vuelve al mismo paso. */
    public function getWizardComponent(): Component
    {
        /** @var Wizard $wizard */
        $wizard = $this->wizardBase();

        return $wizard->persistStepInQueryString();
    }

    // ------------------------------------------------------------------
    // Borrador automático: si se recarga la página o se cierra el navegador
    // a mitad de la solicitud, los datos no se pierden (dura 7 días).
    // ------------------------------------------------------------------

    private function claveBorrador(): string
    {
        return 'borrador_solicitud_'.Auth::id();
    }

    protected function fillForm(): void
    {
        if ($origen = $this->solicitudARenovar()) {
            $this->form->fill($this->datosDeRenovacion($origen));

            Notification::make()
                ->title('Renovación del certificado '.$origen->codigo)
                ->body('Copiamos los datos del vehículo, el propietario y los equipos. Revise que todo siga igual, en especial los cilindros y la prueba hidrostática.')
                ->info()
                ->persistent()
                ->send();

            return;
        }

        $borrador = Cache::get($this->claveBorrador());

        if (! is_array($borrador) || $borrador === []) {
            parent::fillForm();

            return;
        }

        $this->form->fill($borrador);
        $this->hayBorrador = true;

        Notification::make()
            ->title('Recuperamos su solicitud sin terminar')
            ->body('Puede continuar donde iba. Si prefiere, use «Empezar de cero».')
            ->info()
            ->send();
    }

    // ------------------------------------------------------------------
    // Renovación: /solicitudes/create?desde={id} copia los datos de un certificado aprobado.
    // ------------------------------------------------------------------

    private function solicitudARenovar(): ?Solicitud
    {
        $id = request()->integer('desde');

        return $id ? SolicitudResource::getEloquentQuery()
            ->where('estado', EstadoSolicitud::Aprobada->value)
            ->with(['vehicle', 'regulators', 'cilindros'])
            ->find($id) : null;
    }

    private function datosDeRenovacion(Solicitud $origen): array
    {
        return [
            ...$origen->only([
                'user_id', 'service_type_id', 'vehicle_identification_type_id', 'vehicle_identification',
                'owner_document_type', 'owner_document', 'owner_nombre', 'owner_apellido', 'owner_direccion',
                'owner_departamento', 'owner_ciudad', 'owner_telefono', 'owner_email',
                'combustion_system_id', 'application_type_id', 'technology_id',
            ]),
            ...SolicitudForm::datosVehiculo($origen->vehicle),
            'regulators' => $origen->regulators->map(fn ($r) => $r->only(['brand_id', 'numero_serie']))->all(),
            'cilindros' => $origen->cilindros->map(fn ($c) => [
                'brand_id' => $c->brand_id,
                'numero_serie' => $c->numero_serie,
                'capacidad' => $c->capacidad,
                'fecha_fabricacion' => $c->fecha_fabricacion ? Carbon::parse($c->fecha_fabricacion)->toDateString() : null,
                'fecha_prueba' => $c->fecha_prueba ? Carbon::parse($c->fecha_prueba)->toDateString() : null,
            ])->all(),
        ];
    }

    /** Livewire lo llama cada vez que cambia un campo del formulario. */
    public function updated(string $propiedad): void
    {
        if (str_starts_with($propiedad, 'data')) {
            Cache::put($this->claveBorrador(), $this->data, now()->addDays(7));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('empezarDeCero')
                ->label('Empezar de cero')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->hayBorrador)
                ->requiresConfirmation()
                ->modalHeading('¿Descartar el borrador?')
                ->modalDescription('Se borrarán los datos que había ingresado.')
                ->action(function () {
                    Cache::forget($this->claveBorrador());
                    $this->redirect(SolicitudResource::getUrl('create'));
                }),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data = SolicitudForm::guardarVehiculo($data);
        // El taller crea a su nombre; el admin elige el taller en el formulario
        if (! Auth::user()->hasRole('admin') || empty($data['user_id'])) {
            $data['user_id'] = Auth::id();
        }

        return static::getModel()::create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('attachments', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud creada. Ahora cargue la documentación.';
    }

    protected function afterCreate(): void
    {
        Cache::forget($this->claveBorrador());

        $solicitud = $this->record->load(['vehicle', 'user']);
        $taller = $solicitud->user;
        $placa = $solicitud->placa();

        Correo::enviar($taller, 'solicitud_creada', "Solicitud iniciada - Placa: {$placa}", 'emails.solicitud_iniciada', [
            'user_name' => $taller->name,
            'placa' => $placa,
            'url_adjuntos' => $this->getResource()::getUrl('attachments', ['record' => $solicitud]),
        ]);

        foreach ($taller->evaluadores()->get() as $evaluador) {
            Correo::enviar($evaluador, 'solicitud_creada', "🔔 Nueva solicitud del taller {$taller->name}", 'emails.nueva_solicitud_evaluador', [
                'evaluador_name' => $evaluador->name,
                'cliente_name' => $taller->name,
                'placa' => $placa,
                'url_gestion' => EvaluadorSolicitudResource::getUrl('index'),
            ]);
        }
    }
}

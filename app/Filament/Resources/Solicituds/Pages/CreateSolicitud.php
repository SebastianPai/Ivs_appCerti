<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorSolicitudResource;
use App\Filament\Resources\Solicituds\Schemas\SolicitudForm;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Support\Correo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
        $data['user_id'] = Auth::id();

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

        $solicitud = $this->record->load('vehicle');
        $taller = Auth::user();
        $placa = $solicitud->placa();

        Correo::enviar($taller->email, "Solicitud iniciada - Placa: {$placa}", 'emails.solicitud_iniciada', [
            'user_name' => $taller->name,
            'placa' => $placa,
            'url_adjuntos' => $this->getResource()::getUrl('attachments', ['record' => $solicitud]),
        ]);

        foreach ($taller->evaluadores()->get() as $evaluador) {
            Correo::enviar($evaluador->email, "🔔 Nueva solicitud del taller {$taller->name}", 'emails.nueva_solicitud_evaluador', [
                'evaluador_name' => $evaluador->name,
                'cliente_name' => $taller->name,
                'placa' => $placa,
                'url_gestion' => EvaluadorSolicitudResource::getUrl('index'),
            ]);
        }
    }
}

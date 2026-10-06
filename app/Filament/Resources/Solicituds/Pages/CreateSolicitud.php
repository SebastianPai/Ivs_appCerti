<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorSolicitudResource;
use App\Filament\Resources\Solicituds\Schemas\SolicitudForm;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Support\Correo;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateSolicitud extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SolicitudResource::class;

    protected static bool $canCreateAnother = false;

    public function getSteps(): array
    {
        return SolicitudForm::steps();
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

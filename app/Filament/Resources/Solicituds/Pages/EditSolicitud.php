<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Filament\Resources\Solicituds\Schemas\SolicitudForm;
use App\Filament\Resources\Solicituds\SolicitudResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSolicitud extends EditRecord
{
    protected static string $resource = SolicitudResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('adjuntos')
                ->label('Documentos')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->url(fn () => SolicitudResource::getUrl('attachments', ['record' => $this->record])),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...SolicitudForm::datosVehiculo($this->record->vehicle)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update(SolicitudForm::guardarVehiculo($data));

        return $record;
    }

    /** Después de corregir datos, el taller sigue con los documentos. */
    protected function getRedirectUrl(): ?string
    {
        return SolicitudResource::getUrl('attachments', ['record' => $this->record]);
    }
}

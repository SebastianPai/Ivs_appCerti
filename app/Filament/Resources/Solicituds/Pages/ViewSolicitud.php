<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Filament\Resources\Solicituds\Schemas\SolicitudInfolist;
use App\Filament\Resources\Solicituds\SolicitudResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSolicitud extends ViewRecord
{
    protected static string $resource = SolicitudResource::class;

    public function getTitle(): string
    {
        return 'Solicitud '.$this->record->placa();
    }

    public function infolist(Schema $schema): Schema
    {
        return SolicitudInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('certificado')
                ->label('Descargar certificado')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('solicitud.certificado', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->estaAprobada()),

            EditAction::make()
                ->label(fn () => $this->record->estado === 'devuelta_taller' ? 'Corregir solicitud' : 'Editar'),

            Action::make('adjuntos')
                ->label('Documentos')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->url(fn () => SolicitudResource::getUrl('attachments', ['record' => $this->record]))
                ->visible(fn () => SolicitudResource::canEdit($this->record)),
        ];
    }
}

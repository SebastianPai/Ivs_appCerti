<?php

namespace App\Filament\Actions;

use App\Exports\SolicitudesExport;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Contracts\HasTable;

/**
 * Botones "Exportar a Excel" para las tablas de solicitudes (taller, evaluador, revisor).
 * Exportan exactamente lo que el usuario está viendo: su alcance, la pestaña activa,
 * los filtros y la búsqueda.
 */
class ExportarSolicitudesAction
{
    /** Botón de la cabecera: exporta todo lo que coincide con la vista actual. */
    public static function make(string $nombre = 'solicitudes'): Action
    {
        return Action::make('exportarExcel')
            ->label('Exportar a Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (HasTable $livewire) use ($nombre) {
                $consulta = $livewire->getFilteredSortedTableQuery();

                if (! $consulta || ! (clone $consulta)->exists()) {
                    Notification::make()->title('No hay solicitudes para exportar con estos filtros')->warning()->send();

                    return null;
                }

                return SolicitudesExport::descargar($consulta, $nombre);
            });
    }

    /** Acción masiva: exporta solo las filas seleccionadas. */
    public static function seleccionadas(string $nombre = 'solicitudes'): BulkAction
    {
        return BulkAction::make('exportarSeleccionadas')
            ->label('Exportar seleccionadas')
            ->icon('heroicon-o-arrow-down-tray')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => SolicitudesExport::descargar(
                $records->first()->newQuery()->whereKey($records->modelKeys()),
                $nombre,
            ));
    }
}

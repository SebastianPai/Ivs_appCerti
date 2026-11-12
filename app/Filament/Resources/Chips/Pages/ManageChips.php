<?php

namespace App\Filament\Resources\Chips\Pages;

use App\Filament\Resources\Chips\ChipResource;
use App\Support\ImportarChips;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageChips extends ManageRecords
{
    protected static string $resource = ChipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar chip')->createAnother(true),

            // Migración: el inventario de chips de la plataforma anterior (ver ImportarChips)
            Action::make('importar')
                ->label('Importar chips')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importar chips de la plataforma anterior')
                ->modalDescription('Agrega los chips que falten con su estado (activo o inactivo). Los que ya existen no se modifican. Puede repetirse sin duplicar.')
                ->modalSubmitActionLabel('Importar')
                ->schema([
                    FileUpload::make('archivo')
                        ->label('Archivo de chips (.json, o .txt/.csv con un código por línea)')
                        ->disk('local')
                        ->directory('importaciones')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/csv'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ruta = $data['archivo'];

                    try {
                        $resumen = ImportarChips::desdeTexto((string) Storage::disk('local')->get($ruta));
                    } catch (\Throwable $e) {
                        Notification::make()->title('No se pudo importar')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($ruta);
                    }

                    $omitidos = count($resumen['omitidos']);

                    Notification::make()
                        ->title('Chips importados')
                        ->body("{$resumen['creados']} nuevos y {$resumen['existentes']} que ya estaban."
                            .($omitidos ? " Sin importar ({$omitidos}): ".implode('; ', array_slice($resumen['omitidos'], 0, 10)).($omitidos > 10 ? '…' : '').'.' : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}

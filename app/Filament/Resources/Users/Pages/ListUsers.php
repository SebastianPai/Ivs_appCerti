<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\ImportarUsuarios;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Paso único de la migración: el archivo con los usuarios de la plataforma anterior
            Action::make('importar')
                ->label('Importar usuarios')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importar usuarios de la plataforma anterior')
                ->modalDescription('Crea o actualiza los usuarios con sus roles, accesos, servicios, registro, inventario y talleres. '
                    .'Los nuevos entran con la contraseña inicial '.ImportarUsuarios::PASSWORD_INICIAL.' y deben cambiarla al entrar. '
                    .'Puede repetirse sin duplicar a nadie.')
                ->modalSubmitActionLabel('Importar')
                ->schema([
                    FileUpload::make('archivo')
                        ->label('Archivo de usuarios (.json)')
                        ->disk('local')
                        ->directory('importaciones')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/json', 'text/plain'])
                        ->maxSize(2048)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ruta = $data['archivo'];

                    try {
                        $resumen = ImportarUsuarios::desde(json_decode(Storage::disk('local')->get($ruta), true, flags: JSON_THROW_ON_ERROR));
                    } catch (\Throwable $e) {
                        Notification::make()->title('No se pudo importar')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        // Tiene datos personales: no se deja en el servidor
                        Storage::disk('local')->delete($ruta);
                    }

                    Notification::make()
                        ->title('Usuarios importados')
                        ->body("{$resumen['creados']} nuevos y {$resumen['actualizados']} actualizados."
                            .($resumen['omitidos'] ? ' Sin importar: '.implode('; ', $resumen['omitidos']).'.' : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}

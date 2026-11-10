<?php

namespace App\Filament\Resources\VehicleModels\Pages;

use App\Filament\Resources\VehicleModels\VehicleModelResource;
use App\Models\VehicleBrand;
use App\Models\VehicleType;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleModels extends ManageRecords
{
    protected static string $resource = VehicleModelResource::class;

    /** Los tres «registros base» de la plataforma anterior: Marca, Modelo y Tipo. */
    protected function getHeaderActions(): array
    {
        return [
            $this->registroBase('marca', 'Marca', VehicleBrand::class),
            CreateAction::make()->label('Modelo'),
            $this->registroBase('tipo', 'Tipo', VehicleType::class),
        ];
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelo */
    private function registroBase(string $nombre, string $etiqueta, string $modelo): Action
    {
        return Action::make($nombre)
            ->label($etiqueta)
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->modalHeading("Agregar {$etiqueta}")
            ->schema([
                TextInput::make('nombre')
                    ->label($etiqueta)
                    ->required()
                    ->maxLength(120)
                    ->unique($modelo, 'nombre'),
            ])
            ->action(function (array $data) use ($modelo) {
                $modelo::create(['nombre' => mb_strtoupper(trim($data['nombre']))]);

                Notification::make()->title('Se actualizó el registro correctamente')->success()->send();
            });
    }
}

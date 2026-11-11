<?php

namespace App\Filament\Pages;

use App\Models\Actividad;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de Industria y Comercio del taller. El taller carga el documento y el admin le asigna
 * la fecha de expedición (vence al año). Mientras esté vencido, el taller solo ve esta pantalla.
 */
class RegistroTaller extends Page
{
    protected string $view = 'filament.pages.registro-taller';

    protected static ?string $slug = 'registro-industria-comercio';

    protected static ?string $title = 'Registro industria y comercio';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->esTaller();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registro industria y comercio')
                    ->description('Cargar o actualizar nuevo archivo.')
                    ->schema([
                        FileUpload::make('archivo')
                            ->hiddenLabel()
                            ->disk('local')
                            ->visibility('private')
                            ->directory('camara-comercio')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $user = Auth::user();

        // El documento nuevo deja sin efecto las fechas anteriores: el admin debe asignarlas otra vez
        $user->update([
            'camara_comercio' => $this->form->getState()['archivo'],
            'fecha_radicado' => null,
            'fecha_vencimiento' => null,
        ]);

        Actividad::registrar('documento', "Registro de Industria y Comercio cargado por {$user->name}", $user);

        Notification::make()
            ->title('Registro de Industria y Comercio por revisar')
            ->body("{$user->name} cargó su registro. Asígnele la fecha de expedición para que pueda entrar.")
            ->warning()
            ->actions([
                // Lleva al usuario, donde están el documento y la fecha de expedición
                \Filament\Actions\Action::make('asignarFecha')
                    ->label('Asignar fecha')
                    ->url(\App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $user]))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::role('admin')->get());

        $this->form->fill();

        Notification::make()
            ->title('Buen trabajo!')
            ->body('El archivo ha sido actualizado correctamente. Por favor, contacta con el administrador para que actualice la fecha de vencimiento del registro.')
            ->success()
            ->persistent()
            ->send();
    }
}

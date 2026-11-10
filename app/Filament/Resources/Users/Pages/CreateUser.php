<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // La contraseña la hashea el cast 'hashed' del modelo User
        $user = User::create(Arr::except($data, ['role']));
        $user->syncRoles([$data['role']]);

        if ($data['role'] === 'cliente') {
            $user->update(['is_taller' => true]);
        }

        // El usuario confirma su correo con el enlace que le llega; sin eso no entra
        try {
            $user->enviarConfirmacionDeCorreo();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar la confirmación de correo a {$user->email}: {$e->getMessage()}");
        }

        // Como en la plataforma anterior, un taller nuevo no tiene servicios hasta que se le asignan
        if ($data['role'] === 'cliente' && \App\Models\SystemSetting::enabled('servicios_por_taller')) {
            \Filament\Notifications\Notification::make()
                ->title('Falta asignarle los servicios')
                ->body("{$user->name} no podrá crear solicitudes hasta que le asigne los servicios (menú de la fila › «Asignación de servicios») y, si es taller, la fecha del registro de Industria y Comercio.")
                ->warning()
                ->persistent()
                ->send();
        }

        return $user;
    }
}

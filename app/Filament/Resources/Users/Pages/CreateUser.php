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

        return $user;
    }
}

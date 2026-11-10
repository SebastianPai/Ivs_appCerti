<?php

namespace App\Filament\Resources\Contactenos\Pages;

use App\Filament\Resources\Contactenos\ContactenosResource;
use App\Models\MensajeContacto;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageContactenos extends ManageRecords
{
    protected static string $resource = ContactenosResource::class;

    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make('Ver Pendientes')
                ->icon('heroicon-o-inbox')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('gestionado_en'))
                ->badge(fn () => MensajeContacto::whereNull('gestionado_en')->count() ?: null)
                ->badgeColor('warning'),

            'gestionadas' => Tab::make('Ver Gestionadas')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('gestionado_en')),
        ];
    }
}

<?php

namespace App\Filament\Resources\Chips\Pages;

use App\Filament\Resources\Chips\ChipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChips extends ManageRecords
{
    protected static string $resource = ChipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar chip')->createAnother(true),
        ];
    }
}

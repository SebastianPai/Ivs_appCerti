<?php

namespace App\Filament\Resources\CylinderBrands\Pages;

use App\Filament\Resources\CylinderBrands\CylinderBrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCylinderBrands extends ManageRecords
{
    protected static string $resource = CylinderBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

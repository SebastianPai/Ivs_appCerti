<?php

namespace App\Filament\Resources\RegulatorBrands\Pages;

use App\Filament\Resources\RegulatorBrands\RegulatorBrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegulatorBrands extends ManageRecords
{
    protected static string $resource = RegulatorBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

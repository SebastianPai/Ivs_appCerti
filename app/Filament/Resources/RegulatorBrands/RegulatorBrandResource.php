<?php

namespace App\Filament\Resources\RegulatorBrands;

use App\Filament\Resources\RegulatorBrands\Pages\ManageRegulatorBrands;
use App\Filament\Resources\Support\MarcaSicom;
use App\Models\RegulatorBrand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RegulatorBrandResource extends Resource
{
    use \App\Filament\Resources\Concerns\RespetaPermisosPropios;

    protected static ?string $model = RegulatorBrand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'marcas-reguladores';

    protected static ?string $navigationLabel = 'Editar Marca Reguladores';

    protected static ?string $modelLabel = 'marca de regulador';

    protected static ?string $pluralModelLabel = 'marcas de reguladores';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return MarcaSicom::form($schema, RegulatorBrand::class);
    }

    public static function table(Table $table): Table
    {
        return MarcaSicom::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegulatorBrands::route('/'),
        ];
    }
}

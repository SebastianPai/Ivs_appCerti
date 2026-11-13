<?php

namespace App\Filament\Resources\CylinderBrands;

use App\Filament\Resources\CylinderBrands\Pages\ManageCylinderBrands;
use App\Filament\Resources\Support\MarcaSicom;
use App\Models\CylinderBrand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CylinderBrandResource extends Resource
{
    use \App\Filament\Resources\Concerns\RespetaPermisosPropios;

    protected static ?string $model = CylinderBrand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'marcas-cilindros';

    protected static ?string $navigationLabel = 'Editar Marca Cilindros';

    protected static ?string $modelLabel = 'marca de cilindro';

    protected static ?string $pluralModelLabel = 'marcas de cilindros';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return MarcaSicom::form($schema, CylinderBrand::class);
    }

    public static function table(Table $table): Table
    {
        return MarcaSicom::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCylinderBrands::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\ServiceTypes;

use App\Filament\Resources\ServiceTypes\Pages\CreateServiceType;
use App\Filament\Resources\ServiceTypes\Pages\EditServiceType;
use App\Filament\Resources\ServiceTypes\Pages\ListServiceTypes;
use App\Filament\Resources\ServiceTypes\Pages\ViewServiceType;
use App\Filament\Resources\ServiceTypes\Schemas\ServiceTypeForm;
use App\Filament\Resources\ServiceTypes\Schemas\ServiceTypeInfolist;
use App\Filament\Resources\ServiceTypes\Tables\ServiceTypesTable;
use App\Models\ServiceType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceTypeResource extends Resource
{
    protected static ?string $model = ServiceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $navigationLabel = 'Tipos de servicio';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    /** Solo administradores (sin esto cualquier usuario logueado podía entrar por URL). */
    public static function canViewAny(): bool
    {
        return (bool) \Illuminate\Support\Facades\Auth::user()?->hasRole('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceTypes::route('/'),
            'create' => CreateServiceType::route('/create'),
            'view' => ViewServiceType::route('/{record}'),
            'edit' => EditServiceType::route('/{record}/edit'),
        ];
    }
}

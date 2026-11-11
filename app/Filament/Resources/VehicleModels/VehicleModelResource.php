<?php

namespace App\Filament\Resources\VehicleModels;

use App\Filament\Resources\VehicleModels\Pages\ManageVehicleModels;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Catálogo de vehículos (Administrador › Editar Vehículos): se elige la marca y se ven sus
 * modelos. Marcas y tipos de vehículo se agregan con los botones de la cabecera.
 */
class VehicleModelResource extends Resource
{
    protected static ?string $model = VehicleModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'vehiculos';

    protected static ?string $navigationLabel = 'Editar Vehículos';

    protected static ?string $modelLabel = 'modelo';

    protected static ?string $pluralModelLabel = 'vehículos';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('brand_id')
                ->label('Marca')
                ->options(fn () => VehicleBrand::orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('nombre')
                ->label('Modelo')
                ->required()
                ->maxLength(120)
                // Se guarda en mayúsculas: se compara igual, sin importar cómo lo digitó
                ->rule(fn (\Filament\Schemas\Components\Utilities\Get $get, ?\App\Models\VehicleModel $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    $repetido = \App\Models\VehicleModel::where('brand_id', $get('brand_id'))
                        ->whereRaw('UPPER(nombre) = ?', [mb_strtoupper(trim((string) $value))])
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->exists();
                    if ($repetido) {
                        $fail('Esa marca ya tiene este modelo.');
                    }
                })
                ->dehydrateStateUsing(fn (?string $state) => $state ? mb_strtoupper(trim($state)) : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('id')->label('Id')->sortable(),
                TextColumn::make('brand.nombre')->label('Marca')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Modelo')->searchable()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Marca')
                    ->options(fn () => VehicleBrand::orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Sin modelos')
            ->emptyStateDescription('Elija otra marca o agregue un modelo.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleModels::route('/'),
        ];
    }
}

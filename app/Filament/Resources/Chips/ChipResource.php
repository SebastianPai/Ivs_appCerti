<?php

namespace App\Filament\Resources\Chips;

use App\Filament\Resources\Chips\Pages\ManageChips;
use App\Models\Chip;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Inventario de chips (Admin Chips › Agregar Chips en la plataforma anterior). El evaluador solo
 * puede asignar a una solicitud un chip registrado aquí y activo.
 */
class ChipResource extends Resource
{
    protected static ?string $model = Chip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static ?string $slug = 'chips';

    protected static ?string $navigationLabel = 'Agregar Chips';

    protected static ?string $modelLabel = 'chip';

    protected static ?string $pluralModelLabel = 'chips';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin Chips';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')
                ->label('Id Chip')
                ->placeholder('Acerque el chip al lector o digite los 16 caracteres')
                ->required()
                ->regex('/^[0-9A-Za-z]{16}$/')
                ->validationMessages(['regex' => 'El Id del chip tiene 16 letras o números.'])
                ->unique(Chip::class, 'codigo', ignoreRecord: true)
                ->autocomplete(false)
                ->extraInputAttributes(['id' => 'ivs-chip-nuevo', 'style' => 'text-transform: uppercase', 'autocapitalize' => 'characters'])
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null),
            // Mismo lector que usa el evaluador; escribe en el campo de este formulario
            View::make('filament.evaluador.solicituds.chip-reader')
                ->viewData(['campo' => 'mountedActions.0.data.codigo', 'entrada' => 'ivs-chip-nuevo', 'notas' => false])
                ->visibleOn('create'),
            TextInput::make('descripcion')
                ->label('Descripción')
                ->maxLength(120),
            Toggle::make('activo')
                ->label('Activo')
                ->helperText(fn () => \App\Services\ChipValidationService::permiteInactivos()
                    ? 'La configuración actual permite usar chips inactivos en las solicitudes.'
                    : 'Un chip inactivo no se puede asignar a una solicitud con chip nuevo.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['verificaciones' => fn ($q) => $q->oldest()->with('solicitud:id,vehicle_identification')]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Id')->sortable(),
                TextColumn::make('codigo')->label('Chip')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('solicitud')
                    ->label('Solicitud')
                    ->state(fn (Chip $record) => ($v = $record->verificaciones->first()) ? "{$v->solicitud_id} · {$v->solicitud?->vehicle_identification}" : null)
                    ->placeholder('Sin usar'),
                TextColumn::make('created_at')->label('Fecha Creación')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('fecha_uso')
                    ->label('Fecha Uso')
                    ->state(fn (Chip $record) => $record->verificaciones->first()?->created_at?->format('d/m/Y H:i'))
                    ->placeholder('—'),
                TextColumn::make('activo')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Estado')->trueLabel('Activo')->falseLabel('Inactivo')->placeholder('Todos'),
                TernaryFilter::make('usado')
                    ->label('Uso')
                    ->trueLabel('Con solicitud')
                    ->falseLabel('Sin usar')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query) => $query->has('verificaciones'),
                        false: fn (Builder $query) => $query->doesntHave('verificaciones'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Aún no hay chips registrados')
            ->emptyStateDescription('Agregue los chips antes de que el evaluador los lea en una solicitud.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageChips::route('/'),
        ];
    }
}

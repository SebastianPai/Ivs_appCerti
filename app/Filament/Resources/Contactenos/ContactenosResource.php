<?php

namespace App\Filament\Resources\Contactenos;

use App\Filament\Resources\Contactenos\Pages\ManageContactenos;
use App\Models\MensajeContacto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Administrador › Contactenos: bandeja de los mensajes que escriben los usuarios, con las pestañas
 * «Ver Pendientes» y «Ver Gestionadas» de la plataforma anterior.
 */
class ContactenosResource extends Resource
{
    protected static ?string $model = MensajeContacto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'admin-contactenos';

    protected static ?string $navigationLabel = 'Contactenos';

    protected static ?string $modelLabel = 'mensaje';

    protected static ?string $pluralModelLabel = 'Contáctenos';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) (MensajeContacto::whereNull('gestionado_en')->count() ?: '') ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Id')->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('asunto')->label('Asunto')->searchable()->wrap(),
                TextColumn::make('mensaje')->label('Mensaje')->searchable()->wrap()->lineClamp(4),
                TextColumn::make('created_at')->label('Fecha Creación')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('gestionado_en')
                    ->label('Gestionado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (MensajeContacto $record) => $record->gestor?->name)
                    ->placeholder('Pendiente'),
            ])
            ->recordActions([
                Action::make('gestionar')
                    ->label('Marcar gestionado')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (MensajeContacto $record) => $record->gestionado_en === null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (MensajeContacto $record) => "¿Marcar como gestionado el mensaje {$record->id}?")
                    ->action(function (MensajeContacto $record) {
                        $record->update(['gestionado_en' => now(), 'gestionado_por' => Auth::id()]);
                        Notification::make()->title('Mensaje gestionado')->success()->send();
                    }),
                Action::make('reabrir')
                    ->label('Volver a pendiente')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (MensajeContacto $record) => $record->gestionado_en !== null)
                    ->action(fn (MensajeContacto $record) => $record->update(['gestionado_en' => null, 'gestionado_por' => null])),
            ])
            ->emptyStateHeading('No hay mensajes');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContactenos::route('/'),
        ];
    }
}

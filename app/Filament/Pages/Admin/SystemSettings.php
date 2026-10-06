<?php

namespace App\Filament\Pages\Admin;

use App\Models\SystemSetting;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SystemSettings extends Page
{
    protected string $view = 'filament.pages.admin.system-settings';

    protected static ?string $slug = 'configuracion-sistema';

    protected static ?string $title = 'Configuración del sistema';

    protected static ?string $navigationLabel = 'Configuración';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 99;

    public const ROLES = ['cliente' => 'Taller (cliente)', 'evaluador' => 'Evaluador', 'revisor' => 'Revisor'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public function mount(): void
    {
        $roles = SystemSetting::meta('restrict_pc_access', 'roles') ?? [];

        $data = [
            'chip_required' => SystemSetting::enabled('chip_required'),
            'restrict_pc_access' => SystemSetting::enabled('restrict_pc_access'),
        ];

        foreach (array_keys(self::ROLES) as $rol) {
            $data["acceso_{$rol}"] = match (true) {
                (bool) ($roles[$rol]['block_all'] ?? false) => 'bloqueado',
                (bool) ($roles[$rol]['restrict_pc'] ?? false) => 'solo_movil',
                default => 'todos',
            };
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $porRol = [];
        foreach (self::ROLES as $rol => $etiqueta) {
            $porRol[] = Radio::make("acceso_{$rol}")
                ->label($etiqueta)
                ->options([
                    'todos' => 'Celular y PC',
                    'solo_movil' => 'Solo celular',
                    'bloqueado' => 'Sin acceso',
                ])
                ->default('todos');
        }

        return $schema
            ->components([
                Section::make('Lectura de chip')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Toggle::make('chip_required')
                            ->label('El chip es obligatorio para evaluar')
                            ->helperText('Si está activo, el evaluador no puede pasar al checklist sin leer un chip válido y activo.'),
                    ]),

                Section::make('Acceso por dispositivo')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->description('Los administradores nunca se restringen.')
                    ->schema([
                        Toggle::make('restrict_pc_access')
                            ->label('Activar restricciones de acceso')
                            ->live(),
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get) => (bool) $get('restrict_pc_access'))
                            ->schema($porRol),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SystemSetting::put('chip_required', (bool) $data['chip_required']);

        $roles = [];
        foreach (array_keys(self::ROLES) as $rol) {
            $modo = $data["acceso_{$rol}"] ?? 'todos';
            $roles[$rol] = [
                'block_all' => $modo === 'bloqueado',
                'restrict_pc' => $modo === 'solo_movil',
            ];
        }

        SystemSetting::put('restrict_pc_access', (bool) $data['restrict_pc_access'], ['roles' => $roles]);

        Notification::make()->title('Configuración guardada')->success()->send();
    }
}

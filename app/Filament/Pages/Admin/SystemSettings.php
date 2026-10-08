<?php

namespace App\Filament\Pages\Admin;

use App\Models\SystemSetting;
use App\Support\Correo;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
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

        $desactivados = SystemSetting::meta('correos', 'desactivados') ?? [];
        $vigencia = SystemSetting::vigencia();

        $data = [
            'chip_required' => SystemSetting::enabled('chip_required'),
            'restrict_pc_access' => SystemSetting::enabled('restrict_pc_access'),
            'correos' => SystemSetting::enabledOr('correos', true),
            'correos_tipos' => array_values(array_diff(array_keys(Correo::TIPOS), $desactivados)),
            'vigencia_meses' => $vigencia['meses'],
            'vigencia_dias_aviso' => $vigencia['dias_aviso'],
            'exigir_2fa' => SystemSetting::enabled('exigir_2fa'),
            'exigir_2fa_roles' => SystemSetting::meta('exigir_2fa', 'roles') ?? ['admin'],
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

                Section::make('Correos')
                    ->icon('heroicon-o-envelope')
                    ->description('Los avisos dentro de la app (campanita) siempre se envían; esto controla solo los correos.')
                    ->schema([
                        Toggle::make('correos')
                            ->label('Enviar correos')
                            ->helperText('Apáguelo, por ejemplo, mientras se configura el servidor de correo o durante pruebas.')
                            ->live(),
                        CheckboxList::make('correos_tipos')
                            ->label('Correos que se envían')
                            ->options(Correo::TIPOS)
                            ->bulkToggleable()
                            ->visible(fn (Get $get) => (bool) $get('correos')),
                    ]),

                Section::make('Vigencia de los certificados')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('vigencia_meses')
                                ->label('Vigencia del certificado')
                                ->numeric()->integer()->minValue(1)->maxValue(120)->required()
                                ->suffix('meses')
                                ->helperText('Aplica a los certificados que se aprueben de aquí en adelante.'),
                            TextInput::make('vigencia_dias_aviso')
                                ->label('Avisar antes del vencimiento')
                                ->numeric()->integer()->minValue(1)->maxValue(180)->required()
                                ->suffix('días')
                                ->helperText('Se avisa una vez al taller y al propietario.'),
                        ]),
                    ]),

                Section::make('Seguridad')
                    ->icon('heroicon-o-lock-closed')
                    ->description('Cada usuario puede activar la verificación en dos pasos desde su perfil (menú del usuario → Perfil).')
                    ->schema([
                        Toggle::make('exigir_2fa')
                            ->label('Exigir verificación en dos pasos')
                            ->helperText('Los roles elegidos deberán configurarla con una app autenticadora (Google Authenticator, Microsoft Authenticator…) al entrar.')
                            ->live(),
                        CheckboxList::make('exigir_2fa_roles')
                            ->label('Roles obligados')
                            ->options(['admin' => 'Administrador', ...self::ROLES])
                            ->columns(['default' => 1, 'md' => 4])
                            ->visible(fn (Get $get) => (bool) $get('exigir_2fa')),
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

        // Las listas ocultas (interruptor apagado) no llegan en $data: se conserva lo que había
        SystemSetting::put('correos', (bool) $data['correos'], [
            'desactivados' => isset($data['correos_tipos'])
                ? array_values(array_diff(array_keys(Correo::TIPOS), $data['correos_tipos']))
                : (SystemSetting::meta('correos', 'desactivados') ?? []),
        ]);

        SystemSetting::put('vigencia', true, [
            'meses' => (int) $data['vigencia_meses'],
            'dias_aviso' => (int) $data['vigencia_dias_aviso'],
        ]);

        SystemSetting::put('exigir_2fa', (bool) $data['exigir_2fa'], [
            'roles' => array_values($data['exigir_2fa_roles'] ?? SystemSetting::meta('exigir_2fa', 'roles') ?? []),
        ]);

        Notification::make()->title('Configuración guardada')->success()->send();
    }
}

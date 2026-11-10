<?php

namespace App\Filament\Pages\Admin;

use App\Models\SystemSetting;
use App\Support\Correo;
use BackedEnum;
use App\Support\MarcaCertificado;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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

    protected static ?string $title = 'Configuración General';

    protected static ?string $navigationLabel = 'Configuración General';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrador';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

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
            'checklist_prellenado' => SystemSetting::enabled('checklist_prellenado'),
            'sicom_consulta_evaluacion' => SystemSetting::enabled('sicom_consulta_evaluacion'),
            'sicom_consulta_diagnostico' => SystemSetting::enabledOr('sicom_consulta_diagnostico', true),
            'certificado_evaluador_fijo' => SystemSetting::enabled('certificado_evaluador_fijo'),
            'certificado_evaluador_nombre' => SystemSetting::meta('certificado_evaluador_fijo', 'nombre'),
            'certificado_al_evaluar' => SystemSetting::enabled('certificado_al_evaluar'),
            'registro_taller_required' => SystemSetting::enabled('registro_taller_required'),
            'servicios_por_taller' => SystemSetting::enabled('servicios_por_taller'),
            'restrict_pc_access' => SystemSetting::enabled('restrict_pc_access'),
            'correos' => SystemSetting::enabledOr('correos', true),
            'correos_tipos' => array_values(array_diff(array_keys(Correo::TIPOS), $desactivados)),
            'vigencia_meses' => $vigencia['meses'],
            'vigencia_dias_aviso' => $vigencia['dias_aviso'],
            'exigir_2fa' => SystemSetting::enabled('exigir_2fa'),
            'exigir_2fa_roles' => SystemSetting::meta('exigir_2fa', 'roles') ?? ['admin'],
            'organismo_nit' => \App\Exports\Exportables::nitCertificador(),
            'consecutivo_certificado' => \App\Models\Solicitud::siguienteNumeroCertificado(),
            'cert_firmante' => MarcaCertificado::firmante(),
            'cert_cargo' => MarcaCertificado::cargo(),
            'cert_opacidad' => (int) round(MarcaCertificado::opacidad() * 100),
            ...collect(array_keys(MarcaCertificado::IMAGENES))->mapWithKeys(fn ($imagen) => ["cert_{$imagen}" => MarcaCertificado::ajustes()[$imagen] ?? null])->all(),
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
                            ->label('Obligar lectura de chip')
                            ->helperText('Si está activo, el evaluador no puede pasar al checklist sin leer un chip válido y activo.'),
                        Toggle::make('sicom_consulta_evaluacion')
                            ->label('Permitir consulta a sicom inspector y taller')
                            ->helperText('Encendido: al crear la solicitud se busca la placa en SICOM, y el chip se contrasta con SICOM (una revisión exige que exista; un chip nuevo, que no exista). Apagado: no se consulta y se acepta el chip leído.'),
                        Toggle::make('sicom_consulta_diagnostico')
                            ->label('Permitir consulta a sicom en diagnóstico de chip')
                            ->helperText('Muestra en Diagnóstico Chip lo que SICOM sabe del chip.'),
                    ]),

                Section::make('Talleres')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Toggle::make('registro_taller_required')
                            ->label('Exigir el registro de Industria y Comercio vigente')
                            ->helperText('Como en la plataforma anterior: el taller con el registro vencido o sin fecha solo puede cargar el documento, y entra cuando el admin le asigna la fecha (aparece en Inicio).'),
                        Toggle::make('servicios_por_taller')
                            ->label('Limitar cada taller a los servicios que tiene asignados')
                            ->helperText('Como en la plataforma anterior: el taller solo ve en «Nueva Solicitud» los tipos de servicio que se le asignaron en Administrar Usuarios › «Asignación de servicios». Apagado, todos ven todos.'),
                    ]),

                Section::make('Checklist técnico')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Toggle::make('certificado_al_evaluar')
                            ->label('Emitir el certificado cuando el evaluador envía la lista')
                            ->helperText('Como en la plataforma anterior: el evaluador entrega el certificado en el sitio y el revisor audita después (marca «revisada» o devuelve). Apagado, el certificado espera la aprobación del revisor.'),
                        Toggle::make('certificado_evaluador_fijo')
                            ->label('Nombre evaluador fijo en el certificado')
                            ->live()
                            ->helperText('Como «Configuraciones de certificado › Nombre evaluador» de la plataforma anterior: el certificado muestra siempre este nombre en lugar del evaluador que inspeccionó.'),
                        TextInput::make('certificado_evaluador_nombre')
                            ->label('Nombre que sale como evaluador')
                            ->maxLength(80)
                            ->visible(fn (Get $get) => (bool) $get('certificado_evaluador_fijo'))
                            ->required(fn (Get $get) => (bool) $get('certificado_evaluador_fijo')),
                        Toggle::make('checklist_prellenado')
                            ->label('Lista de verificación en cumple')
                            ->helperText('Los ítems habituales llegan marcados como «Cumple» (o «N/A» según el sistema de combustión y el número de cilindros). El evaluador revisa y cambia lo que no corresponda; las mediciones en cm siempre se digitan.'),
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

                Section::make('Organismo certificador')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        TextInput::make('organismo_nit')
                            ->label('NIT del organismo certificador')
                            ->maxLength(20)
                            ->helperText('Sale en la columna nit_certificador del exportable SICOM por certificado.'),
                        TextInput::make('consecutivo_certificado')
                            ->label('Próximo número de certificado')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->helperText('Los certificados se numeran en orden, uno tras otro. Al pasar de la plataforma anterior, escriba aquí el número siguiente al último que ella emitió.'),
                    ]),

                Section::make('Diseño de los certificados')
                    ->icon('heroicon-o-photo')
                    ->description('Imágenes y firma que salen en el certificado del vehículo y en el Certificado Taller. Lo que deje vacío sale con la imagen que trae la aplicación, si la hay.')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            self::imagenCertificado('logo', 'Logo de IVS', 'Encabezado de los dos certificados. Vacío: el logo actual.'),
                            self::imagenCertificado('onac', 'Logo de ONAC', 'Junto al logo, en el encabezado.'),
                            self::imagenCertificado('sello', 'Sello', 'Junto a la firma.'),
                            self::imagenCertificado('marca_agua', 'Marca de agua', 'Imagen de fondo, centrada en la página.'),
                            self::imagenCertificado('firma', 'Firma', 'Firma de quien suscribe el Certificado Taller. Mejor en PNG con fondo transparente.'),
                            TextInput::make('cert_opacidad')
                                ->label('Intensidad de la marca de agua')
                                ->numeric()
                                ->integer()
                                ->minValue(3)
                                ->maxValue(30)
                                ->suffix('%')
                                ->required()
                                ->helperText('Baja para que no tape el texto: entre 5 y 10 suele verse bien.'),
                            TextInput::make('cert_firmante')
                                ->label('Quién firma')
                                ->maxLength(80)
                                ->required(),
                            TextInput::make('cert_cargo')
                                ->label('Cargo de quien firma')
                                ->maxLength(80)
                                ->required(),
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
        SystemSetting::put('checklist_prellenado', (bool) $data['checklist_prellenado']);
        SystemSetting::put('sicom_consulta_evaluacion', (bool) $data['sicom_consulta_evaluacion']);
        SystemSetting::put('sicom_consulta_diagnostico', (bool) $data['sicom_consulta_diagnostico']);
        SystemSetting::put('certificado_evaluador_fijo', (bool) $data['certificado_evaluador_fijo'], ['nombre' => filled($data['certificado_evaluador_nombre'] ?? null) ? trim($data['certificado_evaluador_nombre']) : null]);
        SystemSetting::put('certificado_al_evaluar', (bool) $data['certificado_al_evaluar']);
        SystemSetting::put('registro_taller_required', (bool) $data['registro_taller_required']);
        SystemSetting::put('servicios_por_taller', (bool) $data['servicios_por_taller']);

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

        SystemSetting::put('organismo', true, ['nit' => filled($data['organismo_nit'] ?? null) ? trim($data['organismo_nit']) : null]);

        if ((int) $data['consecutivo_certificado'] !== \App\Models\Solicitud::siguienteNumeroCertificado()) {
            SystemSetting::put('consecutivo_certificado', true, ['siguiente' => (int) $data['consecutivo_certificado']]);
        }

        SystemSetting::put('certificados', true, [
            'firmante' => trim($data['cert_firmante']),
            'cargo' => trim($data['cert_cargo']),
            'opacidad' => (int) $data['cert_opacidad'],
            ...collect(array_keys(MarcaCertificado::IMAGENES))->mapWithKeys(fn ($imagen) => [$imagen => $data["cert_{$imagen}"] ?? null])->all(),
        ]);

        Notification::make()->title('Configuración guardada')->success()->send();
    }

    private static function imagenCertificado(string $imagen, string $etiqueta, string $ayuda): FileUpload
    {
        return FileUpload::make("cert_{$imagen}")
            ->label($etiqueta)
            ->helperText($ayuda)
            ->image()
            ->disk('local')
            ->visibility('private')
            ->directory('certificados-marca')
            ->acceptedFileTypes(['image/png', 'image/jpeg'])
            ->maxSize(2048);
    }
}

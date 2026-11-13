<?php

namespace App\Filament\Pages\Admin;

use App\Models\SystemSetting;
use App\Support\Correo;
use App\Support\TextosCorreo;
use BackedEnum;
use App\Support\MarcaCertificado;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
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
            'copia_firebase' => SystemSetting::enabledOr('copia_firebase', true),
            'chip_permitir_inactivo' => SystemSetting::enabled('chip_permitir_inactivo'),
            'checklist_prellenado' => SystemSetting::enabled('checklist_prellenado'),
            'sicom_consulta_evaluacion' => SystemSetting::enabled('sicom_consulta_evaluacion'),
            'sicom_consulta_diagnostico' => SystemSetting::enabledOr('sicom_consulta_diagnostico', true),
            'certificado_evaluador_fijo' => SystemSetting::enabled('certificado_evaluador_fijo'),
            'certificado_evaluador_nombre' => SystemSetting::meta('certificado_evaluador_fijo', 'nombre'),
            'certificado_al_evaluar' => SystemSetting::enabled('certificado_al_evaluar'),
            'registro_taller_required' => SystemSetting::enabled('registro_taller_required'),
            'servicios_por_taller' => SystemSetting::enabled('servicios_por_taller'),
            'restrict_pc_access' => SystemSetting::enabled('restrict_pc_access'),
            'restriccion_horario_inspector' => SystemSetting::enabled('restriccion_horario_inspector'),
            ...\App\Support\HorarioEvaluador::horario(),
            'correos' => SystemSetting::enabledOr('correos', true),
            'correos_tipos' => array_values(array_diff(array_keys(Correo::TIPOS), $desactivados)),
            'correos_limite_hora' => TextosCorreo::limiteHora(),
            'correos_pie' => TextosCorreo::pie(),
            'correos_textos' => TextosCorreo::textos(),
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
                            ->helperText('Si está activo, el evaluador no puede pasar al checklist sin leer un chip válido.'),
                        Toggle::make('chip_permitir_inactivo')
                            ->label('Permitir chips inactivos')
                            ->helperText('Encendido: se puede continuar con chips activos e inactivos (el lector avisa cuando el chip está inactivo). Apagado: un servicio con chip nuevo solo acepta chips activos. Un chip asociado a otro vehículo nunca se acepta.'),
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
                        TextInput::make('correos_limite_hora')
                            ->label('Máximo de correos del mismo tipo por destinatario, cada hora')
                            ->helperText('Evita el spam cuando un mismo correo recibe muchas solicitudes seguidas (p. ej. un taller que crea 100). Lo que pase del límite no sale por correo, pero sí aparece en la campanita. El correo con el certificado siempre se envía. 0 = sin límite.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        Textarea::make('correos_pie')
                            ->label('Pie de los correos (firma y contacto)')
                            ->placeholder("IVS Certificaciones · Calle 8 #47-136 Nueva Tequendama, Cali
(602) 308 7609 · servicioalcliente@ivscertificaciones.com")
                            ->helperText('Vacío = el contacto de siempre.')
                            ->rows(3)
                            ->maxLength(500),
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('correosPrueba')
                                ->label('Enviar correos de prueba')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('gray')
                                ->modalHeading('Enviar correos de prueba')
                                ->modalDescription('Llega un correo de cada tipo, con datos de ejemplo y los textos guardados (guarde antes sus cambios). Cada uno trae en el asunto su número y nombre, y arriba qué hace y a quién le llega.')
                                ->modalSubmitActionLabel('Enviar')
                                ->schema([
                                    TextInput::make('email')->label('Enviar a')->email()->required()->default(fn () => Auth::user()?->email),
                                ])
                                ->action(function (array $data) {
                                    try {
                                        $n = \App\Http\Controllers\VistaPreviaCorreosController::enviarPruebas($data['email']);
                                    } catch (\Throwable $e) {
                                        Notification::make()->title('No se pudieron enviar')->body($e->getMessage())->danger()->persistent()->send();

                                        return;
                                    }

                                    Notification::make()->title("{$n} correos de prueba enviados")->body("Revise la bandeja de {$data['email']} (y la de spam).")->success()->send();
                                }),
                        ]),
                    ]),

                Section::make('Textos de los correos')
                    ->icon('heroicon-o-pencil-square')
                    ->description(new \Illuminate\Support\HtmlString(e('Asunto y mensaje de cada correo. Puede usar {nombre} y {placa}. Lo que deje vacío usa el texto de siempre. El mensaje aparece destacado al inicio del correo.')
                        .' <a href="'.e(route('correos.vista-previa')).'" target="_blank" rel="noopener" class="font-semibold text-primary-600 underline dark:text-primary-400">Ver cómo quedan los correos</a> (guarde antes los cambios).'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        ...collect(TextosCorreo::CUENTA)->map(fn (array $texto, string $tipo) => Fieldset::make($texto['nombre'])
                            ->columns(1)
                            ->schema([
                                TextInput::make("correos_textos.{$tipo}.asunto")->label('Asunto')->placeholder($texto['asunto'])->maxLength(150),
                                Textarea::make("correos_textos.{$tipo}.mensaje")->label('Mensaje')->placeholder($texto['mensaje'])->rows(3)->maxLength(1000),
                            ]))->values()->all(),
                        ...collect(Correo::TIPOS)->map(fn (string $nombre, string $tipo) => Fieldset::make($nombre)
                            ->columns(1)
                            ->schema([
                                TextInput::make("correos_textos.{$tipo}.asunto")->label('Asunto')->placeholder('El de siempre (incluye la placa)')->maxLength(150),
                                Textarea::make("correos_textos.{$tipo}.mensaje")->label('Mensaje adicional')->placeholder('Opcional')->rows(2)->maxLength(1000),
                            ]))->values()->all(),
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

                Section::make('Almacenamiento y respaldos')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->description('Como en la plataforma anterior: cada documento y foto se guarda en el servidor y una copia en Firebase Cloud Storage, en una carpeta por solicitud. La base de datos se respalda allí una vez al día.')
                    ->schema([
                        \Filament\Schemas\Components\Text::make(fn () => \App\Support\Nube::configurada()
                            ? 'Firebase conectado: bucket '.config('filesystems.disks.firebase.bucket').'.'
                            : 'Firebase sin configurar: faltan FIREBASE_STORAGE_BUCKET y FIREBASE_CREDENTIALS en el .env del servidor. Mientras tanto los archivos quedan solo en el servidor.')
                            ->color(fn () => \App\Support\Nube::configurada() ? 'success' : 'warning'),
                        Toggle::make('copia_firebase')
                            ->label('Guardar una copia de los archivos en Firebase')
                            ->helperText('Carpetas: solicitudes/{n.º}/documentos, solicitudes/{n.º}/fotos, talleres/camara-comercio y respaldos/base-de-datos. Si un archivo se pierde en el servidor, la app lo abre desde Firebase.')
                            ->disabled(fn () => ! \App\Support\Nube::configurada()),
                        \Filament\Schemas\Components\Text::make(function () {
                            if (! \App\Support\Nube::activa()) {
                                return '';
                            }

                            try {
                                $lista = \App\Support\RespaldoBase::lista();
                            } catch (\Throwable $e) {
                                return 'No se pudo leer Firebase: '.$e->getMessage();
                            }

                            return $lista
                                ? 'Último respaldo de la base: '.$lista[0]['fecha']->format('d/m/Y h:i a').' · '.count($lista).' guardados (se conservan '.\App\Support\RespaldoBase::DIAS.' días).'
                                : 'Aún no hay respaldos de la base.';
                        }),
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('sincronizarFirebase')
                                ->label('Copiar archivos pendientes')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->visible(fn () => \App\Support\Nube::activa())
                                ->action(function () {
                                    $r = \App\Support\Nube::sincronizar();

                                    Notification::make()
                                        ->title("{$r['copiados']} archivo(s) copiados a Firebase")
                                        ->body(($r['pendientes'] ? "Quedan {$r['pendientes']}: pulse de nuevo para seguir. " : 'Todo está copiado. ').($r['errores'] ? "{$r['errores']} con error (ver el log)." : ''))
                                        ->color($r['errores'] ? 'warning' : 'success')
                                        ->send();
                                }),
                            \Filament\Actions\Action::make('respaldarBase')
                                ->label('Respaldar la base ahora')
                                ->icon('heroicon-o-circle-stack')
                                ->color('gray')
                                ->visible(fn () => \App\Support\Nube::activa())
                                ->action(function () {
                                    try {
                                        $ruta = \App\Support\RespaldoBase::subir();
                                    } catch (\Throwable $e) {
                                        Notification::make()->title('No se pudo respaldar')->body($e->getMessage())->danger()->persistent()->send();

                                        return;
                                    }

                                    Notification::make()->title('Respaldo guardado en Firebase')->body($ruta)->success()->send();
                                }),
                            \Filament\Actions\Action::make('descargarRespaldo')
                                ->label('Descargar un respaldo')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->visible(fn () => \App\Support\Nube::activa())
                                ->schema([
                                    \Filament\Forms\Components\Select::make('ruta')
                                        ->label('Respaldo')
                                        ->options(fn () => collect(\App\Support\RespaldoBase::lista())->mapWithKeys(fn ($r) => [$r['ruta'] => $r['fecha']->format('d/m/Y h:i a').' · '.\Illuminate\Support\Number::fileSize($r['tamano'])]))
                                        ->required(),
                                ])
                                ->action(fn (array $data) => redirect()->away(\Illuminate\Support\Facades\Storage::disk(\App\Support\Nube::DISCO)->temporaryUrl($data['ruta'], now()->addMinutes(5)))),
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

                // Como «Restringir acceso inspector» de la plataforma anterior. Allá las horas venían
                // fijas en el servidor; aquí se eligen.
                Section::make('Horario del evaluador')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Toggle::make('restriccion_horario_inspector')
                            ->label('Restringir acceso inspector')
                            ->helperText('Fuera del horario, el evaluador no puede abrir solicitudes desde Solicitudes Abiertas. El resto del menú sigue disponible.')
                            ->live(),
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get) => (bool) $get('restriccion_horario_inspector'))
                            ->schema([
                                \Filament\Forms\Components\TimePicker::make('horario_desde')->label('Desde')->seconds(false)->required(),
                                \Filament\Forms\Components\TimePicker::make('horario_hasta')->label('Hasta')->seconds(false)->required()->after('horario_desde'),
                                CheckboxList::make('horario_dias')
                                    ->label('Días')
                                    ->options(\App\Support\HorarioEvaluador::DIAS)
                                    ->columns(4)
                                    ->required(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SystemSetting::put('chip_required', (bool) $data['chip_required']);
        // Sin credenciales el interruptor está deshabilitado y no viaja en el formulario
        SystemSetting::put('copia_firebase', (bool) ($data['copia_firebase'] ?? SystemSetting::enabledOr('copia_firebase', true)));
        SystemSetting::put('chip_permitir_inactivo', (bool) $data['chip_permitir_inactivo']);
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

        // Con el interruptor apagado los campos no llegan: se conserva el horario que había
        $horario = \App\Support\HorarioEvaluador::horario();
        SystemSetting::put('restriccion_horario_inspector', (bool) $data['restriccion_horario_inspector'], [
            'desde' => substr($data['horario_desde'] ?? $horario['horario_desde'], 0, 5),
            'hasta' => substr($data['horario_hasta'] ?? $horario['horario_hasta'], 0, 5),
            'dias' => array_values(array_map('intval', $data['horario_dias'] ?? $horario['horario_dias'])),
        ]);

        // Las listas ocultas (interruptor apagado) no llegan en $data: se conserva lo que había
        SystemSetting::put('correos', (bool) $data['correos'], [
            'desactivados' => isset($data['correos_tipos'])
                ? array_values(array_diff(array_keys(Correo::TIPOS), $data['correos_tipos']))
                : (SystemSetting::meta('correos', 'desactivados') ?? []),
            'limite_hora' => max(0, (int) ($data['correos_limite_hora'] ?? TextosCorreo::LIMITE_HORA_POR_DEFECTO)),
            'pie' => filled($data['correos_pie'] ?? null) ? trim($data['correos_pie']) : null,
            // Solo se guardan los textos escritos; lo vacío vuelve al texto de siempre
            'textos' => collect($data['correos_textos'] ?? [])
                ->map(fn ($texto) => array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, (array) $texto), 'filled'))
                ->filter()
                ->all(),
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

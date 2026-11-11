<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Filament\Resources\Solicituds\Schemas\AttachmentForm;
use App\Filament\Resources\Solicituds\SolicitudResource as TallerSolicitudResource;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\SystemSetting;
use App\Services\ChipValidationService;
use App\Support\Correo;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Paso 2: lectura del chip, revisión de documentos y fotos de campo. */
class ViewSolicitud extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudResource::class;

    protected string $view = 'filament.evaluador.solicituds.detalle-solicitud';

    /** Mismos nombres y orden que los bloques «Adjuntar Foto…» de la plataforma anterior. */
    public const FOTOS_OBLIGATORIAS = [
        'Fotos Impronta Cilindros',
        'Foto Panorámica',
        'Foto Regulador',
        'Fotos Ubicación Cilindros',
    ];

    // $record lo resuelve InteractsWithRecord con getEloquentQuery() del recurso (respeta el alcance del rol)
    use InteractsWithRecord;

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return SolicitudResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Solicitud '.$this->record->placa();
    }

    public function soloLectura(): bool
    {
        return ! $this->record->esEvaluable() || ! Auth::user()->hasRole('evaluador');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record)
            ->load(['user', 'vehicle.brand', 'vehicle.model', 'vehicle.type', 'serviceType', 'combustionSystem', 'applicationType', 'technology', 'adjuntos', 'regulators.brand', 'cilindros.brand']);

        if (! $this->soloLectura() && SolicitudResource::bloqueadaPorHorario()) {
            $this->redirect(SolicitudResource::getUrl('index'));

            return;
        }

        // No se puede saltar el filtro de seguridad escribiendo la URL
        if (! $this->soloLectura() && ! SolicitudResource::filtroCompleto($this->record)) {
            $this->redirect(SolicitudResource::getUrl('verificacion', ['record' => $this->record]));

            return;
        }

        $verificacion = SolicitudResource::verificacionDe($this->record)?->load(['chip', 'fotos']);
        $fotos = $verificacion?->fotos->pluck('ruta_foto', 'nombre_foto') ?? collect();

        $this->form->fill([
            'chip_codigo' => $verificacion?->chip?->codigo,
            'observaciones' => $verificacion?->observaciones,
            ...collect(self::FOTOS_OBLIGATORIAS)->mapWithKeys(fn ($n, $i) => ["foto_{$i}" => $fotos[$n] ?? null])->all(),
            'fotos_extra' => $fotos->except(self::FOTOS_OBLIGATORIAS)
                ->map(fn ($ruta, $nombre) => ['nombre' => $nombre, 'ruta' => $ruta])
                ->values()
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $directorio = "evaluadores/{$this->record->id}/fotos";
        $chipObligatorio = SystemSetting::enabled('chip_required');

        $fotosFijas = [];
        foreach (self::FOTOS_OBLIGATORIAS as $i => $nombre) {
            // Sin punto en el nombre: con "fotos.0" Filament no aplicaba la validación de tipo/tamaño
            $fotosFijas[] = AttachmentForm::archivo("foto_{$i}", $directorio)
                ->label($nombre)
                // Solo algunos tipos de servicio (el inicial) exigen las fotos
                ->required(fn () => $this->record->serviceType?->fotos_obligatorias ?? true)
                ->helperText(null);
        }

        return $schema
            ->disabled(fn () => $this->soloLectura())
            ->components([
                // Mismos campos y orden de «Información Básica De La Solicitud» en la plataforma anterior.
                // Placa y tipo de servicio van resaltados, como allá: son lo que el evaluador confirma primero.
                Section::make('Información Básica De La Solicitud')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 2])->schema([
                            TextEntry::make('r_taller')->label('Taller solicitante')->state($this->record->user?->name),
                            TextEntry::make('r_ciudad')->label('Ciudad')->state($this->record->user?->city ?: ($this->record->owner_ciudad ?? '—')),
                            TextEntry::make('r_placa')->label('Placa')->state($this->record->placa())->weight('bold')->badge()->color('warning'),
                            TextEntry::make('r_marca')->label('Marca')->state($this->record->vehicle?->brand?->nombre ?? '—'),
                            TextEntry::make('r_anio')->label('Año')->state($this->record->vehicle?->year ?? '—'),
                            TextEntry::make('r_modelo')->label('Modelo')->state($this->record->vehicle?->model?->nombre ?? '—'),
                            TextEntry::make('r_clase')->label('Clase vehículo')->state($this->record->vehicle?->type?->nombre ?? '—'),
                            TextEntry::make('r_servicio')->label('Tipo servicio')->state($this->record->serviceType?->nombre ?? '—')->badge()->color('warning'),
                            TextEntry::make('r_combustion')->label('Tipo de combustible')->state($this->record->combustionSystem?->nombre ?? '—'),
                            TextEntry::make('r_aplicacion')->label('Tipo de aplicación')->state($this->record->applicationType?->nombre ?? '—'),
                            TextEntry::make('r_tecnologia')->label('Tecnología')->state($this->record->technology?->nombre ?? '—'),
                            TextEntry::make('r_estado')->label('Estado')->badge()
                                ->state(EstadoSolicitud::labelDe($this->record->estado))
                                ->color(EstadoSolicitud::colorDe($this->record->estado)),
                        ]),
                        TextEntry::make('r_obs_revisor')
                            ->label('Corrección pedida por el revisor')
                            ->state($this->record->observaciones_revisor)
                            ->color('danger')
                            ->visible($this->record->estado === EstadoSolicitud::CorreccionTecnica->value && filled($this->record->observaciones_revisor)),
                    ]),

                Section::make('Lectura de Chip')
                    ->icon('heroicon-o-cpu-chip')
                    ->description($chipObligatorio ? 'Obligatoria.' : 'Opcional según la configuración actual.')
                    ->schema([
                        TextInput::make('chip_codigo')
                            ->label('Código del chip')
                            ->placeholder('Acerque el chip o digite el código')
                            ->required($chipObligatorio)
                            ->maxLength(100)
                            ->autocomplete(false)
                            ->extraInputAttributes(['id' => 'ivs-chip-input', 'style' => 'text-transform: uppercase', 'autocapitalize' => 'characters', 'enterkeyhint' => 'done']),
                        View::make('filament.evaluador.solicituds.chip-reader')
                            ->visible(fn () => ! $this->soloLectura()),
                    ]),

                Section::make('Documentos Adjuntos')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->adjuntos, 'type' => 'adjunto']),
                    ]),

                Section::make('Información De Reguladores y Cilindros')
                    ->icon('heroicon-o-beaker')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->regulators, 'type' => 'regulador']),
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->cilindros, 'type' => 'cilindro']),
                    ]),

                Section::make('Adjuntar Fotos')
                    ->icon('heroicon-o-camera')
                    ->description('Desde el celular puede tomarlas directamente con la cámara.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema($fotosFijas),
                        Repeater::make('fotos_extra')
                            ->label('Fotos adicionales')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextInput::make('nombre')->label('Descripción')->required()->maxLength(120)
                                        ->notIn(self::FOTOS_OBLIGATORIAS)->distinct(),
                                    AttachmentForm::archivo('ruta', $directorio)->label('Foto')->required()->helperText(null),
                                ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Agregar foto')
                            ->collapsible(),
                    ]),

                Section::make('Historial')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        View::make('filament.components.linea-tiempo')
                            ->viewData(['actividades' => $this->record->historialPara(Auth::user())]),
                    ]),

                Section::make('Observaciones del evaluador')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Textarea::make('observaciones')
                            ->hiddenLabel()
                            ->placeholder('Hallazgos, aclaraciones…')
                            ->rows(4)
                            ->maxLength(3000),
                    ]),
            ])
            ->statePath('data');
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    public function devolverAlTallerAction(): Action
    {
        return Action::make('devolverAlTaller')
            ->label('Reportar Error al Taller')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->outlined()
            ->visible(fn () => ! $this->soloLectura())
            ->modalHeading('Devolver solicitud al taller')
            ->modalDescription('El taller recibirá esta observación por correo y deberá corregir antes de continuar.')
            ->modalSubmitActionLabel('Devolver')
            ->schema([
                Textarea::make('observacion')
                    ->label('¿Qué debe corregir el taller?')
                    ->required()
                    ->minLength(10)
                    ->rows(4),
            ])
            ->action(function (array $data) {
                SolicitudVerificacion::updateOrCreate(
                    ['solicitud_id' => $this->record->id, 'evaluador_id' => Auth::id()],
                    ['observaciones' => $data['observacion'], 'estado' => 'devuelta'],
                );

                $this->record->update([
                    'estado' => EstadoSolicitud::DevueltaTaller->value,
                    'observacion_devolucion' => $data['observacion'],
                ]);

                $placa = $this->record->placa();
                Correo::enviar($this->record->user, 'devolucion', "⚠️ Solicitud devuelta - Placa: {$placa}", 'emails.solicitud_devuelta', [
                    'user_name' => $this->record->user?->name,
                    'placa' => $placa,
                    'observacion' => $data['observacion'],
                    'url_gestion' => TallerSolicitudResource::getUrl('edit', ['record' => $this->record]),
                ]);

                Notification::make()->title('Solicitud devuelta al taller')->success()->send();

                $this->redirect(SolicitudResource::getUrl('index'));
            });
    }

    public function esCambioDeChip(): bool
    {
        return (bool) $this->record->serviceType?->esCambioDeChip();
    }

    /** Cambio de chip: basta con leer el chip nuevo; no hay fotos obligatorias ni lista. */
    public function aceptarCambioChipAction(): Action
    {
        return Action::make('aceptarCambioChip')
            ->label('Aceptar Cambio de chip')
            ->icon('heroicon-o-cpu-chip')
            ->color('info')
            ->visible(fn () => ! $this->soloLectura() && $this->esCambioDeChip())
            ->requiresConfirmation()
            ->modalHeading('¿Aceptar el cambio de chip?')
            ->modalDescription('La solicitud se finaliza sin lista de verificación ni certificado.')
            ->action(function () {
                $data = $this->form->getState();

                if (blank($data['chip_codigo'] ?? null)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['data.chip_codigo' => 'Debes realizar primero la lectura del chip.']);
                }

                $chip = ChipValidationService::validar($data['chip_codigo'], $this->record);

                DB::transaction(function () use ($data, $chip) {
                    SolicitudVerificacion::updateOrCreate(
                        ['solicitud_id' => $this->record->id, 'evaluador_id' => Auth::id()],
                        ['id_chip' => $chip?->id, 'observaciones' => $data['observaciones'] ?? null, 'estado' => 'enviada', 'verificada_en' => now()],
                    );

                    $this->record->update(['estado' => EstadoSolicitud::Evaluada->value]);
                });

                if (Solicitud::certificaAlEvaluar()) {
                    $this->record->emitirCertificado();
                }

                Notification::make()->title('Solicitud aceptada')->success()->send();

                $this->redirect(SolicitudResource::getUrl('index'));
            });
    }

    public function cancelarAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->outlined()
            ->visible(fn () => ! $this->soloLectura() && $this->record->esCancelable())
            ->requiresConfirmation()
            ->modalHeading("¿Está seguro de cancelar la solicitud {$this->record->id}?")
            ->modalDescription('La solicitud no se ha certificado aún. Al cancelarla sale de las bandejas y no se puede retomar.')
            ->modalSubmitActionLabel('Sí, cancelar')
            ->action(function () {
                $this->record->cancelar();

                Notification::make()->title('La solicitud se canceló correctamente')->success()->send();

                $this->redirect(SolicitudResource::getUrl('index'));
            });
    }

    public function continuarAction(): Action
    {
        return Action::make('continuar')
            ->label('Guardar y continuar al checklist')
            ->icon('heroicon-m-arrow-right')
            ->iconPosition('after')
            // El cambio de chip no pasa por la lista de verificación
            ->visible(fn () => ! $this->soloLectura() && ! $this->esCambioDeChip())
            ->action(function () {
                $data = $this->form->getState();

                try {
                    $chip = ChipValidationService::validar($data['chip_codigo'] ?? null, $this->record);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    // El campo del chip queda lejos del botón: además del error en el campo, se avisa arriba
                    Notification::make()->title('No es posible continuar')->body(collect($e->errors())->flatten()->first())->danger()->persistent()->send();

                    throw $e;
                }

                DB::transaction(function () use ($data, $chip) {
                    $verificacion = SolicitudVerificacion::updateOrCreate(
                        ['solicitud_id' => $this->record->id, 'evaluador_id' => Auth::id()],
                        [
                            'id_chip' => $chip?->id,
                            'observaciones' => $data['observaciones'] ?? null,
                            'estado' => 'en_progreso',
                        ]
                    );

                    $verificacion->fotos()->delete();

                    $fotos = collect(self::FOTOS_OBLIGATORIAS)
                        ->mapWithKeys(fn ($nombre, $i) => [$nombre => $data["foto_{$i}"] ?? null])
                        ->merge(collect($data['fotos_extra'] ?? [])->pluck('ruta', 'nombre'));

                    foreach ($fotos as $nombre => $ruta) {
                        $ruta = is_array($ruta) ? (array_values($ruta)[0] ?? null) : $ruta;

                        if (filled($ruta)) {
                            $verificacion->fotos()->create(['nombre_foto' => $nombre, 'ruta_foto' => $ruta]);
                        }
                    }
                });

                $this->redirect(SolicitudResource::getUrl('checklist', ['record' => $this->record]));
            });
    }
}

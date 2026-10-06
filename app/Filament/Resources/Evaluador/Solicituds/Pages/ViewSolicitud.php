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

    public const FOTOS_OBLIGATORIAS = [
        'Foto panorámica del vehículo',
        'Foto del regulador',
        'Ubicación de cilindros',
        'Importación de cilindros',
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
            'fotos' => collect(self::FOTOS_OBLIGATORIAS)->map(fn ($n) => $fotos[$n] ?? null)->all(),
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
            $fotosFijas[] = AttachmentForm::archivo("fotos.{$i}", $directorio)
                ->label($nombre)
                ->required()
                ->helperText(null);
        }

        return $schema
            ->disabled(fn () => $this->soloLectura())
            ->components([
                Section::make('Resumen')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 3, 'xl' => 4])->schema([
                            TextEntry::make('r_estado')->label('Estado')->badge()
                                ->state(EstadoSolicitud::labelDe($this->record->estado))
                                ->color(EstadoSolicitud::colorDe($this->record->estado)),
                            TextEntry::make('r_taller')->label('Taller')->state($this->record->user?->name),
                            TextEntry::make('r_placa')->label('Placa')->state($this->record->placa())->weight('bold'),
                            TextEntry::make('r_vehiculo')->label('Vehículo')->state(trim(($this->record->vehicle?->brand?->nombre ?? '').' '.($this->record->vehicle?->model?->nombre ?? '').' '.($this->record->vehicle?->year ?? '')) ?: '—'),
                            TextEntry::make('r_clase')->label('Clase')->state($this->record->vehicle?->type?->nombre ?? '—'),
                            TextEntry::make('r_servicio')->label('Servicio')->state($this->record->serviceType?->nombre ?? '—'),
                            TextEntry::make('r_combustion')->label('Sistema')->state($this->record->combustionSystem?->nombre ?? '—'),
                            TextEntry::make('r_tecnologia')->label('Tecnología')->state($this->record->technology?->nombre ?? '—'),
                            TextEntry::make('r_ciudad')->label('Ciudad')->state($this->record->owner_ciudad ?? '—'),
                        ]),
                        TextEntry::make('r_obs_revisor')
                            ->label('Corrección pedida por el revisor')
                            ->state($this->record->observaciones_revisor)
                            ->color('danger')
                            ->visible($this->record->estado === EstadoSolicitud::CorreccionTecnica->value && filled($this->record->observaciones_revisor)),
                    ]),

                Section::make('Lectura del chip')
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

                Section::make('Documentos del taller')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->adjuntos, 'type' => 'adjunto']),
                    ]),

                Section::make('Equipos declarados')
                    ->icon('heroicon-o-beaker')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->regulators, 'type' => 'regulador']),
                        View::make('filament.evaluador.solicituds.list-items')
                            ->viewData(['items' => $this->record->cilindros, 'type' => 'cilindro']),
                    ]),

                Section::make('Fotos de la inspección')
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
            ->label('Devolver al taller')
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
                Correo::enviar($this->record->user?->email, "⚠️ Solicitud devuelta - Placa: {$placa}", 'emails.solicitud_devuelta', [
                    'user_name' => $this->record->user?->name,
                    'placa' => $placa,
                    'observacion' => $data['observacion'],
                    'url_gestion' => TallerSolicitudResource::getUrl('edit', ['record' => $this->record]),
                ]);

                Notification::make()->title('Solicitud devuelta al taller')->success()->send();

                $this->redirect(SolicitudResource::getUrl('index'));
            });
    }

    public function continuarAction(): Action
    {
        return Action::make('continuar')
            ->label('Guardar y continuar al checklist')
            ->icon('heroicon-m-arrow-right')
            ->iconPosition('after')
            ->visible(fn () => ! $this->soloLectura())
            ->action(function () {
                $data = $this->form->getState();
                $chip = ChipValidationService::validar($data['chip_codigo'] ?? null, $this->record);

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
                        ->mapWithKeys(fn ($nombre, $i) => [$nombre => $data['fotos'][$i] ?? null])
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

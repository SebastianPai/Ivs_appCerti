<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ChecklistTecnico;
use App\Support\Correo;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/** Paso 3: inspección técnica NTC y envío al revisor. */
class EvaluacionChecklist extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudResource::class;

    protected string $view = 'filament.evaluador.solicituds.evaluacion-checklist';

    // $record lo resuelve InteractsWithRecord con getEloquentQuery() del recurso (respeta el alcance del rol)
    use InteractsWithRecord;

    public ?array $data = [];

    /** Con el prellenado activo, los ítems ya respondidos se ocultan hasta que el evaluador pida verlos. */
    public bool $mostrarTodos = false;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) Auth::user()?->hasRole('evaluador');
    }

    public function getTitle(): string
    {
        return 'Checklist técnico · '.$this->record->placa();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record)->load('cilindros');

        if (SolicitudResource::bloqueadaPorHorario()) {
            $this->redirect(SolicitudResource::getUrl('index'));

            return;
        }

        if (! $this->record->esEvaluable()) {
            Notification::make()->title('Esta solicitud no está pendiente de evaluación')->warning()->send();
            $this->redirect(SolicitudResource::getUrl('index'));

            return;
        }

        if (! SolicitudResource::campoCompleto($this->record)) {
            Notification::make()->title('Primero complete la lectura del chip y las fotos')->warning()->send();
            $this->redirect(SolicitudResource::getUrl('view', ['record' => $this->record]));

            return;
        }

        $guardado = SolicitudResource::verificacionDe($this->record)?->datos_checklist ?? [];
        // Lo que depende de los cilindros y del tipo de aplicación va antes que el prellenado
        $guardado += array_filter(ChecklistTecnico::reglasDinamicas(
            $guardado['ultra_liviano'] ?? null,
            (array) ($guardado['ubicacion_cilindro'] ?? []),
            ChecklistTecnico::esDedicado($this->record),
        ));

        // Prellenado (opcional): sugiere las respuestas habituales; lo ya guardado siempre manda
        if (SystemSetting::enabled('checklist_prellenado')) {
            $guardado += ChecklistTecnico::valoresIniciales($this->record);
        }

        $this->form->fill($guardado);
    }

    public function form(Schema $schema): Schema
    {
        $pasos = [
            Wizard\Step::make('Cilindros')
                ->icon('heroicon-o-beaker')
                ->description('Tipo y ubicación')
                ->schema([
                    // Se pregunta una sola vez por solicitud, aunque el vehículo tenga varios cilindros
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        ToggleButtons::make('ultra_liviano')
                            ->label('El cilindro es ultraliviano')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->colors(['si' => 'success', 'no' => 'gray'])
                            ->inline()
                            ->required()
                            ->validationMessages(['required' => 'Es obligatorio responder si el cilindro es ultraliviano.'])
                            ->live()
                            ->afterStateUpdated(fn () => $this->aplicarReglas()),
                        Select::make('ubicacion_cilindro')
                            ->label('Ubicación del cilindro')
                            ->helperText('Puede marcar varias.')
                            ->multiple()
                            ->options(ChecklistTecnico::UBICACIONES_CILINDRO)
                            ->required()
                            ->validationMessages(['required' => 'Es obligatorio responder sobre la ubicación del cilindro.'])
                            ->live()
                            ->afterStateUpdated(fn () => $this->aplicarReglas()),
                    ]),
                ]),
        ];

        foreach (ChecklistTecnico::secciones() as $seccion) {
            $pasos[] = Wizard\Step::make($seccion['titulo'])
                ->icon($seccion['icono'])
                ->description($seccion['descripcion'])
                ->schema(array_map(fn (array $item) => $this->fila($item), $seccion['items']));
        }

        $pasos[] = Wizard\Step::make('Conclusión')
            ->icon('heroicon-o-flag')
            ->description('Resultado de la inspección')
            ->schema([
                ToggleButtons::make('conclusion')
                    ->label('Conclusión')
                    ->options(['c' => 'Cumple', 'nc' => 'No Cumple'])
                    ->colors(['c' => 'success', 'nc' => 'danger'])
                    ->icons(['c' => 'heroicon-m-check', 'nc' => 'heroicon-m-x-mark'])
                    ->inline()
                    ->required()
                    ->helperText('Con «No Cumple» la solicitud queda rechazada, sin certificado.'),
                Textarea::make('conclusion_observacion')
                    ->label('Observaciones')
                    ->required()
                    ->maxLength(500)
                    ->rows(4),
            ]);
        return $schema
            ->components([
                Wizard::make($pasos)
                    ->persistStepInQueryString()
                    ->submitAction($this->finalizarAction()),
            ])
            ->statePath('data');
    }

    /** Fija las respuestas que dependen de la ubicación y el tipo de los cilindros; el evaluador las puede cambiar. */
    public function aplicarReglas(): void
    {
        $reglas = ChecklistTecnico::reglasDinamicas(
            $this->data['ultra_liviano'] ?? null,
            (array) ($this->data['ubicacion_cilindro'] ?? []),
            ChecklistTecnico::esDedicado($this->record),
        );

        foreach ($reglas as $id => $valor) {
            $this->data[$id] = $valor;
        }
    }

    /** Hay ítems que ocultar solo si la lista arranca respondida. */
    public function ocultaItems(): bool
    {
        return SystemSetting::enabled('checklist_prellenado');
    }

    /**
     * Como en la plataforma anterior: se ocultan los ítems que arrancan en «Cumple» (y 5.1.22
     * cuando la ubicación lo responde), salvo los que piden medición. Uno marcado «No cumple»
     * o sin responder siempre queda a la vista.
     */
    private function estaOculto(array $item, mixed $valor): bool
    {
        $ocultable = ($item['inicial'] === 'c' && ! ($item['medicion'] ?? false)) || $item['id'] === 'ntc_5_1_22';

        return $ocultable && ! $this->mostrarTodos && $this->ocultaItems() && in_array($valor, ['c', 'na'], true);
    }

    public function alternarItemsAction(): Action
    {
        return Action::make('alternarItems')
            ->label(fn () => $this->mostrarTodos ? 'Ocultar ítems respondidos' : 'Mostrar todos los ítems')
            ->icon(fn () => $this->mostrarTodos ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color('gray')
            ->visible(fn () => $this->ocultaItems())
            ->action(fn () => $this->mostrarTodos = ! $this->mostrarTodos);
    }

    /** Una fila del checklist: numeral + texto, C/NC/NA y, si aplica, la medición en cm. */
    private function fila(array $item): Section
    {
        $id = $item['id'];
        $conMedicion = $item['medicion'] ?? false;
        $minimo = $item['minimo'] ?? null;
        $maximo = $item['maximo'] ?? null;

        return Section::make()
            ->compact()
            // Se oculta con estilo, no con visible(): así el valor se sigue validando y guardando
            ->extraAttributes(fn (Get $get) => $this->estaOculto($item, $get($id)) ? ['style' => 'display: none'] : [])
            ->schema([
                Text::make(new HtmlString(
                    '<span class="ivs-numeral">'.e($item['numeral']).'</span> '.e($item['texto'])
                )),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    ToggleButtons::make($id)
                        ->hiddenLabel()
                        ->options(['c' => 'Cumple', 'nc' => 'No cumple', 'na' => 'N/A'])
                        ->colors(['c' => 'success', 'nc' => 'danger', 'na' => 'gray'])
                        ->icons(['c' => 'heroicon-m-check', 'nc' => 'heroicon-m-x-mark', 'na' => 'heroicon-m-minus'])
                        ->inline()
                        ->required()
                        ->live(),

                    TextInput::make($id.'_valor')
                        ->hiddenLabel()
                        ->numeric()
                        ->inputMode('decimal')
                        ->suffix('cm')
                        ->placeholder(match (true) {
                            (bool) $minimo => "Mínimo {$minimo} cm",
                            (bool) $maximo => "Máximo {$maximo} cm",
                            default => 'Medición',
                        })
                        ->minValue(0)
                        ->visible(fn (Get $get) => $conMedicion && $get($id) === 'c')
                        ->required(fn (Get $get) => $conMedicion && $get($id) === 'c')
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail) use ($minimo, $maximo, $item) {
                                if ($maximo !== null && is_numeric($value) && $value > $maximo) {
                                    $fail("{$item['numeral']}: {$value} cm supera el máximo de {$maximo} cm. Si no cumple, márquelo como «No cumple».");
                                }

                                if ($minimo !== null && is_numeric($value) && $value < $minimo) {
                                    $fail("{$item['numeral']}: {$value} cm es menor al mínimo de {$minimo} cm. Si no cumple, márquelo como «No cumple».");
                                }
                            },
                        ]),
                ]),
            ]);
    }

    private function guardar(string $estadoVerificacion, bool $validar): void
    {
        $data = $validar ? $this->form->getState() : $this->form->getRawState();

        SolicitudVerificacion::updateOrCreate(
            ['solicitud_id' => $this->record->id, 'evaluador_id' => Auth::id()],
            [
                'datos_checklist' => $data,
                'estado' => $estadoVerificacion,
                'verificada_en' => $estadoVerificacion === 'enviada' ? now() : null,
            ]
        );
    }

    public function guardarBorradorAction(): Action
    {
        return Action::make('guardarBorrador')
            ->label('Guardar progreso')
            ->icon('heroicon-o-bookmark')
            ->color('gray')
            ->action(function () {
                $this->guardar('en_progreso', validar: false);
                Notification::make()->title('Progreso guardado')->body('Puede continuar más tarde desde la bandeja.')->success()->send();
            });
    }

    public function finalizarAction(): Action
    {
        return Action::make('finalizar')
            ->label('Finalizar y enviar a revisión')
            ->icon('heroicon-m-paper-airplane')
            ->requiresConfirmation()
            ->modalHeading('¿Enviar la evaluación al revisor?')
            ->modalDescription(function () {
                $nc = ChecklistTecnico::noConformidades($this->form->getRawState());

                return $nc
                    ? new HtmlString('<strong>Atención:</strong> hay '.count($nc).' ítem(s) marcados como «No cumple». Si el taller debe corregir, mejor devuélvale la solicitud.<br>Después de enviar no podrá editar la evaluación.')
                    : 'Después de enviar no podrá editar la evaluación.';
            })
            ->modalSubmitActionLabel('Enviar a revisión')
            ->action(function () {
                $this->guardar('enviada', validar: true);

                // Conclusión «No Cumple»: la solicitud se rechaza y no llega al revisor
                if (($this->data['conclusion'] ?? null) === 'nc') {
                    $motivo = $this->data['conclusion_observacion'] ?? null;

                    $this->record->update(['estado' => EstadoSolicitud::Rechazada->value, 'observacion_devolucion' => $motivo]);

                    $placa = $this->record->placa();
                    Correo::enviar($this->record->user, 'devolucion', "❌ Solicitud rechazada - Placa: {$placa}", 'emails.notificacion', [
                        'titulo' => 'Solicitud rechazada',
                        'saludo' => $this->record->user?->name,
                        'cuerpo' => 'La inspección concluyó que la instalación no cumple. No se emite certificado.',
                        'placa' => $placa,
                        'detalle' => 'Motivo: '.$motivo,
                        'boton_texto' => 'Ver solicitud',
                        'boton_url' => \App\Filament\Resources\Solicituds\SolicitudResource::getUrl('view', ['record' => $this->record]),
                    ]);

                    Notification::make()->title('Solicitud rechazada')->body('Se avisó al taller con el motivo.')->warning()->send();
                    $this->redirect(SolicitudResource::getUrl('index'));

                    return;
                }

                // Modo de la plataforma anterior: el certificado sale ya; el revisor lo audita después
                if (Solicitud::certificaAlEvaluar()) {
                    $this->record->emitirCertificado();
                    $this->notificarRevisores();

                    Notification::make()
                        ->title('La solicitud se guardó correctamente')
                        ->body('El número de certificado generado es '.$this->record->codigo.'.')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->redirect(SolicitudResource::getUrl('index'));

                    return;
                }

                $this->record->update(['estado' => EstadoSolicitud::Evaluada->value]);

                $this->notificarRevisores();

                Notification::make()
                    ->title('Evaluación enviada')
                    ->body('La solicitud pasó al revisor para la aprobación final.')
                    ->success()
                    ->send();

                $this->redirect(SolicitudResource::getUrl('index'));
            });
    }

    /** Revisores asignados a este evaluador; si no tiene, todos los revisores. */
    private function notificarRevisores(): void
    {
        /** @var User $evaluador */
        $evaluador = Auth::user();
        // Solo revisores activos y que sigan teniendo el rol (una asignación vieja no debe recibir avisos)
        $revisores = $evaluador->revisores()->role('revisor')->activos()->get();

        if ($revisores->isEmpty()) {
            $revisores = User::role('revisor')->activos()->get();
        }

        $placa = $this->record->placa();

        foreach ($revisores as $revisor) {
            Correo::enviar($revisor, 'revision', "📋 Nueva revisión pendiente - Placa: {$placa}", 'emails.revision_pendiente', [
                'revisor_name' => $revisor->name,
                'evaluador_name' => $evaluador->name,
                'placa' => $placa,
                'url_gestion' => SolicitudRevisorResource::getUrl('view', ['record' => $this->record]),
            ]);
        }
    }
}

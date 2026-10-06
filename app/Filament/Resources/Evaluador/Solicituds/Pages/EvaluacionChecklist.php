<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use App\Models\User;
use App\Support\ChecklistTecnico;
use App\Support\Correo;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
        $porSerie = collect($guardado['verificacion_cilindros'] ?? [])->keyBy('numero_serie');

        // Una fila por cada cilindro declarado por el taller, con su serie visible
        $guardado['verificacion_cilindros'] = $this->record->cilindros
            ->map(fn ($c) => [
                'numero_serie' => $c->numero_serie,
                'ultra_liviano' => $porSerie[$c->numero_serie]['ultra_liviano'] ?? null,
                'ubicacion' => $porSerie[$c->numero_serie]['ubicacion'] ?? [],
            ])
            ->values()
            ->all();

        $this->form->fill($guardado);
    }

    public function form(Schema $schema): Schema
    {
        $pasos = [
            Wizard\Step::make('Cilindros')
                ->icon('heroicon-o-beaker')
                ->description('Tipo y ubicación')
                ->schema([
                    Repeater::make('verificacion_cilindros')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('numero_serie'),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                ToggleButtons::make('ultra_liviano')
                                    ->label('¿Es ultraliviano?')
                                    ->options(['si' => 'Sí', 'no' => 'No'])
                                    ->colors(['si' => 'success', 'no' => 'gray'])
                                    ->inline()
                                    ->required(),
                                Select::make('ubicacion')
                                    ->label('Ubicación del cilindro')
                                    ->multiple()
                                    ->options(ChecklistTecnico::UBICACIONES_CILINDRO)
                                    ->required(),
                            ]),
                        ])
                        ->itemLabel(fn (array $state) => 'Cilindro serie '.($state['numero_serie'] ?? 's/n'))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),
        ];

        foreach (ChecklistTecnico::secciones() as $seccion) {
            $pasos[] = Wizard\Step::make($seccion['titulo'])
                ->icon($seccion['icono'])
                ->description($seccion['descripcion'])
                ->schema(array_map(fn (array $item) => $this->fila($item), $seccion['items']));
        }

        return $schema
            ->components([
                Wizard::make($pasos)
                    ->persistStepInQueryString()
                    ->submitAction($this->finalizarAction()),
            ])
            ->statePath('data');
    }

    /** Una fila del checklist: numeral + texto, C/NC/NA y, si aplica, la medición en cm. */
    private function fila(array $item): Section
    {
        $id = $item['id'];
        $conMedicion = $item['medicion'] ?? false;
        $minimo = $item['minimo'] ?? null;

        return Section::make()
            ->compact()
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
                        ->placeholder($minimo ? "Mínimo {$minimo} cm" : 'Medición')
                        ->minValue(0)
                        ->visible(fn (Get $get) => $conMedicion && $get($id) === 'c')
                        ->required(fn (Get $get) => $conMedicion && $get($id) === 'c')
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail) use ($minimo, $item) {
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
        $revisores = $evaluador->revisores()->get();

        if ($revisores->isEmpty()) {
            $revisores = User::role('revisor')->get();
        }

        $placa = $this->record->placa();

        foreach ($revisores as $revisor) {
            Correo::enviar($revisor->email, "📋 Nueva revisión pendiente - Placa: {$placa}", 'emails.revision_pendiente', [
                'revisor_name' => $revisor->name,
                'evaluador_name' => $evaluador->name,
                'placa' => $placa,
                'url_gestion' => SolicitudRevisorResource::getUrl('view', ['record' => $this->record]),
            ]);
        }
    }
}

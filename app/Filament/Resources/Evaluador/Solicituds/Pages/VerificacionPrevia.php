<?php

namespace App\Filament\Resources\Evaluador\Solicituds\Pages;

use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource;
use App\Models\EvaluacionGeolocalizacion;
use App\Models\Solicitud;
use App\Models\SolicitudVerificacion;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/** Paso 1: declaración de no conflicto de interés + georreferenciación en sitio. */
class VerificacionPrevia extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudResource::class;

    protected string $view = 'filament.evaluador.solicituds.verificacion-previa';

    // $record lo resuelve InteractsWithRecord con getEloquentQuery() del recurso (respeta el alcance del rol)
    use InteractsWithRecord;

    public ?array $data = [];

    public bool $geolocalizacion_ok = false;

    public ?float $precision = null;

    public static function canAccess(array $parameters = []): bool
    {
        return SolicitudResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Evaluar '.$this->record->placa();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (! $this->record->esEvaluable() || ! Auth::user()->hasRole('evaluador')) {
            Notification::make()->title('Esta solicitud no está pendiente de evaluación')->warning()->send();
            $this->redirect(SolicitudResource::getUrl('view', ['record' => $this->record]));

            return;
        }

        // Si ya pasó el filtro, ir directo al siguiente paso
        if (SolicitudResource::filtroCompleto($this->record)) {
            $this->redirect(SolicitudResource::getUrl('view', ['record' => $this->record]));

            return;
        }

        $geo = EvaluacionGeolocalizacion::where('solicitud_id', $this->record->id)
            ->where('evaluador_id', Auth::id())
            ->first();

        $this->geolocalizacion_ok = (bool) $geo;
        $this->precision = $geo?->precision_m !== null ? (float) $geo->precision_m : null;

        $this->form->fill(['confirmo_conflicto' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Checkbox::make('confirmo_conflicto')
                    ->label('Declaro que no tengo conflicto de interés')
                    ->helperText('No tengo vínculos comerciales, laborales ni personales con este taller ni con el propietario del vehículo.')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Debe aceptar la declaración para continuar.']),
            ])
            ->statePath('data');
    }

    /** Llamado desde el navegador con las coordenadas del GPS. */
    public function guardarGeolocalizacion(array $coords): void
    {
        $lat = filter_var($coords['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($coords['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $precision = filter_var($coords['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
            Notification::make()->title('Coordenadas inválidas')->body('Intente de nuevo.')->danger()->send();

            return;
        }

        EvaluacionGeolocalizacion::updateOrCreate(
            ['solicitud_id' => $this->record->id, 'evaluador_id' => Auth::id()],
            [
                'latitud' => $lat,
                'longitud' => $lng,
                'precision_m' => $precision === false ? null : round($precision, 2),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                'registrado_en' => now(),
            ]
        );

        $this->geolocalizacion_ok = true;
        $this->precision = $precision === false ? null : $precision;

        Notification::make()
            ->title('Ubicación registrada')
            ->body($this->precision && $this->precision > 100
                ? 'Precisión baja (±'.round($this->precision).' m). Si puede, salga a cielo abierto y vuelva a capturarla.'
                : null)
            ->color($this->precision && $this->precision > 100 ? 'warning' : 'success')
            ->icon('heroicon-o-map-pin')
            ->send();
    }

    public function continuar(): void
    {
        $this->form->getState(); // valida la declaración

        if (! $this->geolocalizacion_ok) {
            Notification::make()
                ->title('Falta la ubicación')
                ->body('Presione «Capturar ubicación» y permita el acceso al GPS.')
                ->danger()
                ->send();

            return;
        }

        $geo = EvaluacionGeolocalizacion::where('solicitud_id', $this->record->id)
            ->where('evaluador_id', Auth::id())
            ->firstOrFail();

        $verificacion = SolicitudVerificacion::firstOrNew([
            'solicitud_id' => $this->record->id,
            'evaluador_id' => Auth::id(),
        ]);

        $verificacion->fill([
            'conflicto_interes' => true,
            'lat' => $geo->latitud,
            'lng' => $geo->longitud,
            'accuracy' => $geo->precision_m,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);

        if (! $verificacion->exists || in_array($verificacion->estado, ['iniciada', 'devuelta'], true)) {
            $verificacion->estado = 'validada';
        }

        $verificacion->save();

        $this->redirect(SolicitudResource::getUrl('view', ['record' => $this->record]));
    }
}

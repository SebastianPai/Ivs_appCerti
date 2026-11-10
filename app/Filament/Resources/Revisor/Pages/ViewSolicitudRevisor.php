<?php

namespace App\Filament\Resources\Revisor\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorSolicitudResource;
use App\Filament\Resources\Revisor\SolicitudRevisorResource;
use App\Filament\Resources\Solicituds\SolicitudResource as TallerSolicitudResource;
use App\Models\Solicitud;
use App\Support\ChecklistTecnico;
use App\Support\Correo;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewSolicitudRevisor extends ViewRecord
{
    protected static string $resource = SolicitudRevisorResource::class;

    public function getTitle(): string
    {
        return 'Auditoría · '.$this->record->placa();
    }

    private function puedeDecidir(): bool
    {
        return $this->record->estado === EstadoSolicitud::Evaluada->value
            && Auth::user()->hasAnyRole(['revisor', 'admin']);
    }

    /** Ya tiene certificado (lo emitió el evaluador) y falta la revisión. */
    private function puedeRevisar(): bool
    {
        return $this->record->estaAprobada() && $this->record->revisada_en === null
            && Auth::user()->hasAnyRole(['revisor', 'admin']);
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('certificado')
                ->label('Ver certificado')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('solicitud.certificado', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->tieneCertificado()),

            Action::make('revisar')
                ->label(fn () => $this->record->serviceType?->esCambioDeChip() ? 'Aceptar revisión' : 'Marcar como revisada')
                ->color('success')
                ->icon('heroicon-o-check')
                ->visible(fn () => $this->puedeRevisar())
                ->modalHeading(fn () => "¿Está seguro de revisar la solicitud {$this->record->id}?")
                // Tras un reenvío, la observación anterior viene escrita, como en la plataforma anterior
                ->fillForm(fn () => ['observaciones_revisor' => $this->record->observaciones_revisor])
                ->modalSubmitActionLabel('Ok')
                ->schema([
                    Textarea::make('observaciones_revisor')
                        ->label('Observaciones del revisor')
                        // Opcional, como en la plataforma anterior (la devolución sí exige el motivo)
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'revisada_en' => now(),
                        'revisor_id' => Auth::id(),
                        'observaciones_revisor' => $data['observaciones_revisor'] ?? null,
                    ]);

                    Notification::make()->title('Solicitud revisada')->success()->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('marcarNoRevisada')
                ->label('Marcar como no revisada')
                ->color('gray')
                ->icon('heroicon-o-arrow-uturn-down')
                ->visible(fn () => $this->record->estaAprobada() && $this->record->revisada_en !== null
                    && Solicitud::certificaAlEvaluar() && Auth::user()->hasAnyRole(['revisor', 'admin']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['revisada_en' => null]);

                    Notification::make()->title('La solicitud vuelve a «Pendiente Revisar»')->success()->send();
                }),

            Action::make('devolver')
                ->label('Devolver al evaluador')
                ->color('danger')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->puedeDecidir() || $this->puedeRevisar())
                ->modalHeading('Devolver al evaluador')
                ->modalDescription('Describa qué debe corregir el evaluador. Le llegará por correo.')
                ->schema([
                    Textarea::make('observaciones_revisor')
                        ->label('Motivo de la devolución')
                        ->placeholder('Ej: la foto del regulador está borrosa, tomarla de nuevo.')
                        ->required()
                        ->minLength(10)
                        ->rows(4),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'estado' => EstadoSolicitud::CorreccionTecnica->value,
                        'observaciones_revisor' => $data['observaciones_revisor'],
                        'revisor_id' => Auth::id(),
                        'revisada_en' => null,
                    ]);

                    $evaluador = $this->record->verificacion?->evaluador;
                    $placa = $this->record->placa();

                    Correo::enviar($evaluador, 'devolucion', "🔁 Corrección técnica - Placa: {$placa}", 'emails.notificacion', [
                        'titulo' => 'Corrección técnica solicitada',
                        'saludo' => $evaluador?->name,
                        'cuerpo' => 'El revisor devolvió la evaluación con la siguiente observación:',
                        'placa' => $placa,
                        'detalle' => $data['observaciones_revisor'],
                        'boton_texto' => 'Corregir evaluación',
                        'boton_url' => EvaluadorSolicitudResource::getUrl('view', ['record' => $this->record]),
                    ]);

                    Notification::make()->title('Devuelta al evaluador')->warning()->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('aprobar')
                ->label('Aprobar y certificar')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(fn () => $this->puedeDecidir())
                ->requiresConfirmation()
                ->modalHeading('¿Aprobar y emitir el certificado?')
                ->modalDescription(function () {
                    $nc = ChecklistTecnico::noConformidades($this->record->verificacion?->datos_checklist ?? []);

                    return $nc
                        ? 'ATENCIÓN: el checklist tiene '.count($nc).' ítem(s) «No cumple». ¿Seguro que desea aprobar?'
                        : 'Se generará el número de certificado y el taller podrá descargarlo.';
                })
                ->modalSubmitActionLabel('Aprobar')
                ->action(function () {
                    $this->record->emitirCertificado(Auth::id());

                    if (! $this->record->tieneCertificado()) {
                        Notification::make()->title('Cambio de chip aprobado')->success()->send();
                        $this->redirect(SolicitudRevisorResource::getUrl('index'));

                        return;
                    }

                    Notification::make()
                        ->title('Certificación aprobada')
                        ->body('Certificado '.$this->record->codigo)
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}

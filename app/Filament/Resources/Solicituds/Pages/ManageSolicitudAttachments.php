<?php

namespace App\Filament\Resources\Solicituds\Pages;

use App\Enums\EstadoSolicitud;
use App\Filament\Resources\Evaluador\Solicituds\SolicitudResource as EvaluadorSolicitudResource;
use App\Filament\Resources\Solicituds\Schemas\AttachmentForm;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Models\Solicitud;
use App\Support\Correo;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManageSolicitudAttachments extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudResource::class;

    protected string $view = 'filament.cliente.pages.manage-attachments';

    // $record lo resuelve InteractsWithRecord con getEloquentQuery() del recurso (respeta el alcance del rol)
    use InteractsWithRecord;

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'cliente']);
    }

    public function getTitle(): string
    {
        return 'Documentos · '.$this->record->placa();
    }

    public function mount(int|string $record): void
    {
        // Antes se buscaba con Solicitud::findOrFail() y cualquier taller podía abrir
        // los documentos de otro cambiando el número en la URL.
        $this->record = $this->resolveRecord($record)->load(['adjuntos', 'vehicle']);

        abort_unless(SolicitudResource::canEdit($this->record), 403, 'Esta solicitud ya no admite cambios de documentos.');

        $porNombre = $this->record->adjuntos->keyBy('nombre_adjunto');

        $this->form->fill([
            ...collect(array_keys(AttachmentForm::DOCUMENTOS))
                ->mapWithKeys(fn ($nombre, $i) => [AttachmentForm::campo($i) => $porNombre[$nombre]->ruta_archivo ?? null])
                ->all(),
            'adicionales' => $this->record->adjuntos
                ->reject(fn ($a) => array_key_exists($a->nombre_adjunto, AttachmentForm::DOCUMENTOS))
                ->map(fn ($a) => ['nombre_adjunto' => $a->nombre_adjunto, 'ruta_archivo' => $a->ruta_archivo])
                ->values()
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(AttachmentForm::components($this->record->id))
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $nombresFijos = array_keys(AttachmentForm::DOCUMENTOS);

        DB::transaction(function () use ($state, $nombresFijos) {
            foreach ($nombresFijos as $i => $nombre) {
                $this->record->adjuntos()->updateOrCreate(
                    ['nombre_adjunto' => $nombre],
                    ['ruta_archivo' => self::ruta($state[AttachmentForm::campo($i)] ?? null)],
                );
            }

            $adicionales = collect($state['adicionales'] ?? []);

            // Lo que el taller quitó de la lista se elimina
            $this->record->adjuntos()
                ->whereNotIn('nombre_adjunto', [...$nombresFijos, ...$adicionales->pluck('nombre_adjunto')])
                ->delete();

            foreach ($adicionales as $item) {
                $this->record->adjuntos()->updateOrCreate(
                    ['nombre_adjunto' => $item['nombre_adjunto']],
                    ['ruta_archivo' => self::ruta($item['ruta_archivo'] ?? null)],
                );
            }
        });

        $eraDevuelta = $this->record->estado === EstadoSolicitud::DevueltaTaller->value;

        if ($eraDevuelta) {
            $this->record->update(['estado' => EstadoSolicitud::Subsanada->value]);
        }

        $this->notificar($eraDevuelta);

        Notification::make()
            ->title($eraDevuelta ? 'Correcciones enviadas al evaluador' : 'Documentación guardada')
            ->body('Le avisaremos por correo cuando haya novedades.')
            ->success()
            ->send();

        $this->redirect(SolicitudResource::getUrl('index'));
    }

    private static function ruta(mixed $valor): ?string
    {
        return is_array($valor) ? (array_values($valor)[0] ?? null) : $valor;
    }

    private function notificar(bool $eraDevuelta): void
    {
        $taller = $this->record->user;
        $placa = $this->record->placa();

        Correo::enviar($taller->email, "✅ Documentación recibida - Placa: {$placa}", 'emails.confirmacion_carga', [
            'user_name' => $taller->name,
            'placa' => $placa,
            'url_solicitud' => SolicitudResource::getUrl('view', ['record' => $this->record]),
        ]);

        $asunto = $eraDevuelta ? "🔁 Solicitud corregida - Placa: {$placa}" : "📂 Documentos cargados - Placa: {$placa}";

        foreach ($taller->evaluadores()->get() as $evaluador) {
            Correo::enviar($evaluador->email, $asunto, 'emails.documentos_cargados_evaluador', [
                'evaluador_name' => $evaluador->name,
                'cliente_name' => $taller->name,
                'placa' => $placa,
                'url_gestion' => EvaluadorSolicitudResource::getUrl('verificacion', ['record' => $this->record]),
            ]);
        }
    }
}

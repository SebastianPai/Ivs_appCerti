<?php

namespace App\Providers;

use App\Filament\Resources\Solicituds\Pages\CreateSolicitud;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Borrador automático de la solicitud: los campos de texto de Filament no se envían
        // al servidor mientras se escribe, así que cada 5 s (y al salir/ocultar la página)
        // se sincronizan para que CreateSolicitud::updated() guarde el borrador.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): string => Blade::render(<<<'BLADE'
                <div
                    x-data="{
                        timer: null,
                        sync() { $wire.$commit() },
                        init() {
                            this.timer = setInterval(() => document.visibilityState === 'visible' && this.sync(), 5000);
                        },
                        destroy() { clearInterval(this.timer) },
                    }"
                    x-on:visibilitychange.document="document.visibilityState === 'hidden' && sync()"
                    x-on:pagehide.window="sync()"
                    hidden
                ></div>
            BLADE),
            scopes: CreateSolicitud::class,
        );
    }
}

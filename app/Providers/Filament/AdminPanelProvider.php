<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditarPerfil;
use App\Filament\Pages\Auth\Login;
use App\Exports\Exportables as Exportador;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Exportables;
use App\Filament\Resources\Solicituds\SolicitudResource;
use App\Http\Middleware\Exigir2FA;
use App\Http\Middleware\ExigirRegistroTaller;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Único panel de la aplicación (/ivs). Todos los roles entran aquí y cada recurso
 * decide con canViewAny() qué rol lo ve (taller, evaluador, revisor, admin).
 */
class AdminPanelProvider extends PanelProvider
{
    /** Entrada del menú que abre la página de exportables en un exportable concreto. */
    private static function exportable(string $etiqueta, string $tipo, string $grupo, int $orden): NavigationItem
    {
        return NavigationItem::make($etiqueta)
            ->url(fn () => Exportables::getUrl(['tipo' => $tipo]))
            ->icon('heroicon-o-arrow-down-tray')
            ->group($grupo)
            ->sort($orden)
            ->isActiveWhen(fn () => request()->routeIs(Exportables::getRouteName()) && request()->query('tipo') === $tipo)
            ->visible(fn () => Exportador::puede(Auth::user(), $tipo));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('ivs')
            ->authGuard('web')
            ->login(Login::class)
            ->passwordReset()
            // Como en la plataforma anterior: sin confirmar el correo no se entra
            ->emailVerification()
            // Cambiar el correo exige confirmarlo desde el correo nuevo; al anterior le llega un aviso
            // con un enlace para bloquear el cambio si no lo reconoce
            ->emailChangeVerification()
            ->profile(EditarPerfil::class, isSimple: false)
            // Verificación en dos pasos con app (Google Authenticator, Microsoft Authenticator, Authy…).
            // Es opcional para todos y obligatoria para los roles elegidos en Configuración (ver Exigir2FA).
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable()->brandName('IVS Certificaciones'),
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(Exigir2FA::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->brandName('IVS Certificaciones')
            ->brandLogo(fn () => view('filament.marca'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon-32.png'))
            // Avatar con iniciales generado aquí (el de Filament enviaba el nombre a ui-avatars.com)
            ->defaultAvatarProvider(\App\Filament\AvatarProviders\Iniciales::class)
            // Paleta de la marca: rojo IVS suavizado (rosa apagado). Se define solo aquí y la usan
            // botones, pasos, casillas, enlaces y menús de toda la app.
            ->colors([
                'primary' => [
                    50 => '#fbf4f4',
                    100 => '#f5e6e6',
                    200 => '#ecd0d0',
                    300 => '#ddaeaf',
                    400 => '#c98386',
                    500 => '#b5636a',
                    600 => '#a5484e',
                    700 => '#8b3c42',
                    800 => '#73343a',
                    900 => '#612f34',
                    950 => '#361719',
                ],
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Menú en la barra superior, con los mismos grupos, nombres y orden de la plataforma
            // anterior: los usuarios ya saben dónde está cada cosa.
            ->topNavigation()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Cliente'),
                NavigationGroup::make('Evaluador'),
                NavigationGroup::make('Revisor'),
                NavigationGroup::make('Exportados'),
                NavigationGroup::make('Administrador'),
                NavigationGroup::make('Admin Chips'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Sin registrarlos, Livewire no los encuentra al cargarlos en diferido o al filtrar el periodo
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationItems([
                NavigationItem::make('Nueva Solicitud')
                    ->url(fn () => SolicitudResource::getUrl('create'))
                    ->icon('heroicon-o-document-plus')
                    ->group('Cliente')
                    ->sort(1)
                    ->isActiveWhen(fn () => request()->routeIs(SolicitudResource::getRouteBaseName().'.create'))
                    ->visible(fn () => SolicitudResource::canCreate()),
                self::exportable('Exportables', 'cliente', 'Cliente', 3),
                self::exportable('Reporte de Operaciones', 'operaciones', 'Evaluador', 2),
                NavigationItem::make('Inspección sin conexión')
                    ->url(fn () => route('campo.index'))
                    ->icon('heroicon-o-signal-slash')
                    ->group('Evaluador')
                    ->sort(4)
                    ->visible(fn () => (bool) Auth::user()?->hasRole('evaluador')),
                self::exportable('Exportables', 'revisor', 'Revisor', 2),
                self::exportable('Mis Exportados', 'externo', 'Exportados', 1),
                self::exportable('Exportables SICOM', 'sicom', 'Administrador', 8),
                self::exportable('Exportables IVS', 'ivs', 'Administrador', 9),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // El panel no pasa por el grupo «web»: las cabeceras de seguridad se agregan aquí también
                \App\Http\Middleware\CabecerasSeguridad::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'restrict.access',
                \App\Http\Middleware\ExigirCambioPassword::class,
                ExigirRegistroTaller::class,
                \App\Http\Middleware\RespaldoDiario::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }
}

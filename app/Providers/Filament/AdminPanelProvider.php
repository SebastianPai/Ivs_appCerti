<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditarPerfil;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\Exigir2FA;
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
use Filament\Support\Colors\Color;
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
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('ivs')
            ->authGuard('web')
            ->login()
            ->passwordReset()
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
            ->brandLogo(asset('images/logo-sm.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Red,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Taller'),
                NavigationGroup::make('Evaluación'),
                NavigationGroup::make('Revisión'),
                NavigationGroup::make('Administración')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Inspección sin conexión')
                    ->url(fn () => route('campo.index'))
                    ->icon('heroicon-o-signal-slash')
                    ->group('Evaluación')
                    ->sort(3)
                    ->visible(fn () => (bool) Auth::user()?->hasRole('evaluador')),
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
            ])
            ->authMiddleware([
                Authenticate::class,
                'restrict.access',
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }
}

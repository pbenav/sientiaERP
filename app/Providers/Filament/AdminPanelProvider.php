<?php

namespace App\Providers\Filament;

use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                \App\Filament\Widgets\DailySalesChart::class,
                \App\Filament\Widgets\MonthlySalesChart::class,
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
            ])
            ->navigationGroups([
                'Ventas',
                'Compras',
                'Almacén',
                'Gestión',
                'Configuración',
                'Administración',
            ])
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.document-navigation')
            )
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('
                    <style>
                        /* 
                           Ataque global al padding de las tablas de Filament.
                           Targeteamos cualquier contenedor de celda que tenga py-4.
                        */
                        [class*="fi-ta-"] .py-4,
                        .fi-ta-text,
                        .fi-ta-col-wrp,
                        .fi-ta-cell > div {
                            padding-top: 0.25rem !important;    /* py-1 */
                            padding-bottom: 0.25rem !important; /* py-1 */
                            min-height: unset !important;
                        }

                        /* Eliminar gaps o alturas mínimas que fuerzan espacio */
                        .fi-ta-text-item {
                            gap: 0 !important;
                        }
                        
                        /* Ajuste para iconos y badges */
                        .fi-ta-icon-column div,
                        .fi-ta-badge-column div {
                            padding-top: 0 !important;
                            padding-bottom: 0 !important;
                        }
                    </style>
                ')
            )
            ->renderHook(
                'panels::head.end',
                fn () => new HtmlString(Blade::render("@vite('resources/css/document-lines.css')"))
            );
    }
}

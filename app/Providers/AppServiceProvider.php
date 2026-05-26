<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Filament\RelationManagers\LineasRelationManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('app.filament.relation-managers.lineas-relation-manager', LineasRelationManager::class);
        
        // Register observers
        \App\Models\DocumentoLinea::observe(\App\Observers\DocumentoLineaObserver::class);
        \App\Models\Documento::observe(\App\Observers\DocumentoObserver::class);
        \App\Models\Ticket::observe(\App\Observers\TicketObserver::class);

        // CONFIGURACIÓN PARA PROXY SSL (Se basa directamente en la URL configurada)
        if (str_starts_with(config('app.url') ?? '', 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Forzar el host y el puerto en producción basados en la APP_URL
        // Esto resuelve el error 401 en subidas de Livewire tras proxies como Apache/Proxmox
        if (app()->environment('production') || env('APP_ENV') === 'production') {
            $parsedUrl = parse_url(config('app.url'));
            if (!empty($parsedUrl['host'])) {
                request()->headers->set('HOST', $parsedUrl['host']);
                if (!empty($parsedUrl['port'])) {
                    request()->headers->set('X-FORWARDED-PORT', $parsedUrl['port']);
                } else {
                    request()->headers->set('X-FORWARDED-PORT', ($parsedUrl['scheme'] ?? 'https') === 'https' ? 443 : 80);
                }
            }
        }
    }
}

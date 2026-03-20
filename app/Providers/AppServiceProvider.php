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
    }
}

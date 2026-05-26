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

        // Forzar el host y el puerto basados en la APP_URL si difiere del de la petición actual
        // Esto resuelve el error 401 en subidas de Livewire tras proxies como Apache/Proxmox
        $parsedUrl = parse_url(config('app.url'));
        if (!empty($parsedUrl['host']) && request()->headers->get('HOST') !== $parsedUrl['host']) {
            $isHttps = ($parsedUrl['scheme'] ?? 'https') === 'https';
            $port = $isHttps ? 443 : 80;
            
            // 1. Sobrescribir superglobales de servidor de bajo nivel (leídas por Symfony)
            $_SERVER['HTTP_HOST'] = $parsedUrl['host'];
            $_SERVER['SERVER_NAME'] = $parsedUrl['host'];
            $_SERVER['SERVER_PORT'] = $port;
            $_SERVER['HTTPS'] = $isHttps ? 'on' : 'off';
            
            // 2. Sobrescribir los datos en el Request actual
            request()->server->set('HTTP_HOST', $parsedUrl['host']);
            request()->server->set('SERVER_NAME', $parsedUrl['host']);
            request()->server->set('SERVER_PORT', $port);
            request()->headers->set('HOST', $parsedUrl['host']);
            request()->headers->set('X-FORWARDED-PORT', $port);
            
            // 3. Forzar a Symfony a vaciar su caché interna reinicializando el Request con los nuevos datos
            request()->initialize(
                request()->query->all(),
                request()->request->all(),
                request()->attributes->all(),
                request()->cookies->all(),
                request()->files->all(),
                request()->server->all(),
                request()->getContent()
            );
        }

        // DEPURADOR DE FIRMAS EN PRODUCCIÓN
        if (request()->is('livewire/upload-file') || str_contains(request()->path(), 'upload-file')) {
            \Illuminate\Support\Facades\Log::info('Livewire Upload Signature Debug:', [
                'request_url' => request()->url(),
                'request_full_url' => request()->fullUrl(),
                'has_valid_signature' => request()->hasValidSignature(),
                'expires' => request()->query('expires'),
                'signature' => request()->query('signature'),
                'server_http_host' => $_SERVER['HTTP_HOST'] ?? null,
                'server_name' => $_SERVER['SERVER_NAME'] ?? null,
                'server_port' => $_SERVER['SERVER_PORT'] ?? null,
                'https_state' => $_SERVER['HTTPS'] ?? null,
                'app_url' => config('app.url'),
                'app_key_hash' => substr(config('app.key') ?? '', 0, 15) . '...',
            ]);
        }
    }
}

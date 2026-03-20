<footer class="text-center py-4 text-xs text-gray-400 dark:text-gray-500">
    <div class="flex flex-col md:flex-row justify-center items-center gap-2 md:gap-6">
        <span>&copy; {{ date('Y') }} Sientia ERP - Todos los derechos reservados</span>

        <span class="flex items-center gap-1">
            <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" />
            Versi&oacute;n: {{ config('app.version') }}
        </span>

        <a href="https://www.patreon.com/sientia" target="_blank" tabindex="-1"
            class="flex items-center gap-1 text-orange-400 hover:text-orange-500 transition-colors duration-200">
            <x-filament::icon icon="heroicon-o-heart" class="h-4 w-4" />
            Ap&oacute;yanos en Patreon
        </a>
    </div>
</footer>

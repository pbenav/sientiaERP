<footer class="text-center py-4 text-xs text-gray-400 dark:text-gray-500">
    <div class="flex flex-col md:flex-row justify-center items-center gap-2 md:gap-6">
        <span>&copy; {{ date('Y') }} Sientia ERP - Todos los derechos reservados</span>

        <span class="flex items-center gap-1">
            <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" />
            Versi&oacute;n: {{ config('app.version') }}
        </span>

        <a href="https://www.patreon.com/cw/sientia" target="_blank" tabindex="-1"
            class="flex items-center gap-1 text-orange-400 hover:text-orange-500 transition-colors duration-200">
            <x-filament::icon icon="heroicon-o-heart" class="h-4 w-4" />
            Ap&oacute;yanos
        </a>

        <div class="flex items-center gap-4 ml-2 border-l border-gray-200 dark:border-gray-700 pl-4">
            <a href="https://github.com/pbenav" target="_blank" title="GitHub" class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
            </a>
            <a href="https://gitlab.com/pbenav" target="_blank" title="GitLab" class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M23.955 13.587l-1.342-4.135-2.664-8.189c-.135-.417-.724-.417-.86 0L16.425 9.452h-8.85l-2.664-8.189c-.135-.417-.724-.417-.86 0L1.387 9.452.045 13.587c-.11.34.01.711.306.925l11.65 8.458 11.648-8.458c.296-.214.416-.585.306-.925z"/></svg>
            </a>
        </div>
    </div>

    <script>
        (function() {
            window.applyZoom = function(val) {
                // Si estamos en el TPV, ignoramos el zoom global para evitar doble escalado
                // el TPV ya tiene su propio control de zoom independiente
                if (document.getElementById('tpv-main-container')) {
                    document.body.style.zoom = 1.0;
                    return;
                }

                document.body.style.zoom = val;
                const zoomLabel = document.getElementById('global-zoom-label');
                if (zoomLabel) {
                    zoomLabel.innerText = Math.round(val * 100) + '%';
                }
            }

            window.adjustGlobalZoom = function(delta) {
                let currentZoom = parseFloat(localStorage.getItem('global_zoom') || '1.0');
                currentZoom = Math.round((currentZoom + delta) * 100) / 100;
                currentZoom = Math.max(0.6, Math.min(1.2, currentZoom));
                localStorage.setItem('global_zoom', currentZoom);
                window.applyZoom(currentZoom);
            }

            // Aplicar al cargar
            const savedZoom = localStorage.getItem('global_zoom') || '1.0';
            window.applyZoom(parseFloat(savedZoom));
        })();
    </script>
</footer>

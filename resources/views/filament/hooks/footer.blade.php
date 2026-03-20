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

        {{-- Zoom Global --}}
        <div
            class="flex items-center bg-gray-100 dark:bg-gray-800 rounded-md p-1 border border-gray-200 dark:border-gray-700 h-7">
            <button onclick="adjustGlobalZoom(-0.05)" type="button"
                class="px-1.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-gray-400 font-bold text-xs leading-none">-</button>
            <span class="px-2 text-[9px] font-black text-gray-500 min-w-[35px] uppercase"
                id="global-zoom-label">100%</span>
            <button onclick="adjustGlobalZoom(0.05)" type="button"
                class="px-1.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-gray-400 font-bold text-xs leading-none">+</button>
        </div>
    </div>

    <script>
        (function() {
            const zoomLabel = document.getElementById('global-zoom-label');

            window.applyZoom = function(val) {
                // Si estamos en el TPV, ignoramos el zoom global para evitar doble escalado
                // el TPV ya tiene su propio control de zoom independiente
                if (document.getElementById('tpv-main-container')) {
                    document.body.style.zoom = 1.0;
                    return;
                }

                document.body.style.zoom = val;
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

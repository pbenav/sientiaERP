<footer class="mt-8 border-t border-gray-200 dark:border-gray-800 py-6 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm rounded-xl mb-4">
    <div class="px-6 flex flex-col md:flex-row justify-between items-center text-[11px] text-gray-500 dark:text-gray-400 font-medium">
        <div class="mb-3 md:mb-0 flex items-center gap-2">
            <span class="font-bold">© {{ date('Y') }} <a href="https://www.sientia.com" class="hover:underline hover:text-primary-600 transition-colors text-gray-700 dark:text-gray-200">Sientia</a></span>
            <span class="mx-1 text-gray-300 dark:text-gray-700 text-[10px]">|</span>
            <span>v{{ config('app.version', '2.0.0') }}</span>
            <span class="mx-1 text-gray-300 dark:text-gray-700 text-[10px]">|</span>
            <a href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank"
                class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Licencia AGPL v3</a>
        </div>
        <div class="flex items-center space-x-6">
            <!-- Open Source Links -->
            <div class="flex items-center gap-3 border-r border-gray-200 dark:border-gray-800 pr-5 mr-3">
                <a href="https://github.com/pbenav" target="_blank" title="GitHub" class="hover:text-gray-900 dark:hover:text-white transition-all transform hover:scale-110">
                    <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                </a>
                <a href="https://gitlab.com/pbenav" target="_blank" title="GitLab" class="hover:text-gray-900 dark:hover:text-white transition-all transform hover:scale-110">
                    <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M23.955 13.587l-1.342-4.135-2.664-8.189c-.135-.417-.724-.417-.86 0L16.425 9.452h-8.85l-2.664-8.189c-.135-.417-.724-.417-.86 0L1.387 9.452.045 13.587c-.11.34.01.711.306.925l11.65 8.458 11.648-8.458c.296-.214.416-.585.306-.925z"/></svg>
                </a>
            </div>
            
            <a href="https://www.patreon.com/cw/sientia" target="_blank"
                class="text-orange-600 hover:text-orange-700 font-bold transition-all flex items-center gap-1.5 group">
                <i class="fab fa-patreon group-hover:scale-110 transition-transform"></i>
                Patreon
            </a>
            <span class="text-gray-300 dark:text-gray-700 mx-1">|</span>
            <a href="https://buymeacoffee.com/sientia" target="_blank"
                class="text-yellow-600 hover:text-yellow-700 font-bold transition-all flex items-center gap-1.5 group">
                <i class="fas fa-coffee group-hover:scale-110 transition-transform"></i>
                Buy me a coffee
            </a>
        </div>
    </div>

    <!-- Zoom Script -->
    <script>
        (function() {
            window.applyZoom = function(val) {
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
            const savedZoom = localStorage.getItem('global_zoom') || '1.0';
            window.applyZoom(parseFloat(savedZoom));
        })();
    </script>
</footer>

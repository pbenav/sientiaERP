@if (!request()->routeIs('filament.admin.resources.tickets.create'))
    {{-- Zoom Global Header Control --}}
    <div
        class="flex items-center bg-gray-100 dark:bg-gray-800 rounded-md p-1 border border-gray-200 dark:border-gray-700 h-8 self-center mr-3">
        <button onclick="adjustGlobalZoom(-0.05)" type="button"
            class="px-2 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-gray-500 dark:text-gray-400 font-black text-sm leading-none transition-colors border border-transparent active:border-primary-500">
            -
        </button>

        <div class="flex flex-col items-center justify-center px-1 min-w-[40px]">
            <span class="text-[9px] font-black text-gray-500 dark:text-gray-400 leading-none">ZOOM</span>
            <span class="text-[10px] font-black text-primary-600 dark:text-primary-500 leading-tight uppercase"
                id="global-zoom-label">100%</span>
        </div>

        <button onclick="adjustGlobalZoom(0.05)" type="button"
            class="px-2 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-gray-500 dark:text-gray-400 font-black text-sm leading-none transition-colors border border-transparent active:border-primary-500">
            +
        </button>
    </div>
@endif

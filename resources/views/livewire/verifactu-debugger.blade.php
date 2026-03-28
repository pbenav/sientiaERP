<div class="space-y-6">
    <!-- Cabecera de Estado -->
    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            @if($status === 'idle')
                <div class="p-2 bg-gray-200 rounded-full"><x-heroicon-o-play class="w-6 h-6 text-gray-600" /></div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Listo para procesar</h3>
                    <p class="text-xs text-gray-500">Haz clic en iniciar para ver el rastro técnico.</p>
                </div>
            @elseif($status === 'running')
                <div class="p-2 bg-primary-100 rounded-full animate-pulse"><x-heroicon-o-arrow-path class="w-6 h-6 text-primary-600 animate-spin" /></div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Procesando envío...</h3>
                    <p class="text-xs text-gray-500">Conectando con los sevidores de la AEAT.</p>
                </div>
            @elseif($status === 'success')
                <div class="p-2 bg-green-100 rounded-full"><x-heroicon-o-check-circle class="w-6 h-6 text-green-600" /></div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Envío Completado</h3>
                    <p class="text-xs text-green-600 font-medium">Registro aceptado correctamente.</p>
                </div>
            @elseif($status === 'error')
                <div class="p-2 bg-red-100 rounded-full"><x-heroicon-o-x-circle class="w-6 h-6 text-red-600" /></div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Error Detectado</h3>
                    <p class="text-xs text-red-600 font-medium">Revisar los detalles abajo.</p>
                </div>
            @endif
        </div>

        @if($status === 'idle' || $status === 'error')
            <button wire:click="start" wire:loading.attr="disabled" 
                class="flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white text-sm font-bold rounded-lg shadow-sm transition">
                <span>{{ $status === 'idle' ? 'Iniciar Envío' : 'Reintentar' }}</span>
            </button>
        @endif
    </div>

    <!-- Consola de Pasos -->
    <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700 shadow-xl">
        <div class="bg-gray-800 px-4 py-2 flex items-center justify-between border-b border-gray-700">
            <span class="text-xs font-mono text-gray-400 uppercase tracking-widest">Traza de Ejecución</span>
            <div class="flex gap-1">
                <div class="w-2 h-2 rounded-full bg-red-500"></div>
                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                <div class="w-2 h-2 rounded-full bg-green-500"></div>
            </div>
        </div>
        <div class="p-4 font-mono text-xs space-y-2 max-h-60 overflow-y-auto bg-black">
            @forelse($steps as $step)
                <div class="flex gap-3">
                    <span class="text-gray-600">[{{ $step['time'] }}]</span>
                    @if($step['type'] === 'success')
                        <span class="text-green-400">✔ {{ $step['message'] }}</span>
                    @elseif($step['type'] === 'error')
                        <span class="text-red-400">✘ {{ $step['message'] }}</span>
                    @elseif($step['type'] === 'running')
                        <span class="text-blue-400">➜ {{ $step['message'] }}</span>
                    @else
                        <span class="text-gray-300"># {{ $step['message'] }}</span>
                    @endif
                </div>
            @empty
                <div class="text-gray-600 italic">Esperando inicio de operación...</div>
            @endforelse
            
            <div wire:loading wire:target="start" class="text-primary-400 animate-pulse">
                Ejecutando proceso remoto...
            </div>
        </div>
    </div>

    @if($errorMessage)
        <div class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">
            <p class="font-bold">Detalle del Error:</p>
            <p>{{ $errorMessage }}</p>
        </div>
    @endif

    <!-- XML Inspector -->
    <div x-data="{ activeTab: 'request' }" class="space-y-2">
        <div class="flex border-b border-gray-200 dark:border-gray-700">
            <button @click="activeTab = 'request'" :class="activeTab === 'request' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500'" class="px-4 py-2 text-sm font-medium border-b-2 transition">
                XML Petición (Sent)
            </button>
            <button @click="activeTab = 'response'" :class="activeTab === 'response' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500'" class="px-4 py-2 text-sm font-medium border-b-2 transition">
                XML Respuesta (Received)
            </button>
        </div>

        <div x-show="activeTab === 'request'" class="relative group">
            <pre class="p-4 bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-200 dark:border-gray-800 text-[10px] font-mono overflow-x-auto max-h-96 text-gray-700 dark:text-gray-300">
@if($requestXml){{ $requestXml }}@else<span class="text-gray-400 italic">No se ha generado el XML todavía.</span>@endif
            </pre>
            @if($requestXml)
                <button onclick="navigator.clipboard.writeText(`{{ addslashes($requestXml) }}`)" class="absolute top-2 right-2 p-1 bg-white dark:bg-gray-800 border rounded shadow-sm opacity-0 group-hover:opacity-100 transition text-xs">Copiar</button>
            @endif
        </div>

        <div x-show="activeTab === 'response'" class="relative group">
            <pre class="p-4 bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-200 dark:border-gray-800 text-[10px] font-mono overflow-x-auto max-h-96 text-gray-700 dark:text-gray-300">
@if($responseXml){{ $responseXml }}@else<span class="text-gray-400 italic">Esperando respuesta del servidor AEAT...</span>@endif
            </pre>
            @if($responseXml)
                <button onclick="navigator.clipboard.writeText(`{{ addslashes($responseXml) }}`)" class="absolute top-2 right-2 p-1 bg-white dark:bg-gray-800 border rounded shadow-sm opacity-0 group-hover:opacity-100 transition text-xs">Copiar</button>
            @endif
        </div>
    </div>
</div>

<div class="flex flex-col items-center justify-center p-4 bg-white border rounded-lg shadow-sm">
    <div class="mb-2 text-sm font-medium text-gray-500">Consulta Veri*Factu (AEAT)</div>
    <div class="p-2 bg-gray-50 rounded-md">
        {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(150)->generate($getState()) !!}
    </div>
    <div class="mt-2 text-xs text-gray-400 break-all text-center max-w-xs">
        {{ $getState() }}
    </div>
</div>

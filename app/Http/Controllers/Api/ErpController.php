<?php

namespace App\Http\Controllers\Api;

use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Models\Documento;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ErpController extends Controller
{
    /**
     * Terceros
     */
    public function getTerceros(Request $request): JsonResponse
    {
        $query = Tercero::with('tipos');
        
        // Filtrar por tipo si se especifica
        if ($request->has('tipo')) {
            $query->whereHas('tipos', function ($q) use ($request) {
                $q->where('codigo', strtoupper(substr($request->tipo, 0, 3)));
            });
        }
        
        $perPage = min($request->get('per_page', 15), 50);
        $terceros = $query->paginate($perPage);
        
        return response()->json($terceros);
    }

    public function getTercero(int $id): JsonResponse
    {
        $tercero = Tercero::with('tipos')->findOrFail($id);
        return response()->json($tercero);
    }

    public function createTercero(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre_comercial' => 'required|string|max:255',
            'nif_cif' => 'required|string|max:20|unique:terceros',
            'tipos' => 'required|array',
        ]);

        $tercero = Tercero::create($validated);
        
        // Resolver IDs de tipos
        $tipoIds = TipoTercero::whereIn('codigo', $validated['tipos'])->pluck('id');
        $tercero->tipos()->attach($tipoIds);

        return response()->json($tercero->load('tipos'), 201);
    }

    public function updateTercero(Request $request, int $id): JsonResponse
    {
        $tercero = Tercero::findOrFail($id);
        
        $validated = $request->validate([
            'nombre_comercial' => 'sometimes|string|max:255',
            'nif_cif' => 'sometimes|string|max:20|unique:terceros,nif_cif,' . $id,
        ]);

        $tercero->update($validated);

        if ($request->has('tipos')) {
            $tipoIds = TipoTercero::whereIn('codigo', $request->tipos)->pluck('id');
            $tercero->tipos()->sync($tipoIds);
        }

        return response()->json($tercero->load('tipos'));
    }

    public function deleteTercero(int $id): JsonResponse
    {
        $tercero = Tercero::findOrFail($id);
        $tercero->delete();

        return response()->json(['message' => 'Tercero eliminado']);
    }

    /**
     * Documentos
     */
    public function getDocumentos(Request $request): JsonResponse
    {
        $query = Documento::with(['tercero', 'user']);
        
        // Filtrar por tipo
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        
        // Ordenar por fecha descendente
        $query->orderBy('fecha', 'desc');
        
        $perPage = min($request->get('per_page', 15), 50);
        $documentos = $query->paginate($perPage);
        
        return response()->json($documentos);
    }

    public function getDocumento(int $id): JsonResponse
    {
        $documento = Documento::with(['tercero', 'user', 'lineas.product'])->findOrFail($id);
        return response()->json($documento);
    }

    public function createDocumento(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => 'required|in:presupuesto,pedido,albaran,factura,recibo',
            'tercero_id' => 'required|exists:terceros,id',
            'fecha' => 'required|date',
            'lineas' => 'sometimes|array',
        ]);

        $validated['user_id'] = auth()->id();
        
        $documento = Documento::create($validated);
        
        // Crear líneas si se proporcionan
        if (isset($validated['lineas'])) {
            foreach ($validated['lineas'] as $linea) {
                $documento->lineas()->create($linea);
            }
            $documento->recalcularTotales();
        }

        return response()->json($documento->load(['tercero', 'lineas']), 201);
    }

    public function updateDocumento(Request $request, int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        
        $validated = $request->validate([
            'tercero_id' => 'sometimes|exists:terceros,id',
            'fecha' => 'sometimes|date',
            'estado' => 'sometimes|in:borrador,confirmado,parcial,completado,anulado',
        ]);

        $documento->update($validated);
        $documento->recalcularTotales();

        return response()->json($documento->load(['tercero', 'lineas']));
    }

    public function deleteDocumento(int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        $documento->delete();

        return response()->json(['message' => 'Documento eliminado']);
    }

    public function convertirDocumento(Request $request, int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        
        $validated = $request->validate([
            'tipo_destino' => 'required|in:pedido,albaran,factura,recibo',
        ]);

        $nuevoDocumento = $documento->convertirA($validated['tipo_destino']);

        return response()->json($nuevoDocumento->load(['tercero', 'lineas']), 201);
    }

    /**
     * Productos
     */
    public function getProductos(Request $request): JsonResponse
    {
        $query = Product::query();
        
        $perPage = min($request->get('per_page', 15), 50);
        $productos = $query->paginate($perPage);
        
        return response()->json($productos);
    }

    public function getProducto(int $id): JsonResponse
    {
        $producto = Product::findOrFail($id);
        return response()->json($producto);
    }

    public function searchProducto(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        $productos = Product::where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->orWhere('barcode', 'like', "%{$query}%")
            ->limit(20)
            ->get();
        
        return response()->json($productos);
    }

    public function createProducto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:products|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'tax_rate' => 'numeric|min:0',
            'barcode' => 'nullable|string|max:50',
        ]);

        $producto = Product::create($validated);

        return response()->json($producto, 201);
    }

    public function updateProducto(Request $request, int $id): JsonResponse
    {
        $producto = Product::findOrFail($id);
        
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:50|unique:products,sku,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'integer|min:0',
            'tax_rate' => 'numeric|min:0',
        ]);

        $producto->update($validated);

        return response()->json($producto);
    }

    public function deleteProducto(int $id): JsonResponse
    {
        $producto = Product::findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }
}

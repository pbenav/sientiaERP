<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PosController extends Controller
{
    /**
     * Autenticar operador y generar token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Datos inválidos', 'details' => $validator->errors()], 422);
        }

        /*
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $user = Auth::user();
        */
        
        // TODO: REMOVE FOR PRODUCTION - BYPASS AUTH
        $user = \App\Models\User::first();
        if (!$user) {
             // Fallback if no user exists, though unlikely in dev
             return response()->json(['error' => 'No testing user found'], 500);
        }
        // Emitir un token con acceso total para que funcione tanto en POS como en ERP
        $token = $user->createToken('erp-tui-access', ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Obtener producto por SKU o código de barras
     */
    public function getProduct(string $code): JsonResponse
    {
        $product = Product::findByCode($code);

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json([
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'tax_rate' => (float) $product->tax_rate,
            'stock' => $product->stock,
            'barcode' => $product->barcode,
        ]);
    }

    /**
     * Crear nuevo ticket
     */
    public function createTicket(Request $request): JsonResponse
    {
        $ticket = Ticket::create([
            'user_id' => auth()->id(),
            'status' => 'open',
        ]);

        // Cachear el session_id para recuperación rápida
        Cache::put("pos:session:{$ticket->session_id}", $ticket->id, now()->addHours(8));

        return response()->json([
            'session_id' => $ticket->session_id,
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    /**
     * Añadir producto al ticket
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Datos inválidos', 'details' => $validator->errors()], 422);
        }

        $ticket = Ticket::where('session_id', $request->session_id)
            ->where('status', 'open')
            ->first();

        if (!$ticket) {
            // Crear ticket si no existe
            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'session_id' => $request->session_id,
                'status' => 'open',
            ]);
        }

        $product = Product::findOrFail($request->product_id);
        $quantity = $request->quantity ?? 1;

        // Verificar stock
        if (!$product->hasStock($quantity)) {
            return response()->json(['error' => 'Stock insuficiente'], 422);
        }

        // Buscar si ya existe el producto en el ticket
        $existingItem = $ticket->items()->where('product_id', $product->id)->first();

        // Convertir PVP de producto a precio base para almacenamiento interno
        $taxRate = (float) $product->tax_rate;
        $basePrice = $product->price / (1 + ($taxRate / 100));

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->unit_price = $basePrice; // Asegurar que usamos base
            $existingItem->save();
            $item = $existingItem;
        } else {
            $item = $ticket->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $basePrice,
                'tax_rate' => $taxRate,
            ]);
        }

        $ticket->recalculateTotals();
        $ticket->load('items.product');

        return response()->json([
            'ticket' => $this->formatTicket($ticket),
            'item' => $this->formatItem($item),
        ]);
    }

    /**
     * Eliminar ítem del ticket
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $item = TicketItem::findOrFail($itemId);
        $ticket = $item->ticket;

        if ($ticket->status !== 'open') {
            return response()->json(['error' => 'El ticket ya está cerrado'], 422);
        }

        $item->delete();
        $ticket->recalculateTotals();
        $ticket->load('items.product');

        return response()->json([
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    /**
     * Obtener ticket actual por session_id
     */
    public function getCurrentTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'session_id requerido'], 422);
        }

        $ticket = Ticket::where('session_id', $request->session_id)
            ->where('status', 'open')
            ->with('items.product')
            ->first();

        if (!$ticket) {
            return response()->json(['error' => 'No hay ticket abierto'], 404);
        }

        return response()->json([
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    /**
     * Completar venta (checkout)
     */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'payment_method' => 'required|in:cash,card,mixed',
            'amount_paid' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Datos inválidos', 'details' => $validator->errors()], 422);
        }

        $ticket = Ticket::where('session_id', $request->session_id)
            ->where('status', 'open')
            ->with('items.product')
            ->first();

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        if ($ticket->items->isEmpty()) {
            return response()->json(['error' => 'El ticket está vacío'], 422);
        }

        if ($request->amount_paid < $ticket->total) {
            return response()->json(['error' => 'Pago insuficiente'], 422);
        }

        $ticket->complete($request->payment_method, $request->amount_paid);

        // Limpiar caché
        Cache::forget("pos:session:{$ticket->session_id}");

        return response()->json([
            'ticket' => $this->formatTicket($ticket->fresh('items.product')),
            'change' => (float) $ticket->change_given,
            'message' => 'Venta completada exitosamente',
        ]);
    }

    /**
     * Obtener totales del turno del operador
     */
    public function getTotals(Request $request): JsonResponse
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        $tickets = Ticket::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $today)
            ->get();

        $totalSales = $tickets->sum('total');
        $totalTransactions = $tickets->count();
        $cashSales = $tickets->where('payment_method', 'cash')->sum('total');
        $cardSales = $tickets->where('payment_method', 'card')->sum('total');

        return response()->json([
            'operator' => $user->name,
            'date' => $today->format('Y-m-d'),
            'total_sales' => (float) $totalSales,
            'total_transactions' => $totalTransactions,
            'cash_sales' => (float) $cashSales,
            'card_sales' => (float) $cardSales,
        ]);
    }

    /**
     * Formatear ticket para respuesta
     */
    private function formatTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'session_id' => $ticket->session_id,
            'status' => $ticket->status,
            'subtotal' => (float) $ticket->subtotal,
            'tax' => (float) $ticket->tax,
            'total' => (float) $ticket->total,
            'items' => $ticket->items->map(fn($item) => $this->formatItem($item))->toArray(),
            'payment_method' => $ticket->payment_method,
            'amount_paid' => $ticket->amount_paid ? (float) $ticket->amount_paid : null,
            'change_given' => $ticket->change_given ? (float) $ticket->change_given : null,
            'completed_at' => $ticket->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Formatear ítem para respuesta
     */
    private function formatItem(TicketItem $item): array
    {
        return [
            'id' => $item->id,
            'product' => [
                'id' => $item->product->id,
                'sku' => $item->product->sku,
                'name' => $item->product->name,
            ],
            'quantity' => $item->quantity,
            'unit_price' => (float) ($item->total / $item->quantity), // Return PVP for TUI
            'tax_rate' => (float) $item->tax_rate,
            'subtotal' => (float) $item->subtotal,
            'tax_amount' => (float) $item->tax_amount,
            'total' => (float) $item->total,
        ];
    }
}

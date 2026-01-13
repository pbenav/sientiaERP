<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Documento;
use App\Models\Tercero;
use App\Models\Product;
use App\Models\User;
use App\Models\DocumentoLinea;

class DocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@sientia.com')->first();
        if (!$user) {
            $user = User::first();
        }
        
        $clientes = Tercero::whereHas('tipos', fn($q) => $q->where('codigo', 'CLI'))->get();
        $proveedores = Tercero::whereHas('tipos', fn($q) => $q->where('codigo', 'PRO'))->get();
        $products = Product::all();

        if ($clientes->isEmpty() || $products->isEmpty()) {
            return;
        }

        // Evitar duplicados masivos si se ejecuta varias veces
        if (Documento::count() > 10) {
            return;
        }

        // 1. Algunos Presupuestos
        foreach ($clientes->take(2) as $cliente) {
            $this->createDocument($cliente, $user, 'presupuesto', $products->random(rand(1, 3)));
        }

        // 2. Algunos Pedidos (uno de ellos convertido de presupuesto)
        foreach ($clientes->slice(1, 1) as $cliente) {
            $this->createDocument($cliente, $user, 'pedido', $products->random(rand(1, 3)), 'confirmado');
        }

        // 3. Albaranes y Facturas
        $clienteFactura = $clientes->first();
        $factura = $this->createDocument($clienteFactura, $user, 'factura', $products->random(2), 'confirmado');
        
        // 4. Recibos
        $this->createDocument($clienteFactura, $user, 'recibo', $products->random(1), 'completado');

        // 5. De proveedores (Documentos de compra)
        foreach ($proveedores as $index => $proveedor) {
            // CHAIN: Pedido -> Albaran -> Factura -> Recibo (Flujo completo)
            // 1. Crear Pedido Inicial
            $pedido = $this->createDocument($proveedor, $user, 'pedido', $products->random(rand(2, 4)), 'confirmado');
            
            // 2. Convertir a Albaran
            $albaran = $pedido->convertirA('albaran');
            $albaran->confirmar();
            
            // 3. Convertir a Factura
            $factura = $albaran->convertirA('factura');
            $factura->confirmar();

            // 4. Generar Recibo (Manual, la conversión directa a recibo no siempre es workflow estándar pero lo simulamos)
            $recibo = $factura->convertirA('recibo');
            $recibo->update(['estado' => 'completado']);

            // UNLINKED: Documentos sueltos para variedad
            if ($index === 0) {
                 // Albaran suelto (Directo)
                 $this->createDocument($proveedor, $user, 'albaran', $products->random(rand(2, 4)), 'confirmado');
                 
                 // Factura suelta (Directa)
                 $this->createDocument($proveedor, $user, 'factura', $products->random(rand(1, 4)), 'confirmado');
            }
        }
    }

    private function createDocument($tercero, $user, $tipo, $selectedProducts, $estado = 'borrador')
    {
        $doc = Documento::create([
            'tipo' => $tipo,
            'tercero_id' => $tercero->id,
            'user_id' => $user->id,
            'fecha' => now()->subDays(rand(0, 30)),
            'estado' => $estado,
            'serie' => 'A',
            'forma_pago' => $tercero->forma_pago,
            'dias_pago' => $tercero->dias_pago,
        ]);

        foreach ($selectedProducts as $index => $product) {
            $cantidad = rand(1, 10);
            $precio = $product->price;
            $subtotal = $cantidad * $precio;
            $iva = $product->tax_rate;
            $importeIva = $subtotal * ($iva / 100);
            
            DocumentoLinea::create([
                'documento_id' => $doc->id,
                'product_id' => $product->id,
                'orden' => $index,
                'codigo' => $product->sku,
                'descripcion' => $product->name,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'importe_iva' => $importeIva,
                'total' => $subtotal + $importeIva,
            ]);
        }

        $doc->recalcularTotales();
        return $doc;
    }
}

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
        // Si hay más de 5 documentos, asumimos que ya se sembró y salimos.
        if (Documento::count() > 5) {
            return;
        }

        // Variable para controlar flujo (aunque con el return de arriba ya no haría falta, lo dejo simplificado)
        $currentDate = now()->subDays(30);

        // 1. Algunos Presupuestos

            // 1. Algunos Presupuestos
            foreach ($clientes->take(2) as $cliente) {
                $this->createDocument($cliente, $user, 'presupuesto', $products->random(rand(1, 3)), 'borrador', $currentDate);
                $currentDate->addDay();
            }

            // 2. Algunos Pedidos
            foreach ($clientes->slice(1, 1) as $cliente) {
                $this->createDocument($cliente, $user, 'pedido', $products->random(rand(1, 3)), 'confirmado', $currentDate);
                $currentDate->addDay();
            }

            // 2.5 Algunos Albaranes de Venta
            foreach ($clientes->take(2) as $cliente) {
                $this->createDocument($cliente, $user, 'albaran', $products->random(rand(1, 3)), 'confirmado', $currentDate);
                $currentDate->addDay();
            }

            // 3. Albaranes y Facturas
            $clienteFactura = $clientes->first();
            $factura = $this->createDocument($clienteFactura, $user, 'factura', $products->random(2), 'confirmado', $currentDate);
            $currentDate->addDay();
            
            // 4. Recibos (Generados automáticamente al confirmar facturas, no es necesario crear manuales si ya creamos facturas arriba)
            // Se eliminó la creación manual para evitar desincronización de números.
            // $this->createDocument($clienteFactura, $user, 'recibo', $products->random(1), 'completado', $currentDate);
            // $currentDate->addDay();

        // 5. De proveedores (Documentos de compra)
        $currentDateCompra = now()->subDays(60); // Compras suelen ser anteriores
        foreach ($proveedores as $index => $proveedor) {
            // CHAIN: Pedido -> Albaran -> Factura (Flujo completo de compras)
            // 1. Crear Pedido de Compra
            $pedido = $this->createDocument($proveedor, $user, 'pedido_compra', $products->random(rand(2, 4)), 'confirmado', $currentDateCompra);
            $currentDateCompra->addDay();
            
            // 2. Convertir a Albaran de Compra
            $albaran = $pedido->convertirA('albaran_compra');
            $albaran->fecha = $currentDateCompra->copy();
            $albaran->save();
            $albaran->confirmar();
            $currentDateCompra->addDay();
            
            // 3. Convertir a Factura de Compra
            $factura = $albaran->convertirA('factura_compra');
            $factura->fecha = $currentDateCompra->copy();
            $factura->save();
            $factura->confirmar();
            $currentDateCompra->addDay();

            // UNLINKED: Documentos sueltos para variedad
            if ($index === 0) {
                 // Albaran suelto (Directo)
                 $this->createDocument($proveedor, $user, 'albaran_compra', $products->random(rand(2, 4)), 'confirmado', $currentDateCompra);
                 $currentDateCompra->addDay();
                 
                 // Factura suelta (Directa)
                 $this->createDocument($proveedor, $user, 'factura_compra', $products->random(rand(1, 4)), 'confirmado', $currentDateCompra);
                 $currentDateCompra->addDay();
            }
        }
    }

    private function createDocument($tercero, $user, $tipo, $selectedProducts, $estado = 'borrador', $fecha = null)
    {
        $doc = Documento::create([
            'tipo' => $tipo,
            'tercero_id' => $tercero->id,
            'user_id' => $user->id,
            'fecha' => $fecha ?? now(),
            'estado' => 'borrador',
            'serie' => 'A',
            'forma_pago_id' => \App\Models\FormaPago::where('tipo', 'contado')->first()?->id,
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

        // Si se pidió un estado distinto de borrador, lo confirmamos
        // Esto es vital para facturas, para que se genere el número vía ->confirmar()
        if ($estado !== 'borrador') {
            $doc->refresh(); // Asegurar que tenemos todos los campos (incluida serie si viene de default)
            $doc->confirmar();
            if ($estado === 'completado' || $estado === 'cobrado') {
                $doc->update(['estado' => $estado]);
            }
        }

        return $doc;
    }
}

<?php

use App\Models\Documento;
use Illuminate\Support\Facades\Validator;

echo "Checking Pedido Data Integrity...\n";

$pedidos = Documento::where('tipo', 'pedido')->get();

foreach ($pedidos as $pedido) {
    echo "Pedido ID: {$pedido->id}, Ref: {$pedido->numero}\n";
    
    $data = $pedido->toArray();
    
    // Manual validation simulation based on Form Schema
    // serie required
    // fecha required
    // tercero_id required
    // forma_pago_id required (in form) but might be null in DB
    
    if (empty($pedido->serie)) echo "  [WARN] Serie is empty\n";
    if (empty($pedido->fecha)) echo "  [WARN] Fecha is empty\n";
    if (empty($pedido->tercero_id)) echo "  [ERROR] Tercero_id is empty (Required)\n";
    
    if (empty($pedido->forma_pago_id)) {
        echo "  [INFO] FormaPago is empty. This validation might block save if field is required.\n";
    }
}

echo "Done.\n";

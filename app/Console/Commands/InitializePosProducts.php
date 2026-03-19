<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;

class InitializePosProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:pos-init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inicializa productos especiales de TPV (Bolsa, Varios, etc) para que no descuenten stock.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $skus = ['BOLSA', 'VARIO', 'GENERICO'];
        
        $this->info('Configurando productos especiales en el TPV...');
        
        $count = Product::whereIn('sku', $skus)
            ->update([
                'requires_stock' => false,
                'is_salable' => true,
                'is_purchasable' => false, // Normalmente bolsas/genéricos no se compran vía ERP sino reposición interna
            ]);
            
        $this->info("¡Hecho! Se han configurado {$count} productos especiales.");
    }
}

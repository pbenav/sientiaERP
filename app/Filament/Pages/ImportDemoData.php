<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\Tercero;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Forms\Components\CheckboxList;

class ImportDemoData extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Importar datos de prueba';
    protected static ?string $title = 'Generador de Datos Demo';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static string $view = 'filament.pages.import-demo-data';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'products_count' => 50,
            'clients_count' => 10,
            'suppliers_count' => 5,
            'documents_count' => 20,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Parámetros de Generación')
                    ->description('Selecciona la cantidad de registros que deseas generar para cada módulo.')
                    ->schema([
                        TextInput::make('products_count')
                            ->label('Productos')
                            ->numeric()
                            ->default(50)
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        
                        TextInput::make('clients_count')
                            ->label('Clientes')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(200)
                            ->required(),

                        TextInput::make('suppliers_count')
                            ->label('Proveedores')
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->maxValue(50)
                            ->required(),

                        TextInput::make('documents_count')
                            ->label('Documentos (Varios tipos)')
                            ->numeric()
                            ->default(20)
                            ->minValue(0)
                            ->maxValue(500)
                            ->required()
                            ->helperText('Se generarán tipos aleatorios: presupuestos, pedidos, facturas, etc.'),
                    ])
                    ->columns(2),

                Section::make('PELIGRO: Reseteo de Base de Datos')
                    ->description('Selecciona qué tablas deseas VACIAR por completo y REINICIAR sus contadores (ID=1). ESTA ACCIÓN NO SE PUEDE DESHACER.')
                    ->collapsed() // Escondido por defecto por seguridad
                    ->schema([
                        CheckboxList::make('reset_tables')
                            ->label('¿Qué quieres resetear?')
                            ->options([
                                'ventas_tpv'  => 'Ventas TPV (Tickets y líneas de ticket)',
                                'ventas_docs' => 'Ventas de Gestión (Presupuestos, Pedidos, Albaranes y Facturas)',
                                'compras_docs'=> 'Compras de Gestión (Pedidos, Albaranes y Facturas de Compra)',
                                'terceros'    => 'Terceros (Clientes y Proveedores)',
                                'catalogo'    => 'Catálogo (Productos, Categorías, Marcas y Stock)',
                            ])
                            ->columns(2)
                            ->required(),
                    ])
                    ->footerActions([
                        Action::make('resetData')
                            ->label('¡Borrar y Reiniciar Seleccionados!')
                            ->color('danger')
                            ->icon('heroicon-o-trash')
                            ->requiresConfirmation()
                            ->modalHeading('¿ESTÁS ABSOLUTAMENTE SEGURO?')
                            ->modalDescription('Esta acción truncará las tablas seleccionadas. Todos los registros y contadores se reiniciarán a 1.')
                            ->action('resetDatabase')
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $formData = $this->form->getState();

        try {
            $results = [
                'products' => ['success' => 0, 'error' => 0],
                'clients' => ['success' => 0, 'error' => 0],
                'suppliers' => ['success' => 0, 'error' => 0],
                'documents' => ['success' => 0, 'error' => 0],
            ];

            // 1. Productos
            if ($formData['products_count'] > 0) {
                for ($i = 0; $i < $formData['products_count']; $i++) {
                    try {
                        Product::factory()->create();
                        $results['products']['success']++;
                    } catch (\Exception $e) {
                        $results['products']['error']++;
                        \Log::error("Demo Import Error (Product): " . $e->getMessage());
                    }
                }
            }

            // 2. Clientes
            if ($formData['clients_count'] > 0) {
                for ($i = 0; $i < $formData['clients_count']; $i++) {
                    try {
                        Tercero::factory()->cliente()->create();
                        $results['clients']['success']++;
                    } catch (\Exception $e) {
                        $results['clients']['error']++;
                    }
                }
            }

            // 3. Proveedores
            if ($formData['suppliers_count'] > 0) {
                for ($i = 0; $i < $formData['suppliers_count']; $i++) {
                    try {
                        Tercero::factory()->proveedor()->create();
                        $results['suppliers']['success']++;
                    } catch (\Exception $e) {
                        $results['suppliers']['error']++;
                    }
                }
            }

            // 4. Documentos
            if ($formData['documents_count'] > 0) {
                if (Product::count() === 0 || Tercero::count() === 0) {
                    Notification::make()
                        ->title('Error')
                        ->body('Debes generar productos y terceros antes que los documentos.')
                        ->danger()
                        ->send();
                    return;
                }

                for ($i = 0; $i < $formData['documents_count']; $i++) {
                    try {
                        Documento::factory()
                            ->withLines()
                            ->confirmado()
                            ->create();
                        $results['documents']['success']++;
                    } catch (\Exception $e) {
                        $results['documents']['error']++;
                        \Log::error("Demo Import Error (Document): " . $e->getMessage());
                    }
                }
            }

            $summary = "Importación finalizada.\n\n" .
                "Productos: {$results['products']['success']} ok / {$results['products']['error']} error\n" .
                "Clientes: {$results['clients']['success']} ok / {$results['clients']['error']} error\n" .
                "Proveedores: {$results['suppliers']['success']} ok / {$results['suppliers']['error']} error\n" .
                "Documentos: {$results['documents']['success']} ok / {$results['documents']['error']} error";

            Notification::make()
                ->title('Proceso Completado')
                ->body($summary)
                ->status($results['products']['error'] > 0 ? 'warning' : 'success')
                ->send();

            return redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en la generación')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetDatabase()
    {
        $formData = $this->form->getRawState();
        $toReset = $formData['reset_tables'] ?? [];

        if (empty($toReset)) {
            Notification::make()->title('Ninguna tabla seleccionada')->warning()->send();
            return;
        }

        try {
            DB::beginTransaction();
            Schema::disableForeignKeyConstraints();

            if (in_array('ventas_tpv', $toReset)) {
                DB::table('ticket_items')->truncate();
                DB::table('tickets')->truncate();
            }

            $resetVentas = in_array('ventas_docs', $toReset);
            $resetCompras = in_array('compras_docs', $toReset);

            if ($resetVentas && $resetCompras) {
                DB::table('documento_lineas')->truncate();
                DB::table('documentos')->truncate();
            } elseif ($resetVentas) {
                // Tipos de venta
                $tiposVenta = ['presupuesto', 'pedido', 'albaran', 'factura'];
                $ids = DB::table('documentos')->whereIn('tipo', $tiposVenta)->pluck('id');
                DB::table('documento_lineas')->whereIn('documento_id', $ids)->delete();
                DB::table('documentos')->whereIn('id', $ids)->delete();
            } elseif ($resetCompras) {
                // Tipos de compra
                $tiposCompra = ['presupuesto_compra', 'pedido_compra', 'albaran_compra', 'factura_compra', 'factura_proveedor'];
                $ids = DB::table('documentos')->whereIn('tipo', $tiposCompra)->pluck('id');
                DB::table('documento_lineas')->whereIn('documento_id', $ids)->delete();
                DB::table('documentos')->whereIn('id', $ids)->delete();
            }

            if (in_array('terceros', $toReset)) {
                DB::table('terceros')->truncate();
            }

            if (in_array('catalogo', $toReset)) {
                DB::table('stocks')->truncate();
                DB::table('category_product')->truncate();
                DB::table('brands')->truncate();
                DB::table('categories')->truncate();
                DB::table('products')->truncate();
            }

            Schema::enableForeignKeyConstraints();
            DB::commit();

            Notification::make()
                ->title('Reseteo Correcto')
                ->body('Se han vaciado las tablas seleccionadas y se han reiniciado los contadores de ID.')
                ->success()
                ->send();

            return redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            Notification::make()
                ->title('Error al resetear')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

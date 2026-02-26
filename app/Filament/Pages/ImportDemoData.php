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
}
